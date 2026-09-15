<?php

use Illuminate\Support\Facades\File;

it('deletes old temp files and truncates oversized logs but keeps recent files', function () {
    $tmp = storage_path('app/tmp');
    $logs = storage_path('logs');
    File::ensureDirectoryExists($tmp);
    File::ensureDirectoryExists($logs);

    $old = $tmp.'/old-sheet.xlsx';
    $fresh = $tmp.'/fresh-sheet.xlsx';
    File::put($old, 'x');
    File::put($fresh, 'x');
    touch($old, now()->subDays(3)->getTimestamp());

    $rotated = $logs.'/laravel-2020-01-01.log';
    File::put($rotated, 'x');
    touch($rotated, now()->subDays(60)->getTimestamp());

    $big = $logs.'/laravel.log';
    $backup = is_file($big) ? File::get($big) : null;
    File::put($big, str_repeat('a', 2 * 1024 * 1024));

    try {
        $this->artisan('certificates:cleanup', ['--log-max-mb' => 1])->assertSuccessful();

        expect(is_file($old))->toBeFalse()
            ->and(is_file($fresh))->toBeTrue()
            ->and(is_file($rotated))->toBeFalse()
            ->and(filesize($big))->toBe(0);
    } finally {
        @unlink($fresh);
        $backup === null ? @unlink($big) : File::put($big, $backup);
    }
});

it('reports without deleting in dry-run mode', function () {
    $tmp = storage_path('app/tmp');
    File::ensureDirectoryExists($tmp);
    $old = $tmp.'/old-dry.xlsx';
    File::put($old, 'x');
    touch($old, now()->subDays(3)->getTimestamp());

    try {
        $this->artisan('certificates:cleanup', ['--dry-run' => true])->assertSuccessful();
        expect(is_file($old))->toBeTrue();
    } finally {
        @unlink($old);
    }
});
