<?php

use App\Actions\Staff\AssignQueueItem;
use App\Actions\Staff\Queues\ListFlagsQueue;
use App\Actions\Staff\Queues\ListHeldReviewsQueue;
use App\Actions\Staff\Queues\ListModerationIncidentsQueue;
use App\Domain\Moderation\FlagStatus;
use App\Domain\Moderation\IncidentStatus;
use App\Domain\Moderation\IncidentType;
use App\Domain\Staff\StaffRole;
use App\Models\Business;
use App\Models\Flag;
use App\Models\ModerationIncident;
use App\Models\Review;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function queueStaff(StaffRole $role = StaffRole::Moderator): User
{
    return User::factory()->create(['staff_role' => $role->value]);
}

it('lists only held reviews, oldest first (FR-006-11)', function () {
    $held = Review::factory()->held()->create();
    Review::factory()->create();

    $queue = (new ListHeldReviewsQueue)->handle(queueStaff());

    expect($queue->pluck('id')->all())->toBe([$held->id]);
});

it('rejects a non-staff user listing the held-review queue', function () {
    (new ListHeldReviewsQueue)->handle(User::factory()->create());
})->throws(AuthorizationException::class);

it('defaults the flags queue to unresolved flags, ordered by SLA (FR-006-11)', function () {
    $urgent = Flag::factory()->create(['status' => FlagStatus::Blurred, 'sla_due_at' => now()->addHour()]);
    Flag::factory()->create(['status' => FlagStatus::Open, 'sla_due_at' => now()->addDays(7)]);
    Flag::factory()->create(['status' => FlagStatus::Rejected]);

    $queue = (new ListFlagsQueue)->handle(queueStaff());

    expect($queue->first()->id)->toBe($urgent->id)
        ->and($queue)->toHaveCount(2);
});

it('filters the flags queue by an explicit status', function () {
    $rejected = Flag::factory()->create(['status' => FlagStatus::Rejected]);
    Flag::factory()->create(['status' => FlagStatus::Open]);

    $queue = (new ListFlagsQueue)->handle(queueStaff(), status: FlagStatus::Rejected);

    expect($queue->pluck('id')->all())->toBe([$rejected->id]);
});

it('defaults the incidents queue to open/investigating incidents (FR-006-11)', function () {
    $business = Business::factory()->create();
    $open = ModerationIncident::factory()->for($business)->create(['status' => IncidentStatus::Open, 'type' => IncidentType::ReviewSpike]);
    ModerationIncident::factory()->for($business)->create(['status' => IncidentStatus::Resolved, 'type' => IncidentType::ReviewSpike]);

    $queue = (new ListModerationIncidentsQueue)->handle(queueStaff());

    expect($queue->pluck('id')->all())->toBe([$open->id]);
});

it('assigns a review, a flag, and an incident to a staff member (FR-006-11)', function () {
    $assignee = queueStaff();
    $actor = queueStaff();
    $review = Review::factory()->held()->create();
    $flag = Flag::factory()->create();
    $incident = ModerationIncident::factory()->create();

    (new AssignQueueItem)->handle($actor, $review, $assignee);
    (new AssignQueueItem)->handle($actor, $flag, $assignee);
    (new AssignQueueItem)->handle($actor, $incident, $assignee);

    expect($review->fresh()->assigned_to)->toBe($assignee->id)
        ->and($flag->fresh()->assigned_to)->toBe($assignee->id)
        ->and($incident->fresh()->assigned_to)->toBe($assignee->id);
});

it('unassigns an item when given a null assignee', function () {
    $review = Review::factory()->held()->create(['assigned_to' => queueStaff()->id]);

    (new AssignQueueItem)->handle(queueStaff(), $review, null);

    expect($review->fresh()->assigned_to)->toBeNull();
});

it('rejects assigning to a non-staff user', function () {
    $review = Review::factory()->held()->create();

    (new AssignQueueItem)->handle(queueStaff(), $review, User::factory()->create());
})->throws(ValidationException::class);

it('rejects a non-staff actor assigning a queue item', function () {
    $review = Review::factory()->held()->create();

    (new AssignQueueItem)->handle(User::factory()->create(), $review, queueStaff());
})->throws(AuthorizationException::class);
