<?php

namespace Tests\Feature;

use App\Http\Middleware\NoIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_creator_legal_pages_never_mention_agents_or_task_work(): void
    {
        foreach (['terms', 'privacy'] as $doc) {
            $html = $this->get(route('legal', ['doc' => $doc]))->assertOk()->getContent();
            $main = (string) str($html)->after('<main')->before('</main>');

            foreach (['agent', 'reward', 'earn ', 'watch hours', 'organic', 'real people'] as $word) {
                $this->assertStringNotContainsStringIgnoringCase($word, $main, "Creator {$doc} page mentions \"{$word}\".");
            }
            $this->assertStringNotContainsString(route('agents.legal'), $html);
        }
    }

    public function test_public_help_center_does_not_mention_agents(): void
    {
        $this->get(route('help.article', 'for-agents'))->assertNotFound();

        foreach (\App\Support\HelpContent::all() as $article) {
            $text = json_encode($article);
            foreach (['agent', 'reward', 'withdraw'] as $word) {
                $this->assertStringNotContainsStringIgnoringCase($word, $text, "Help article {$article['slug']} mentions \"{$word}\".");
            }
        }
        $this->assertStringNotContainsStringIgnoringCase('agent', json_encode([config('help.categories'), config('help.faqs')]));
    }

    public function test_creator_legal_pages_link_to_youtube_and_google_policies(): void
    {
        $this->get(route('legal', ['doc' => 'privacy']))
            ->assertSee('https://www.youtube.com/t/terms', false)
            ->assertSee('https://policies.google.com/privacy', false)
            ->assertSee('https://developers.google.com/terms/api-services-user-data-policy', false)
            ->assertSee('https://myaccount.google.com/connections', false);
    }

    public function test_agent_legal_pages_exist_but_are_hidden_from_search(): void
    {
        foreach (['terms' => 'Agent Terms of Use', 'privacy' => 'Agent Privacy Policy'] as $doc => $label) {
            $this->get(route('agents.legal', ['doc' => $doc]))
                ->assertOk()
                ->assertHeader('X-Robots-Tag', NoIndex::VALUE)
                ->assertSee('<meta name="robots"', false)
                ->assertDontSee('rel="canonical"', false)
                ->assertSee($label)
                ->assertSee(route('agents'), false)
                ->assertDontSee(route('legal', ['doc' => 'terms']), false);
        }

        $this->get(route('sitemap'))->assertDontSee('/agents/legal', false);
        $this->get('/robots.txt')->assertDontSee('/agents/legal', false);
    }

    public function test_agent_page_footer_links_to_agent_legal_pages(): void
    {
        $this->get(route('agents'))
            ->assertOk()
            ->assertSee(route('agents.legal', ['doc' => 'terms']), false)
            ->assertSee(route('agents.legal', ['doc' => 'privacy']), false)
            ->assertSee('Agent Terms of Use')
            ->assertDontSee(route('legal', ['doc' => 'terms']), false);

        $this->get(route('home'))
            ->assertSee(route('legal', ['doc' => 'terms']), false)
            ->assertDontSee(route('agents.legal', ['doc' => 'terms']), false);
    }

    public function test_signup_forms_link_to_the_right_terms(): void
    {
        $this->get(route('agent-onboarding'))
            ->assertOk()
            ->assertSee(route('agents.legal', ['doc' => 'terms']), false)
            ->assertSee(route('agents.legal', ['doc' => 'privacy']), false);

        $this->get(route('register'))
            ->assertOk()
            ->assertSee(route('legal', ['doc' => 'terms']), false)
            ->assertDontSee(route('agents.legal', ['doc' => 'terms']), false);
    }
}
