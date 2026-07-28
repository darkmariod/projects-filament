<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\ProductModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductModel>
 */
class ProductModelFactory extends Factory
{
    protected $model = ProductModel::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => fake()->unique()->word() . ' Model',
            'code' => strtoupper(fake()->unique()->bothify('MOD-???###')),
            'type' => fake()->randomElement(['Colchón', 'Base', 'Almohada', 'Protector']),
            'class' => fake()->randomElement(['A', 'B', 'C', 'Premium']),
            'warranty_years' => fake()->numberBetween(1, 10),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
