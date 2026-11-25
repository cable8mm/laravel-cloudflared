<?php

namespace Cable8mm\Cloudflared\Console\Commands;

use Cable8mm\Cloudflared\Concerns\InteractsWithCloudflareApi;
use Cable8mm\Cloudflared\Concerns\InteractsWithHerd;
use Cable8mm\Cloudflared\Concerns\InteractsWithTunnel;
use Cable8mm\Cloudflared\Concerns\ManagesProject;
use Cable8mm\Cloudflared\Data\ProjectConfig;
use Cable8mm\Cloudflared\Data\TunnelConfig;
use Cable8mm\Cloudflared\Exceptions\CommandCancelledException;
use Cable8mm\Cloudflared\Exceptions\DnsRecordAlreadyExistsException;
use Cable8mm\Cloudflared\Facades\Cloudflared;
use Cable8mm\Cloudflared\Support\Path;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class CloudflaredInstall extends Command
{
    use InteractsWithCloudflareApi, InteractsWithHerd, InteractsWithTunnel, ManagesProject;

    protected $signature = 'cloudflared:install';

    protected $description = 'Create a Cloudflare Tunnel for this project.';

    public function handle(): int
    {
        $this->verifyCloudflaredFoundInPath();
        $this->verifyHerdFoundInPath();

        try {
            Cloudflared::isInstalled()
                ? $this->handleExistingInstallation()
                : $this->handleNewInstallation();
        } catch (CommandCancelledException) {
            error(' ⚠ Cancelled.');
        }

        return self::SUCCESS;
    }

    protected function handleNewInstallation(): void
    {
        $hostname = $this->askForSubdomain();

        $vite = confirm(
            label: 'Do you want to create a DNS record for Vite?',
            hint: 'Required for vite-plugin-laravel-cloudflared. Creates DNS record: '.ProjectConfig::viteHostnameFor($hostname),
        );

        $tunnelDetails = $this->createTunnel();

        $projectConfig = new ProjectConfig(
            id: $tunnelDetails->id,
            name: $tunnelDetails->name,
            hostname: $hostname,
            vite: $vite
        );

        $this->createDnsRecords($projectConfig);
        $this->createHerdLink($projectConfig->hostname);
        $this->saveProjectConfig($projectConfig);
    }

    protected function handleExistingInstallation(): void
    {
        $tunnelConfig = Cloudflared::tunnelConfig();

        if (! $this->tunnelExists($tunnelConfig->name())) {
            warning(" ⚠ Tunnel {$tunnelConfig->name()} doesn't exist. Cleaning up old configs and creating a new tunnel.");

            $this->deleteHerdLink($tunnelConfig->hostname());
            $this->deleteProject($tunnelConfig);
            $this->handleNewInstallation();

            return;
        }

        warning(" ⚠ Tunnel {$tunnelConfig->name()} exists.");

        $selection = select(
            label: 'What would you like to do?',
            options: [
                'Keep existing configuration',
                'Change subdomain',
                ...! $tunnelConfig->projectConfig->vite ? ['Create Vite DNS record'] : [],
                'Repair DNS records',
                'Delete and recreate tunnel',
            ],
            default: 'Keep existing configuration'
        );

        match ($selection) {
            'Keep existing configuration' => throw new CommandCancelledException,
            'Change subdomain' => $this->changeSubdomain($tunnelConfig->projectConfig),
            'Repair DNS records' => $this->repairDnsRecords($tunnelConfig->projectConfig),
            'Delete and recreate tunnel' => $this->recreateTunnel($tunnelConfig),
            'Create Vite DNS record' => $this->createViteDnsRecord($tunnelConfig->projectConfig),
        };
    }

    protected function changeSubdomain(ProjectConfig $projectConfig, bool $enableVite = false): void
    {
        $oldHostname = $projectConfig->hostname;
        $oldHostnames = $projectConfig->hostnames();

        $projectConfig->hostname = $this->askForSubdomain();
        $projectConfig->vite = $projectConfig->vite || $enableVite;

        foreach ($oldHostnames as $hostname) {
            $this->deleteDnsRecord($hostname);
        }

        $this->deleteHerdLink($oldHostname);

        $this->createDnsRecords($projectConfig);

        $this->createHerdLink($projectConfig->hostname);

        $this->saveProjectConfig($projectConfig);
    }

    protected function repairDnsRecords(ProjectConfig $projectConfig): void
    {
        $message = $projectConfig->vite
            ? "Are you sure you want to update the DNS records for {$projectConfig->hostname} and {$projectConfig->viteHostname()} to point to your tunnel?"
            : "Are you sure you want to update the DNS record for {$projectConfig->hostname} to point to your tunnel?";

        $hint = $projectConfig->vite
            ? 'This will overwrite the existing DNS records.'
            : 'This will overwrite the existing DNS record.';

        $this->confirmOrCancel(label: $message, hint: $hint);

        foreach ($projectConfig->hostnames() as $hostname) {
            $this->overwriteDnsRecord($projectConfig->id, $hostname);
        }
    }

    protected function recreateTunnel(TunnelConfig $tunnelConfig): void
    {
        $this->deleteTunnel($tunnelConfig->name());
        $this->deleteDnsRecords($tunnelConfig);

        $this->deleteHerdLink($tunnelConfig->hostname());
        $this->deleteProject($tunnelConfig);
        $this->handleNewInstallation();
    }

    protected function createViteDnsRecord(ProjectConfig $projectConfig): void
    {
        $viteHostname = ProjectConfig::viteHostnameFor($projectConfig->hostname);

        $this->confirmOrCancel(
            label: 'Are you sure you want to create a DNS record for Vite?',
            hint: "Creates DNS record: {$viteHostname}"
        );

        try {
            $this->createDnsRecord($projectConfig->id, $viteHostname);
        } catch (DnsRecordAlreadyExistsException) {
            if (! $this->shouldOverwriteExistingDnsRecords([$viteHostname])) {
                // The old hostname's records belong to this tunnel, so changing
                // the subdomain replaces them along with the Herd link.
                $this->changeSubdomain($projectConfig, enableVite: true);

                return;
            }

            $this->overwriteDnsRecord($projectConfig->id, $viteHostname);
        }

        $projectConfig->vite = true;
        $this->saveProjectConfig($projectConfig);
    }

    protected function createDnsRecords(ProjectConfig $projectConfig): void
    {
        $createdRecords = [];
        $existingRecords = [];

        foreach ($projectConfig->hostnames() as $hostname) {
            try {
                $this->createDnsRecord($projectConfig->id, $hostname);
                $createdRecords[] = $hostname;
            } catch (DnsRecordAlreadyExistsException) {
                $existingRecords[] = $hostname;
            }
        }

        if (empty($existingRecords)) {
            return;
        }

        if ($this->shouldOverwriteExistingDnsRecords($existingRecords)) {
            foreach ($existingRecords as $hostname) {
                $this->overwriteDnsRecord($projectConfig->id, $hostname);
            }

            return;
        }

        // Records created for the abandoned hostname would otherwise be left behind.
        foreach ($createdRecords as $hostname) {
            $this->deleteDnsRecord($hostname);
        }

        $projectConfig->hostname = $this->askForSubdomain();
        $this->createDnsRecords($projectConfig);
    }

    /**
     * @param  list<string>  $existingRecords
     */
    protected function shouldOverwriteExistingDnsRecords(array $existingRecords): bool
    {
        warning(' ⚠ '.trans_choice(
            '{1} DNS record :records already exists.|[2,*] DNS records :records already exist.',
            count($existingRecords),
            ['records' => Arr::join($existingRecords, ', ', ' and ')]
        ));

        $selection = select(
            label: 'How do you want to proceed?',
            options: [
                'Overwrite and point to your tunnel',
                'Choose a different hostname',
            ]
        );

        return $selection === 'Overwrite and point to your tunnel';
    }

    protected function askForSubdomain(): string
    {
        $domain = $this->authenticatedDomain();

        $subdomain = text(
            label: 'What subdomain do you want to use for this tunnel?',
            placeholder: Path::projectName(),
            default: Path::projectName(),
            hint: "The tunnel will be available at {subdomain}.{$domain}",
            required: true,
        );

        return "{$subdomain}.{$domain}";
    }

    protected function confirmOrCancel(string $label, string $hint = ''): void
    {
        if (! confirm(label: $label, hint: $hint)) {
            throw new CommandCancelledException;
        }
    }
}
