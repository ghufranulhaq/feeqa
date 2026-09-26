<?php

namespace App\Drivers\BusinessClaiming;

use App\Domain\Businesses\ClaimMethod;
use App\Drivers\BusinessClaiming\Contracts\DomainClaimChecker;
use App\Models\BusinessClaim;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * BUSINESS_CLAIM_DOMAIN_CHECKER=dns_and_http (production). Best-effort,
 * same spirit as App\Drivers\BusinessListing\DnsBusinessListingChecker:
 * a human moderator is always the fallback if a legitimate claimant's
 * setup doesn't match exactly (they can use method (d) instead).
 */
class DnsAndHttpDomainClaimChecker implements DomainClaimChecker
{
    public function check(BusinessClaim $claim): bool
    {
        $domain = $claim->business->primary_domain;
        $token = $claim->verification_token;

        if ($domain === null || $token === null) {
            return false;
        }

        return match ($claim->method) {
            ClaimMethod::DnsTxt => $this->checkDnsTxt($domain, $token),
            ClaimMethod::HtmlFile => $this->checkHtmlFile($domain, $token),
            default => false,
        };
    }

    private function checkDnsTxt(string $domain, string $token): bool
    {
        $records = dns_get_record($domain, DNS_TXT) ?: [];

        foreach ($records as $record) {
            if (str_contains($record['txt'] ?? '', "feeqa-verify={$token}")) {
                return true;
            }
        }

        return false;
    }

    private function checkHtmlFile(string $domain, string $token): bool
    {
        try {
            $response = Http::timeout(5)->get("https://{$domain}/feeqa-verify-{$token}.txt");

            if ($response->successful() && str_contains($response->body(), $token)) {
                return true;
            }

            $response = Http::timeout(5)->get("https://{$domain}/");

            return $response->successful()
                && str_contains($response->body(), "feeqa-verification\" content=\"{$token}\"");
        } catch (Throwable) {
            return false;
        }
    }
}
