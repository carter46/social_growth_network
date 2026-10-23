<?php

namespace Tests\Feature\Admin;

use App\Models\IntegrationProvider;
use App\Models\User;
use App\Services\Communications\Email\EmailSendReport;
use App\Services\Communications\Email\EmailService;
use App\Services\Communications\Email\SendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class EmailTestMailDiagnosticTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('admin');

        return $user;
    }

    public function test_brevo_success_is_reported_as_ok(): void
    {
        $report = new EmailSendReport(
            SendResult::ok(IntegrationProvider::BREVO, 'msg-1', 12, 201),
            [[
                'step' => 1,
                'label' => 'Brevo',
                'provider' => IntegrationProvider::BREVO,
                'success' => true,
                'error' => null,
                'message_id' => 'msg-1',
                'http_status' => 201,
                'latency_ms' => 12,
            ]],
            EmailSendReport::OUTCOME_BREVO,
            'Site',
            'noreply@example.com',
        );

        $emails = Mockery::mock(EmailService::class);
        $emails->shouldReceive('sendRawWithReport')->once()->andReturn($report);
        $this->app->instance(EmailService::class, $emails);

        $this->actingAs($this->admin())
            ->postJson(route('admin.settings.test-mail'), [
                'test_email' => 'admin@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('outcome', 'brevo')
            ->assertJsonPath('attempts.0.success', true);
    }

    public function test_smtp_fallback_is_not_reported_as_clean_success(): void
    {
        $report = new EmailSendReport(
            SendResult::ok(IntegrationProvider::LARAVEL_MAIL, null, 40),
            [
                [
                    'step' => 1,
                    'label' => 'Brevo',
                    'provider' => IntegrationProvider::BREVO,
                    'status' => 'failed',
                    'success' => false,
                    'skipped' => false,
                    'error' => 'Invalid sender',
                    'message_id' => null,
                    'http_status' => 400,
                    'latency_ms' => 20,
                ],
                [
                    'step' => 2,
                    'label' => 'Brevo (retry)',
                    'provider' => IntegrationProvider::BREVO,
                    'status' => 'failed',
                    'success' => false,
                    'skipped' => false,
                    'error' => 'Invalid sender',
                    'message_id' => null,
                    'http_status' => 400,
                    'latency_ms' => 18,
                ],
                [
                    'step' => 3,
                    'label' => 'SMTP / Laravel Mail (fallback)',
                    'provider' => IntegrationProvider::LARAVEL_MAIL,
                    'status' => 'ok',
                    'success' => true,
                    'skipped' => false,
                    'error' => null,
                    'message_id' => null,
                    'http_status' => null,
                    'latency_ms' => 40,
                ],
            ],
            EmailSendReport::OUTCOME_FALLBACK,
            'Site',
            'noreply@example.com',
        );

        $emails = Mockery::mock(EmailService::class);
        $emails->shouldReceive('sendRawWithReport')->once()->andReturn($report);
        $this->app->instance(EmailService::class, $emails);

        $this->actingAs($this->admin())
            ->postJson(route('admin.settings.test-mail'), [
                'test_email' => 'admin@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('outcome', 'fallback')
            ->assertJsonPath('used_fallback', true)
            ->assertJsonPath('delivered', true)
            ->assertJsonFragment(['error' => 'Invalid sender']);
    }

    public function test_smtp_with_brevo_disabled_is_clean_success(): void
    {
        $report = new EmailSendReport(
            SendResult::ok(IntegrationProvider::LARAVEL_MAIL, null, 25),
            [
                [
                    'step' => 1,
                    'label' => 'Brevo',
                    'provider' => IntegrationProvider::BREVO,
                    'status' => 'skipped',
                    'success' => null,
                    'skipped' => true,
                    'error' => 'Skipped — Brevo is disabled or has no API key.',
                    'message_id' => null,
                    'http_status' => null,
                    'latency_ms' => null,
                ],
                [
                    'step' => 2,
                    'label' => 'SMTP / Laravel Mail',
                    'provider' => IntegrationProvider::LARAVEL_MAIL,
                    'status' => 'ok',
                    'success' => true,
                    'skipped' => false,
                    'error' => null,
                    'message_id' => null,
                    'http_status' => null,
                    'latency_ms' => 25,
                ],
            ],
            EmailSendReport::OUTCOME_SMTP,
            'Site',
            'noreply@example.com',
        );

        $emails = Mockery::mock(EmailService::class);
        $emails->shouldReceive('sendRawWithReport')->once()->andReturn($report);
        $this->app->instance(EmailService::class, $emails);

        $this->actingAs($this->admin())
            ->postJson(route('admin.settings.test-mail'), [
                'test_email' => 'admin@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('outcome', 'smtp')
            ->assertJsonPath('used_fallback', false);
    }

    public function test_total_failure_returns_422_with_attempt_trail(): void
    {
        $report = new EmailSendReport(
            SendResult::fail(IntegrationProvider::LARAVEL_MAIL, 'SMTP connection refused'),
            [
                [
                    'step' => 1,
                    'label' => 'Brevo',
                    'provider' => IntegrationProvider::BREVO,
                    'success' => false,
                    'error' => 'API key invalid',
                    'message_id' => null,
                    'http_status' => 401,
                    'latency_ms' => 10,
                ],
                [
                    'step' => 3,
                    'label' => 'SMTP / Laravel Mail (fallback)',
                    'provider' => IntegrationProvider::LARAVEL_MAIL,
                    'success' => false,
                    'error' => 'SMTP connection refused',
                    'message_id' => null,
                    'http_status' => null,
                    'latency_ms' => 5,
                ],
            ],
            EmailSendReport::OUTCOME_FAILED,
            'Site',
            'noreply@example.com',
        );

        $emails = Mockery::mock(EmailService::class);
        $emails->shouldReceive('sendRawWithReport')->once()->andReturn($report);
        $this->app->instance(EmailService::class, $emails);

        $this->actingAs($this->admin())
            ->postJson(route('admin.settings.test-mail'), [
                'test_email' => 'admin@example.com',
            ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('outcome', 'failed')
            ->assertJsonPath('delivered', false);
    }
}
