<?php

use App\Actions\Businesses\CreateUnclaimedBusiness;
use App\Domain\Staff\StaffRole;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('lists businesses left in "Other / Uncategorised", oldest first (FR-002-35)', function () {
    Queue::fake();
    Category::factory()->create(['slug' => Category::OTHER_UNCATEGORISED_SLUG, 'is_system' => true]);
    $categorised = Category::factory()->create();

    $older = app(CreateUnclaimedBusiness::class)->handle([
        'domain' => 'older.example', 'name' => null, 'country' => null, 'city' => null, 'category_id' => null,
    ]);
    $older->forceFill(['created_at' => now()->subDay()])->save();
    app(CreateUnclaimedBusiness::class)->handle([
        'domain' => 'sorted-elsewhere.example', 'name' => null, 'country' => null, 'city' => null, 'category_id' => $categorised->id,
    ]);
    $newer = app(CreateUnclaimedBusiness::class)->handle([
        'domain' => 'newer.example', 'name' => null, 'country' => null, 'city' => null, 'category_id' => null,
    ]);

    $staff = User::factory()->create();
    $staff->forceFill(['staff_role' => StaffRole::Support->value])->save();

    $response = $this->actingAs($staff)->get(route('staff.businesses.uncategorised'))->assertOk();

    expect($response->json('businesses.*.slug'))->toBe([$older->slug, $newer->slug]);
});

it('rejects a non-staff user viewing the queue', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('staff.businesses.uncategorised'))->assertForbidden();
});
