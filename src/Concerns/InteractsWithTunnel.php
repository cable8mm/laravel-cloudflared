<?php

namespace Cable8mm\Cloudflared\Concerns;

use Cable8mm\Cloudflared\Clients\CloudflaredCli;
use Cable8mm\Cloudflared\Data\TunnelDetails;
use Cable8mm\Cloudflared\Exceptions\DnsRecordAlreadyExistsException;
use Cable8mm\Cloudflared\Support\Path;
use Illuminate\Support\Str;

use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

trait InteractsWithTunnel
{
    protected function cloudflared(): CloudflaredCli
    {
        return app(CloudflaredCli::class);
    }

    protected function verifyCloudflaredFoundInPath(): void
    {
        if (! $this->cloudflared()->installed()) {
            $this->fail('cloudflared not found in PATH.');
        }
    }

    protected function tunnelExists(string $name): bool
    {
        return spin(
            callback: fn () => $this->cloudflared()->tunnelExists($name),
            message: "Verifying tunnel {$name} exists"
        );
    }

    protected function createTunnel(): TunnelDetails
    {
        $name = Path::projectName().'-'.Str::lower(Str::random(8));

        $tunnel = spin(
            callback: fn () => $this->cloudflared()->createTunnel($name),
            message: "Creating tunnel: {$name}"
        );

        info(" ✔ Created tunnel: {$name}");

        return $tunnel;
    }

    protected function deleteTunnel(string $name): void
    {
        $deleted = spin(
            callback: fn () => $this->cloudflared()->deleteTunnel($name),
            message: "Deleting tunnel: {$name}"
        );

        $deleted
            ? info(" ✔ Deleted tunnel: {$name}")
            : warning(" ⚠ Can't delete tunnel {$name} because it doesn't exist.");
    }

    /**
     * @throws DnsRecordAlreadyExistsException
     */
    protected function createDnsRecord(string $tunnelId, string $hostname): void
    {
        spin(
            callback: fn () => $this->cloudflared()->routeDns($tunnelId, $hostname),
            message: "Creating DNS record: {$hostname}"
        );

        info(" ✔ Created DNS record: {$hostname}");
    }

    protected function overwriteDnsRecord(string $tunnelId, string $hostname): void
    {
        spin(
            callback: fn () => $this->cloudflared()->routeDns($tunnelId, $hostname, overwrite: true),
            message: "Overwriting DNS record: {$hostname}"
        );

        info(" ✔ Overwrote DNS record: {$hostname}");
    }
}
