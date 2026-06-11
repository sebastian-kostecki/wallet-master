<?php

declare(strict_types=1);

namespace App\Console\Commands\Backup;

use App\Exceptions\DomainException;
use App\Support\Backup\RestoreDatabaseFromArchive;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

use function Laravel\Prompts\select;

#[Signature('backup:restore
    {backup? : Path on the backups disk or an absolute filesystem path}
    {--latest : Restore the newest backup from the configured disk}
    {--disk= : Backup filesystem disk (defaults to BACKUP_DISK)}
    {--connection= : Database connection to restore into}
    {--force : Skip destructive-operation confirmation}')]
#[Description('Restore the database from a spatie/laravel-backup archive')]
final class RestoreDatabase extends Command
{
    public function handle(RestoreDatabaseFromArchive $restorer): int
    {
        $connection = (string) ($this->option('connection') ?: config('database.default'));
        $databaseName = (string) config("database.connections.{$connection}.database", '');
        $diskName = (string) ($this->option('disk') ?: config('backup.backup.destination.disks.0', env('BACKUP_DISK', 'backups')));
        $backupName = (string) config('backup.backup.name');

        try {
            $archivePath = $this->resolveArchivePath($restorer, $diskName, $backupName);
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $confirmed = $this->confirm(
                "This will overwrite the entire \"{$connection}\" database ({$databaseName}) with data from \"{$archivePath}\". Continue?",
                false,
            );

            if (! $confirmed) {
                $this->info('Cancelled.');

                return self::SUCCESS;
            }
        }

        if ((string) config("database.connections.{$connection}.driver") === 'sqlite') {
            DB::disconnect($connection);
        }

        try {
            $restorer->restore($archivePath, $connection);

            if ((string) config("database.connections.{$connection}.driver") === 'sqlite') {
                DB::purge($connection);
            }
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('Database restore failed.');
            $this->line('Exception: '.$exception::class);
            $this->line('Message: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Database restored successfully from \"{$archivePath}\".");

        return self::SUCCESS;
    }

    private function resolveArchivePath(
        RestoreDatabaseFromArchive $restorer,
        string $diskName,
        string $backupName,
    ): string {
        $backupArgument = $this->argument('backup');
        $backupArgument = is_string($backupArgument) ? trim($backupArgument) : null;
        $backupArgument = $backupArgument === '' ? null : $backupArgument;

        if ($this->option('latest')) {
            return $restorer->resolveBackupPath(null, true, $diskName, $backupName);
        }

        if ($backupArgument !== null) {
            return $restorer->resolveBackupPath($backupArgument, false, $diskName, $backupName);
        }

        if ($this->input->isInteractive()) {
            return $this->resolveInteractiveArchivePath($restorer, $diskName, $backupName);
        }

        return $restorer->resolveBackupPath(null, true, $diskName, $backupName);
    }

    private function resolveInteractiveArchivePath(
        RestoreDatabaseFromArchive $restorer,
        string $diskName,
        string $backupName,
    ): string {
        $backups = $restorer->listRecentBackups($diskName, $backupName);

        if ($backups === []) {
            throw new DomainException('No backups found on the configured disk.');
        }

        $options = [];

        foreach ($backups as $backup) {
            $options[$backup->path()] = $backup->path().' ('.$backup->date()->toDateTimeString().')';
        }

        /** @var string $selectedPath */
        $selectedPath = select(
            label: 'Which backup should be restored?',
            options: $options,
            default: array_key_first($options),
        );

        return $restorer->resolveBackupPath($selectedPath, false, $diskName, $backupName);
    }
}
