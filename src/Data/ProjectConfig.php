<?php

namespace Cable8mm\Cloudflared\Data;

use Cable8mm\Cloudflared\Exceptions\ProjectNotInstalledException;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

class ProjectConfig
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public string $hostname,
        public bool $vite = false
    ) {}

    public static function load(): self
    {
        if (! static::exists()) {
            throw new ProjectNotInstalledException;
        }

        $data = Yaml::parseFile(static::path());

        $missingKeys = array_diff(['id', 'name', 'hostname'], array_keys(is_array($data) ? $data : []));

        if (! empty($missingKeys)) {
            throw new RuntimeException('.cloudflared.yaml is missing required keys: '.implode(', ', $missingKeys));
        }

        return new self(
            id: (string) $data['id'],
            name: (string) $data['name'],
            hostname: (string) $data['hostname'],
            vite: (bool) ($data['vite'] ?? false),
        );
    }

    public function save(): void
    {
        File::put(static::path(), Yaml::dump([
            'id' => $this->id,
            'name' => $this->name,
            'hostname' => $this->hostname,
            'vite' => $this->vite,
        ]));
    }

    public static function viteHostnameFor(string $hostname): string
    {
        return "vite-{$hostname}";
    }

    public function viteHostname(): ?string
    {
        return $this->vite ? static::viteHostnameFor($this->hostname) : null;
    }

    /**
     * The hostnames that need a DNS record pointing to the tunnel.
     *
     * @return list<string>
     */
    public function hostnames(): array
    {
        return array_values(array_filter([$this->hostname, $this->viteHostname()]));
    }

    public function delete(): void
    {
        File::delete(static::path());
    }

    public static function exists(): bool
    {
        return File::exists(static::path());
    }

    public static function path(): string
    {
        return base_path('.cloudflared.yaml');
    }
}
