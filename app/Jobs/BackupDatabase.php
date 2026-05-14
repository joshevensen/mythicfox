<?php

namespace App\Jobs;

use App\Services\Backup\SubprocessRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BackupDatabase implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Maximum seconds allowed for the job (pg_dump can be slow). */
    public int $timeout = 660;

    public function handle(SubprocessRunner $runner): void
    {
        $now = Carbon::now();
        $localName = 'mythicfox-'.$now->format('Ymd-Hi').'.dump';
        $localPath = storage_path('app/backups/'.$localName);
        $spacesPath = 'backups/db/'.$now->format('Y/m').'/mythicfox-'.$now->format('YmdHi').'.dump';

        $this->ensureBackupDir();

        try {
            $db = (array) config('database.connections.pgsql');
            $runner->pgDump($localPath, $db);
            $this->uploadToSpaces($localPath, $spacesPath);
            $this->pruneOldBackups($now);

            Log::info('DB backup uploaded.', [
                'path' => $spacesPath,
                'bytes' => filesize($localPath),
            ]);
        } catch (\Throwable $e) {
            Log::error('DB backup failed.', ['error' => $e->getMessage()]);
            throw $e; // bubble so queue worker records the failure
        } finally {
            $this->cleanupLocal($localPath);
        }
    }

    private function ensureBackupDir(): void
    {
        $dir = storage_path('app/backups');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    private function uploadToSpaces(string $localPath, string $spacesPath): void
    {
        $stream = fopen($localPath, 'rb');

        if ($stream === false) {
            throw new \RuntimeException("Cannot open dump file for upload: {$localPath}");
        }

        try {
            Storage::disk('spaces')->writeStream($spacesPath, $stream);
        } finally {
            fclose($stream);
        }
    }

    /**
     * Delete dump files on the spaces disk that are older than 30 days.
     */
    private function pruneOldBackups(Carbon $now): void
    {
        $disk = Storage::disk('spaces');
        $cutoff = $now->copy()->subDays(30);
        $files = $disk->files('backups/db', true);

        foreach ($files as $file) {
            try {
                $modified = Carbon::createFromTimestamp((int) $disk->lastModified($file));

                if ($modified->lt($cutoff)) {
                    $disk->delete($file);
                    Log::info('Pruned old backup.', ['path' => $file]);
                }
            } catch (\Throwable $e) {
                Log::warning('Could not check/prune backup file.', [
                    'path' => $file,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function cleanupLocal(string $path): void
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }
}
