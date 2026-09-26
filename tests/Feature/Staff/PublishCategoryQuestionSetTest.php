<?php

use App\Actions\Staff\PublishCategoryQuestionSet;
use App\Domain\Staff\StaffRole;
use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

function staffMember(StaffRole $role): User
{
    $user = User::factory()->create();
    $user->forceFill(['staff_role' => $role->value])->save();

    return $user;
}

it('lets a Senior Moderator publish a question set (FR-002-21, FR-002-33)', function () {
    $category = Category::factory()->create();
    $staff = staffMember(StaffRole::SeniorModerator);

    $set = (new PublishCategoryQuestionSet)->handle($category, $staff, [
        ['key' => 'q1', 'label' => ['en-GB' => 'Q1'], 'type' => 'yes_no', 'required' => true],
    ]);

    expect($set->version)->toBe(1)
        ->and($set->questions)->toHaveCount(1);
});

it('rejects a plain Moderator publishing a question set', function () {
    $category = Category::factory()->create();
    $staff = staffMember(StaffRole::Moderator);

    (new PublishCategoryQuestionSet)->handle($category, $staff, [
        ['key' => 'q1', 'label' => ['en-GB' => 'Q1'], 'type' => 'yes_no'],
    ]);
})->throws(AuthorizationException::class);

it('rejects more than 8 questions (FR-002-19)', function () {
    $category = Category::factory()->create();
    $staff = staffMember(StaffRole::Admin);
    $questions = array_map(fn ($i) => ['key' => "q{$i}", 'label' => ['en-GB' => "Q{$i}"], 'type' => 'yes_no'], range(1, 9));

    (new PublishCategoryQuestionSet)->handle($category, $staff, $questions);
})->throws(ValidationException::class);

it('publishes a new version without touching the previous one (FR-002-20)', function () {
    $category = Category::factory()->create();
    $staff = staffMember(StaffRole::Admin);

    $v1 = (new PublishCategoryQuestionSet)->handle($category, $staff, [
        ['key' => 'q1', 'label' => ['en-GB' => 'Q1'], 'type' => 'yes_no'],
    ]);
    $v2 = (new PublishCategoryQuestionSet)->handle($category, $staff, [
        ['key' => 'q1', 'label' => ['en-GB' => 'Q1 updated'], 'type' => 'yes_no'],
        ['key' => 'q2', 'label' => ['en-GB' => 'Q2'], 'type' => 'yes_no'],
    ]);

    expect($v2->version)->toBe(2)
        ->and($category->currentQuestionSet()->id)->toBe($v2->id)
        ->and($v1->fresh()->questions()->where('key', 'q1')->sole()->label['en-GB'])->toBe('Q1');
});
