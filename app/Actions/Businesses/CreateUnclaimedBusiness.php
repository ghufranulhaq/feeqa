<?php

namespace App\Actions\Businesses;

use App\Domain\Businesses\BusinessStatus;
use App\Domain\Businesses\DomainNormalizer;
use App\Jobs\RunBusinessListingCheck;
use App\Models\Business;
use App\Models\Category;
use App\Support\Businesses\DuplicateBusinessException;
use InvalidArgumentException;

/**
 * FR-002-08: a signed-in consumer creates an unclaimed Business by domain,
 * or by name + country + city when it has no website. FR-002-09's
 * duplicate check runs first either way; FR-002-10's automated check is
 * queued straight after (App\Jobs\RunBusinessListingCheck).
 */
class CreateUnclaimedBusiness
{
    public function __construct(private readonly FindDuplicateBusiness $duplicates) {}

    /**
     * @param  array{domain?: ?string, name?: ?string, country?: ?string, city?: ?string, category_id?: ?int}  $data
     *
     * @throws DuplicateBusinessException
     */
    public function handle(array $data): Business
    {
        $categoryId = $data['category_id'] ?? Category::where('slug', Category::OTHER_UNCATEGORISED_SLUG)->value('id');

        $business = ($data['domain'] ?? null) !== null
            ? $this->createFromDomain($data['domain'], $categoryId)
            : $this->createFromNameAndLocation($data['name'], $data['country'], $data['city'], $categoryId);

        RunBusinessListingCheck::dispatch($business->id);

        return $business;
    }

    /**
     * @throws DuplicateBusinessException
     */
    private function createFromDomain(string $rawDomain, ?int $categoryId): Business
    {
        $domain = DomainNormalizer::normalize($rawDomain);

        if ($domain === null) {
            throw new InvalidArgumentException('That does not look like a valid domain.');
        }

        if ($existing = $this->duplicates->byDomain($domain)) {
            throw new DuplicateBusinessException($existing);
        }

        return Business::create([
            'name' => Business::guessNameFromDomain($domain),
            'primary_domain' => $domain,
            'primary_category_id' => $categoryId,
            'status' => BusinessStatus::Pending,
        ]);
    }

    /**
     * @throws DuplicateBusinessException
     */
    private function createFromNameAndLocation(string $name, string $country, string $city, ?int $categoryId): Business
    {
        if ($existing = $this->duplicates->byNameAndCity($name, $country, $city)) {
            throw new DuplicateBusinessException($existing);
        }

        return Business::create([
            'name' => $name,
            'country' => $country,
            'city' => $city,
            'primary_category_id' => $categoryId,
            'status' => BusinessStatus::Pending,
        ]);
    }
}
