<?php

namespace Cable8mm\Cloudflared\Console;

enum InstallAction: string
{
    case Keep = 'keep';
    case ChangeSubdomain = 'change-subdomain';
    case CreateViteRecord = 'create-vite-record';
    case RepairDnsRecords = 'repair-dns-records';
    case RecreateTunnel = 'recreate-tunnel';

    public function label(): string
    {
        return match ($this) {
            self::Keep => 'Keep existing configuration',
            self::ChangeSubdomain => 'Change subdomain',
            self::CreateViteRecord => 'Create Vite DNS record',
            self::RepairDnsRecords => 'Repair DNS records',
            self::RecreateTunnel => 'Delete and recreate tunnel',
        };
    }

    /**
     * The actions offered for an existing installation, keyed by value.
     *
     * @return array<string, string>
     */
    public static function options(bool $viteEnabled): array
    {
        $actions = array_filter(
            self::cases(),
            fn (self $action) => ! ($viteEnabled && $action === self::CreateViteRecord),
        );

        return collect($actions)
            ->mapWithKeys(fn (self $action) => [$action->value => $action->label()])
            ->all();
    }
}
