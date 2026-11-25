<?php

namespace Cable8mm\Cloudflared\Console\Commands;

use Cable8mm\Cloudflared\Concerns\InteractsWithHerd;
use Cable8mm\Cloudflared\Concerns\InteractsWithTunnel;
use Cable8mm\Cloudflared\Concerns\ManagesProject;
use Cable8mm\Cloudflared\Data\TunnelConfig;
use Cable8mm\Cloudflared\Exceptions\ProjectNotInstalledException;
use Cable8mm\Cloudflared\Facades\Cloudflared;
use Illuminate\Console\Command;

use function Laravel\Prompts\info;

class CloudflaredRun extends Command
{
    use InteractsWithHerd, InteractsWithTunnel, ManagesProject;

    protected $signature = 'cloudflared:run';

    protected $description = 'Run the Cloudflare Tunnel of this project.';

    public function handle(): int
    {
        $this->verifyCloudflaredFoundInPath();
        $this->verifyHerdFoundInPath();

        if (! Cloudflared::isInstalled()) {
            $this->fail((new ProjectNotInstalledException)->getMessage());
        }

        $tunnelConfig = Cloudflared::tunnelConfig();

        $this->saveTunnelConfig($tunnelConfig);
        $this->createHerdLink($tunnelConfig->hostname());

        return $this->runTunnel($tunnelConfig);
    }

    protected function runTunnel(TunnelConfig $tunnelConfig): int
    {
        $process = $this->cloudflared()->run($tunnelConfig->path());

        info(' ✔ Started tunnel.');

        // Keep PHP alive on Ctrl+C and friends so the tunnel config is always
        // cleaned up: forward the signal and let wait() return once it exits.
        $this->trap([SIGINT, SIGTERM, SIGHUP], function () use ($process) {
            if ($process->running()) {
                $process->signal(SIGTERM);
            }
        });

        try {
            return $process->wait()->exitCode() ?? self::SUCCESS;
        } finally {
            info(' ✔ Stopped tunnel.');

            $this->deleteTunnelConfig($tunnelConfig);
        }
    }
}
