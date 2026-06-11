<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

function configureSqliteBackupRestoreTest(string $sqlitePath): void
{
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => $sqlitePath,
        'backup.backup.source.databases' => ['sqlite'],
        'backup.backup.destination.disks' => ['backups'],
        'backup.backup.temporary_directory' => storage_path('app/backup-temp'),
    ]);

    DB::purge('sqlite');
}

test('backup restore imports database from the latest spatie archive', function () {
    if (! sqlite3_binary_available()) {
        test()->markTestSkipped('sqlite3 binary is not available.');
    }

    $sqlitePath = tempnam(sys_get_temp_dir(), 'wallet-restore-');
    expect($sqlitePath)->not->toBeFalse();

    configureSqliteBackupRestoreTest((string) $sqlitePath);
    Storage::fake('backups');

    $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();

    $user = User::factory()->create([
        'email' => 'restore-test@example.com',
    ]);

    $this->artisan('backup:run --only-db')->assertSuccessful();

    User::query()->whereKey($user->id)->delete();
    expect(User::query()->where('email', 'restore-test@example.com')->exists())->toBeFalse();

    $this->artisan('backup:restore --latest --force')
        ->expectsOutputToContain('Database restored successfully')
        ->assertSuccessful();

    expect(User::query()->where('email', 'restore-test@example.com')->exists())->toBeTrue();

    @unlink($sqlitePath);
});

test('backup restore can be cancelled without force', function () {
    if (! sqlite3_binary_available()) {
        test()->markTestSkipped('sqlite3 binary is not available.');
    }

    $sqlitePath = tempnam(sys_get_temp_dir(), 'wallet-restore-');
    expect($sqlitePath)->not->toBeFalse();

    configureSqliteBackupRestoreTest((string) $sqlitePath);
    Storage::fake('backups');

    $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();

    $user = User::factory()->create([
        'email' => 'restore-cancel@example.com',
    ]);

    $this->artisan('backup:run --only-db')->assertSuccessful();

    User::query()->whereKey($user->id)->delete();
    expect(User::query()->where('email', 'restore-cancel@example.com')->exists())->toBeFalse();

    $backupPath = Storage::disk('backups')->allFiles()[0];
    $archivePath = Storage::disk('backups')->path($backupPath);

    $this->artisan('backup:restore --latest')
        ->expectsConfirmation(
            'This will overwrite the entire "sqlite" database ('.$sqlitePath.') with data from "'.$archivePath.'". Continue?',
            'no',
        )
        ->expectsOutputToContain('Cancelled.')
        ->assertSuccessful();

    expect(User::query()->where('email', 'restore-cancel@example.com')->exists())->toBeFalse();

    @unlink($sqlitePath);
});

function sqlite3_binary_available(): bool
{
    return is_executable('/usr/bin/sqlite3') || is_executable('/usr/local/bin/sqlite3');
}
