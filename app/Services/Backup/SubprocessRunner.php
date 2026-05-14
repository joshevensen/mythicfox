<?php

namespace App\Services\Backup;

interface SubprocessRunner
{
    /**
     * Run pg_dump and write output to $localPath.
     *
     * @param  array<string, mixed>  $db  PostgreSQL connection config from config('database.connections.pgsql')
     *
     * @throws \RuntimeException on non-zero exit
     */
    public function pgDump(string $localPath, array $db): void;
}
