<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class YoutubeSubscribeButtonTest extends TestCase
{
    public function test_subscribe_button_opens_the_confirmation_in_a_new_tab(): void
    {
        $html = Blade::render(
            '<x-dashboard.button :href="$url" target="_blank" rel="noopener noreferrer">Subscribe on YouTube</x-dashboard.button>',
            ['url' => 'https://www.youtube.com/channel/UC_x5XG1OV2P6uZZ5FSM9Ttw?sub_confirmation=1'],
        );

        $this->assertStringContainsString('href="https://www.youtube.com/channel/UC_x5XG1OV2P6uZZ5FSM9Ttw?sub_confirmation=1"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('noopener', $html);
        $this->assertStringContainsString('Subscribe on YouTube', $html);
    }
}
