<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'second_name' => fake()->optional()->firstName(),
            'last_name' => fake()->lastName(),
            'second_last_name' => fake()->optional()->lastName(),
            'document_type' => fake()->randomElement(['cedula', 'ruc', 'pasaporte']),
            'document_number' => fake()->unique()->numerify('##########'),
            'birth_date' => fake()->optional()->date(max: '2005-01-01'),
            'gender' => fake()->optional()->randomElement(['masculino', 'femenino', 'otro']),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('09########'),
            'address' => fake()->address(),
            'province' => fake()->state(),
            'city' => fake()->city(),
            'sector' => fake()->optional()->word(),
        ];
    }
}
