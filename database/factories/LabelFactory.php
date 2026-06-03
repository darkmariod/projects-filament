<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Label;
use App\Models\LabelBatch;
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

        return [
            'label_batch_id' => $labelBatch->id,
            'product_id' => $labelBatch->product_id,
            'serial' => strtoupper(fake()->unique()->bothify('SN-??-#####-V-########-#')),
            'sequence_number' => fake()->unique()->numberBetween(1, 99999999),
            'barcode' => fake()->unique()->ean13(),
            'qr_url' => fake()->url() . '/p/' . fake()->uuid(),
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
