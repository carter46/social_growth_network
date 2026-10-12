<?php

namespace App\Services\Catalog;

use App\Enums\CampaignTask;
use App\Models\PlatformProduct;
use App\Models\PlatformProductVariant;

/**
 * Package price for a product and the quantities a creator picked.
 *
 * The main service is priced per unit (variant price / pricing units). Companion
 * services include their admin minimum; every unit above it costs the same unit price.
 */
final class PackageQuoteService
{
    /**
     * Quantity rows for the product page and checkout, main service first.
     *
     * @return array{unit_price: string, package_price: string, rows: list<array{task: string, label: string, primary: bool, min: int, max: int, hint: ?string}>}|null
     */
    public function layout(PlatformProduct $product): ?array
    {
        $variant = $product->pricingVariant();
        if (! $variant || ! $variant->isPerUnit()) {
            return null;
        }

        $profile = $product->taskProfile();
        $primary = $profile->primaryTask();
        $unitPrice = $variant->billingUnitPrice();
        $limits = $product->companionLimits();
        $watchMinutes = max(0, (int) ($product->estimated_minutes ?? 0));

        $rows = [];
        if ($primary !== null) {
            $rows[] = [
                'task' => $primary->value,
                'label' => $profile->quantityLabel($primary),
                'primary' => true,
                'min' => $variant->effectiveMinUnits(),
                'max' => $variant->effectiveMaxUnits(),
                'hint' => $this->hint($primary, $watchMinutes),
            ];
        }
        foreach ($profile->companionTasks() as $task) {
            if (! isset($limits[$task->value])) {
                continue;
            }
            $rows[] = [
                'task' => $task->value,
                'label' => $profile->quantityLabel($task),
                'primary' => false,
                'min' => $limits[$task->value]['min'],
                'max' => $limits[$task->value]['max'],
                'hint' => $this->hint($task, $watchMinutes),
            ];
        }

        return [
            'unit_price' => $unitPrice,
            'package_price' => bcmul($unitPrice, (string) $variant->effectiveMinUnits(), 2),
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $companionQty  task value => quantity
     * @return array{unit_price: string, main_task: ?string, main_qty: int, task_quantities: array<string, int>, task_limits: array<string, array{min: int, max: int}>, extra_units: int, billable_units: int, total: string}
     *
     * @throws PackageQuoteException
     */
    public function quote(PlatformProduct $product, mixed $mainQty, array $companionQty = [], ?PlatformProductVariant $variant = null): array
    {
        $variant ??= $product->pricingVariant();
        if (! $variant || ! $variant->isPerUnit()) {
            throw new PackageQuoteException('This product is not available to buy yet. Please try again later.');
        }

        $profile = $product->taskProfile();
        $primary = $profile->primaryTask();
        $unitPrice = $variant->billingUnitPrice();

        $main = $this->wholeNumber($mainQty);
        $min = $variant->effectiveMinUnits();
        $max = $variant->effectiveMaxUnits();
        $mainLabel = $primary ? strtolower($profile->quantityLabel($primary)) : 'units';
        if ($main === null || $main < $min || $main > $max) {
            throw new PackageQuoteException(
                'Enter between '.number_format($min).' and '.number_format($max).' '.$mainLabel.'.',
                'units',
            );
        }

        $companions = $profile->companionTasks();
        $allowed = array_map(fn (CampaignTask $t) => $t->value, $companions);
        foreach (array_keys($companionQty) as $key) {
            if (! in_array((string) $key, $allowed, true)) {
                throw new PackageQuoteException('This product does not include that service.', 'qty');
            }
        }

        $limits = $product->companionLimits();
        $taskQuantities = $primary ? [$primary->value => $main] : [];
        $extra = 0;

        foreach ($companions as $task) {
            $range = $limits[$task->value] ?? null;
            if ($range === null) {
                throw new PackageQuoteException('This product is not available to buy yet. Please try again later.');
            }

            $raw = $companionQty[$task->value] ?? $range['min'];
            $qty = $this->wholeNumber($raw);
            if ($qty === null || $qty < $range['min'] || $qty > $range['max']) {
                throw new PackageQuoteException(
                    'Enter between '.number_format($range['min']).' and '.number_format($range['max']).' '.strtolower($profile->quantityLabel($task)).'.',
                    'qty.'.$task->value,
                );
            }

            if ($qty > 0) {
                $taskQuantities[$task->value] = $qty;
            }
            $extra += $qty - $range['min'];
        }

        $billable = $main + $extra;

        return [
            'unit_price' => $unitPrice,
            'main_task' => $primary?->value,
            'main_qty' => $main,
            'task_quantities' => $taskQuantities,
            'task_limits' => array_intersect_key($limits, array_flip($allowed)),
            'extra_units' => $extra,
            'billable_units' => $billable,
            'total' => bcmul($unitPrice, (string) $billable, 2),
        ];
    }

    private function wholeNumber(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^\d{1,9}$/', trim($value))) {
            return (int) trim($value);
        }

        return null;
    }

    private function hint(CampaignTask $task, int $watchMinutes): ?string
    {
        return match ($task) {
            CampaignTask::Watch => $watchMinutes > 0
                ? 'Each unit is one agent watching your video for '.$watchMinutes.' '.($watchMinutes === 1 ? 'minute' : 'minutes').'. This counts agent watch sessions, not YouTube Analytics watch hours.'
                : 'Each unit is one agent watching your video for the set time.',
            CampaignTask::Subscribe => 'Each unit is one agent subscribing to your channel.',
            CampaignTask::Comment => 'Each unit is one agent writing their own comment.',
            CampaignTask::Like => 'Each unit is one agent liking your video.',
        };
    }
}
