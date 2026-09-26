<?php

namespace App\Actions\Staff;

use App\Actions\Businesses\FindDuplicateBusiness;
use App\Domain\Businesses\BusinessStatus;
use App\Domain\Businesses\DomainNormalizer;
use App\Domain\Staff\StaffRole;
use App\Jobs\RunBusinessListingCheck;
use App\Models\Business;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

/**
 * FR-002-24, FR-002-34: staff Admin bulk-imports unclaimed Businesses
 * from a public or licensed source only — never scraped from another
 * review platform (constitution L1). Every row gets the same FR-002-10
 * automated check as a consumer-created business (App\Actions\Businesses\
 * CreateUnclaimedBusiness), and duplicate detection (T4) skips a row
 * that already exists rather than creating a second listing for it.
 */
class ImportBusinesses
{
    public function __construct(private readonly FindDuplicateBusiness $duplicates) {}

    /**
     * @param  list<array{name: string, domain?: ?string, country: string, primary_category_id: int, logo_path?: ?string}>  $rows
     *
     * @throws AuthorizationException
     */
    public function handle(User $staff, array $rows, string $dataSource, ?string $importBatch = null): ImportBusinessesResult
    {
        if ($staff->staffRole() !== StaffRole::Admin) {
            throw new AuthorizationException('Only a staff Admin can seed businesses.');
        }

        $importBatch ??= now()->format('Ymd').'-'.Str::random(6);
        $created = collect();
        $skipped = [];

        foreach ($rows as $row) {
            $domain = isset($row['domain']) ? DomainNormalizer::normalize($row['domain']) : null;

            if ($domain !== null && $this->duplicates->byDomain($domain)) {
                $skipped[] = ['name' => $row['name'], 'reason' => 'duplicate_domain'];

                continue;
            }

            $business = Business::create([
                'name' => $row['name'],
                'primary_domain' => $domain,
                'country' => $row['country'],
                'primary_category_id' => $row['primary_category_id'],
                'logo_path' => $row['logo_path'] ?? null,
                'status' => BusinessStatus::Pending,
                'data_source' => $dataSource,
                'import_batch' => $importBatch,
            ]);

            RunBusinessListingCheck::dispatch($business->id);

            $created->push($business);
        }

        return new ImportBusinessesResult($created, $skipped);
    }
}
