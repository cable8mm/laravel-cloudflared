<?php

namespace Cable8mm\Cloudflared\Data;

use Cable8mm\Cloudflared\Support\Path;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

class TunnelConfig
{
    public function __construct(public readonly ProjectConfig $projectConfig) {}

    public function save(): void
    {
        File::put($this->path(), Yaml::dump([
            'tunnel' => $this->id(),
            'credentials-file' => $this->credentialsPath(),
            'ingress' => [
                ['hostname' => $this->hostname(), 'service' => $this->service()],
                ['service' => 'http_status:404'],
            ],
        ], inline: 3, indent: 2, flags: Yaml::DUMP_COMPACT_NESTED_MAPPING));
    }

    public function delete(): void
    {
        File::delete($this->path());
    }

    public function hostname(): string
    {
        return $this->projectConfig->hostname;
    }

    public function viteHostname(): ?string
    {
        return $this->projectConfig->viteHostname();
    }

    public function id(): string
    {
        return $this->projectConfig->id;
    }

    public function name(): string
    {
        return $this->projectConfig->name;
    }

    public function service(): string
    {
        return config('app.url');
    }

    public function url(): string
    {
        return "https://{$this->hostname()}";
    }

    public function path(): string
    {
        return Path::cloudflared("{$this->id()}.yaml");
    }

    public function credentialsPath(): string
    {
        return Path::cloudflared("{$this->id()}.json");
    }
}
