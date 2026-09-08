<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'password_set_at',
        'phone',
        'country',
        'bio',
        'avatar',
        'terms_accepted_at',
        'profile_completed_at',
    ];

    protected $guarded = ['kyc_level', 'is_suspended'];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_set_at' => 'datetime',
            'is_suspended' => 'boolean',
            'suspended_at' => 'datetime',
            'anonymized_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'profile_completed_at' => 'datetime',
        ];
    }

    public function authProviders(): HasMany
    {
        return $this->hasMany(UserAuthProvider::class);
    }

    public function hasPasswordSet(): bool
    {
        return $this->password_set_at !== null;
    }

    public function hasAuthProvider(string $provider): bool
    {
        return $this->authProviders()->where('provider', $provider)->exists();
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(UserBankAccount::class);
    }

    public function activeBankAccount(): HasOne
    {
        return $this->hasOne(UserBankAccount::class)->where('active', true);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function domainRegistrations(): HasManyThrough
    {
        return $this->hasManyThrough(DomainRegistration::class, Order::class);
    }

    public function tools(): HasMany
    {
        return $this->hasMany(UserTool::class);
    }

    public function kycSubmissions(): HasMany
    {
        return $this->hasMany(KycSubmission::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    /**
     * Root-absolute (or scheme-absolute) avatar URL for img src.
     */
    public function avatarUrl(): ?string
    {
        if (! is_string($this->avatar) || $this->avatar === '') {
            return null;
        }

        return app(\App\Services\Media\MediaPathService::class)->urlFromLegacyPath($this->avatar);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $letters = collect($parts)
            ->filter()
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->take(2)
            ->implode('');

        if ($letters !== '') {
            return $letters;
        }

        $fallback = (string) ($this->username ?: $this->email ?: '?');

        return mb_strtoupper(mb_substr($fallback, 0, 1));
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function suspend(?int $administratorId = null): bool
    {
        if ($this->is_suspended) {
            return true;
        }

        return $this->forceFill([
            'is_suspended' => true,
            'suspended_at' => now(),
            'suspended_by' => $administratorId,
        ])->save();
    }

    public function restoreAccess(): bool
    {
        return $this->forceFill([
            'is_suspended' => false,
            'suspended_at' => null,
            'suspended_by' => null,
        ])->save();
    }

    public function scopeNotAnonymized(Builder $query): Builder
    {
        return $query->whereNull($this->getTable().'.anonymized_at');
    }

    public function isAnonymized(): bool
    {
        return $this->anonymized_at !== null;
    }

    /** Safe name for UI (never expose tombstone usernames). */
    public function displayName(): string
    {
        return $this->isAnonymized() ? __('Deleted User') : (string) ($this->name ?: __('User'));
    }

    /** Safe email for UI — null when anonymized so tombstone addresses are never shown. */
    public function displayEmail(): ?string
    {
        return $this->isAnonymized() ? null : $this->email;
    }

    /** Single-line admin label (email preferred; falls back to name). */
    public function adminLabel(): string
    {
        if ($this->isAnonymized()) {
            return __('Deleted User');
        }

        return (string) ($this->email ?: $this->name ?: __('User'));
    }

    public static function labelFor(?self $user): string
    {
        return $user?->adminLabel() ?? __('Deleted User');
    }

    public static function nameFor(?self $user): string
    {
        return $user?->displayName() ?? __('Deleted User');
    }

    /**
     * Scrub personal data immediately. The tombstone is hidden from admin lists and
     * hard-purged after 24 hours by `users:purge-anonymized`.
     * Admins must never be anonymized.
     */
    public function anonymize(?int $administratorId = null): bool
    {
        if ($this->hasRole('admin') || $this->anonymized_at !== null) {
            return false;
        }

        return DB::transaction(function () use ($administratorId): bool {
            $id = $this->getKey();
            $tombstoneUsername = 'deleted_'.$id;

            $saved = $this->forceFill([
                'name' => 'Deleted User',
                'username' => $tombstoneUsername,
                'email' => "deleted+{$id}@invalid.local",
                'phone' => null,
                'country' => null,
                'bio' => null,
                'avatar' => null,
                'email_verified_at' => null,
                'remember_token' => null,
                // Plain string — hashed cast will hash once.
                'password' => Str::random(64),
                'password_set_at' => null,
                'is_suspended' => true,
                'suspended_at' => $this->suspended_at ?? now(),
                'suspended_by' => $administratorId ?? $this->suspended_by,
                'anonymized_at' => now(),
            ])->save();

            $this->authProviders()->delete();

            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $id)->delete();
            }

            if (Schema::hasTable('personal_access_tokens')) {
                DB::table('personal_access_tokens')
                    ->where('tokenable_type', self::class)
                    ->where('tokenable_id', $id)
                    ->delete();
            }

            return $saved;
        });
    }

    /**
     * Hard-delete anonymized tombstones older than the retention window.
     * Pass 0 hours to purge every anonymized tombstone immediately.
     *
     * @return array{purged: int, failed: int}
     */
    public static function purgeAnonymizedOlderThanHours(int $hours = 24): array
    {
        $cutoff = $hours <= 0 ? now() : now()->subHours($hours);
        $purged = 0;
        $failed = 0;

        static::query()
            ->whereNotNull('anonymized_at')
            ->where('anonymized_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(50, function ($users) use (&$purged, &$failed) {
                foreach ($users as $user) {
                    try {
                        DB::transaction(function () use ($user) {
                            // Clear reverse refs that block delete (nullOnDelete columns).
                            static::query()->where('suspended_by', $user->id)->update(['suspended_by' => null]);

                            if (method_exists($user, 'roles')) {
                                $user->roles()->detach();
                            }
                            if (method_exists($user, 'permissions')) {
                                $user->permissions()->detach();
                            }

                            $user->delete();
                        });
                        $purged++;
                    } catch (\Throwable $e) {
                        report($e);
                        $failed++;
                    }
                }
            });

        return compact('purged', 'failed');
    }

    public function unreadNotificationsCount(): int
    {
        try {
            if (! Schema::hasTable('user_notifications')) {
                return 0;
            }

            return (int) $this->notifications()->whereNull('read_at')->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function hasApprovedKyc(int $level = 1): bool
    {
        // Platform-wide toggle: when KYC is not required, treat members as verified.
        if (! SystemSetting::kycRequired()) {
            return true;
        }

        return $this->kyc_level >= $level;
    }

    /**
     * Role-aware landing page after login/verification.
     */
    public function homeRoute(): string
    {
        if ($this->hasRole('admin')) {
            return route('admin', absolute: false);
        }

        if ($this->hasRole('agent')) {
            return route('agent', absolute: false);
        }

        return route('dashboard', absolute: false);
    }

    public function isCreator(): bool
    {
        return $this->hasRole('user');
    }

    public function isAgent(): bool
    {
        return $this->hasRole('agent');
    }

    public function sendEmailVerificationNotification(): void
    {
        \App\Http\Controllers\Auth\OtpVerificationController::createAndSendOtp($this);
    }

    public function sendPasswordResetNotification($token): void
    {
        $url = url(route('password.reset', [
            'token' => $token,
            'email' => $this->email,
        ], false));

        $html = view('emails.password-reset', [
            'url' => $url,
            'count' => config('auth.passwords.users.expire', 60),
            'user' => $this,
        ])->render();

        app(\App\Services\Communications\Email\EmailService::class)->sendMailableHtml(
            to: $this->email,
            subject: 'Reset your password - '.config('app.name'),
            html: $html,
            profile: \App\Services\Communications\Email\EmailProfile::Security,
            templateKey: 'password_reset',
        );
    }
}
