<?php

namespace Tests\Feature\Reporting;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\Wallet\Services\WalletService;
use App\Services\Reporting\ReportingRange;
use App\Services\Reporting\ReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverviewReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_and_revenue_section_agree_for_same_range(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        $platform = app(WalletService::class)->getPlatformWallet();
        Transaction::query()->create([
            'user_id' => $platform->user_id,
            'wallet_id' => $platform->id,
            'reference' => 'TXN-OV1',
            'type' => TransactionType::PlatformFee->value,
            'label' => 'Fee',
            'amount' => 2500,
            'currency' => 'USD',
            'status' => 'completed',
        ]);

        $range = ReportingRange::preset('24h');
        $overview = app(ReportingService::class)->overview($range);
        $section = app(ReportingService::class)->revenueSection($range);

        $this->assertEqualsWithDelta($section['total'], $overview['pulse']['revenue']['value'], 0.01);

        $this->actingAs($admin)
            ->get(route('admin', ['range' => '24h']))
            ->assertOk()
            ->assertSee('Business Pulse', false)
            ->assertSee('Revenue & Transactional Volume', false)
            ->assertSee('24 Hours', false);

        $this->actingAs($admin)
            ->get(route('admin.overview.panel', ['range' => '24h']))
            ->assertOk()
            ->assertSee('command-pulse', false);
    }
}
