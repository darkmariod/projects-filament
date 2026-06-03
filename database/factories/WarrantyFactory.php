<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Label;
use App\Models\Warranty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warranty>
 */
class WarrantyFactory extends Factory
{
    protected $model = Warranty::class;

    public function definition(): array
    {
        $purchaseDate = fake()->dateTimeBetween('-1 year', 'now');
        $endDate = (clone $purchaseDate)->modify('+1 year');

        return [
            'label_id' => Label::factory()->registered(),
            'customer_id' => Customer::factory(),
            'store_name' => fake()->company(),
            'invoice_number' => 'INV-' . fake()->unique()->numerify('######'),
            'purchase_date' => $purchaseDate,
            'warranty_start_date' => $purchaseDate,
            'warranty_end_date' => $endDate,
            'status' => 'active',
            'terms_accepted' => true,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
        ]);
    }

    public function anulled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'anulled',
        ]);
    }
}
