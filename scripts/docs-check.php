<?php

/**
 * Enforces what constitution §8 rule 9 can be checked automatically:
 * README.md and docs/system-overview + docs/user-guides exist and have
 * real content, and every spec that has started implementation (has a
 * tasks.md) is mentioned somewhere in the docs. It can't check that the
 * docs are *accurate* — that's a human review question — only that they
 * exist and were touched.
 */
$root = dirname(__DIR__);
$errors = [];

function fail(array &$errors, string $message): void
{
    $errors[] = $message;
}

// README.md
$readmePath = $root.'/README.md';
if (! is_file($readmePath)) {
    fail($errors, 'README.md is missing.');
} elseif (filesize($readmePath) < 500) {
    fail($errors, 'README.md exists but looks like a stub (< 500 bytes).');
} else {
    $readme = file_get_contents($readmePath);
    foreach (['make ci', 'make test', '.env'] as $mustMention) {
        if (! str_contains($readme, $mustMention)) {
            fail($errors, "README.md doesn't mention \"{$mustMention}\".");
        }
    }
}

// docs/system-overview and docs/user-guides: at least one real file each.
foreach (['docs/system-overview', 'docs/user-guides'] as $dir) {
    $path = $root.'/'.$dir;

    if (! is_dir($path)) {
        fail($errors, "{$dir}/ is missing.");

        continue;
    }

    $files = glob($path.'/*.md');
    $nonEmpty = array_filter($files, fn (string $f) => filesize($f) > 200);

    if ($nonEmpty === []) {
        fail($errors, "{$dir}/ has no non-trivial .md file.");
    }
}

// Every spec that has started implementation (has a tasks.md) should be
// mentioned somewhere in the docs — by folder name or spec number.
$docsText = '';
foreach (array_merge(glob($root.'/docs/system-overview/*.md'), glob($root.'/docs/user-guides/*.md')) as $file) {
    $docsText .= file_get_contents($file)."\n";
}

foreach (glob($root.'/specs/*/tasks.md') as $tasksFile) {
    $specDir = basename(dirname($tasksFile));

    if (! preg_match('/^(\d{3})-/', $specDir, $matches)) {
        continue;
    }

    $specNumber = $matches[1];

    if (! str_contains($docsText, $specDir) && ! str_contains($docsText, $specNumber)) {
        fail($errors, "spec {$specDir} has started implementation (tasks.md exists) but isn't mentioned in docs/system-overview or docs/user-guides.");
    }
}

if ($errors !== []) {
    fwrite(STDERR, "docs-check failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "  - {$error}\n");
    }
    exit(1);
}

echo "docs-check passed.\n";
