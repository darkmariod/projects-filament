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
}
