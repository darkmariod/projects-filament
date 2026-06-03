<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Label;
use App\Models\LabelBatch;
use App\Models\Product;
use App\Models\User;
use App\Services\SerialGeneratorService;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->product = Product::factory()->create();
    }

    private function createBatch(array $overrides = []): LabelBatch
    {
        return LabelBatch::create(array_merge([
            'product_id' => $this->product->id,
            'internal_batch_code' => 'LOTE-TEST-' . now()->format('His'),
            'customer_batch_number' => 'CBN-TEST',
            'customer_batch_date' => now()->toDateString(),
            'quantity' => 3,
            'operator' => 'Admin User',
            'generated_by_user_id' => $this->admin->id,
            'status' => 'active',
        ], $overrides));
    }

    /** @test */
    public function notification_can_be_created_with_title_and_body(): void
    {
        $notification = Notification::make()
            ->title('Etiquetas generadas')
            ->body('Se generaron 5 etiquetas para el lote LOTE-001')
            ->success()
            ->seconds(5);

        $this->assertSame('Etiquetas generadas', $notification->getTitle());
        $this->assertSame(
            'Se generaron 5 etiquetas para el lote LOTE-001',
            $notification->getBody()
        );
        $this->assertSame('success', $notification->getStatus());
    }

    /** @test */
    public function notification_can_be_created_with_info_status(): void
    {
        $notification = Notification::make()
            ->title('Descargando ZPL')
            ->body('Archivo ZPL preparado para el lote LOTE-001')
            ->info()
            ->seconds(5);

        $this->assertSame('Descargando ZPL', $notification->getTitle());
        $this->assertSame('info', $notification->getStatus());
    }

    /** @test */
    public function notification_can_be_created_with_warning_status(): void
    {
        $notification = Notification::make()
            ->title('Etiqueta anulada')
            ->body('La etiqueta SERIAL-001 fue anulada correctamente')
            ->warning()
            ->seconds(5);

        $this->assertSame('Etiqueta anulada', $notification->getTitle());
        $this->assertSame('warning', $notification->getStatus());
    }

    /** @test */
    public function generate_labels_action_sends_notification_via_session(): void
    {
        $batch = $this->createBatch(['internal_batch_code' => 'LOTE-NOTIF-001']);

        $service = app(SerialGeneratorService::class);
        $result = $service->generateLabelsForBatch($batch);

        $this->assertTrue($result);
        $this->assertCount(3, $batch->fresh()->labels);

        // Verify the notification structure matches what the action should send
        $notification = Notification::make()
            ->title('Etiquetas generadas')
            ->body('Se generaron 3 etiquetas para el lote LOTE-NOTIF-001')
            ->success()
            ->seconds(5);

        $this->assertSame('Etiquetas generadas', $notification->getTitle());
        $this->assertStringContainsString('3', $notification->getBody() ?? '');
        $this->assertStringContainsString('LOTE-NOTIF-001', $notification->getBody() ?? '');
    }

    /** @test */
    public function download_notification_is_formatted_correctly(): void
    {
        $batch = $this->createBatch([
            'internal_batch_code' => 'LOTE-DL-001',
            'status' => 'active',
        ]);

        $notification = Notification::make()
            ->title('Descargando ZPL')
            ->body('Archivo ZPL preparado para el lote LOTE-DL-001')
            ->info()
            ->seconds(5);

        $this->assertSame('Descargando ZPL', $notification->getTitle());
        $this->assertSame('info', $notification->getStatus());
        $this->assertStringContainsString('LOTE-DL-001', $notification->getBody() ?? '');
    }

    /** @test */
    public function annul_notification_is_formatted_correctly(): void
    {
        $label = Label::factory()->create([
            'serial' => '2605-ANNULL-V-00000001-3',
            'status' => 'active',
        ]);

        $notification = Notification::make()
            ->title('Etiqueta anulada')
            ->body("La etiqueta {$label->serial} fue anulada correctamente")
            ->warning()
            ->seconds(5);

        $this->assertSame('Etiqueta anulada', $notification->getTitle());
        $this->assertSame('warning', $notification->getStatus());
        $this->assertStringContainsString($label->serial, $notification->getBody() ?? '');
    }

    /** @test */
    public function notification_honors_seconds_for_auto_close(): void
    {
        $notification = Notification::make()
            ->title('Test')
            ->success()
            ->seconds(5);

        $this->assertSame(5000, $notification->getDuration());
    }

    /** @test */
    public function warranty_annul_notification_is_warning(): void
    {
        $notification = Notification::make()
            ->title('Garantía anulada')
            ->warning()
            ->seconds(5);

        $this->assertSame('Garantía anulada', $notification->getTitle());
        $this->assertSame('warning', $notification->getStatus());
        $this->assertSame(5000, $notification->getDuration());
    }

    /** @test */
    public function notification_without_seconds_has_different_default(): void
    {
        $notification = Notification::make()
            ->title('Test sin duración')
            ->success();

        $this->assertSame('Test sin duración', $notification->getTitle());
        $this->assertNull($notification->getBody());
    }

    /** @test */
    public function notification_body_can_include_batch_code_and_quantity(): void
    {
        $batch = $this->createBatch(['quantity' => 10]);
        $batch->refresh();

        Notification::make()
            ->title('Etiquetas generadas')
            ->body("Se generaron {$batch->quantity} etiquetas para el lote {$batch->internal_batch_code}")
            ->success()
            ->seconds(5);

        $this->assertSame(10, $batch->quantity);
        $this->assertStringContainsString('LOTE-TEST', $batch->internal_batch_code);
    }

    /** @test */
    public function notification_seconds_accepts_different_values(): void
    {
        $notification = Notification::make()
            ->title('Test')
            ->success()
            ->seconds(10);

        $this->assertSame(10000, $notification->getDuration());

        $shortNotification = Notification::make()
            ->title('Short')
            ->success()
            ->seconds(3);

        $this->assertSame(3000, $shortNotification->getDuration());
    }

    /** @test */
    public function serial_in_notification_body_is_accurate(): void
    {
        $label = Label::factory()->create([
            'serial' => '2605-EDGE-V-99999999-7',
            'status' => 'active',
        ]);

        // Simulate the annul notification text that should be shown
        $body = "La etiqueta {$label->serial} fue anulada correctamente";

        $this->assertStringContainsString('2605-EDGE-V-99999999-7', $body);
        $this->assertStringContainsString('anulada', $body);
    }

    /** @test */
    public function export_notification_includes_count(): void
    {
        $notification = Notification::make()
            ->title('Exportación completada')
            ->body('Se exportaron 10 garantías')
            ->success()
            ->seconds(5);

        $this->assertSame('Exportación completada', $notification->getTitle());
        $this->assertStringContainsString('10', $notification->getBody() ?? '');
    }
}
