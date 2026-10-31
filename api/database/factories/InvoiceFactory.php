<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'user_id' => fn (array $attributes) => Customer::find($attributes['customer_id'])->user_id,
            'number' => 'INV-'.fake()->unique()->numerify('#####'),
            'description' => fake()->sentence(3),
            'amount_cents' => 12500,
            'currency' => 'aud',
            'status' => InvoiceStatus::Open,
            'due_on' => now()->addDays(14),
        ];
    }
}
