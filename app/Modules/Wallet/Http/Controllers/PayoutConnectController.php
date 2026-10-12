<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Wallet\Payments\Bachs\BachsConnectService;
use App\Modules\Wallet\Payments\Bachs\ConnectLinkException;
use App\Modules\Wallet\Payments\Exceptions\GatewayException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Hosted Bachs onboarding for agents. The return and refresh redirects are never trusted:
 * readiness comes from re-reading the account (and from Connect webhooks).
 */
class PayoutConnectController extends Controller
{
    public function __construct(
        private BachsConnectService $connect,
    ) {}

    public function start(Request $request): RedirectResponse
    {
        return $this->redirectToBachs($request);
    }

    public function return(Request $request): RedirectResponse
    {
        $connection = $this->connect->connection($request->user());
        if ($connection && filled($connection->account_id) && $this->connect->available()) {
            try {
                $connection = $this->connect->sync($connection);
            } catch (GatewayException $e) {
                Log::warning('bachs.connect.return_sync_failed', ['user_id' => $request->user()->id, 'code' => $e->providerCode]);
            }
        }

        $message = match (true) {
            $connection?->isActive() => __('Your payout account is connected.'),
            $connection?->needsSetup() ?? true => __('Setup is not finished yet. Continue setup when you are ready.'),
            default => __('Thanks. Bachs is reviewing your details. We will update your status when they finish.'),
        };

        return redirect()->route('agent.wallet')->with($connection?->isActive() ? 'success' : 'info', $message);
    }

    /** The previous link expired or was already used, so issue a new one. */
    public function refresh(Request $request): RedirectResponse
    {
        return $this->redirectToBachs($request);
    }

    private function redirectToBachs(Request $request): RedirectResponse
    {
        if (! $this->connect->available()) {
            return redirect()->route('agent.wallet')->with('error', __('Payout account setup is not available right now. Please try again later.'));
        }

        try {
            return redirect()->away($this->connect->onboardingLink($request->user()));
        } catch (ConnectLinkException $e) {
            return redirect()->route('agent.wallet')->with('error', $e->getMessage());
        } catch (GatewayException $e) {
            Log::warning('bachs.connect.link_failed', ['user_id' => $request->user()->id, 'code' => $e->providerCode]);

            return redirect()->route('agent.wallet')->with('error', __('We could not open payout account setup. Please try again in a few minutes.'));
        }
    }
}
