<?php

use App\Models\Business;
use App\Models\InvitationTemplate;
use App\Models\ReviewInvitation;
use App\Notifications\ReviewInvitationNotification;

it('routes replies to the business template\'s reply-to and sender name (FR-005-08)', function () {
    $business = Business::factory()->create();
    InvitationTemplate::factory()->for($business)->create([
        'sender_name' => 'Skyhop Travel Support',
        'reply_to' => 'support@skyhop-travel.example',
    ]);
    $invitation = ReviewInvitation::factory()->for($business)->create();

    $message = (new ReviewInvitationNotification($invitation))->toMail($invitation);

    expect($message->replyTo)->toBe([['support@skyhop-travel.example', 'Skyhop Travel Support']]);
});

it('sets no reply-to when the business has no active template', function () {
    $business = Business::factory()->create();
    $invitation = ReviewInvitation::factory()->for($business)->create();

    $message = (new ReviewInvitationNotification($invitation))->toMail($invitation);

    expect($message->replyTo)->toBe([]);
});
