<?php

use App\Actions\Businesses\UpdateBusinessProfile;
use App\Domain\Businesses\BusinessRole;
use App\Models\Business;
use App\Models\BusinessProfileChangeRequest;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BusinessRolesSeeder::class);
});

function memberOf(Business $business, BusinessRole $role): User
{
    $user = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $user->assignRole($role->value);

    return $user;
}

it('publishes non-sensitive fields immediately (FR-002-03)', function () {
    $business = Business::factory()->claimed()->create();
    $owner = memberOf($business, BusinessRole::Owner);

    $result = (new UpdateBusinessProfile)->handle($business, $owner, [
        'description' => 'A great airline.',
        'website' => 'https://skyhop-travel.com',
        'email' => 'hello@skyhop-travel.com',
        'phone' => '+44 20 7946 0958',
    ]);

    expect($result->changeRequest)->toBeNull()
        ->and($result->business->description)->toBe('A great airline.')
        ->and($result->business->website)->toBe('https://skyhop-travel.com')
        ->and($result->business->email)->toBe('hello@skyhop-travel.com');
});

it('syncs up to 5 secondary categories (FR-002-03)', function () {
    $business = Business::factory()->claimed()->create();
    $owner = memberOf($business, BusinessRole::Owner);
    $categories = Category::factory()->count(2)->create();

    $result = (new UpdateBusinessProfile)->handle($business, $owner, [
        'secondary_category_ids' => $categories->pluck('id')->all(),
    ]);

    expect($result->business->secondaryCategories->pluck('id')->sort()->values()->all())
        ->toBe($categories->pluck('id')->sort()->values()->all());
});

it('queues a name/domain/category change on a claimed business instead of publishing it (FR-002-07)', function () {
    $business = Business::factory()->claimed()->create(['name' => 'Old Name', 'primary_domain' => 'old.example']);
    $owner = memberOf($business, BusinessRole::Owner);
    $newCategory = Category::factory()->create();

    $result = (new UpdateBusinessProfile)->handle($business, $owner, [
        'name' => 'New Name',
        'domain' => 'https://www.new.example',
        'category_id' => $newCategory->id,
    ]);

    expect($result->changeRequest)->not->toBeNull()
        ->and($result->changeRequest->changes)->toBe([
            'name' => ['old' => 'Old Name', 'new' => 'New Name'],
            'primary_domain' => ['old' => 'old.example', 'new' => 'new.example'],
            'primary_category_id' => ['old' => null, 'new' => $newCategory->id],
        ])
        ->and($result->business->name)->toBe('Old Name')
        ->and($result->business->primary_domain)->toBe('old.example');

    expect(BusinessProfileChangeRequest::where('business_id', $business->id)->where('status', 'pending')->exists())->toBeTrue();
});

it('applies a sensitive-field change immediately on an unclaimed business (FR-002-07)', function () {
    $business = Business::factory()->create(['name' => 'Old Name']);
    $owner = memberOf($business, BusinessRole::Owner);

    $result = (new UpdateBusinessProfile)->handle($business, $owner, ['name' => 'New Name']);

    expect($result->changeRequest)->toBeNull()
        ->and($result->business->name)->toBe('New Name');
});

it('does not queue a request when the sensitive field is unchanged', function () {
    $business = Business::factory()->claimed()->create(['name' => 'Same Name']);
    $owner = memberOf($business, BusinessRole::Owner);

    $result = (new UpdateBusinessProfile)->handle($business, $owner, ['name' => 'Same Name']);

    expect($result->changeRequest)->toBeNull();
    expect(BusinessProfileChangeRequest::count())->toBe(0);
});

it('rejects an Analyst editing the profile (edge cases table)', function () {
    $business = Business::factory()->claimed()->create();
    $analyst = memberOf($business, BusinessRole::Analyst);

    (new UpdateBusinessProfile)->handle($business, $analyst, ['description' => 'Hi']);
})->throws(AuthorizationException::class);
