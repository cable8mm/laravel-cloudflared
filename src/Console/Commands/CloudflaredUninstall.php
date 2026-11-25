<?php

namespace Cable8mm\Cloudflared\Console\Commands;

use Cable8mm\Cloudflared\Concerns\InteractsWithCloudflareApi;
use Cable8mm\Cloudflared\Concerns\InteractsWithHerd;
use Cable8mm\Cloudflared\Concerns\InteractsWithTunnel;
use Cable8mm\Cloudflared\Concerns\ManagesProject;
use Cable8mm\Cloudflared\Exceptions\ProjectNotInstalledException;
use Cable8mm\Cloudflared\Facades\Cloudflared;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;

class CloudflaredUninstall extends Command
{
    use InteractsWithCloudflareApi, InteractsWithHerd, InteractsWithTunnel, ManagesProject;

    protected $signature = 'cloudflared:uninstall';

    protected $description = 'Delete the Cloudflare Tunnel of this project.';

    public function handle(): int
    {
        $this->verifyCloudflaredFoundInPath();
        $this->verifyHerdFoundInPath();

        if (! Cloudflared::isInstalled()) {
            $this->fail(new ProjectNotInstalledException);
        }

        $tunnelConfig = Cloudflared::tunnelConfig();

        $confirmed = confirm(
            label: "Are you sure you want to delete tunnel {$tunnelConfig->name()}?",
            hint: 'Deletes the tunnel, DNS records, Herd link, and all associated configs.',
            default: false,
        );

        if (! $confirmed) {
            error(' ⚠ Cancelled.');

            return self::SUCCESS;
        }

        $this->deleteTunnel($tunnelConfig->name());
        $this->deleteHerdLink($tunnelConfig->hostname());
        $this->deleteDnsRecords($tunnelConfig);
        $this->deleteProject($tunnelConfig);

        return self::SUCCESS;
    }
}
