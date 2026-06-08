<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;

test('backup run stores archive on configured backups disk', function () {
    Storage::fake('backups');

    $this->artisan('backup:run --only-db')
        ->assertSuccessful();

    expect(Storage::disk('backups')->allFiles())->not->toBeEmpty();
});
