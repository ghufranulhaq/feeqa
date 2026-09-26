<?php

use App\Drivers\Signing\SigningService;
use App\Models\Business;
use App\Models\Review;
use App\Models\ReviewVerification;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config(['platform.signing.keys_path' => storage_path('framework/testing/signing-'.uniqid().'.json')]);
    app()->forgetInstance(SigningService::class);
    app(SigningService::class)->generateKey();
});

it('lets the author request document verification for their published review', function () {
    $business = Business::factory()->create(['name' => 'Skyline Airways']);
    $review = Review::factory()->for($business)->create(['date_of_experience' => now()->toDateString()]);

    $response = $this->actingAs($review->reviewer)->post(route('reviews.verifications.store', $review), [
        'proofs' => [UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.7\ntrailer<< /Root 1 0 R >>\nstartxref\n0\n%%EOF")],
    ]);

    $response->assertRedirect();
    expect(ReviewVerification::where('review_id', $review->id)->exists())->toBeTrue();
});

it('forbids a non-author from requesting verification', function () {
    $review = Review::factory()->create();
    $notAuthor = User::factory()->create();

    $response = $this->actingAs($notAuthor)->post(route('reviews.verifications.store', $review), [
        'proofs' => [UploadedFile::fake()->create('receipt.pdf', 100)],
    ]);

    $response->assertForbidden();
    expect(ReviewVerification::where('review_id', $review->id)->exists())->toBeFalse();
});

it('requires authentication', function () {
    $review = Review::factory()->create();

    $response = $this->post(route('reviews.verifications.store', $review), [
        'proofs' => [UploadedFile::fake()->create('receipt.pdf', 100)],
    ]);

    $response->assertRedirect(route('login'));
});

it('validates that at least one proof file is present', function () {
    $review = Review::factory()->create();

    $response = $this->actingAs($review->reviewer)->post(route('reviews.verifications.store', $review), []);

    $response->assertSessionHasErrors('proofs');
});
