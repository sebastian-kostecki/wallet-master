<?php

declare(strict_types=1);

namespace App\Support\Backup;

use App\Exceptions\DomainException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupDestination;
use Symfony\Component\Process\Process;
use ZipArchive;

final class RestoreDatabaseFromArchive
{
    public function resolveBackupPath(
        ?string $backupArgument,
        bool $latest,
        string $diskName,
        string $backupName,
    ): string {
        if ($latest) {
            $backup = $this->resolveLatestBackup($diskName, $backupName);

            return $this->ensureLocalArchivePath($backup->disk(), $backup->path());
        }

        if ($backupArgument !== null && $backupArgument !== '') {
            if (is_file($backupArgument)) {
                return $backupArgument;
            }

            $disk = Storage::disk($diskName);

            if (! $disk->exists($backupArgument)) {
                throw new DomainException("Backup archive not found: {$backupArgument}");
            }

            return $this->ensureLocalArchivePath($disk, $backupArgument);
        }

        throw new DomainException('No backup specified. Pass a backup path or use --latest.');
    }

    /**
     * @return array<int, Backup>
     */
    public function listRecentBackups(string $diskName, string $backupName, int $limit = 10): array
    {
        $destination = BackupDestination::create($diskName, $backupName);

        if (! $destination->isReachable()) {
            throw new DomainException("Backup disk \"{$diskName}\" is not reachable.");
        }

        return $destination->backups()->take($limit)->all();
    }

    public function restore(string $archivePath, string $connection): void
    {
        $sqlPath = null;
        $decompressedPath = null;
        $temporaryDirectory = $this->temporaryDirectory();
        $shouldDeleteArchive = str_starts_with($archivePath, $temporaryDirectory);

        try {
            if (! is_file($archivePath)) {
                throw new DomainException("Backup archive not found: {$archivePath}");
            }

            $sqlPath = $this->extractDatabaseDump($archivePath, $connection);
            $decompressedPath = $this->decompressIfNeeded($sqlPath);
            $this->importSqlDump($decompressedPath, $connection);
        } finally {
            if ($decompressedPath !== null && $decompressedPath !== $sqlPath && is_file($decompressedPath)) {
                @unlink($decompressedPath);
            }

            if ($sqlPath !== null && is_file($sqlPath)) {
                @unlink($sqlPath);
            }

            if ($shouldDeleteArchive && is_file($archivePath)) {
                @unlink($archivePath);
            }
        }
    }

    public function extractDatabaseDump(string $zipPath, string $connection): string
    {
        $driver = (string) config("database.connections.{$connection}.driver");

        $zip = new ZipArchive;
        $result = $zip->open($zipPath);

        if ($result !== true) {
            throw new DomainException("Could not open backup archive. ZipArchive error code: {$result}");
        }

        $password = config('backup.backup.password');

        if (is_string($password) && $password !== '') {
            $zip->setPassword($password);
        }

        $dumpEntry = $this->findDatabaseDumpEntry($zip, $driver);

        if ($dumpEntry === null) {
            $zip->close();

            throw new DomainException("No database dump for driver \"{$driver}\" found in backup archive.");
        }

        $temporaryDirectory = $this->temporaryDirectory();
        $extractedPath = $temporaryDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $dumpEntry);

        if (! $zip->extractTo($temporaryDirectory, [$dumpEntry])) {
            $zip->close();

            throw new DomainException('Failed to extract database dump from backup archive.');
        }

        $zip->close();

        if (! is_file($extractedPath)) {
            throw new DomainException('Extracted database dump file is missing.');
        }

        return $extractedPath;
    }

    public function decompressIfNeeded(string $path): string
    {
        if (! str_ends_with($path, '.gz')) {
            return $path;
        }

        $decompressedPath = substr($path, 0, -3);
        $contents = gzdecode((string) file_get_contents($path));

        if ($contents === false) {
            throw new DomainException('Failed to decompress database dump.');
        }

        if (file_put_contents($decompressedPath, $contents) === false) {
            throw new DomainException('Failed to write decompressed database dump.');
        }

        return $decompressedPath;
    }

    public function importSqlDump(string $sqlPath, string $connection): void
    {
        $driver = (string) config("database.connections.{$connection}.driver");

        match ($driver) {
            'mysql', 'mariadb' => $this->importMysqlDump($sqlPath, $connection),
            'sqlite' => $this->importSqliteDump($sqlPath, $connection),
            default => throw new DomainException("Unsupported database driver for restore: {$driver}"),
        };
    }

    private function resolveLatestBackup(string $diskName, string $backupName): Backup
    {
        $destination = BackupDestination::create($diskName, $backupName);

        if (! $destination->isReachable()) {
            throw new DomainException("Backup disk \"{$diskName}\" is not reachable.");
        }

        $backup = $destination->backups()->newest();

        if ($backup === null) {
            throw new DomainException('No backups found on the configured disk.');
        }

        return $backup;
    }

    private function ensureLocalArchivePath(Filesystem $disk, string $path): string
    {
        if (method_exists($disk, 'path')) {
            $localPath = $disk->path($path);

            if (is_file($localPath)) {
                return $localPath;
            }
        }

        $stream = $disk->readStream($path);

        if (! is_resource($stream)) {
            throw new DomainException("Could not read backup archive: {$path}");
        }

        $localPath = $this->temporaryDirectory().DIRECTORY_SEPARATOR.'restore-'.basename($path);

        $target = fopen($localPath, 'wb');

        if ($target === false) {
            fclose($stream);

            throw new DomainException('Could not create temporary backup archive file.');
        }

        stream_copy_to_stream($stream, $target);
        fclose($stream);
        fclose($target);

        return $localPath;
    }

    private function findDatabaseDumpEntry(ZipArchive $zip, string $driver): ?string
    {
        $prefix = match ($driver) {
            'mysql', 'mariadb' => "db-dumps/{$driver}-",
            'sqlite' => 'db-dumps/sqlite-',
            default => null,
        };

        if ($prefix === null) {
            return null;
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = $zip->getNameIndex($index);

            if (! is_string($entry)) {
                continue;
            }

            if (str_starts_with($entry, $prefix) && ! str_ends_with($entry, '/')) {
                return $entry;
            }
        }

        return null;
    }

    private function importMysqlDump(string $sqlPath, string $connection): void
    {
        /** @var array{host?: string, port?: string|int, database?: string, username?: string, password?: string|null} $config */
        $config = config("database.connections.{$connection}");

        $command = [
            'mysql',
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.(string) ($config['port'] ?? '3306'),
            '--user='.($config['username'] ?? 'root'),
            (string) ($config['database'] ?? ''),
        ];

        $environment = array_filter([
            'MYSQL_PWD' => $config['password'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        $process = new Process(
            $command,
            null,
            $environment,
            (string) file_get_contents($sqlPath),
        );

        $process->setTimeout(null);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new DomainException(trim($process->getErrorOutput()) ?: 'MySQL restore failed.');
        }
    }

    private function importSqliteDump(string $sqlPath, string $connection): void
    {
        $databasePath = (string) config("database.connections.{$connection}.database");

        if ($databasePath === '' || $databasePath === ':memory:') {
            throw new DomainException('SQLite restore requires a file-based database path.');
        }

        if (is_file($databasePath) && ! unlink($databasePath)) {
            throw new DomainException('Could not remove existing SQLite database file before restore.');
        }

        $command = sprintf(
            'sqlite3 %s',
            escapeshellarg($databasePath),
        );

        $process = Process::fromShellCommandline($command);
        $process->setInput((string) file_get_contents($sqlPath));
        $process->setTimeout(null);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new DomainException(trim($process->getErrorOutput()) ?: 'SQLite restore failed.');
        }
    }

    private function temporaryDirectory(): string
    {
        $directory = (string) config('backup.backup.temporary_directory', storage_path('app/backup-temp'));

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new DomainException("Could not create temporary directory: {$directory}");
        }

        return $directory;
    }
}
