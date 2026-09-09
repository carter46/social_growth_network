<?php

namespace Tests\Feature;

use App\Support\HelpContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_hub_loads_with_categories(): void
    {
        $this->get(route('help'))
            ->assertOk()
            ->assertSee('Help Center')
            ->assertSee('Getting Started')
            ->assertSee('Campaigns & Tasks')
            ->assertSee('For Creators')
            ->assertSee('For Agents')
            ->assertSee('Contact Support')
            ->assertDontSee('Institutional security', false);
    }

    public function test_help_article_loads(): void
    {
        $this->get(route('help.article', 'getting-started'))
            ->assertOk()
            ->assertSee('Getting Started')
            ->assertSee('Creators and Agents')
            ->assertSee('Creating your account')
            ->assertSee('assets/images/ai-powered-device-concept copy.jpg', false);
    }

    public function test_every_help_article_has_an_existing_hero_image(): void
    {
        foreach (HelpContent::all() as $slug => $article) {
            $this->assertArrayHasKey('hero_image', $article, "Missing hero image for {$slug}.");
            $this->assertFileExists(public_path($article['hero_image']), "Hero image does not exist for {$slug}.");
        }
    }

    public function test_unknown_help_article_404s(): void
    {
        $this->get(route('help.article', 'does-not-exist'))
            ->assertNotFound();
    }

    public function test_help_content_estimates_reading_time(): void
    {
        $article = HelpContent::find('getting-started');
        $this->assertNotNull($article);
        $this->assertGreaterThanOrEqual(1, $article['reading_minutes']);
        $this->assertNotEmpty($article['updated_at_display']);
    }

    public function test_contact_page_loads(): void
    {
        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('Contact & Support')
            ->assertSee('Direct contact methods')
            ->assertSee('Support tickets')
            ->assertDontSee('Live chat is not enabled yet');
    }

    public function test_help_article_contains_section_anchors(): void
    {
        $this->get(route('help.article', 'billing-wallets-payments'))
            ->assertOk()
            ->assertSee('id="funding"', false)
            ->assertSee('data-help-section', false)
            ->assertSee('Secure payment protection');
    }

    public function test_legal_terms_describe_creator_agent_marketplace(): void
    {
        $this->get(route('legal', ['doc' => 'terms']))
            ->assertOk()
            ->assertSee('Creators and Agents')
            ->assertSee('Campaigns, Packages & Tasks')
            ->assertDontSee('does not operate a peer-to-peer marketplace', false);
    }

    public function test_legal_privacy_covers_proof_and_kyc(): void
    {
        $this->get(route('legal', ['doc' => 'privacy']))
            ->assertOk()
            ->assertSee('Creators and Agents')
            ->assertSee('proof assets')
            ->assertSee('KYC');
    }

    public function test_search_index_includes_guides_and_sections(): void
    {
        $index = HelpContent::searchIndex();
        $this->assertNotEmpty($index);
        $types = collect($index)->pluck('type')->unique()->all();
        $this->assertContains('guide', $types);
        $this->assertContains('section', $types);
    }
}
