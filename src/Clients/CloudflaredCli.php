<?php

namespace Cable8mm\Cloudflared\Clients;

use Cable8mm\Cloudflared\Data\TunnelDetails;
use Cable8mm\Cloudflared\Exceptions\DnsRecordAlreadyExistsException;
use Illuminate\Contracts\Process\InvokedProcess;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Symfony\Component\Process\Process as SymfonyProcess;

class CloudflaredCli
{
    public function installed(): bool
    {
        return $this->exec('--version')->successful();
    }

    public function tunnelExists(string $name): bool
    {
        return $this->exec('tunnel', 'info', $name)->successful();
    }

    public function createTunnel(string $name): TunnelDetails
    {
        $output = $this->exec('tunnel', 'create', $name)->throw()->output();

        if (! preg_match('/Created tunnel .+ with id ([a-f0-9\-]+)/', $output, $matches)) {
            throw new RuntimeException('Unable to extract tunnel ID from cloudflared output.');
        }

        return new TunnelDetails(id: $matches[1], name: $name);
    }

    /**
     * Delete a tunnel. Returns false if it doesn't exist.
     */
    public function deleteTunnel(string $name): bool
    {
        $result = $this->exec('tunnel', 'delete', $name);

        if ($result->seeInErrorOutput("there should only be 1 non-deleted Tunnel named {$name}")) {
            return false;
        }

        $result->throw();

        return true;
    }

    /**
     * Point a DNS record at a tunnel.
     *
     * @throws DnsRecordAlreadyExistsException when the record exists and $overwrite is false
     */
    public function routeDns(string $tunnelId, string $hostname, bool $overwrite = false): void
    {
        $result = $overwrite
            ? $this->exec('tunnel', 'route', 'dns', '--overwrite-dns', $tunnelId, $hostname)
            : $this->exec('tunnel', 'route', 'dns', $tunnelId, $hostname);

        if (! $overwrite && $result->seeInErrorOutput('Failed to add route: code: 1003')) {
            throw new DnsRecordAlreadyExistsException($hostname);
        }

        $result->throw();
    }

    /**
     * Start the tunnel in the foreground, sharing this terminal when there is one.
     */
    public function run(string $configPath): InvokedProcess
    {
        return Process::forever()
            ->tty(SymfonyProcess::isTtySupported())
            ->start(['cloudflared', 'tunnel', '--config', $configPath, 'run']);
    }

    protected function exec(string ...$arguments): ProcessResult
    {
        return Process::run(['cloudflared', ...$arguments]);
    }
}
