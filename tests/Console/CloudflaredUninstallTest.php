<?php

use Cable8mm\Cloudflared\Clients\CloudflareClient;
use Cable8mm\Cloudflared\Data\Certificate;
use Cable8mm\Cloudflared\Data\ProjectConfig;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    app()->instance(CloudflareClient::class, new CloudflareClient(
        new Certificate(zoneId: 'zone-123', accountId: 'account-456', apiToken: 'test-token')
    ));

    Http::fake(fn (Request $request) => match (true) {
        str_contains($request->url(), 'dns_records?') => Http::response(['result' => [['id' => 'record-1']]]),
        default => Http::response(['result' => ['content' => 'fake-tunnel-id.cfargotunnel.com']]),
    });

    Process::fake();
    installCloudflared();
});

it('deletes the tunnel, dns record, herd link and config', function () {
    $this->artisan('cloudflared:uninstall')
        ->expectsConfirmation('Are you sure you want to delete tunnel fake-tunnel-name?', 'yes')
        ->assertSuccessful();

    Process::assertRan(fn ($process) => $process->command === ['cloudflared', 'tunnel', 'delete', 'fake-tunnel-name']);
    Process::assertRan(fn ($process) => $process->command === ['herd', 'unlink', 'myapp.example.com']);
    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE');

    expect(ProjectConfig::exists())->toBeFalse();
});

it('does nothing when not confirmed', function () {
    $this->artisan('cloudflared:uninstall')
        ->expectsConfirmation('Are you sure you want to delete tunnel fake-tunnel-name?', 'no')
        ->assertSuccessful();

    Process::assertDidntRun(fn ($process) => in_array('delete', (array) $process->command));
    Http::assertNothingSent();

    expect(ProjectConfig::exists())->toBeTrue();
});

it('fails with a helpful message when the project is not installed', function () {
    File::delete(ProjectConfig::path());

    $this->artisan('cloudflared:uninstall')
        ->expectsOutputToContain('No project configuration found')
        ->assertFailed();
});
