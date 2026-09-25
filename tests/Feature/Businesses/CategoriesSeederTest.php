<?php

use App\Domain\Businesses\CategoryState;
use App\Models\Category;
use Database\Seeders\Base\CategoriesSeeder;

it('seeds Travel launched and every other industry draft (FR-002-29)', function () {
    $this->seed(CategoriesSeeder::class);

    $travel = Category::where('slug', 'travel')->sole();
    expect($travel->isIndustry())->toBeTrue()
        ->and($travel->launched)->toBeTrue()
        ->and($travel->state)->toBe(CategoryState::Launched);

    $otherIndustries = Category::whereNull('parent_id')
        ->where('is_system', false)
        ->where('slug', '!=', 'travel')
        ->get();

    expect($otherIndustries)->not->toBeEmpty();
    foreach ($otherIndustries as $industry) {
        expect($industry->launched)->toBeFalse()
            ->and($industry->state)->toBe(CategoryState::Draft);
    }
});

it('seeds the Travel sub-tree with the launched leaves from travel-content.md (FR-002-18)', function () {
    $this->seed(CategoriesSeeder::class);

    $travel = Category::where('slug', 'travel')->sole();

    foreach (['airlines', 'travel-agencies-otas', 'airports'] as $slug) {
        $category = Category::where('slug', $slug)->sole();
        expect($category->parent_id)->toBe($travel->id)
            ->and($category->launched)->toBeTrue();
    }

    foreach (['hotels', 'car-hire', 'tour-operators'] as $slug) {
        $category = Category::where('slug', $slug)->sole();
        expect($category->parent_id)->toBe($travel->id)
            ->and($category->launched)->toBeFalse();
    }

    $agencies = Category::where('slug', 'travel-agencies-otas')->sole();
    foreach (['online-travel-agencies', 'high-street-tour-agencies'] as $slug) {
        $category = Category::where('slug', $slug)->sole();
        expect($category->parent_id)->toBe($agencies->id)
            ->and(Category::depthUnder($category->parent))->toBe(3);
    }
});

it('seeds the "Other / Uncategorised" system category, unlaunched and top-level (FR-002-29)', function () {
    $this->seed(CategoriesSeeder::class);

    $other = Category::where('slug', 'other-uncategorised')->sole();

    expect($other->is_system)->toBeTrue()
        ->and($other->parent_id)->toBeNull()
        ->and($other->launched)->toBeFalse()
        ->and($other->state)->toBeNull();
});

it('is idempotent: running it twice does not duplicate categories', function () {
    $this->seed(CategoriesSeeder::class);
    $countAfterFirstRun = Category::count();

    $this->seed(CategoriesSeeder::class);

    expect(Category::count())->toBe($countAfterFirstRun);
});

it('resolves a localised name, falling back to en-GB (constitution §5.5)', function () {
    $category = Category::factory()->create(['name' => ['en-GB' => 'Airlines', 'fr-FR' => 'Compagnies aériennes']]);

    expect($category->localisedName('fr-FR'))->toBe('Compagnies aériennes')
        ->and($category->localisedName('de-DE'))->toBe('Airlines');
});

it('computes tree depth for the MAX_DEPTH guard (FR-002-18)', function () {
    $industry = Category::factory()->create(['parent_id' => null]);
    $child = Category::factory()->create(['parent_id' => $industry->id]);
    $grandchild = Category::factory()->create(['parent_id' => $child->id]);

    expect(Category::depthUnder(null))->toBe(1)
        ->and(Category::depthUnder($industry))->toBe(2)
        ->and(Category::depthUnder($child))->toBe(3)
        ->and(Category::depthUnder($grandchild))->toBe(4);
});
