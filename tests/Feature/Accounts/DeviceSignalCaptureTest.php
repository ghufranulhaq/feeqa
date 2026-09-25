<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('records the network address and user agent for a session (FR-001-18)', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Macintosh) TestSuite/1.0'])
        ->get('/dashboard')
        ->assertOk();

    $session = DB::table('sessions')->where('user_id', $user->id)->first();

    expect($session)->not->toBeNull();
    expect($session->ip_address)->not->toBeNull();
    expect($session->user_agent)->toContain('TestSuite/1.0');
});
