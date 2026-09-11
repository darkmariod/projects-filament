<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Label;
use App\Models\PrintQueue;
use App\Models\PrintQueueItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El agente pregunta por trabajo pendiente cada 10 segundos. Un lote de la
 * fabrica son mas de mil etiquetas y tarda cerca de veinte minutos: en ese rato
 * se corta la red, se reinicia la PC o el vigilante relanza el agente. La cola
 * queda entonces en 'processing', y si el endpoint no la devuelve, sus
 * etiquetas no las imprime nadie nunca mas.
 */
class PrintQueueAgentPendingTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/agent/pending';

    private function headers(): array
    {
        return ['X-Agent-Key' => config('app.print_agent_key')];
    }

    private function crearCola(string $estado, int $pendientes = 2, int $impresas = 0): PrintQueue
    {
        $queue = PrintQueue::create([
            'label_batch_id'  => Label::factory()->create()->label_batch_id,
            'user_id'         => User::factory()->create()->id,
            'zebra_ip'        => '',
            'zebra_port'      => 9100,
            'connection_type' => 'usb',
            'printer_name'    => 'ZDesigner ZT411-203dpi ZPL',
            'status'          => $estado,
            'total_labels'    => $pendientes + $impresas,
            'printed_labels'  => $impresas,
            'failed_labels'   => 0,
        ]);

        $seq = 1;
        foreach (['printed' => $impresas, 'pending' => $pendientes] as $estadoItem => $cuantos) {
            for ($i = 0; $i < $cuantos; $i++) {
                PrintQueueItem::create([
                    'print_queue_id' => $queue->id,
                    'label_id'       => Label::factory()->create()->id,
                    'sequence'       => $seq++,
                    'zpl_content'    => '^XA^FDprueba^FS^XZ',
                    'status'         => $estadoItem,
                    'attempts'       => 0,
                    'max_attempts'   => 3,
                ]);
            }
        }

        return $queue;
    }

    /** @test */
    public function it_returns_a_queue_that_is_still_pending(): void
    {
        $queue = $this->crearCola('pending');

        $response = $this->withHeaders($this->headers())->getJson(self::ENDPOINT);

        $response->assertOk();
        $this->assertSame([$queue->id], array_column($response->json('queues'), 'queue_id'));
    }

    /**
     * Este es el caso que dejaba etiquetas huerfanas: la cola arranco, el agente
     * se corto a mitad de camino y la cola quedo en 'processing' con sus
     * etiquetas sin imprimir.
     *
     * @test
     */
    public function it_lets_the_agent_resume_a_queue_left_half_printed(): void
    {
        $queue = $this->crearCola('processing', pendientes: 3, impresas: 2);

        $response = $this->withHeaders($this->headers())->getJson(self::ENDPOINT);

        $response->assertOk();

        $devueltas = array_column($response->json('queues'), 'queue_id');
        $this->assertContains(
            $queue->id,
            $devueltas,
            'Una cola interrumpida debe volver a ofrecerse o sus etiquetas no se imprimen nunca'
        );

        $items = $response->json('queues.0.items');
        $this->assertCount(3, $items, 'Solo deben volver las etiquetas que faltan, no las ya impresas');
    }

    /** @test */
    public function it_ignores_a_queue_somebody_paused_on_purpose(): void
    {
        $this->crearCola('paused');

        $response = $this->withHeaders($this->headers())->getJson(self::ENDPOINT);

        $response->assertOk();
        $this->assertSame([], $response->json('queues'));
    }

    /** @test */
    public function it_rejects_a_request_without_the_agent_key(): void
    {
        $this->crearCola('pending');

        $this->getJson(self::ENDPOINT)->assertUnauthorized();
    }

    /**
     * Retomar una cola implica que el agente puede avisar dos veces por la misma
     * etiqueta: si se corta justo despues de imprimirla, al volver la recibe de
     * nuevo. Contar los dos avisos deja lotes que dicen haber impreso mas
     * etiquetas de las que tienen.
     *
     * @test
     */
    public function reporting_the_same_label_twice_counts_it_once(): void
    {
        $queue = $this->crearCola('processing', pendientes: 3);
        $item  = $queue->items()->first();
        $url   = "/api/agent/{$queue->id}/item/{$item->id}/complete";

        $this->withHeaders($this->headers())->postJson($url)->assertOk();
        $this->withHeaders($this->headers())->postJson($url)->assertOk();

        $this->assertSame(
            1,
            $queue->fresh()->printed_labels,
            'Dos avisos de la misma etiqueta se cuentan una sola vez'
        );
    }

    /**
     * El agente que ya esta instalado en la planta no manda ?limit. Tiene que
     * seguir recibiendo todo, exactamente como antes.
     *
     * @test
     */
    public function without_a_limit_the_old_agent_still_receives_everything(): void
    {
        $this->crearCola('pending', pendientes: 7);

        $response = $this->withHeaders($this->headers())->getJson(self::ENDPOINT);

        $response->assertOk();
        $this->assertCount(7, $response->json('queues.0.items'));
        $this->assertSame(7, $response->json('queues.0.remaining'));
    }

    /** @test */
    public function with_a_limit_the_agent_receives_a_batch_and_knows_how_many_remain(): void
    {
        $this->crearCola('pending', pendientes: 7);

        $response = $this->withHeaders($this->headers())->getJson(self::ENDPOINT . '?limit=3');

        $response->assertOk();
        $this->assertCount(3, $response->json('queues.0.items'), 'Solo la tanda pedida');
        $this->assertSame(7, $response->json('queues.0.remaining'), 'Pero sabe que quedan siete en total');
    }

    /**
     * Un limit() dentro de with() acota el total y no cada cola: con dos colas
     * pendientes la segunda podria recibir cero. Este caso lo vigila.
     *
     * @test
     */
    public function the_limit_applies_to_each_queue_and_not_to_the_total(): void
    {
        $this->crearCola('pending', pendientes: 5);
        $this->crearCola('pending', pendientes: 5);

        $response = $this->withHeaders($this->headers())->getJson(self::ENDPOINT . '?limit=2');

        $response->assertOk();
        $this->assertCount(2, $response->json('queues'));
        $this->assertCount(2, $response->json('queues.0.items'));
        $this->assertCount(2, $response->json('queues.1.items'), 'La segunda cola tambien recibe su tanda');
    }

    /** @test */
    public function the_agent_can_report_several_labels_in_one_request(): void
    {
        $queue = $this->crearCola('processing', pendientes: 4);
        $ids   = $queue->items->pluck('id')->take(3)->all();

        $response = $this->withHeaders($this->headers())
            ->postJson("/api/agent/{$queue->id}/items/complete", ['item_ids' => $ids]);

        $response->assertOk();
        $this->assertSame(3, $response->json('marked'));
        $this->assertSame(3, $queue->fresh()->printed_labels);
        $this->assertSame(3, $queue->items()->where('status', 'printed')->count());
    }

    /** @test */
    public function reporting_in_bulk_ignores_items_that_belong_to_another_queue(): void
    {
        $mia  = $this->crearCola('processing', pendientes: 2);
        $otra = $this->crearCola('processing', pendientes: 2);

        $response = $this->withHeaders($this->headers())->postJson(
            "/api/agent/{$mia->id}/items/complete",
            ['item_ids' => $otra->items->pluck('id')->all()]
        );

        $response->assertOk();
        $this->assertSame(0, $response->json('marked'));
        $this->assertSame(0, $otra->fresh()->printed_labels, 'Un agente no puede marcar items de otra cola');
    }

    /** @test */
    public function reporting_in_bulk_counts_each_label_once(): void
    {
        $queue = $this->crearCola('processing', pendientes: 2);
        $ids   = $queue->items->pluck('id')->all();
        $url   = "/api/agent/{$queue->id}/items/complete";

        $this->withHeaders($this->headers())->postJson($url, ['item_ids' => $ids]);
        $this->withHeaders($this->headers())->postJson($url, ['item_ids' => $ids]);

        $this->assertSame(2, $queue->fresh()->printed_labels);
    }

    /** @test */
    public function the_printed_count_never_exceeds_the_size_of_the_batch(): void
    {
        $queue = $this->crearCola('processing', pendientes: 2);

        foreach ($queue->items as $item) {
            $url = "/api/agent/{$queue->id}/item/{$item->id}/complete";
            $this->withHeaders($this->headers())->postJson($url);
            $this->withHeaders($this->headers())->postJson($url);
            $this->withHeaders($this->headers())->postJson($url);
        }

        $queue->refresh();

        $this->assertLessThanOrEqual(
            $queue->total_labels,
            $queue->printed_labels,
            'Un lote no puede reportar mas impresas que su propio total'
        );
    }
}
