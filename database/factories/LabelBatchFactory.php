<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LabelBatch;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabelBatch>
 */
class LabelBatchFactory extends Factory
{
    protected $model = LabelBatch::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'internal_batch_code' => 'LOTE-' . strtoupper(fake()->unique()->bothify('???-####')),
            'customer_batch_number' => 'CBN-' . fake()->unique()->numerify('#####'),
            'customer_batch_date' => fake()->date(),
            'quantity' => fake()->numberBetween(1, 100),
            'operator' => fake()->name(),
            'generated_by_user_id' => User::factory(),
            'status' => 'active',
        ];
    }

    public function generated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'generated',
            'generated_at' => now(),
        ]);
    }

    public function anulled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'anulled',
        ]);
    }
}
