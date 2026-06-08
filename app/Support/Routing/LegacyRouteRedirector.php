<?php

declare(strict_types=1);

namespace App\Support\Routing;

use Illuminate\Support\Facades\Route;

final class LegacyRouteRedirector
{
    public static function register(): void
    {
        $pairs = [
            ['transactions', 'transactions/create', 'transactions/{transaction}/edit', 'transactions/{transaction}'],
            ['accounts', 'accounts/create', 'accounts/{account}/edit', 'accounts/{account}/balance'],
            ['categories', 'categories/create', 'categories/{category}/edit', 'categories/reorder',
                'categories/{category}/estimates/annual', 'categories/{category}/estimates/monthly'],
            ['pockets', 'pockets/create', 'pockets/{pocket}/edit', 'pockets/reorder'],
            ['imports', 'imports/{import}', 'imports/upload', 'imports/{import}/commit',
                'import-failed-rows/dismiss-all', 'import-failed-rows/{importFailedRow}/dismiss'],
            ['transfers/create', 'transfers', 'transfers/candidates/{transaction}/confirm',
                'transfers/candidates/{transaction}/reject', 'transfers/{transferId}/unlink'],
            ['budget/monthly', 'budget/yearly'],
            ['settings', 'settings/profile', 'settings/password', 'settings/appearance'],
            ['dashboard'],
            ['login', 'register', 'forgot-password', 'reset-password/{token}', 'reset-password',
                'verify-email', 'verify-email/{id}/{hash}', 'email/verification-notification',
                'confirm-password'],
        ];

        $flatLegacy = collect($pairs)->flatten()->all();

        foreach ($flatLegacy as $legacyPath) {
            $target = self::resolveTarget($legacyPath);

            if ($target !== null && $target !== $legacyPath) {
                Route::redirect($legacyPath, $target, 301);
            }
        }
    }

    private static function resolveTarget(string $legacyPath): ?string
    {
        $map = [
            'transactions' => route_path('transactions'),
            'transactions/create' => route_path('transactions').'/utworz',
            'transactions/{transaction}/edit' => route_path('transactions').'/{transaction}/edytuj',
            'transactions/{transaction}' => route_path('transactions').'/{transaction}',
            'accounts' => route_path('accounts'),
            'accounts/create' => route_path('accounts').'/utworz',
            'accounts/{account}/edit' => route_path('accounts').'/{account}/edytuj',
            'accounts/{account}/balance' => route_path('accounts.balance'),
            'categories' => route_path('categories'),
            'categories/create' => route_path('categories').'/utworz',
            'categories/{category}/edit' => route_path('categories').'/{category}/edytuj',
            'categories/reorder' => route_path('categories.reorder'),
            'categories/{category}/estimates/annual' => route_path('categories.estimates.annual'),
            'categories/{category}/estimates/monthly' => route_path('categories.estimates.monthly'),
            'pockets' => route_path('pockets'),
            'pockets/create' => route_path('pockets').'/utworz',
            'pockets/{pocket}/edit' => route_path('pockets').'/{pocket}/edytuj',
            'pockets/reorder' => route_path('pockets.reorder'),
            'imports' => route_path('imports'),
            'imports/{import}' => route_path('imports').'/{import}',
            'imports/upload' => route_path('imports.upload'),
            'imports/{import}/commit' => route_path('imports.commit'),
            'import-failed-rows/dismiss-all' => route_path('imports.failed_rows.dismiss_all'),
            'import-failed-rows/{importFailedRow}/dismiss' => route_path('imports.failed_rows.dismiss'),
            'transfers/create' => route_path('transfers.create'),
            'transfers' => route_path('transfers'),
            'transfers/candidates/{transaction}/confirm' => route_path('transfers.candidates.confirm'),
            'transfers/candidates/{transaction}/reject' => route_path('transfers.candidates.reject'),
            'transfers/{transferId}/unlink' => route_path('transfers.unlink'),
            'budget/monthly' => route_path('budget.monthly'),
            'budget/yearly' => route_path('budget.yearly'),
            'settings' => route_path('settings'),
            'settings/profile' => route_path('settings.profile'),
            'settings/password' => route_path('settings.password'),
            'settings/appearance' => route_path('settings.appearance'),
            'dashboard' => route_path('dashboard'),
            'login' => route_path('auth.login'),
            'register' => route_path('auth.register'),
            'forgot-password' => route_path('auth.password.request'),
            'reset-password/{token}' => route_path('auth.password.reset'),
            'reset-password' => route_path('auth.password.store'),
            'verify-email' => route_path('auth.verification.notice'),
            'verify-email/{id}/{hash}' => route_path('auth.verification.verify'),
            'email/verification-notification' => route_path('auth.verification.send'),
            'confirm-password' => route_path('auth.password.confirm'),
        ];

        return $map[$legacyPath] ?? null;
    }
}
