<?php

use App\Http\Controllers\Auth\AccountReactivationController;
use App\Http\Controllers\Auth\AgentOnboardingController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\OtpVerificationController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:5,1');

    Route::get('register/username-check', [RegisteredUserController::class, 'checkUsername'])
        ->middleware('throttle:60,1')
        ->name('register.username-check');

    Route::match(['get', 'post'], 'register/agent', [RegisteredUserController::class, 'redirectToAgentOnboarding'])
        ->middleware('noindex');

    Route::get('agent-onboarding', [AgentOnboardingController::class, 'create'])
        ->middleware('noindex')
        ->name('agent-onboarding');

    Route::post('agent-onboarding', [AgentOnboardingController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('agent-onboarding.store');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:5,1');

    Route::get('agent-login', [AuthenticatedSessionController::class, 'createAgent'])
        ->middleware('noindex')
        ->name('agent-login');

    Route::get('activate-account', [AccountReactivationController::class, 'create'])
        ->middleware('noindex')
        ->name('account.reactivate');
    Route::post('activate-account', [AccountReactivationController::class, 'send'])
        ->middleware('throttle:5,1')
        ->name('account.reactivate.send');
    Route::get('activate-account/verify', [AccountReactivationController::class, 'showVerify'])
        ->middleware('noindex')
        ->name('account.reactivate.verify');
    Route::post('activate-account/verify', [AccountReactivationController::class, 'verify'])
        ->middleware('throttle:10,1')
        ->name('account.reactivate.verify.store');

    Route::post('auth/google', [GoogleAuthController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('auth.google');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', [OtpVerificationController::class, 'show'])
        ->name('verification.notice');

    Route::post('verify-email', [OtpVerificationController::class, 'verify'])
        ->middleware('throttle:6,1')
        ->name('verification.verify');

    Route::post('verify-email/resend', [OtpVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('auth/google/link', [GoogleAuthController::class, 'link'])
        ->middleware('throttle:5,1')
        ->name('auth.google.link');

    Route::delete('auth/google', [GoogleAuthController::class, 'unlink'])
        ->middleware('throttle:5,1')
        ->name('auth.google.unlink');

    Route::match(['get', 'post'], 'logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
