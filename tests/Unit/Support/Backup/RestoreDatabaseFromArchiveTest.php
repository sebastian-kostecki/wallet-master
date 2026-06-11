<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Support\Backup\RestoreDatabaseFromArchive;
use Tests\TestCase;

uses(TestCase::class);

function createSpatieBackupZip(string $dumpEntryPath, string $dumpContents): string
{
    $zipPath = sys_get_temp_dir().'/backup-test-'.uniqid('', true).'.zip';
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE);
    $zip->addFromString($dumpEntryPath, $dumpContents);
    $zip->close();

    return $zipPath;
}

it('extracts a mysql database dump from a spatie backup archive', function () {
    config([
        'database.connections.mysql_restore_test' => [
            'driver' => 'mysql',
            'database' => 'wallet',
        ],
        'backup.backup.temporary_directory' => sys_get_temp_dir().'/wallet-backup-restore-'.uniqid('', true),
    ]);

    $zipPath = createSpatieBackupZip('db-dumps/mysql-wallet.sql', 'SELECT 1;');
    $restorer = new RestoreDatabaseFromArchive;

    $extractedPath = $restorer->extractDatabaseDump($zipPath, 'mysql_restore_test');

    expect($extractedPath)->toBeFile()
        ->and(file_get_contents($extractedPath))->toBe('SELECT 1;');

    @unlink($extractedPath);
    @unlink($zipPath);
});

it('throws when no database dump exists in the archive', function () {
    config([
        'database.connections.mysql_restore_test' => [
            'driver' => 'mysql',
            'database' => 'wallet',
        ],
        'backup.backup.temporary_directory' => sys_get_temp_dir().'/wallet-backup-restore-'.uniqid('', true),
    ]);

    $zipPath = createSpatieBackupZip('readme.txt', 'no dump here');
    $restorer = new RestoreDatabaseFromArchive;

    expect(fn () => $restorer->extractDatabaseDump($zipPath, 'mysql_restore_test'))
        ->toThrow(DomainException::class, 'No database dump for driver "mysql" found in backup archive.');

    @unlink($zipPath);
});

it('decompresses gzipped database dumps', function () {
    $restorer = new RestoreDatabaseFromArchive;
    $gzPath = sys_get_temp_dir().'/wallet-dump-'.uniqid('', true).'.sql.gz';
    $sql = "CREATE TABLE example (id INTEGER);\n";

    file_put_contents($gzPath, gzencode($sql));

    $decompressedPath = $restorer->decompressIfNeeded($gzPath);

    expect($decompressedPath)->toEndWith('.sql')
        ->and(file_get_contents($decompressedPath))->toBe($sql);

    @unlink($gzPath);
    @unlink($decompressedPath);
});

it('returns the original path when the dump is not compressed', function () {
    $restorer = new RestoreDatabaseFromArchive;
    $sqlPath = sys_get_temp_dir().'/wallet-dump-'.uniqid('', true).'.sql';

    file_put_contents($sqlPath, 'SELECT 1;');

    expect($restorer->decompressIfNeeded($sqlPath))->toBe($sqlPath);

    @unlink($sqlPath);
});
