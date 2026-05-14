<?php

namespace App\Jobs;

use App\Models\File;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PurgeExpiredFiles implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;

    public function handle(): void
    {
        $cutoff = Carbon::now()->subDays(90);

        File::query()
            ->where('file_path', 'like', 'imports/%')
            ->where('uploaded_at', '<', $cutoff)
            ->whereNull('expired_at')
            ->chunkById(500, function ($files) {
                foreach ($files as $file) {
                    $this->purgeOne($file);
                }
            });
    }

    private function purgeOne(File $file): void
    {
        $disk = Storage::disk();

        try {
            $disk->delete($file->file_path);
        } catch (\Throwable $e) {
            Log::warning('Purge failed for file, will retry next run.', [
                'file_id' => $file->id,
                'path' => $file->file_path,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $file->expired_at = Carbon::now();
        $file->save();

        Log::info('Purged file.', ['file_id' => $file->id, 'path' => $file->file_path]);
    }
}
