<?php

namespace Cable8mm\Cloudflared\Concerns;

use Cable8mm\Cloudflared\Clients\HerdCli;

use function Laravel\Prompts\info;

trait InteractsWithHerd
{
    protected function herd(): HerdCli
    {
        return app(HerdCli::class);
    }

    protected function verifyHerdFoundInPath(): void
    {
        if (! $this->herd()->installed()) {
            $this->fail('Laravel Herd not found in PATH.');
        }
    }

    protected function createHerdLink(string $hostname): void
    {
        $this->herd()->link($hostname);

        info(" ✔ Created Herd link: {$hostname}");
    }

    protected function deleteHerdLink(string $hostname): void
    {
        $this->herd()->unlink($hostname);

        info(" ✔ Deleted Herd link: {$hostname}");
    }
}
