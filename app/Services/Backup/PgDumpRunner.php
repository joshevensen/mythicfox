<?php

namespace App\Services\Backup;

use Symfony\Component\Process\Process;

class PgDumpRunner implements SubprocessRunner
{
    public function pgDump(string $localPath, array $db): void
    {
        $command = [
            'pg_dump',
            '--host='.$db['host'],
            '--port='.$db['port'],
            '--username='.$db['username'],
            '--dbname='.$db['database'],
            '--format=custom',
            '--no-owner',
            '--no-privileges',
            '--file='.$localPath,
        ];

        $process = new Process($command, timeout: 600);
        $process->setEnv(['PGPASSWORD' => (string) ($db['password'] ?? '')]);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(
                'pg_dump failed (exit '.$process->getExitCode().'): '.$process->getErrorOutput()
            );
        }
    }
}
