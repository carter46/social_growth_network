<?php

namespace App\Services\Campaigns;

use App\Enums\CampaignTask;
use App\Models\PlatformProduct;
use App\Services\Engagement\TargetUrlValidator;
use App\Services\Engagement\YoutubeChannelApi;

/**
 * Checks the creator's links and comment instructions for combined YouTube products
 * and returns the order item options to store with the line.
 */
class CampaignBriefService
{
    public const COMMENT_INSTRUCTIONS_MAX = 1000;

    public function __construct(private YoutubeChannelApi $api) {}

    /**
     * Task setup frozen on the order line at purchase, so payment confirmed later
     * builds the campaign the creator actually bought.
     *
     * @return array<string, mixed>
     */
    public function taskSnapshot(PlatformProduct $product): array
    {
        $profile = $product->taskProfile();
        $completion = (int) ($product->completion_minutes ?? 0);
        $estimated = (int) ($product->estimated_minutes ?? 0);

        $snapshot = [
            'completion_minutes' => $completion > 0 ? $completion : null,
            'estimated_minutes' => $estimated > 0 ? $estimated : null,
        ];
        if ($profile->isMultiTask()) {
            $snapshot['required_tasks'] = $profile->taskValues();
        }

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function resolve(PlatformProduct $product, array $data): array
    {
        $profile = $product->taskProfile();
        if (! $profile->isMultiTask()) {
            return [];
        }

        $videoUrl = TargetUrlValidator::normalize(isset($data['target_url']) ? (string) $data['target_url'] : null);
        $videoId = TargetUrlValidator::isPostOrVideoUrl($videoUrl, 'youtube')
            ? TargetUrlValidator::extractYoutubeVideoId($videoUrl)
            : null;
        if (! $videoId) {
            throw new CampaignBriefException('Please provide a public YouTube video URL for this product.');
        }

        $brief = ['video_id' => substr($videoId, 0, 32), 'match_verified' => false];

        $channelUrl = null;
        if ($profile->needsChannelUrl()) {
            $channelUrl = TargetUrlValidator::normalize(isset($data['channel_url']) ? (string) $data['channel_url'] : null);
            if (! $channelUrl || ! TargetUrlValidator::isYoutubeChannelUrl($channelUrl)) {
                throw new CampaignBriefException(
                    'Please enter your YouTube channel link (for example https://www.youtube.com/@yourchannel).',
                    'channel_url',
                );
            }

            $brief['channel_url'] = $channelUrl;
            $ref = TargetUrlValidator::extractYoutubeChannelRef($channelUrl);
            if (($ref['type'] ?? null) === 'id') {
                $brief['channel_id'] = $ref['value'];
            }
        }

        if ($profile->needsCommentInstructions()) {
            $instructions = trim((string) ($data['comment_instructions'] ?? ''));
            if ($instructions === '') {
                throw new CampaignBriefException('Please tell agents what their comment should be about.', 'comment_instructions');
            }
            if (mb_strlen($instructions) > self::COMMENT_INSTRUCTIONS_MAX) {
                throw new CampaignBriefException('Comment instructions must be 1,000 characters or fewer.', 'comment_instructions');
            }
            $brief['comment_instructions'] = $instructions;
        }

        if (! $this->api->hasKey()) {
            return $brief;
        }

        $video = $this->api->videoLookup($videoId);
        if ($video === null) {
            return $brief;
        }

        if (! $video['found']) {
            throw new CampaignBriefException("We couldn't find that video. Check that it's public and the link is correct.");
        }

        if ($profile->has(CampaignTask::Comment) && $video['comments_disabled']) {
            throw new CampaignBriefException('Comments are turned off on that video. Turn them on in YouTube Studio, then try again.');
        }

        if ($channelUrl !== null) {
            $channel = $this->api->resolveChannel($channelUrl);
            if ($channel === null) {
                // The video's own channel is still the one agents must subscribe to; only the match is unconfirmed.
                if (! empty($video['channel_id'])) {
                    $brief['channel_id'] = $video['channel_id'];
                }

                return $brief;
            }

            if ($channel['id'] !== $video['channel_id']) {
                throw new CampaignBriefException('That video is not on the channel you entered. Use a video from the same channel.', 'channel_url');
            }
        }

        $brief['channel_id'] = $video['channel_id'];
        $brief['match_verified'] = true;

        return $brief;
    }
}
