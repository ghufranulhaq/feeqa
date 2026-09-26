<?php

use App\Domain\Verification\TransactionRecordHash;
use App\Drivers\Signing\SigningService;
use App\Models\Business;
use App\Models\BusinessTransactionRecord;
use App\Models\Review;
use App\Models\ReviewVerification;
use App\Models\User;

beforeEach(function () {
    config(['platform.signing.keys_path' => storage_path('framework/testing/signing-'.uniqid().'.json')]);
    app()->forgetInstance(SigningService::class);
    app(SigningService::class)->generateKey();
});

it('lets the author submit a reference match over HTTP (FR-004-13)', function () {
    $reviewer = User::factory()->create(['email' => 'jane@example.com', 'email_verified_at' => now()]);
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create(['reviewer_id' => $reviewer->id]);
    BusinessTransactionRecord::factory()->for($business)->create([
        'reference_hash' => TransactionRecordHash::reference('ABC-1'),
        'email_hash' => TransactionRecordHash::email('jane@example.com'),
        'transaction_date' => now()->subDays(5)->toDateString(),
    ]);

    $response = $this->actingAs($reviewer)->post(route('reviews.reference-match.store', $review), [
        'reference' => 'ABC-1',
    ]);

    $response->assertRedirect();
    expect(ReviewVerification::where('review_id', $review->id)->exists())->toBeTrue();
});

it('forbids a non-author from submitting a reference match', function () {
    $review = Review::factory()->create();
    $notAuthor = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($notAuthor)->post(route('reviews.reference-match.store', $review), [
        'reference' => 'ABC-1',
    ]);

    $response->assertForbidden();
});

it('requires authentication', function () {
    $review = Review::factory()->create();

    $this->post(route('reviews.reference-match.store', $review), ['reference' => 'ABC-1'])
        ->assertRedirect(route('login'));
});

it('validates that a reference is present', function () {
    $reviewer = User::factory()->create(['email_verified_at' => now()]);
    $review = Review::factory()->create(['reviewer_id' => $reviewer->id]);

    $this->actingAs($reviewer)->post(route('reviews.reference-match.store', $review), [])
        ->assertSessionHasErrors('reference');
});
