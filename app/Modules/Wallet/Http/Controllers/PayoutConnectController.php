<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Wallet\Payments\Bachs\BachsConnectService;
use App\Modules\Wallet\Payments\Exceptions\GatewayException;
use App\Modules\Wallet\Services\BankCatalogService;
use App\Support\MemberShell;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * On-site Bachs recipient onboarding for agents (API onboarding — no external redirect).
 */
class PayoutConnectController extends Controller
{
    public function __construct(
        private BachsConnectService $connect,
        private BankCatalogService $banks,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        if (! $this->connect->available()) {
            return redirect()->route('agent.wallet')
                ->with('error', __('Payout account setup is not available right now. Please try again later.'));
        }

        $user = $request->user();
        [$first, $last] = BachsConnectService::splitName((string) $user->name);
        $countries = $this->connect->offerableCountries();
        $connection = $this->connect->connection($user);

        return view('dashboard.agent.payout-connect', [
            'layout' => MemberShell::layout(),
            'prefix' => MemberShell::prefix(),
            'countries' => $countries,
            'connection' => $connection,
            'prefill' => [
                'first_name' => old('first_name', $first),
                'last_name' => old('last_name', $last),
                'email' => old('email', (string) $user->email),
                'phone' => old('phone', (string) ($user->phone ?? '')),
                'country' => old('country', strtoupper((string) ($user->country ?? ''))),
                'dob' => old('dob', ''),
                'address_line1' => old('address_line1', ''),
                'address_city' => old('address_city', ''),
                'address_state' => old('address_state', ''),
                'address_postal_code' => old('address_postal_code', ''),
                'nin' => old('nin', ''),
                'method' => old('method', 'bank_account'),
                'bank_code' => old('bank_code', ''),
                'account_number' => old('account_number', ''),
                'account_name' => old('account_name', trim($first.' '.$last)),
                'mobile_provider' => old('mobile_provider', ''),
                'phone_number' => old('phone_number', (string) ($user->phone ?? '')),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $this->connect->available()) {
            return redirect()->route('agent.wallet')
                ->with('error', __('Payout account setup is not available right now. Please try again later.'));
        }

        $countries = array_keys($this->connect->offerableCountries());
        $validated = $request->validate([
            'country' => ['required', 'string', 'size:2', Rule::in($countries !== [] ? $countries : ['NG'])],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'dob' => ['required', 'date', 'before:-18 years', 'after:1900-01-01'],
            'address_line1' => ['required', 'string', 'max:160'],
            'address_city' => ['required', 'string', 'max:80'],
            'address_state' => ['required', 'string', 'max:80'],
            'address_postal_code' => ['nullable', 'string', 'max:20'],
            'nin' => ['nullable', 'string', 'max:20'],
            'method' => ['required', 'in:bank_account,mobile_money'],
            'bank_code' => ['required_if:method,bank_account', 'nullable', 'string', 'max:20'],
            'account_number' => ['required_if:method,bank_account', 'nullable', 'string', 'max:30'],
            'account_name' => ['required_if:method,bank_account', 'nullable', 'string', 'max:120'],
            'mobile_provider' => ['required_if:method,mobile_money', 'nullable', 'string', 'max:40'],
            'phone_number' => ['required_if:method,mobile_money', 'nullable', 'string', 'max:20'],
            'id_document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        try {
            $connection = $this->connect->submitRecipientOnboarding(
                $request->user(),
                $validated,
                $request->file('id_document'),
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (GatewayException $e) {
            Log::warning('bachs.connect.api_onboarding_failed', [
                'user_id' => $request->user()->id,
                'code' => $e->providerCode,
            ]);

            return redirect()->route('agent.payouts.connect')
                ->withInput()
                ->with('error', __('We could not finish payout setup. Please try again in a few minutes.'));
        }

        $message = match (true) {
            $connection->isActive() => __('Your payout account is ready.'),
            $connection->needsSetup() => __('Details saved. Complete any remaining steps if prompted, or check back shortly.'),
            default => __('Details submitted. We will update your status once verification finishes.'),
        };

        return redirect()->route('agent.wallet')
            ->with($connection->isActive() ? 'success' : 'info', $message);
    }

    /** Bank / mobile network list for the onboarding form. */
    public function options(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['required', 'string', 'size:2'],
            'method' => ['required', 'in:bank_account,mobile_money'],
        ]);

        if (! $this->connect->available()) {
            return response()->json(['options' => []]);
        }

        return response()->json([
            'options' => $this->banks->options($validated['country'], $validated['method']),
        ]);
    }

    /** Legacy return URL from older hosted links — sync status only. */
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
            $connection?->isActive() => __('Your payout account is ready.'),
            $connection?->needsSetup() ?? true => __('Setup is not finished yet. Continue when you are ready.'),
            default => __('Thanks. Your details are under review.'),
        };

        return redirect()->route('agent.wallet')->with($connection?->isActive() ? 'success' : 'info', $message);
    }

    /** Legacy refresh URL — send the agent to the on-site form instead of an external link. */
    public function refresh(Request $request): RedirectResponse
    {
        return redirect()->route('agent.payouts.connect');
    }
}
