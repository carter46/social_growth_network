<?php

namespace App\Support;

use App\Enums\CampaignTask;
use App\Enums\EngagementMetric;
use App\Models\Campaign;
use App\Models\PlatformProduct;

/**
 * What one unit of a product (or a campaign snapshot) asks an agent to do.
 *
 * Multi-task products run the bundled step flow. Single-task products and
 * legacy campaigns (required_tasks = null) keep their original flows.
 */
final class ProductTaskProfile
{
    /**
     * @param  list<CampaignTask>  $tasks
     */
    private function __construct(
        public readonly ?string $slug,
        private readonly array $tasks,
        private readonly ?EngagementMetric $metric,
        private readonly bool $fromSnapshot = false,
        private readonly ?CampaignTask $main = null,
    ) {}

    public static function forSlug(?string $slug, mixed $order = null, mixed $main = null): self
    {
        $slug = filled($slug) ? strtolower(trim((string) $slug)) : null;
        $allowed = self::allowedTasks($slug);
        $main = $main instanceof CampaignTask ? $main : CampaignTask::tryFrom((string) $main);

        return new self(
            $slug,
            self::normalizeOrder($allowed, $order) ?? $allowed,
            EngagementMetric::fromProductSlug($slug),
            main: $main !== null && in_array($main, $allowed, true) ? $main : null,
        );
    }

    public static function forProduct(?PlatformProduct $product): self
    {
        return self::forSlug($product?->slug, $product?->task_order, $product?->main_task);
    }

    /**
     * The task bundle frozen on an order line at purchase. Lines bought before bundles
     * existed carry no required_tasks and keep the product's original single task.
     *
     * @param  array<string, mixed>  $options
     */
    public static function forOrderOptions(?string $slug, array $options): self
    {
        $slug = filled($slug) ? strtolower(trim((string) $slug)) : null;
        $metric = EngagementMetric::fromProductSlug($slug);

        $tasks = [];
        foreach ((array) ($options['required_tasks'] ?? []) as $value) {
            $task = CampaignTask::tryFrom((string) $value);
            if ($task !== null && ! in_array($task, $tasks, true)) {
                $tasks[] = $task;
            }
        }

        if (count($tasks) > 1) {
            return new self($slug, $tasks, $metric, true);
        }

        $legacy = CampaignTask::fromMetric($metric);

        return new self($slug, $legacy ? [$legacy] : [], $metric);
    }

    public static function forCampaign(?Campaign $campaign): self
    {
        if ($campaign === null) {
            return new self(null, [], null);
        }

        $meta = is_array($campaign->meta) ? $campaign->meta : [];
        $slug = $meta['product_slug'] ?? ($campaign->relationLoaded('product') ? $campaign->product?->slug : null);
        $metric = EngagementMetric::tryFrom((string) $campaign->engagement_metric)
            ?? EngagementMetric::fromProductSlug($slug);

        $snapshot = $campaign->required_tasks;
        if (is_array($snapshot) && $snapshot !== []) {
            $tasks = [];
            foreach ($snapshot as $value) {
                $task = CampaignTask::tryFrom((string) $value);
                if ($task !== null && ! in_array($task, $tasks, true)) {
                    $tasks[] = $task;
                }
            }

            return new self($slug, $tasks, $metric, true);
        }

        $legacy = CampaignTask::fromMetric($metric);

        return new self($slug, $legacy ? [$legacy] : [], $metric);
    }

    /**
     * Tasks a product includes, in the default order from config.
     *
     * @return list<CampaignTask>
     */
    public static function allowedTasks(?string $slug): array
    {
        $slug = filled($slug) ? strtolower(trim((string) $slug)) : '';
        $configured = ((array) config('platform_products.tasks', []))[$slug] ?? null;

        if (is_array($configured) && $configured !== []) {
            return array_values(array_filter(array_map(
                fn ($value) => CampaignTask::tryFrom((string) $value),
                $configured,
            )));
        }

        $legacy = CampaignTask::fromMetric(EngagementMetric::fromProductSlug($slug));

        return $legacy ? [$legacy] : [];
    }

    /**
     * The given order as tasks when it holds exactly the allowed tasks, each once; otherwise null.
     *
     * @param  list<CampaignTask>  $allowed
     * @return list<CampaignTask>|null
     */
    public static function normalizeOrder(array $allowed, mixed $order): ?array
    {
        if (! is_array($order) || $order === []) {
            return null;
        }

        $tasks = [];
        foreach ($order as $value) {
            $task = $value instanceof CampaignTask ? $value : CampaignTask::tryFrom((string) $value);
            if ($task === null || in_array($task, $tasks, true)) {
                return null;
            }
            $tasks[] = $task;
        }

        $want = array_map(fn (CampaignTask $t) => $t->value, $allowed);
        $got = array_map(fn (CampaignTask $t) => $t->value, $tasks);
        sort($want);
        sort($got);

        return $want === $got ? $tasks : null;
    }

    public static function isValidOrder(?string $slug, mixed $order): bool
    {
        return self::normalizeOrder(self::allowedTasks($slug), $order) !== null;
    }

    /** @return list<CampaignTask> */
    public function tasks(): array
    {
        return $this->tasks;
    }

    /** @return list<string> */
    public function taskValues(): array
    {
        return array_map(fn (CampaignTask $t) => $t->value, $this->tasks);
    }

    public function isMultiTask(): bool
    {
        return count($this->tasks) > 1;
    }

    /** Campaign runs the bundled step flow (snapshot present). */
    public function usesStepFlow(): bool
    {
        return $this->fromSnapshot && $this->tasks !== [];
    }

    public function has(CampaignTask $task): bool
    {
        return in_array($task, $this->tasks, true);
    }

    /** The service the product is priced on (its main quantity): the admin's choice, else the product's metric. */
    public function primaryTask(): ?CampaignTask
    {
        if ($this->main !== null && $this->has($this->main)) {
            return $this->main;
        }

        $fromMetric = CampaignTask::fromMetric($this->metric);
        if ($fromMetric !== null && $this->has($fromMetric)) {
            return $fromMetric;
        }

        return $this->tasks[0] ?? null;
    }

    /**
     * Tasks other than the main one, in the product's task order.
     *
     * @return list<CampaignTask>
     */
    public function companionTasks(): array
    {
        $primary = $this->primaryTask();

        return array_values(array_filter($this->tasks, fn (CampaignTask $t) => $t !== $primary));
    }

    /** Customer-facing name for the quantity of one task. */
    public function quantityLabel(CampaignTask $task): string
    {
        if ($task === CampaignTask::fromMetric($this->metric)) {
            $fromMetric = match ($this->metric) {
                EngagementMetric::Likes => 'Likes',
                EngagementMetric::Comments => 'Comments',
                EngagementMetric::Views => 'Views',
                EngagementMetric::WatchHours => 'Watch hours',
                EngagementMetric::Subscribers => 'Subscribers',
                default => null,
            };
            if ($fromMetric !== null) {
                return $fromMetric;
            }
        }

        return match ($task) {
            CampaignTask::Subscribe => 'Subscribers',
            CampaignTask::Watch => 'Watch hours',
            CampaignTask::Comment => 'Comments',
            CampaignTask::Like => 'Likes',
        };
    }

    public function legacyMetric(): ?EngagementMetric
    {
        return $this->metric;
    }

    /** Single-task Subscribers: the target URL itself is the channel. */
    public function targetIsChannel(): bool
    {
        return ! $this->isMultiTask() && $this->has(CampaignTask::Subscribe);
    }

    /** Combined product with a subscribe step: a separate channel URL is collected. */
    public function needsChannelUrl(): bool
    {
        return $this->isMultiTask() && $this->has(CampaignTask::Subscribe);
    }

    public function needsVideoUrl(): bool
    {
        return ! $this->targetIsChannel();
    }

    public function needsCommentInstructions(): bool
    {
        return $this->isMultiTask() && $this->has(CampaignTask::Comment);
    }

    public function needsWatchMinutes(): bool
    {
        return $this->has(CampaignTask::Watch);
    }

    public function requiresCompletionTime(): bool
    {
        return $this->slug !== null
            && in_array($this->slug, (array) config('platform_products.completion_time_required', []), true);
    }

    /** Standalone watch products pay when the watch timer is claimed. */
    public function paysOnWatchClaim(): bool
    {
        return ! $this->isMultiTask() && $this->has(CampaignTask::Watch);
    }

    /** Legacy count-change verification (Likes, and single-task legacy Comments campaigns). */
    public function usesCountChangeVerification(): bool
    {
        return ! $this->isMultiTask() && (bool) $this->metric?->usesCountChangeVerification();
    }

    /** Singular label for one unit (one agent completing every task once). */
    public function unitLabel(): string
    {
        if ($this->isMultiTask()) {
            return 'unit';
        }

        return match ($this->metric) {
            EngagementMetric::Likes => 'like',
            EngagementMetric::Comments => 'comment',
            EngagementMetric::Views => 'view',
            EngagementMetric::WatchHours => 'watch hour',
            EngagementMetric::Subscribers => 'subscriber',
            default => 'unit',
        };
    }

    public function sequenceLabel(string $separator = ' → '): string
    {
        return implode($separator, array_map(fn (CampaignTask $t) => $t->shortLabel(), $this->tasks));
    }

    /** @return list<array{value: string, label: string, short: string}> */
    public function toArray(): array
    {
        return array_map(fn (CampaignTask $t) => [
            'value' => $t->value,
            'label' => $t->label(),
            'short' => $t->shortLabel(),
        ], $this->tasks);
    }
}
