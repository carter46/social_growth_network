<?php

namespace App\Services\Catalog;

use App\Models\PlatformProduct;
use App\Services\Campaigns\CampaignBriefException;
use App\Services\Campaigns\CampaignBriefService;
use App\Services\Engagement\TargetUrlValidator;
use App\Services\Engagement\YoutubeLookupService;
use InvalidArgumentException;

/**
 * Checks the links and comment instructions on a package before checkout, using the
 * same YouTube rules as the live preview, and returns the campaign brief to store.
 */
final class PackageLinkValidator
{
    public function __construct(
        private YoutubeLookupService $lookup,
        private CampaignBriefService $briefs,
    ) {}

    /**
     * @param  array<string, mixed>  $input  target_url, channel_url, comment_instructions
     * @return array<string, mixed>
     *
     * @throws CampaignBriefException
     */
    public function validate(PlatformProduct $product, array $input): array
    {
        if (! $product->requiresTargetUrl()) {
            return [];
        }

        $profile = $product->taskProfile();
        $tasks = $profile->tasks();

        $targetUrl = TargetUrlValidator::normalize(isset($input['target_url']) ? (string) $input['target_url'] : null);
        if ($targetUrl === null) {
            if ($tasks === []) {
                return [];
            }
            throw new CampaignBriefException('Enter your link before checkout.', 'target_url');
        }
        try {
            TargetUrlValidator::assertValidForProduct($targetUrl, (string) $product->slug);
        } catch (InvalidArgumentException $e) {
            throw new CampaignBriefException($e->getMessage(), 'target_url');
        }

        if ($tasks !== []) {
            if ($profile->targetIsChannel()) {
                $this->assertAllowed($this->lookup->channel($targetUrl, $tasks, 0), 'target_url');
            } else {
                $this->assertAllowed($this->lookup->video($targetUrl, $tasks), 'target_url');
            }

            if ($profile->needsChannelUrl() && filled($input['channel_url'] ?? null)) {
                $this->assertAllowed($this->lookup->channel((string) $input['channel_url'], $tasks, 0), 'channel_url');
            }
        }

        return $this->briefs->resolve($product, $input);
    }

    /** @param  array{status: string, message: ?string}  $result */
    private function assertAllowed(array $result, string $field): void
    {
        if (! YoutubeLookupService::allowsPurchase($result['status'])) {
            throw new CampaignBriefException((string) ($result['message'] ?? 'Check the link and try again.'), $field);
        }
    }
}
