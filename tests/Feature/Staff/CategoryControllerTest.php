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
        ->post(route('staff.categories.store'), ['slug' => 'finance', 'name' => ['en-GB' => 'Finance'], 'icon' => 'bank'])
        ->assertRedirect();

    $industry = Category::where('slug', 'finance')->sole();
    Category::factory()->create(['parent_id' => $industry->id]);

    $this->actingAs($admin)
        ->post(route('staff.categories.launch', $industry), ['acknowledge_warnings' => true])
        ->assertRedirect();

    expect($industry->fresh()->state)->toBe(CategoryState::Launched);
});

it('rejects launching over HTTP with a blocking item missing (FR-002-31)', function () {
    $admin = categoryStaffHttp(StaffRole::Admin);
    $industry = Category::factory()->create(['parent_id' => null, 'icon' => null]);

    $this->actingAs($admin)
        ->post(route('staff.categories.launch', $industry), ['acknowledge_warnings' => true])
        ->assertSessionHasErrors('checklist');
});

it('lets any staff role preview an industry (FR-002-32)', function () {
    $support = categoryStaffHttp(StaffRole::Support);
    $industry = Category::factory()->create(['parent_id' => null]);
    Category::factory()->create(['parent_id' => $industry->id, 'name' => ['en-GB' => 'Sub']]);

    $response = $this->actingAs($support)->get(route('staff.categories.preview', $industry));

    $response->assertOk();
    expect($response->json('category_page.sub_categories.0.name'))->toBe('Sub')
        ->and($response->json('ranking'))->toBeNull();
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
