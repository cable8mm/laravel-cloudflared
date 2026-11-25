<?php

use Cable8mm\Cloudflared\Clients\CloudflareClient;
use Cable8mm\Cloudflared\Data\Certificate;
use Cable8mm\Cloudflared\Data\ProjectConfig;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    config(['cache.default' => 'array']);

    app()->instance(CloudflareClient::class, new CloudflareClient(
        new Certificate(zoneId: 'zone-123', accountId: 'account-456', apiToken: 'test-token')
    ));

    Http::fake(function (Request $request) {
        $url = $request->url();

        return match (true) {
            $request->method() === 'DELETE' => Http::response(['result' => ['id' => 'record-1']]),
            str_ends_with($url, 'zones/zone-123') => Http::response(['result' => ['name' => 'example.com']]),
            str_contains($url, 'dns_records/record-1') => Http::response(['result' => ['content' => 'tunnel-id.cfargotunnel.com']]),
            str_contains($url, 'dns_records?') => Http::response(['result' => [['id' => 'record-1']]]),
        };
    });
});

function fakeCloudflaredProcesses(array $existingHostnames = []): void
{
    $fakes = [];

    foreach ($existingHostnames as $hostname) {
        $fakes["*'route' 'dns' '6f1c-42ab' '{$hostname}'"] = Process::result(
            errorOutput: 'Failed to add route: code: 1003, reason: An A, AAAA, or CNAME record with that host already exists.',
            exitCode: 1,
        );
    }

    $fakes["*'tunnel' 'create'*"] = Process::result(output: 'Created tunnel myapp-abc with id 6f1c-42ab');
    $fakes['*'] = Process::result();

    Process::fake($fakes);
}

it('passes user input to external commands as separate arguments', function () {
    fakeCloudflaredProcesses();

    $this->artisan('cloudflared:install')
        ->expectsQuestion('What subdomain do you want to use for this tunnel?', 'my app; rm -rf ~')
        ->expectsConfirmation('Do you want to create a DNS record for Vite?', 'no')
        ->assertSuccessful();

    Process::assertRan(fn ($process) => $process->command === ['herd', 'link', 'my app; rm -rf ~.example.com']);
    Process::assertRan(fn ($process) => $process->command === ['cloudflared', 'tunnel', 'route', 'dns', '6f1c-42ab', 'my app; rm -rf ~.example.com']);
});

it('deletes records created for an abandoned hostname when choosing a different one', function () {
    fakeCloudflaredProcesses(existingHostnames: ['vite-myapp.example.com']);

    $this->artisan('cloudflared:install')
        ->expectsQuestion('What subdomain do you want to use for this tunnel?', 'myapp')
        ->expectsConfirmation('Do you want to create a DNS record for Vite?', 'yes')
        ->expectsChoice('How do you want to proceed?', 'Choose a different hostname', [
            'Overwrite and point to your tunnel',
            'Choose a different hostname',
        ])
        ->expectsQuestion('What subdomain do you want to use for this tunnel?', 'other')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
        && str_ends_with($request->url(), 'dns_records/record-1'));

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && str_contains($request->url(), 'name=myapp.example.com'));

    Process::assertRan(fn ($process) => $process->command === ['cloudflared', 'tunnel', 'route', 'dns', '6f1c-42ab', 'other.example.com']);
    Process::assertRan(fn ($process) => $process->command === ['cloudflared', 'tunnel', 'route', 'dns', '6f1c-42ab', 'vite-other.example.com']);
    Process::assertRan(fn ($process) => $process->command === ['herd', 'link', 'other.example.com']);

    expect(ProjectConfig::load())
        ->hostname->toBe('other.example.com')
        ->vite->toBeTrue();
});

it('exits successfully when keeping the existing configuration', function () {
    installCloudflared();
    fakeCloudflaredProcesses();

    $this->artisan('cloudflared:install')
        ->expectsChoice('What would you like to do?', 'Keep existing configuration', [
            'Keep existing configuration',
            'Change subdomain',
            'Create Vite DNS record',
            'Repair DNS records',
            'Delete and recreate tunnel',
        ])
        ->expectsOutputToContain('Cancelled.')
        ->assertSuccessful();

    Process::assertDidntRun(fn ($process) => in_array('route', (array) $process->command));
});
