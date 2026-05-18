<?php

use Symfony\Component\Process\Process;

test('pdftotext is available on PATH', function () {
    $process = new Process(['pdftotext', '-v']);
    $process->run();

    // pdftotext writes version info to stderr; exit code 0 or 99 both indicate it ran.
    expect($process->getExitCode())->not->toBeNull();
    expect(str_contains($process->getErrorOutput().$process->getOutput(), 'pdftotext'))->toBeTrue();
})->group('dependencies');
