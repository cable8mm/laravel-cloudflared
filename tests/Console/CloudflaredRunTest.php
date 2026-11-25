<?php

use Illuminate\Support\Facades\Process;

it('fails when the project is not installed', function () {
    Process::fake();

    $this->artisan('cloudflared:run')
        ->expectsOutputToContain('No project configuration found')
        ->assertFailed();
});

it('runs the tunnel and removes its config when it stops', function () {
    installCloudflared();
    Process::fake();

    $configPath = getenv('HOME').'/.cloudflared/fake-tunnel-id.yaml';

    $this->artisan('cloudflared:run')
        ->expectsOutputToContain('Stopped tunnel.')
        ->assertSuccessful();

    Process::assertRan(fn ($process) => $process->command === ['herd', 'link', 'myapp.example.com']);
    Process::assertRan(fn ($process) => $process->command === ['cloudflared', 'tunnel', '--config', $configPath, 'run']);

    expect($configPath)->not->toBeFile();
});

it('returns the tunnel exit code', function () {
    installCloudflared();
    Process::fake([
        "*'run'" => Process::result(exitCode: 3),
        '*' => Process::result(),
    ]);

    $this->artisan('cloudflared:run')->assertExitCode(3);
});
