<?php

namespace Database\Seeders\Base;

use App\Domain\Businesses\CategoryState;
use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * FR-002-29: the full top-level taxonomy ships at launch, but only Travel
 * is `launched` — every other industry exists `draft` so businesses can
 * already be listed under it (FR-002-30). The Travel sub-tree and its two
 * unlaunched leaves come from travel-content.md §1 (client-approved
 * launch content). Safe to run every environment and every deploy:
 * everything here is updateOrCreate by slug.
 */
class CategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $travel = $this->upsertIndustry('travel', 'Travel', CategoryState::Launched, launched: true);

        $this->upsertCategory('airlines', 'Airlines', $travel, launched: true);

        $agencies = $this->upsertCategory('travel-agencies-otas', 'Travel Agencies & OTAs', $travel, launched: true);
        $this->upsertCategory('online-travel-agencies', 'Online Travel Agencies', $agencies, launched: true);
        $this->upsertCategory('high-street-tour-agencies', 'High-street / Tour Agencies', $agencies, launched: true);

        $this->upsertCategory('airports', 'Airports', $travel, launched: true);

        $this->upsertCategory('hotels', 'Hotels', $travel, launched: false);
        $this->upsertCategory('car-hire', 'Car Hire', $travel, launched: false);
        $this->upsertCategory('tour-operators', 'Tour Operators', $travel, launched: false);

        foreach ([
            'finance-insurance' => 'Finance & Insurance',
            'retail-ecommerce' => 'Retail & E-commerce',
            'technology-software' => 'Technology & Software',
            'home-local-services' => 'Home & Local Services',
            'health-wellbeing' => 'Health & Wellbeing',
            'education' => 'Education',
            'automotive' => 'Automotive',
            'telecoms-utilities' => 'Telecoms & Utilities',
            'food-hospitality' => 'Food & Hospitality',
            'public-nonprofit' => 'Public & Non-profit',
        ] as $slug => $name) {
            $this->upsertIndustry($slug, $name, CategoryState::Draft, launched: false);
        }

        Category::updateOrCreate(
            ['slug' => 'other-uncategorised'],
            [
                'parent_id' => null,
                'name' => ['en-GB' => 'Other / Uncategorised'],
                'launched' => false,
                'state' => null,
                'is_system' => true,
            ],
        );
    }

    private function upsertIndustry(string $slug, string $name, CategoryState $state, bool $launched): Category
    {
        return Category::updateOrCreate(
            ['slug' => $slug],
            [
                'parent_id' => null,
                'name' => ['en-GB' => $name],
                'launched' => $launched,
                'state' => $state,
                'is_system' => false,
            ],
        );
    }

    private function upsertCategory(string $slug, string $name, Category $parent, bool $launched): Category
    {
        return Category::updateOrCreate(
            ['slug' => $slug],
            [
                'parent_id' => $parent->id,
                'name' => ['en-GB' => $name],
                'launched' => $launched,
                'state' => null,
                'is_system' => false,
            ],
        );
    }
}
