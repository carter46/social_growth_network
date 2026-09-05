<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 1000, 75000);

        return [
            'source' => 'platform',
            'user_id' => User::factory(),
            'reference' => 'ORD-'.Str::upper(Str::random(8)),
            'idempotency_key' => Str::uuid()->toString(),
            'amount' => $amount,
            'total_amount' => $amount,
            'status' => 'pending',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => 'completed']);
    }

    public function processing(): static
    {
        return $this->state(fn () => ['status' => 'processing']);
    }

    public function platform(): static
    {
        return $this->state(fn () => ['source' => 'platform']);
    }
}
