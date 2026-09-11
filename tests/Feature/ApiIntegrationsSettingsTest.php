<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiIntegrationsSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_youtube_api_key(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.settings.api-integrations.update'), [
                'channel' => 'youtube',
                'api_youtube_data_key' => 'test-yt-key',
            ])
            ->assertRedirect();

        $this->assertSame('test-yt-key', SystemSetting::get('api_youtube_data_key'));
    }
}
