<?php

namespace Tests\Feature;

use App\Models\EmailIdentity;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Services\Communications\Email\EmailProfile;
use App\Services\Communications\Email\EmailService;
use App\Services\Communications\Email\SendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class NewsletterSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_shows_newsletter_form(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('id="newsletter"', false)
            ->assertSee(route('newsletter.subscribe'), false)
            ->assertSee('Have questions?', false);
    }

    public function test_signup_is_saved_and_sent_to_the_general_inbox(): void
    {
        EmailIdentity::updateOrCreate(
            ['profile' => EmailIdentity::GENERAL],
            ['from_name' => 'Team', 'from_email' => 'hello@example.com', 'notify_to_email' => 'team@example.com', 'enabled' => true],
        );

        $mailer = Mockery::mock(EmailService::class);
        $mailer->shouldReceive('sendRaw')
            ->once()
            ->withArgs(fn ($to, $subject, $body, $profile) => $to === 'team@example.com'
                && str_contains($subject, 'fan@example.com')
                && str_contains($body, 'fan@example.com')
                && $profile === EmailProfile::General)
            ->andReturn(SendResult::fail('test', 'not sent'));
        $this->app->instance(EmailService::class, $mailer);

        $this->from(route('home'))
            ->post(route('newsletter.subscribe'), ['email' => ' Fan@Example.com ', 'source' => 'home'])
            ->assertRedirect(route('home').'#newsletter')
            ->assertSessionHas('newsletter_status');

        $subscriber = NewsletterSubscriber::sole();
        $this->assertSame('fan@example.com', $subscriber->email);
        $this->assertSame('home', $subscriber->source);
        $this->assertTrue($subscriber->isActive());
    }

    public function test_repeat_signup_does_not_duplicate_or_resend(): void
    {
        NewsletterSubscriber::create(['email' => 'fan@example.com', 'subscribed_at' => now()]);

        $mailer = Mockery::mock(EmailService::class);
        $mailer->shouldNotReceive('sendRaw');
        $this->app->instance(EmailService::class, $mailer);

        $this->from(route('home'))
            ->post(route('newsletter.subscribe'), ['email' => 'fan@example.com'])
            ->assertSessionHas('newsletter_status');

        $this->assertSame(1, NewsletterSubscriber::count());
    }

    public function test_invalid_email_and_honeypot_are_rejected(): void
    {
        $this->from(route('home'))
            ->post(route('newsletter.subscribe'), ['email' => 'not-an-email'])
            ->assertSessionHasErrorsIn('newsletter', ['email']);

        $this->from(route('home'))
            ->post(route('newsletter.subscribe'), ['email' => 'bot@example.com', 'website' => 'http://spam.test'])
            ->assertSessionHas('newsletter_status');

        $this->assertSame(0, NewsletterSubscriber::count());
    }

    public function test_admin_can_list_and_export_subscribers(): void
    {
        NewsletterSubscriber::create(['email' => 'fan@example.com', 'source' => 'home', 'subscribed_at' => now()]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.newsletter'))
            ->assertOk()
            ->assertSee('fan@example.com');

        $csv = $this->actingAs($admin)->get(route('admin.newsletter.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('fan@example.com', $csv);
    }

    public function test_non_admin_cannot_view_subscribers(): void
    {
        $creator = User::factory()->creator()->create();

        $this->actingAs($creator)->get(route('admin.newsletter'))->assertForbidden();
    }
}
