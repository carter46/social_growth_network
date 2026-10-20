<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PayoutAccount;
use App\Modules\Wallet\Payments\Bachs\BachsConnectService;
use App\Modules\Wallet\Services\BankCatalogService;
use App\Modules\Wallet\Services\PayoutAccountService;
use App\Modules\Wallet\Services\PayoutCoverageService;
use App\Support\MemberShell;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Payout accounts (up to three, one primary). Adding, removing and changing the primary
 * need the password + email code step first.
 */
class BankAccountController extends Controller
{
    public function __construct(
        private PayoutAccountService $accounts,
        private PayoutCoverageService $coverage,
        private BankCatalogService $banks,
        private BachsConnectService $connect,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $prefix = MemberShell::prefix();
        $connectAvailable = $prefix === 'agent' && $this->connect->available() && Route::has('agent.payouts.connect');

        return view('dashboard.user.banks.index', [
            'accounts' => $user->payoutAccounts()->get(),
            'maxAccounts' => $this->accounts->maxAccounts(),
            'hasOpenWithdrawal' => $this->accounts->hasOpenWithdrawal($user),
            'verified' => $this->accounts->isVerified($user),
            'hasRoutes' => $this->coverage->offeredCountries() !== [],
            'connectAvailable' => $connectAvailable,
            'payoutConnection' => $connectAvailable ? $this->connect->connection($user) : null,
            'layout' => MemberShell::layout(),
            'prefix' => $prefix,
        ]);
    }

    /** Verification steps, then the add form. */
    public function replaceForm(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $prefix = MemberShell::prefix();
        $verified = $this->accounts->isVerified($user);
        $intent = (string) $request->query('intent', session('payout_intent', 'add'));

        if ($verified && $intent !== 'add') {
            return redirect()->route($prefix.'.banks.index');
        }

        $countries = $this->coverage->offeredCountries();
        $schemas = [];
        foreach ($countries as $code => $country) {
            foreach (array_keys($country['methods']) as $method) {
                $schemas[$code][$method] = [
                    'fields' => $this->accounts->fieldsFor($code, $method),
                    'name_check' => $this->accounts->nameCheck($code, $method),
                ];
            }
        }

        return view('dashboard.user.banks.replace', [
            'step' => $verified ? 'add' : session('bank_replace_step', 'password'),
            'intent' => $intent,
            'countries' => $countries,
            'schemas' => $schemas,
            'atLimit' => $user->payoutAccounts()->count() >= $this->accounts->maxAccounts(),
            'profileName' => (string) $user->name,
            'bounceFee' => PayoutAccountService::bounceFee(),
            'layout' => MemberShell::layout(),
            'prefix' => $prefix,
        ]);
    }

    public function sendOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'intent' => ['nullable', 'in:add,manage'],
        ]);

        $this->accounts->startVerification($request->user(), $validated['password']);

        return redirect()
            ->route(MemberShell::prefix().'.banks.replace')
            ->with('bank_replace_step', 'otp')
            ->with('payout_intent', $validated['intent'] ?? 'add')
            ->with('status', __('A verification code was sent to your email.'));
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'otp' => ['required', 'string', 'size:6'],
            'intent' => ['nullable', 'in:add,manage'],
        ]);

        $this->accounts->verifyCode($request->user(), $validated['otp']);
        $prefix = MemberShell::prefix();

        if (($validated['intent'] ?? 'add') === 'manage') {
            return redirect()
                ->route($prefix.'.banks.index')
                ->with('status', __('Email verified. You can now change your primary account or remove an account.'));
        }

        return redirect()
            ->route($prefix.'.banks.replace')
            ->with('status', __('Email verified. Add your payout account.'));
    }

    /** Bank or mobile network list for a country and method (JSON). */
    public function options(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['required', 'string', 'size:2'],
            'method' => ['required', 'string', 'max:30'],
        ]);

        if (! $this->coverage->isRouteOffered($validated['country'], $validated['method'])) {
            return response()->json(['options' => []]);
        }

        return response()->json(['options' => $this->banks->options($validated['country'], $validated['method'])]);
    }

    /** Name lookup preview for countries that support it (JSON). */
    public function resolve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['required', 'string', 'size:2'],
            'method' => ['required', 'string', 'max:30'],
            'fields' => ['required', 'array'],
            'fields.*' => ['nullable', 'string', 'max:160'],
        ]);

        $user = $request->user();
        if (! $this->accounts->isVerified($user)) {
            return response()->json(['message' => __('Verify the email code before continuing.')], 422);
        }

        try {
            $checked = $this->accounts->validateFields($validated['country'], $validated['method'], $validated['fields']);
            $name = $this->accounts->lookupName($user, $validated['country'], $validated['method'], $checked);
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first()], 422);
        }

        return response()->json([
            'account_name' => $name,
            'matches' => $name === null ? null : PayoutAccountService::namesMatch((string) $user->name, $name),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'country' => ['required', 'string', 'size:2'],
            'method' => ['required', 'string', 'max:30'],
            'fields' => ['required', 'array'],
            'fields.*' => ['nullable', 'string', 'max:160'],
            'confirm_name' => ['nullable', 'boolean'],
            'accept_terms' => ['nullable', 'boolean'],
        ]);

        $this->accounts->add(
            $request->user(),
            $validated['country'],
            $validated['method'],
            $validated['fields'],
            (bool) ($validated['confirm_name'] ?? false),
            (bool) ($validated['accept_terms'] ?? false),
            $request->ip(),
            $request->userAgent(),
        );

        return redirect()
            ->route(MemberShell::prefix().'.banks.index')
            ->with('status', __('Payout account saved.'));
    }

    public function makePrimary(Request $request, PayoutAccount $account): RedirectResponse
    {
        $this->accounts->setPrimary($request->user(), $account, $request->ip());

        return redirect()->route(MemberShell::prefix().'.banks.index')->with('status', __('Primary payout account updated.'));
    }

    public function destroy(Request $request, PayoutAccount $account): RedirectResponse
    {
        $this->accounts->remove($request->user(), $account, $request->ip());

        return redirect()->route(MemberShell::prefix().'.banks.index')->with('status', __('Payout account removed.'));
    }
}
