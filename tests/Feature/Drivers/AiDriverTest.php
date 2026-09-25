<?php

use App\Drivers\Ai\Contracts\AiDriver;
use App\Drivers\Ai\FakeAiDriver;
use App\Drivers\Ai\OpenAiCompatibleAiDriver;

it('resolves the fake driver when AI_DRIVER=fake', function () {
    config(['platform.ai.driver' => 'fake']);
    app()->forgetInstance(AiDriver::class);

    expect(app(AiDriver::class))->toBeInstanceOf(FakeAiDriver::class);
});

it('resolves the openai-compatible driver for any other value', function () {
    config(['platform.ai.driver' => 'openai-compatible']);
    app()->forgetInstance(AiDriver::class);

    expect(app(AiDriver::class))->toBeInstanceOf(OpenAiCompatibleAiDriver::class);
});

it('is never available', function () {
    expect((new FakeAiDriver)->isAvailable())->toBeFalse();
});

it('makes callers check availability before completing', function () {
    (new FakeAiDriver)->complete('anything');
})->throws(RuntimeException::class);

it('reports the real driver unavailable without AI_BASE_URL/AI_API_KEY', function () {
    config(['platform.ai.base_url' => null, 'platform.ai.api_key' => null]);

    expect((new OpenAiCompatibleAiDriver)->isAvailable())->toBeFalse();
});

it('reports the real driver available once base URL and key are set', function () {
    config(['platform.ai.base_url' => 'https://api.deepseek.com', 'platform.ai.api_key' => 'test-key']);

    expect((new OpenAiCompatibleAiDriver)->isAvailable())->toBeTrue();
});
