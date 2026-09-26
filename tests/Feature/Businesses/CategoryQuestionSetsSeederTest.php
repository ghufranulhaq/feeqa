<?php

use App\Domain\Businesses\QuestionType;
use App\Models\Category;
use Database\Seeders\Base\CategoriesSeeder;
use Database\Seeders\Base\CategoryQuestionSetsSeeder;

beforeEach(function () {
    $this->seed(CategoriesSeeder::class);
    $this->seed(CategoryQuestionSetsSeeder::class);
});

it('seeds the Travel parent question set (travel-content.md §2)', function () {
    $travel = Category::where('slug', 'travel')->sole();
    $set = $travel->currentQuestionSet();

    expect($set->version)->toBe(1)
        ->and($set->questions)->toHaveCount(3);

    $useAgain = $set->questions()->where('key', 'use_again')->sole();
    expect($useAgain->type)->toBe(QuestionType::YesNo)
        ->and($useAgain->required)->toBeTrue();
});

it('seeds Airlines with 7 questions, including a single_choice with options', function () {
    $airlines = Category::where('slug', 'airlines')->sole();
    $set = $airlines->currentQuestionSet();

    expect($set->questions)->toHaveCount(7);

    $cabinClass = $set->questions()->where('key', 'cabin_class')->sole();
    expect($cabinClass->type)->toBe(QuestionType::SingleChoice)
        ->and($cabinClass->options['en-GB'])->toBe(['Economy', 'Premium Economy', 'Business', 'First']);

    $onTime = $set->questions()->where('key', 'on_time')->sole();
    expect($onTime->required)->toBeTrue();
});

it('seeds Travel Agencies & OTAs and Airports with their own sets', function () {
    $agencies = Category::where('slug', 'travel-agencies-otas')->sole();
    $airports = Category::where('slug', 'airports')->sole();

    expect($agencies->currentQuestionSet()->questions)->toHaveCount(5)
        ->and($airports->currentQuestionSet()->questions)->toHaveCount(5);
});

it('does not seed a question set for a category with none in travel-content.md', function () {
    $otas = Category::where('slug', 'online-travel-agencies')->sole();

    expect($otas->currentQuestionSet())->toBeNull();
});

it('is idempotent: re-running it does not publish a second version', function () {
    $this->seed(CategoryQuestionSetsSeeder::class);

    $travel = Category::where('slug', 'travel')->sole();
    expect($travel->questionSets()->count())->toBe(1);
});
