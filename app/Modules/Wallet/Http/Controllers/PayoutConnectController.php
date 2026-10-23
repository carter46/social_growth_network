<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PayoutConnection;
use App\Modules\Wallet\Payments\Bachs\BachsConnectService;
use App\Modules\Wallet\Payments\Bachs\ConnectLinkException;
use App\Modules\Wallet\Payments\Exceptions\GatewayException;
use App\Modules\Wallet\Services\BankCatalogService;
use App\Modules\Wallet\Services\PayoutAccountService;
use App\Support\MemberShell;
use App\Support\PhoneNumber;
use App\Support\Payouts\AccountFormat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Country-aware payout Connect: on-site API for local rails, hosted partner flow when
 * Bachs requires international destination registration outside inline fields.
 */
class PayoutConnectController extends Controller
{
    public function __construct(
        private BachsConnectService $connect,
        private BankCatalogService $banks,
        private PayoutAccountService $accounts,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        if (! $this->connect->available()) {
            return redirect()->route('agent.wallet')
                ->with('error', __('Payout account setup is not available right now. Please try again later.'));
        }

        $user = $request->user();
        if (! $user->hasCompletePayoutProfile()) {
            return redirect()->route('agent.wallet')
                ->with('error', __('Update your phone, country, and address before connecting a bank account.'));
        }

        $connection = $this->connect->connection($user);
        if ($connection?->status === PayoutConnection::ACTIVE) {
            return redirect()->route('agent.banks.index')
                ->with('status', __('Your payout identity is already verified. Add a bank account below for withdrawals.'));
        }

        $country = strtoupper((string) $user->country);
        if ($this->connect->prefersHostedOnboarding($country)) {
            return view('dashboard.agent.payout-connect-hosted', [
                'layout' => MemberShell::layout(),
                'prefix' => MemberShell::prefix(),
                'country' => $country,
                'countryName' => (string) config('payout_methods.countries.'.$country.'.name', $country),
                'connection' => $connection,
            ]);
        }

        [$first, $last] = BachsConnectService::splitName((string) $user->name);
        $countries = $this->connect->offerableCountries();
        $nameChecks = [];
        foreach (array_keys($countries) as $code) {
            foreach (array_keys((array) ($countries[$code]['methods'] ?? [])) as $method) {
                $nameChecks[$code][$method] = $this->accounts->nameCheck((string) $code, (string) $method);
            }
        }

        return view('dashboard.agent.payout-connect', [
            'layout' => MemberShell::layout(),
            'prefix' => MemberShell::prefix(),
            'countries' => $countries,
            'nameChecks' => $nameChecks,
            'connection' => $connection,
            'prefill' => [
                'first_name' => old('first_name', $first),
                'last_name' => old('last_name', $last),
                'email' => old('email', (string) $user->email),
                'phone' => old('phone', (string) ($user->phone ?? '')),
                'country' => old('country', $country),
                'dob' => old('dob', $user->date_of_birth?->format('Y-m-d') ?? ''),
                'address_line1' => old('address_line1', (string) ($user->address_line1 ?? '')),
                'address_city' => old('address_city', (string) ($user->address_city ?? '')),
                'address_state' => old('address_state', (string) ($user->address_state ?? '')),
                'address_postal_code' => old('address_postal_code', (string) ($user->address_postal_code ?? '')),
                'id_type' => old('id_type', 'id_card'),
                'id_number' => old('id_number', ''),
                'method' => old('method', 'bank_account'),
                'bank_code' => old('bank_code', ''),
                'account_number' => old('account_number', ''),
                'account_name' => old('account_name', ''),
                'mobile_provider' => old('mobile_provider', ''),
                'phone_number' => old('phone_number', (string) ($user->phone ?? '')),
            ],
        ]);
    }

    /** Start hosted partner onboarding (international payout countries). */
    public function startHosted(Request $request): RedirectResponse
    {
        if (! $this->connect->available()) {
            return redirect()->route('agent.wallet')
                ->with('error', __('Payout account setup is not available right now. Please try again later.'));
        }

        $user = $request->user();
        if (! $user->hasCompletePayoutProfile()) {
            return redirect()->route('agent.wallet')
                ->with('error', __('Update your phone, country, and address before connecting a bank account.'));
        }

        if ($this->connect->connection($user)?->status === PayoutConnection::ACTIVE) {
            return redirect()->route('agent.banks.index')
                ->with('status', __('Your payout identity is already verified. Add a bank account below for withdrawals.'));
        }

        if (! $this->connect->prefersHostedOnboarding((string) $user->country)) {
            return redirect()->route('agent.payouts.connect');
        }

        try {
            $this->connect->ensureAccount($user, strtoupper((string) $user->country));

            return redirect()->away($this->connect->onboardingLink($user));
        } catch (ConnectLinkException $e) {
            return redirect()->route('agent.wallet')->with('error', $e->getMessage());
        } catch (GatewayException $e) {
            Log::warning('bachs.connect.hosted_failed', ['user_id' => $user->id, 'code' => $e->providerCode]);

            return redirect()->route('agent.wallet')
                ->with('error', __('We could not open payout setup. Please try again in a few minutes.'));
        }
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $this->connect->available()) {
            return redirect()->route('agent.wallet')
                ->with('error', __('Payout account setup is not available right now. Please try again later.'));
        }

        $user = $request->user();
        if (! $user->hasCompletePayoutProfile()) {
            return redirect()->route('agent.wallet')
                ->with('error', __('Update your phone, country, and address before connecting a bank account.'));
        }

        if ($this->connect->connection($user)?->status === PayoutConnection::ACTIVE) {
            return redirect()->route('agent.banks.index')
                ->with('status', __('Your payout identity is already verified. Add a bank account below for withdrawals.'));
        }

        if ($this->connect->prefersHostedOnboarding((string) $user->country)) {
            return redirect()->route('agent.payouts.connect');
        }

        $countries = array_keys($this->connect->offerableCountries());
        $validated = $request->validate([
            'country' => ['required', 'string', 'size:2', Rule::in($countries !== [] ? $countries : [strtoupper((string) $user->country)])],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:120'],
            'phone' => PhoneNumber::e164Rules(true),
            'dob' => ['required', 'date', 'before:-18 years', 'after:1900-01-01'],
            'address_line1' => ['required', 'string', 'max:160'],
            'address_city' => ['required', 'string', 'max:80'],
            'address_state' => ['required', 'string', 'max:80'],
            'address_postal_code' => ['nullable', 'string', 'max:20'],
            'id_type' => ['required', 'in:passport,id_card,drivers_license,residence_permit,nin,bvn'],
            'id_number' => ['required', 'string', 'max:40'],
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
                $user,
                $validated,
                $request->file('id_document'),
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (GatewayException $e) {
            Log::warning('bachs.connect.api_onboarding_failed', [
                'user_id' => $user->id,
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

    /** Resolve bank account holder name (same lookup used for payout banks; no bank-replace OTP). */
    public function resolve(Request $request): JsonResponse
    {
        if (! $this->connect->available()) {
            return response()->json(['message' => __('Payout setup is not available right now.')], 422);
        }

        $validated = $request->validate([
            'country' => ['required', 'string', 'size:2'],
            'method' => ['required', 'in:bank_account,mobile_money'],
            'bank_code' => ['required', 'string', 'max:20'],
            'account_number' => ['required', 'string', 'max:30'],
        ]);

        $country = strtoupper($validated['country']);
        $method = $validated['method'];
        if ($this->accounts->nameCheck($country, $method) !== PayoutAccountService::NAME_CHECK_AUTO) {
            return response()->json(['message' => __('Automatic name lookup is not available for this country.')], 422);
        }

        $accountNumber = AccountFormat::digits($validated['account_number']);
        $option = $this->banks->find($country, $method, $validated['bank_code']);
        if ($option === null || $accountNumber === '') {
            return response()->json(['message' => __('Choose a bank and enter a valid account number.')], 422);
        }

        try {
            $name = $this->accounts->lookupName($request->user(), $country, $method, [
                'fields' => [
                    'bank_code' => $option['code'],
                    'account_number' => $accountNumber,
                ],
                'bank_code' => $option['code'],
                'bank_name' => $option['name'],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first()], 422);
        }

        return response()->json([
            'account_name' => $name,
            'matches' => $name === null ? null : PayoutAccountService::namesMatch((string) $request->user()->name, $name),
        ]);
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

        $user = $request->user();
        if ($connection?->isActive() && $user->payoutAccounts()->where('active', true)->doesntExist()) {
            return redirect()->route('agent.banks.replace')
                ->with('status', __('Identity setup finished. Add your bank account to receive withdrawals.'));
        }

        $message = match (true) {
            $connection?->isActive() => __('Your payout account is ready.'),
            $connection?->needsSetup() ?? true => __('Setup is not finished yet. Continue when you are ready.'),
            default => __('Thanks. Your details are under review.'),
        };

        return redirect()->route('agent.wallet')->with($connection?->isActive() ? 'success' : 'info', $message);
    }

    public function refresh(Request $request): RedirectResponse
    {
        return redirect()->route('agent.payouts.connect');
    }
}
