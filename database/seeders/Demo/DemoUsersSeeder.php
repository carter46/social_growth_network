<?php

namespace Database\Seeders\Demo;

use App\Enums\WalletType;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\Demo\Support\DemoContext;
use Database\Seeders\Demo\Support\DemoPersonaCatalog;
use Database\Seeders\Demo\Support\DemoTimeline;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUsersSeeder extends Seeder
{
    public function run(DemoContext $ctx, DemoTimeline $timeline): void
    {
        foreach (DemoPersonaCatalog::allMembers() as $row) {
            $createdAt = $timeline->monthsAgo((int) $row['months_ago'], 8, 9);
            $profileAt = $row['key'] === 'emily'
                ? null
                : $createdAt->copy()->addDays(3);

            $user = User::query()->firstOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'username' => $row['username'],
                    'password' => Hash::make('password'),
                    'bio' => $row['bio'] ?? null,
                    'country' => 'NG',
                    'phone' => '+234800'.str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT),
                ]
            );

            $user->forceFill([
                'name' => $row['name'],
                'username' => $row['username'],
                'bio' => $row['bio'] ?? null,
                'kyc_level' => (int) ($row['kyc_level'] ?? 0),
                'is_suspended' => (bool) ($row['is_suspended'] ?? false),
                'email_verified_at' => $row['key'] === 'emily' ? null : $createdAt,
                'profile_completed_at' => $profileAt,
                'terms_accepted_at' => $createdAt,
                'suspended_at' => ! empty($row['is_suspended']) ? $createdAt->copy()->addMonths(2) : null,
            ])->save();

            if (! $user->hasRole('user')) {
                $user->assignRole('user');
            }

            $ctx->stamp($user, $createdAt, [
                'email_verified_at' => $user->email_verified_at,
                'profile_completed_at' => $user->profile_completed_at,
                'terms_accepted_at' => $createdAt,
            ]);

            Wallet::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'type' => WalletType::User,
                    'balance' => 0,
                    'locked_balance' => 0,
                    'currency' => config('platform.currency'),
                    'status' => 'active',
                ]
            );
            $wallet = Wallet::query()->where('user_id', $user->id)->first();
            if ($wallet) {
                $ctx->track($wallet);
            }

            $ctx->registerMember($row['key'], $user->fresh());
        }

        $ctx->note('✓ Members created ('.count($ctx->members).' personas with wallets)');
    }
}
