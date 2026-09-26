<?php

use App\Actions\Invitations\CreateInvitation;
use App\Domain\Businesses\BusinessRole;
use App\Domain\Invitations\InvitationMethod;
use App\Domain\Invitations\InvitationStatus;
use App\Domain\Verification\TransactionRecordHash;
use App\Models\Business;
use App\Models\Category;
use App\Models\InvitationSuppression;
use App\Models\ReviewInvitation;
use App\Models\User;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

it('queues a manual invitation with a signed single-use token (FR-005-01, FR-005-10)', function () {
    $business = Business::factory()->create();

    $invitation = (new CreateInvitation)->handle($business, InvitationMethod::Manual, [
        'recipient_email' => 'consumer@example.com',
    ]);

    expect($invitation->status)->toBe(InvitationStatus::Queued)
        ->and($invitation->token)->not->toBeEmpty()
        ->and(now()->diffInDays($invitation->expires_at))->toBeLessThanOrEqual(60)
        ->and(now()->diffInDays($invitation->expires_at))->toBeGreaterThan(59);
});

it('defaults the send delay to the spec\'s plain 7 days with no matching category (FR-005-08)', function () {
    $business = Business::factory()->create();

    $invitation = (new CreateInvitation)->handle($business, InvitationMethod::Manual, [
        'recipient_email' => 'consumer@example.com',
    ]);

    expect((int) $invitation->created_at->diffInDays($invitation->scheduled_at))->toBe(7);
});

it('defaults an airline invitation to 1 day after the trigger with no travel date (travel-content.md §4)', function () {
    $airlines = Category::factory()->create(['slug' => 'airlines']);
    $business = Business::factory()->create(['primary_category_id' => $airlines->id]);

    $invitation = (new CreateInvitation)->handle($business, InvitationMethod::Manual, [
        'recipient_email' => 'consumer@example.com',
    ]);

    expect((int) $invitation->created_at->diffInDays($invitation->scheduled_at))->toBe(1);
});

it('defaults an airline invitation to 1 day after the travel date when one is known', function () {
    $airlines = Category::factory()->create(['slug' => 'airlines']);
    $business = Business::factory()->create(['primary_category_id' => $airlines->id]);
    $travelDate = now()->addDays(10);

    $invitation = (new CreateInvitation)->handle($business, InvitationMethod::Manual, [
        'recipient_email' => 'consumer@example.com',
        'travel_date' => $travelDate,
    ]);

    expect($invitation->scheduled_at->toDateString())->toBe($travelDate->copy()->addDay()->toDateString());
});

it('defaults a travel agency invitation to 3 days after booking with no travel date', function () {
    $agencies = Category::factory()->create(['slug' => 'travel-agencies-otas']);
    $business = Business::factory()->create(['primary_category_id' => $agencies->id]);

    $invitation = (new CreateInvitation)->handle($business, InvitationMethod::Manual, [
        'recipient_email' => 'consumer@example.com',
    ]);

    expect((int) $invitation->created_at->diffInDays($invitation->scheduled_at))->toBe(3);
});

it('defaults a travel agency invitation to 1 day after the travel date when one is known', function () {
    $agencies = Category::factory()->create(['slug' => 'travel-agencies-otas']);
    $business = Business::factory()->create(['primary_category_id' => $agencies->id]);
    $travelDate = now()->addDays(5);

    $invitation = (new CreateInvitation)->handle($business, InvitationMethod::Manual, [
        'recipient_email' => 'consumer@example.com',
        'travel_date' => $travelDate,
    ]);

    expect($invitation->scheduled_at->toDateString())->toBe($travelDate->copy()->addDay()->toDateString());
});

it('respects a leaf category under Travel Agencies & OTAs by walking the parent chain', function () {
    $agencies = Category::factory()->create(['slug' => 'travel-agencies-otas']);
    $onlineAgencies = Category::factory()->create(['slug' => 'online-travel-agencies', 'parent_id' => $agencies->id]);
    $business = Business::factory()->create(['primary_category_id' => $onlineAgencies->id]);

    $invitation = (new CreateInvitation)->handle($business, InvitationMethod::Manual, [
        'recipient_email' => 'consumer@example.com',
    ]);

    expect((int) $invitation->created_at->diffInDays($invitation->scheduled_at))->toBe(3);
});

it('respects a per-business send delay override (FR-005-08)', function () {
    $business = Business::factory()->create();

    $invitation = (new CreateInvitation)->handle($business, InvitationMethod::Manual, [
        'recipient_email' => 'consumer@example.com',
        'send_delay_days' => 14,
    ]);

    expect((int) $invitation->created_at->diffInDays($invitation->scheduled_at))->toBe(14);
});

it('collapses a second invitation to the same recipient within 30 days into the same row (FR-005-09)', function () {
    $business = Business::factory()->create();

    $first = (new CreateInvitation)->handle($business, InvitationMethod::Manual, [
        'recipient_email' => 'consumer@example.com',
        'reference' => 'ORDER-1',
    ]);
    $second = (new CreateInvitation)->handle($business, InvitationMethod::Manual, [
        'recipient_email' => 'consumer@example.com',
        'reference' => 'ORDER-2',
    ]);

    expect($second->id)->toBe($first->id)
        ->and($second->reference)->toBe('ORDER-2')
        ->and(ReviewInvitation::where('business_id', $business->id)->count())->toBe(1);
});

it('does not collapse invitations to the same recipient across different businesses', function () {
    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();

    (new CreateInvitation)->handle($businessA, InvitationMethod::Manual, ['recipient_email' => 'consumer@example.com']);
    (new CreateInvitation)->handle($businessB, InvitationMethod::Manual, ['recipient_email' => 'consumer@example.com']);

    expect(ReviewInvitation::count())->toBe(2);
});

it('is idempotent for a repeated reference, regardless of recipient (FR-005-09, edge case table)', function () {
    $business = Business::factory()->create();

    $first = (new CreateInvitation)->handle($business, InvitationMethod::Api, [
        'recipient_email' => 'first@example.com',
        'reference' => 'SK-448812',
    ]);
    $second = (new CreateInvitation)->handle($business, InvitationMethod::Api, [
        'recipient_email' => 'second@example.com',
        'reference' => 'SK-448812',
    ]);

    expect($second->id)->toBe($first->id)
        ->and(ReviewInvitation::count())->toBe(1);
});

it('suppresses (not rejects) an invitation to a business-level suppression (FR-005-15)', function () {
    $business = Business::factory()->create();
    InvitationSuppression::factory()->for($business)->create([
        'recipient_email_hash' => TransactionRecordHash::email('consumer@example.com'),
    ]);

    $invitation = (new CreateInvitation)->handle($business, InvitationMethod::Manual, [
        'recipient_email' => 'consumer@example.com',
    ]);

    expect($invitation->status)->toBe(InvitationStatus::Suppressed);
});

it('suppresses an invitation to a globally suppressed recipient regardless of business (FR-005-15)', function () {
    $business = Business::factory()->create();
    InvitationSuppression::factory()->global()->create([
        'recipient_email_hash' => TransactionRecordHash::email('consumer@example.com'),
    ]);

    $invitation = (new CreateInvitation)->handle($business, InvitationMethod::Manual, [
        'recipient_email' => 'consumer@example.com',
    ]);

    expect($invitation->status)->toBe(InvitationStatus::Suppressed);
});

it('holds a new invitation as queued/plan_limit once the plan\'s monthly limit is reached (FR-005-20)', function () {
    config(['platform.invitations.plan_limits.monthly_invitations.free' => 1]);
    $business = Business::factory()->create();

    $first = (new CreateInvitation)->handle($business, InvitationMethod::Manual, [
        'recipient_email' => 'consumer-1@example.com',
    ]);
    $second = (new CreateInvitation)->handle($business, InvitationMethod::Manual, [
        'recipient_email' => 'consumer-2@example.com',
    ]);

    expect($first->status)->toBe(InvitationStatus::Queued)
        ->and($first->queued_reason)->toBeNull()
        ->and($second->status)->toBe(InvitationStatus::Queued)
        ->and($second->queued_reason)->toBe('plan_limit');
});

it('never blocks a suppressed recipient behind the plan limit (FR-005-20)', function () {
    config(['platform.invitations.plan_limits.monthly_invitations.free' => 0]);
    $business = Business::factory()->create();
    InvitationSuppression::factory()->for($business)->create([
        'recipient_email_hash' => TransactionRecordHash::email('consumer@example.com'),
    ]);

    $invitation = (new CreateInvitation)->handle($business, InvitationMethod::Manual, [
        'recipient_email' => 'consumer@example.com',
    ]);

    expect($invitation->status)->toBe(InvitationStatus::Suppressed)
        ->and($invitation->queued_reason)->toBeNull();
});

it('never enforces a plan limit configured as unlimited (FR-005-20)', function () {
    config(['platform.invitations.plan_limits.monthly_invitations.free' => null]);
    $business = Business::factory()->create();

    (new CreateInvitation)->handle($business, InvitationMethod::Manual, ['recipient_email' => 'consumer-1@example.com']);
    $second = (new CreateInvitation)->handle($business, InvitationMethod::Manual, ['recipient_email' => 'consumer-2@example.com']);

    expect($second->queued_reason)->toBeNull();
});

it('suppresses an invitation whose recipient is a member of the business being invited (edge case table)', function () {
    $this->seed(BusinessRolesSeeder::class);
    $business = Business::factory()->create();
    $member = User::factory()->create(['email' => 'staffer@example.com']);
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $member->assignRole(BusinessRole::Responder->value);

    $invitation = (new CreateInvitation)->handle($business, InvitationMethod::Manual, [
        'recipient_email' => 'staffer@example.com',
    ]);

    expect($invitation->status)->toBe(InvitationStatus::Suppressed);
});
