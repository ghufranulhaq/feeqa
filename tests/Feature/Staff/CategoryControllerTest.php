<?php

use App\Domain\Businesses\CategoryState;
use App\Domain\Staff\StaffRole;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function categoryStaffHttp(StaffRole $role): User
{
    $user = User::factory()->create();
    $user->forceFill(['staff_role' => $role->value])->save();

    return $user;
}

it('lets staff Admin create and launch an industry over HTTP (FR-002-28)', function () {
    $admin = categoryStaffHttp(StaffRole::Admin);

    $this->actingAs($admin)
        ->post(route('staff.categories.store'), ['slug' => 'finance', 'name' => ['en-GB' => 'Finance']])
        ->assertRedirect();

    $industry = Category::where('slug', 'finance')->sole();

    $this->actingAs($admin)
        ->post(route('staff.categories.launch', $industry))
        ->assertRedirect();

    expect($industry->fresh()->state)->toBe(CategoryState::Launched);
});

it('rejects a non-Admin creating a category over HTTP', function () {
    $moderator = categoryStaffHttp(StaffRole::Moderator);

    $this->actingAs($moderator)
        ->post(route('staff.categories.store'), ['slug' => 'finance', 'name' => ['en-GB' => 'Finance']])
        ->assertForbidden();
});

it('lets a Senior Moderator rename a category over HTTP', function () {
    $moderator = categoryStaffHttp(StaffRole::SeniorModerator);
    $category = Category::factory()->create();

    $this->actingAs($moderator)
        ->patch(route('staff.categories.update', $category), ['name' => ['en-GB' => 'Renamed']])
        ->assertRedirect();

    expect($category->fresh()->localisedName())->toBe('Renamed');
});
