<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= 'password',
            'password_set_at' => now(),
            'remember_token' => Str::random(10),
            'kyc_level' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            if (! $user->roles()->exists()) {
                $user->assignRole('user');
            }
        });
    }

    public function withoutPasswordSet(): static
    {
        return $this->state(fn (array $attributes) => [
            'password_set_at' => null,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function kycApproved(): static
    {
        return $this->state(fn (array $attributes) => [
            'kyc_level' => 1,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_suspended' => true,
        ]);
    }

    public function admin(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->syncRoles(['admin']);
            $user->givePermissionTo(\Database\Seeders\PermissionSeeder::PERMISSIONS);
        });
    }

    public function creator(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->syncRoles(['user']);
        });
    }

    public function agent(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->syncRoles(['agent']);
        });
    }

    public function anonymized(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_suspended' => true,
            'anonymized_at' => now(),
            'name' => 'Deleted User',
            'email' => 'deleted+'.$attributes['email'].'@invalid.local',
        ]);
    }
}
