<?php

use App\Jobs\BackupDatabase;
use App\Services\Backup\SubprocessRunner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

// ── Helpers ───────────────────────────────────────────────────

function fakeRunner(string $content = 'fake-dump'): SubprocessRunner
{
    return new class($content) implements SubprocessRunner
    {
        public function __construct(private readonly string $content) {}

        public function pgDump(string $localPath, array $db): void
        {
            file_put_contents($localPath, $this->content);
        }
    };
}

function failingRunner(): SubprocessRunner
{
    return new class implements SubprocessRunner
    {
        public function pgDump(string $localPath, array $db): void
        {
            throw new RuntimeException('pg_dump failed: exit 1');
        }
    };
}

function bindRunner(SubprocessRunner $runner): void
{
    app()->instance(SubprocessRunner::class, $runner);
}

// ── Success path ──────────────────────────────────────────────

test('success path: file uploaded to spaces at expected path, temp file cleaned up', function () {
    Storage::fake('spaces');

    bindRunner(fakeRunner());

    dispatch_sync(new BackupDatabase);

    $now = Carbon::now();
    $expectedPrefix = 'backups/db/'.$now->format('Y/m').'/mythicfox-'.$now->format('Ymd');

    $uploaded = collect(Storage::disk('spaces')->files('backups/db', true))
        ->first(fn (string $f) => str_starts_with($f, $expectedPrefix));

    expect($uploaded)->not->toBeNull();

    // Local temp file cleaned up.
    $backups = glob(storage_path('app/backups/*.dump')) ?: [];
    expect($backups)->toBeEmpty();
});

// ── Failure path ──────────────────────────────────────────────

test('failure path: exception bubbles, no upload, temp file cleaned up', function () {
    Storage::fake('spaces');

    bindRunner(failingRunner());

    expect(fn () => dispatch_sync(new BackupDatabase))
        ->toThrow(RuntimeException::class);

    expect(Storage::disk('spaces')->files('backups/db', true))->toBeEmpty();

    $backups = glob(storage_path('app/backups/*.dump')) ?: [];
    expect($backups)->toBeEmpty();
});

// ── Retention ─────────────────────────────────────────────────

test('retention: backups older than 30 days are pruned after successful upload', function () {
    Storage::fake('spaces');

    // Seed an old backup file on the fake disk.
    $oldPath = 'backups/db/2024/01/mythicfox-202401010200.dump';
    Storage::disk('spaces')->put($oldPath, 'old dump');

    // Manually set a very old last-modified via the fake disk's internal filesystem.
    // Storage::fake uses the Illuminate local driver — we can set the timestamp by
    // touching the underlying file with an old mtime.
    $internalPath = Storage::disk('spaces')->path($oldPath);
    touch($internalPath, Carbon::now()->subDays(31)->timestamp);

    bindRunner(fakeRunner());

    dispatch_sync(new BackupDatabase);

    expect(Storage::disk('spaces')->exists($oldPath))->toBeFalse();

    // The just-uploaded file should still be there.
    $remaining = Storage::disk('spaces')->files('backups/db', true);
    expect($remaining)->toHaveCount(1);
});
