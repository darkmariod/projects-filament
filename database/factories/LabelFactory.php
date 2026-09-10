<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Label;
use App\Models\LabelBatch;
use App\Services\SerialGeneratorService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Label>
 */
class LabelFactory extends Factory
{
    protected $model = Label::class;

    public function definition(): array
    {
        $labelBatch = LabelBatch::factory()->create();

        // El código público se arma igual que en producción: 20 caracteres del
        // mismo alfabeto, y la URL del QR lo lleva a él y nunca al serial. Un
        // factory que se aparta de eso deja pasar fallas de seguridad reales.
        $token = app(SerialGeneratorService::class)->generatePublicToken();

        return [
            'label_batch_id' => $labelBatch->id,
            'product_id' => $labelBatch->product_id,
            'serial' => strtoupper(fake()->unique()->bothify('SN-??-#####-V-########-#')),
            'public_token' => $token,
            'sequence_number' => fake()->unique()->numberBetween(1, 99999999),
            'barcode' => fake()->unique()->ean13(),
            'qr_url' => app(SerialGeneratorService::class)->buildPublicUrl($token),
            'status' => 'available',
        ];
    }

    public function registered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'registered',
            'registered_at' => now(),
        ]);
    }

    public function anulled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'anulled',
        ]);
    }

    public function printed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'printed',
            'printed_at' => now(),
        ]);
    }
}
