<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
final class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = (float) fake()->numberBetween(500, 50000);

        return [
            'user_id' => User::factory(),
            'ipn' => (string) fake()->unique()->numerify('############'),
            'terminal_id' => (string) fake()->bothify('######??'),
            'status' => Payment::STATUS_PENDING,
            'amount' => $amount,
            'total_amount' => $amount,
            'amount_paid' => 0,
            'description' => 'Osun State Harmonised Bill',
            'customer_id' => (string) fake()->numerify('######'),
            'customer_ipn' => null,
            'customer_name' => fake()->name(),
            'customer_email' => null,
            'customer_phone' => (string) fake()->numerify('0##########'),
            'customer_address' => fake()->address(),
            'reference' => null,
            'paid_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'amount_paid' => $attributes['total_amount'],
            'reference' => 'TRX-'.fake()->numerify('######'),
            'paid_at' => now(),
        ]);
    }
}
