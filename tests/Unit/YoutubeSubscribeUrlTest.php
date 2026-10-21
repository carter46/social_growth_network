<?php

namespace Tests\Unit;

use App\Services\Engagement\TargetUrlValidator;
use PHPUnit\Framework\TestCase;

class YoutubeSubscribeUrlTest extends TestCase
{
    public function test_channel_id_opens_the_subscribe_confirmation(): void
    {
        $url = TargetUrlValidator::youtubeSubscribeUrl(
            'https://www.youtube.com/@creator',
            'UCabcdefghijklmnopqrstuv',
        );

        $this->assertSame(
            'https://www.youtube.com/channel/UCabcdefghijklmnopqrstuv?sub_confirmation=1',
            $url,
        );
    }

    public function test_handle_link_opens_the_subscribe_confirmation(): void
    {
        $this->assertSame(
            'https://www.youtube.com/@creator?sub_confirmation=1',
            TargetUrlValidator::youtubeSubscribeUrl('https://www.youtube.com/@creator'),
        );
    }

    public function test_a_video_link_is_not_turned_into_a_subscribe_link(): void
    {
        $this->assertNull(TargetUrlValidator::youtubeSubscribeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
    }

    public function test_a_mobile_channel_tab_still_opens_the_confirmation(): void
    {
        $this->assertSame(
            'https://www.youtube.com/@creator?sub_confirmation=1',
            TargetUrlValidator::youtubeSubscribeUrl('https://m.youtube.com/@Creator/videos?si=abc'),
        );
    }

    public function test_a_stored_confirmation_link_is_not_doubled(): void
    {
        $this->assertSame(
            'https://www.youtube.com/channel/UC_x5XG1OV2P6uZZ5FSM9Ttw?sub_confirmation=1',
            TargetUrlValidator::youtubeSubscribeUrl('https://www.youtube.com/channel/UC_x5XG1OV2P6uZZ5FSM9Ttw?sub_confirmation=1'),
        );
    }
}
