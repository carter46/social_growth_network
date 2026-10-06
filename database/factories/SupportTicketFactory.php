<?php

namespace Database\Factories;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    protected $model = SupportTicket::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category' => fake()->randomElement(array_keys(SupportTicket::CREATOR_CATEGORIES)),
            'subject' => fake()->sentence(6),
            'body' => fake()->paragraph(),
            'status' => 'open',
            'priority' => fake()->randomElement(['low', 'normal', 'high']),
            'assigned_to' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => ['status' => 'open']);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }

    public function resolved(): static
    {
        return $this->state(fn () => ['status' => 'resolved']);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => 'closed']);
    }

    public function payment(): static
    {
        return $this->state(fn () => [
            'category' => 'payment',
            'subject' => 'Deposit not reflected',
        ]);
    }

    public function kyc(): static
    {
        return $this->state(fn () => [
            'category' => 'kyc',
            'subject' => 'Document rejected',
        ]);
    }

    public function marketplace(): static
    {
        return $this->state(fn () => [
            'category' => 'marketplace',
            'subject' => 'Order not delivered',
        ]);
    }
}
