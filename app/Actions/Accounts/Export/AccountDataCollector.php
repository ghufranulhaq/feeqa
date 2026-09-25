<?php

namespace App\Actions\Accounts\Export;

use App\Contracts\ExportsUserData;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * FR-001-19: the account-level slice of the export — profile, consents,
 * sessions, business memberships, linked social providers, and the
 * avatar file. Other specs' personal data (reviews, cases, votes, flags…)
 * gets its own collector when that spec is built.
 */
class AccountDataCollector implements ExportsUserData
{
    public function exportSectionName(): string
    {
        return 'account';
    }

    public function collectExportData(User $user): array
    {
        return [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'country' => $user->country,
                'locale' => $user->locale,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at' => $user->created_at->toIso8601String(),
            ],
            'consents' => $user->consents()->orderBy('consented_at')->get()->map(fn ($consent) => [
                'terms_version' => $consent->terms_version,
                'privacy_version' => $consent->privacy_version,
                'marketing_opt_in' => $consent->marketing_opt_in,
                'consented_at' => $consent->consented_at->toIso8601String(),
            ])->all(),
            'linked_providers' => $user->providers()->get()->map(fn ($provider) => [
                'provider' => $provider->provider,
                'linked_at' => $provider->created_at->toIso8601String(),
            ])->all(),
            'sessions' => DB::table('sessions')
                ->where('user_id', $user->id)
                ->get(['ip_address', 'user_agent', 'last_activity'])
                ->map(fn ($session) => [
                    'ip_address' => $session->ip_address,
                    'user_agent' => $session->user_agent,
                    'last_active_at' => Carbon::createFromTimestamp($session->last_activity)->toIso8601String(),
                ])->all(),
            'business_memberships' => DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->join('businesses', 'businesses.id', '=', 'model_has_roles.business_id')
                ->where('model_has_roles.model_id', $user->id)
                ->where('model_has_roles.model_type', $user->getMorphClass())
                ->get(['businesses.name as business_name', 'roles.name as role'])
                ->map(fn ($row) => ['business' => $row->business_name, 'role' => $row->role])
                ->all(),
        ];
    }

    public function collectExportMedia(User $user): array
    {
        if (! $user->avatar_path || ! Storage::disk('public')->exists($user->avatar_path)) {
            return [];
        }

        return [
            basename($user->avatar_path) => Storage::disk('public')->path($user->avatar_path),
        ];
    }
}
