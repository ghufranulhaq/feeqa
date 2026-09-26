<?php

use App\Models\Category;
use Database\Seeders\Base\CategoriesSeeder;
use Database\Seeders\Base\CategoryQuestionSetsSeeder;

it('gives a leaf category its own questions plus every ancestor\'s, root first (FR-002-19)', function () {
    $this->seed(CategoriesSeeder::class);
    $this->seed(CategoryQuestionSetsSeeder::class);

    $airlines = Category::where('slug', 'airlines')->sole();
    $keys = $airlines->effectiveQuestions()->pluck('key')->all();

    // Travel's 3 (root, first), then Airlines' own 7.
    expect($keys)->toBe([
        'booking_ease', 'value_for_money', 'use_again',
        'on_time', 'baggage', 'crew', 'seat_comfort', 'disruption_handling', 'cabin_class', 'flight_type',
    ]);
});

it('inherits through 3 levels: Online Travel Agencies gets Travel + Travel Agencies & OTAs', function () {
    $this->seed(CategoriesSeeder::class);
    $this->seed(CategoryQuestionSetsSeeder::class);

    $otas = Category::where('slug', 'online-travel-agencies')->sole();
    $keys = $otas->effectiveQuestions()->pluck('key')->all();

    expect($keys)->toBe([
        'booking_ease', 'value_for_money', 'use_again',
        'price_transparency', 'customer_service', 'changes_cancellations', 'refund_handling', 'documents_on_time',
    ]);
});

it('has no effective questions for a category with no question set anywhere in its chain', function () {
    $orphanParent = Category::factory()->create();
    $orphanChild = Category::factory()->create(['parent_id' => $orphanParent->id]);

    expect($orphanChild->effectiveQuestions())->toBeEmpty();
});

it('lets a leaf question override an ancestor\'s question sharing the same key, in the ancestor\'s position', function () {
    $parent = Category::factory()->create();
    $parentSet = $parent->questionSets()->create(['version' => 1, 'published_at' => now()]);
    $parentSet->questions()->create(['key' => 'shared', 'label' => ['en-GB' => 'Parent label'], 'type' => 'yes_no', 'order' => 0]);
    $parentSet->questions()->create(['key' => 'only_parent', 'label' => ['en-GB' => 'Only parent'], 'type' => 'yes_no', 'order' => 1]);

    $child = Category::factory()->create(['parent_id' => $parent->id]);
    $childSet = $child->questionSets()->create(['version' => 1, 'published_at' => now()]);
    $childSet->questions()->create(['key' => 'shared', 'label' => ['en-GB' => 'Child label'], 'type' => 'rating_1_5', 'order' => 0]);

    $effective = $child->effectiveQuestions();

    expect($effective->pluck('key')->all())->toBe(['shared', 'only_parent'])
        ->and($effective->firstWhere('key', 'shared')->localisedLabel())->toBe('Child label');
});
