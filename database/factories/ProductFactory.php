<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'product_model_id' => ProductModel::factory(),
            'name' => fake()->unique()->word() . ' Product',
            'product_code' => strtoupper(fake()->unique()->bothify('PRD-???###')),
            'barcode' => fake()->unique()->ean13(),
            'measurements_text' => fake()->randomElement(['150x190x30', '200x190x35', '100x190x20', '90x190x25']),
            'description' => fake()->sentence(),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    public function withCode(string $code): static
    {
        return $this->state(fn (array $attributes) => [
            'product_code' => $code,
        ]);
    }
}
