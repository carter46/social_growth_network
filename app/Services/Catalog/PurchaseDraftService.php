<?php

namespace App\Services\Catalog;

use Illuminate\Contracts\Session\Session;

/**
 * The package a visitor configured before logging in, kept in the session so the
 * order can be finished after login or sign-up. Only one draft is kept at a time.
 */
final class PurchaseDraftService
{
    private const KEY = 'purchase_draft';

    private const TTL_MINUTES = 120;

    public function __construct(private Session $session) {}

    /**
     * @param  array<string, int>  $taskQuantities  companion task value => quantity
     */
    public function put(string $slug, int $units, array $taskQuantities, array $links): void
    {
        $this->session->put(self::KEY, [
            'slug' => $slug,
            'units' => $units,
            'task_quantities' => array_map('intval', $taskQuantities),
            'target_url' => $this->text($links['target_url'] ?? null, 2048),
            'channel_url' => $this->text($links['channel_url'] ?? null, 2048),
            'comment_instructions' => $this->text($links['comment_instructions'] ?? null, 1000),
            'saved_at' => now()->getTimestamp(),
        ]);
    }

    /** @return array<string, mixed>|null */
    public function get(?string $slug = null): ?array
    {
        $draft = $this->session->get(self::KEY);
        if (! is_array($draft) || empty($draft['slug'])) {
            return null;
        }
        if ((int) ($draft['saved_at'] ?? 0) < now()->subMinutes(self::TTL_MINUTES)->getTimestamp()) {
            $this->clear();

            return null;
        }
        if ($slug !== null && $draft['slug'] !== $slug) {
            return null;
        }

        return $draft;
    }

    public function has(): bool
    {
        return $this->get() !== null;
    }

    public function clear(?string $slug = null): void
    {
        if ($slug === null || ($this->session->get(self::KEY)['slug'] ?? null) === $slug) {
            $this->session->forget(self::KEY);
        }
    }

    /**
     * Query string for the dashboard checkout page.
     *
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    public static function checkoutQuery(array $draft): array
    {
        $query = ['units' => (int) ($draft['units'] ?? 0)];
        foreach ((array) ($draft['task_quantities'] ?? []) as $task => $qty) {
            $query['qty'][(string) $task] = (int) $qty;
        }
        foreach (['target_url', 'channel_url', 'comment_instructions'] as $field) {
            if (filled($draft[$field] ?? null)) {
                $query[$field] = $draft[$field];
            }
        }

        return $query;
    }

    private function text(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
