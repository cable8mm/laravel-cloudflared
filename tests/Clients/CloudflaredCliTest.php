<?php

use Cable8mm\Cloudflared\Clients\CloudflaredCli;
use Cable8mm\Cloudflared\Exceptions\DnsRecordAlreadyExistsException;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Facades\Process;

beforeEach(fn () => $this->cli = new CloudflaredCli);

it('creates a tunnel and parses its id', function () {
    Process::fake(['*' => Process::result(output: 'Created tunnel myapp-x1 with id 6f1c-42ab')]);

    expect($this->cli->createTunnel('myapp-x1'))
        ->id->toBe('6f1c-42ab')
        ->name->toBe('myapp-x1');

    Process::assertRan(fn ($process) => $process->command === ['cloudflared', 'tunnel', 'create', 'myapp-x1']);
});

it('throws when the tunnel id is missing from the output', function () {
    Process::fake(['*' => Process::result(output: 'unexpected')]);

    $this->cli->createTunnel('myapp-x1');
})->throws(RuntimeException::class, 'Unable to extract tunnel ID');

it('returns false when deleting a tunnel that does not exist', function () {
    Process::fake(['*' => Process::result(
        errorOutput: 'there should only be 1 non-deleted Tunnel named myapp-x1',
        exitCode: 1,
    )]);

    expect($this->cli->deleteTunnel('myapp-x1'))->toBeFalse();
});

it('throws when deleting a tunnel fails for another reason', function () {
    Process::fake(['*' => Process::result(errorOutput: 'boom', exitCode: 1)]);

    $this->cli->deleteTunnel('myapp-x1');
})->throws(ProcessFailedException::class);

it('throws when the dns record already exists', function () {
    Process::fake(['*' => Process::result(errorOutput: 'Failed to add route: code: 1003', exitCode: 1)]);

    $this->cli->routeDns('6f1c-42ab', 'myapp.example.com');
})->throws(DnsRecordAlreadyExistsException::class, 'myapp.example.com');

it('overwrites an existing dns record', function () {
    Process::fake();

    $this->cli->routeDns('6f1c-42ab', 'myapp.example.com', overwrite: true);

    Process::assertRan(fn ($process) => $process->command === ['cloudflared', 'tunnel', 'route', 'dns', '--overwrite-dns', '6f1c-42ab', 'myapp.example.com']);
});
