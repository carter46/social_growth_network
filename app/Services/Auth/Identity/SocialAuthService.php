<?php

namespace App\Services\Auth\Identity;

use App\Events\UserRegistered;
use App\Models\IntegrationProvider;
use App\Models\User;
use App\Models\UserAuthProvider;
use App\Modules\Admin\Services\AuditLogService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class SocialAuthService
{
    public function __construct(
        private readonly GoogleIdentityProvider $google,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * Authenticate a guest via a verified Google ID token.
     *
     * @return array{user: User, action: string}
     */
    public function authenticateWithGoogle(string $credential, ?string $accountType = null): array
    {
        $started = microtime(true);

        try {
            $identity = $this->google->verifyCredential($credential);
            if (! $identity->emailVerified) {
                throw new InvalidArgumentException('Google email address is not verified.');
            }

            $result = DB::transaction(function () use ($identity, $accountType) {
                $resolved = $this->resolveOrCreate($identity, $accountType);
                $this->assertNotSuspended($resolved['user']);
                $this->auditAuthSuccess($resolved['user'], $resolved['action'], $identity);

                return $resolved;
            });

            IntegrationProvider::forProvider(IntegrationProvider::GOOGLE_IDENTITY)
                ->recordSuccess((int) round((microtime(true) - $started) * 1000));

            return $result;
        } catch (\Throwable $e) {
            $this->recordAuthFailure($e->getMessage(), 'user.google.login_failed');
            throw $e;
        }
    }

    /**
     * Link Google to the currently authenticated user.
     */
    public function linkGoogle(User $user, string $credential): UserAuthProvider
    {
        $started = microtime(true);

        try {
            $identity = $this->google->verifyCredential($credential);
            if (! $identity->emailVerified) {
                throw new InvalidArgumentException('Google email address is not verified.');
            }

            $existing = UserAuthProvider::query()
                ->where('provider', $identity->provider)
                ->where('provider_user_id', $identity->providerUserId)
                ->first();

            if ($existing && (int) $existing->user_id !== (int) $user->id) {
                throw new RuntimeException('This Google account is already linked to another user.');
            }

            $current = $user->authProviders()->where('provider', $identity->provider)->first();
            if ($current) {
                if ($current->provider_user_id !== $identity->providerUserId) {
                    throw new RuntimeException('A different Google account is already linked. Disconnect it first.');
                }

                $current->fill([
                    'provider_email' => $identity->email,
                    'avatar_url' => $identity->avatarUrl,
                ])->save();

                IntegrationProvider::forProvider(IntegrationProvider::GOOGLE_IDENTITY)
                    ->recordSuccess((int) round((microtime(true) - $started) * 1000));

                return $current;
            }

            $link = $this->linkProvider($user, $identity);
            if ($user->email_verified_at === null && strcasecmp($user->email, $identity->email) === 0) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $this->audit->log(null, 'user.google.linked', $user, null, [
                'provider' => $identity->provider,
                'provider_user_id' => $identity->providerUserId,
                'email' => $identity->email,
            ], null, ['actor_id' => $user->id, 'actor_type' => 'user', 'module' => 'user']);

            IntegrationProvider::forProvider(IntegrationProvider::GOOGLE_IDENTITY)
                ->recordSuccess((int) round((microtime(true) - $started) * 1000));

            return $link;
        } catch (\Throwable $e) {
            $this->recordAuthFailure($e->getMessage(), 'user.google.link_failed');
            throw $e;
        }
    }

    public function unlinkGoogle(User $user): void
    {
        if (! $this->canDisconnectGoogle($user)) {
            throw new RuntimeException('Set a password before disconnecting Google, or you may lock yourself out.');
        }

        $link = $user->authProviders()->where('provider', UserAuthProvider::GOOGLE)->first();
        if (! $link) {
            return;
        }

        $providerUserId = $link->provider_user_id;
        $link->delete();

        $this->audit->log(null, 'user.google.unlinked', $user, [
            'provider' => UserAuthProvider::GOOGLE,
            'provider_user_id' => $providerUserId,
        ], null, null, ['actor_id' => $user->id, 'actor_type' => 'user', 'module' => 'user']);
    }

    public function canDisconnectGoogle(User $user): bool
    {
        if (! $user->hasAuthProvider(UserAuthProvider::GOOGLE)) {
            return false;
        }

        return $user->hasPasswordSet() || $user->authProviders()->where('provider', '!=', UserAuthProvider::GOOGLE)->exists();
    }

    /**
     * @return array{user: User, action: string}
     */
    private function resolveOrCreate(VerifiedIdentity $identity, ?string $accountType = null): array
    {
        $byProvider = UserAuthProvider::query()
            ->where('provider', $identity->provider)
            ->where('provider_user_id', $identity->providerUserId)
            ->first();

        if ($byProvider) {
            $user = $byProvider->user;
            $byProvider->fill([
                'provider_email' => $identity->email,
                'avatar_url' => $identity->avatarUrl,
            ])->save();

            return ['user' => $user, 'action' => 'login'];
        }

        $user = User::query()->where('email', $identity->email)->first();

        if ($user) {
            $this->linkProvider($user, $identity);
            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            return ['user' => $user, 'action' => 'linked_login'];
        }

        $user = $this->createUser($identity, $accountType);
        $this->linkProvider($user, $identity);

        return ['user' => $user, 'action' => 'signup'];
    }

    private function auditAuthSuccess(User $user, string $action, VerifiedIdentity $identity): void
    {
        $context = ['actor_id' => $user->id, 'actor_type' => 'user', 'module' => 'user'];
        $payload = [
            'provider' => $identity->provider,
            'provider_user_id' => $identity->providerUserId,
            'email' => $identity->email,
        ];

        if ($action === 'signup') {
            $this->audit->log(null, 'user.google.signup', $user, null, $payload, null, $context);

            return;
        }

        if ($action === 'linked_login') {
            $this->audit->log(null, 'user.google.linked', $user, null, $payload, null, $context);
        }

        $this->audit->log(null, 'user.google.login', $user, null, $payload, null, $context);
    }

    private function createUser(VerifiedIdentity $identity, ?string $accountType = null): User
    {
        $name = trim((string) ($identity->name ?: Str::before($identity->email, '@')));
        if ($name === '') {
            $name = 'Member';
        }

        $user = User::create([
            'name' => $name,
            'username' => $this->uniqueUsername($identity),
            'email' => $identity->email,
            'password' => Str::password(32),
            'terms_accepted_at' => now(),
        ]);

        $user->forceFill([
            'password_set_at' => null,
            'email_verified_at' => now(),
        ])->save();

        $role = $accountType === 'agent' ? 'agent' : 'user';
        $user->assignRole($role);

        event(new Registered($user));
        UserRegistered::dispatch($user->id);

        return $user;
    }

    private function linkProvider(User $user, VerifiedIdentity $identity): UserAuthProvider
    {
        return UserAuthProvider::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'provider' => $identity->provider,
            ],
            [
                'provider_user_id' => $identity->providerUserId,
                'provider_email' => $identity->email,
                'avatar_url' => $identity->avatarUrl,
            ]
        );
    }

    private function uniqueUsername(VerifiedIdentity $identity): string
    {
        $base = Str::slug(Str::before($identity->email, '@'), '_');
        $base = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $base) ?: 'user';
        $base = Str::lower(Str::limit($base, 40, ''));

        $candidate = $base;
        $i = 0;
        while (User::query()->where('username', $candidate)->exists()) {
            $i++;
            $candidate = Str::limit($base, 32, '').'_'.$i;
        }

        return $candidate;
    }

    private function assertNotSuspended(User $user): void
    {
        if ($user->is_suspended) {
            throw new RuntimeException(__('Your account has been suspended.'));
        }
    }

    private function recordAuthFailure(string $message, string $action = 'user.google.login_failed'): void
    {
        try {
            IntegrationProvider::forProvider(IntegrationProvider::GOOGLE_IDENTITY)
                ->recordFailure($message);

            $this->audit->log(null, $action, null, null, [
                'error' => mb_substr($message, 0, 500),
            ], null, ['module' => 'user']);
        } catch (\Throwable) {
            // Never break the failure path because health/audit logging failed.
        }
    }
}
