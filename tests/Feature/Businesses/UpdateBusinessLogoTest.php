<?php

use App\Actions\Businesses\UpdateBusinessLogo;
use App\Drivers\Malware\Contracts\MalwareScanner;
use App\Drivers\Malware\ScanResult;
use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

it('stores a valid logo (FR-002-03)', function () {
    $business = Business::factory()->create(['logo_path' => null]);

    app(UpdateBusinessLogo::class)->handle($business, File::image('logo.jpg', 200, 200));

    expect($business->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($business->logo_path);
});

it('replaces the previous logo file', function () {
    $business = Business::factory()->create(['logo_path' => null]);

    app(UpdateBusinessLogo::class)->handle($business, File::image('first.jpg', 200, 200));
    $first = $business->fresh()->logo_path;

    app(UpdateBusinessLogo::class)->handle($business->fresh(), File::image('second.jpg', 200, 200));
    $second = $business->fresh()->logo_path;

    expect($second)->not->toBe($first);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);
});

it('rejects a logo smaller than 200x200 (FR-002-03)', function () {
    $business = Business::factory()->create(['logo_path' => null]);

    app(UpdateBusinessLogo::class)->handle($business, File::image('small.jpg', 199, 199));
})->throws(ValidationException::class);

it('accepts a transparent PNG logo (edge cases table)', function () {
    $business = Business::factory()->create(['logo_path' => null]);
    $image = imagecreatetruecolor(200, 200);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);

    $file = File::createWithContent('transparent.png', $bytes)->mimeType('image/png');

    app(UpdateBusinessLogo::class)->handle($business, $file);

    expect($business->logo_path)->not->toBeNull();
});

it('rejects an animated WebP logo (edge cases table)', function () {
    $business = Business::factory()->create(['logo_path' => null]);
    $webp = File::image('logo.webp', 200, 200);
    $bytes = file_get_contents($webp->getRealPath()).'ANIM';
    $animated = File::createWithContent('logo.webp', $bytes)->mimeType('image/webp');

    app(UpdateBusinessLogo::class)->handle($business, $animated);
})->throws(ValidationException::class);

it('rejects a file that fails the malware scan', function () {
    $business = Business::factory()->create(['logo_path' => null]);
    app()->instance(MalwareScanner::class, new class implements MalwareScanner
    {
        public function scan(string $filePath): ScanResult
        {
            return new ScanResult(clean: false, reason: 'infected');
        }
    });

    app(UpdateBusinessLogo::class)->handle($business, File::image('logo.jpg', 200, 200));
})->throws(ValidationException::class);
