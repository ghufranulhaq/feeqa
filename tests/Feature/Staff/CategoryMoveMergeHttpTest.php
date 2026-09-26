<?php

use App\Domain\Staff\StaffRole;
use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function httpCategoryAdmin(): User
{
    $user = User::factory()->create();
    $user->forceFill(['staff_role' => StaffRole::Admin->value])->save();

    return $user;
}

it('merges two categories over HTTP', function () {
    $admin = httpCategoryAdmin();
    $source = Category::factory()->create();
    $target = Category::factory()->create();

    $this->actingAs($admin)
        ->post(route('staff.categories.merge', $source), ['target' => $target->id])
        ->assertRedirect();

    expect(Category::find($source->id))->toBeNull();
});

it('moves businesses between categories over HTTP', function () {
    $admin = httpCategoryAdmin();
    $from = Category::factory()->create();
    $to = Category::factory()->create();
    $business = Business::factory()->create(['primary_category_id' => $from->id]);

    $this->actingAs($admin)
        ->post(route('staff.categories.move-businesses', $from), ['to' => $to->id])
        ->assertRedirect();

    expect($business->fresh()->primary_category_id)->toBe($to->id);
});

it('deletes an empty category over HTTP', function () {
    $admin = httpCategoryAdmin();
    $category = Category::factory()->create();

    $this->actingAs($admin)
        ->delete(route('staff.categories.destroy', $category))
        ->assertRedirect();

    expect(Category::find($category->id))->toBeNull();
});

it('merges duplicate businesses over HTTP', function () {
    $admin = httpCategoryAdmin();
    $source = Business::factory()->create();
    $target = Business::factory()->create();

    $this->actingAs($admin)
        ->post(route('staff.businesses.merge', $source), ['target' => $target->id])
        ->assertRedirect();

    expect(Business::find($source->id))->toBeNull();
});
