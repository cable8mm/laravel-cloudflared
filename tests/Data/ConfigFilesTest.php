<?php

use Cable8mm\Cloudflared\Data\ProjectConfig;
use Cable8mm\Cloudflared\Data\TunnelConfig;
use Cable8mm\Cloudflared\Exceptions\ProjectNotInstalledException;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

it('round-trips the project config', function () {
    (new ProjectConfig(id: 'abc-123', name: 'myapp-x1', hostname: 'myapp.example.com', vite: true))->save();

    expect(ProjectConfig::load())
        ->id->toBe('abc-123')
        ->name->toBe('myapp-x1')
        ->hostname->toBe('myapp.example.com')
        ->vite->toBeTrue()
        ->hostnames()->toBe(['myapp.example.com', 'vite-myapp.example.com']);
});

it('ignores unknown keys and defaults vite to false', function () {
    File::put(ProjectConfig::path(), "id: abc-123\nname: myapp-x1\nhostname: myapp.example.com\nextra: value\n");

    expect(ProjectConfig::load())
        ->vite->toBeFalse()
        ->hostnames()->toBe(['myapp.example.com']);
});

it('reports missing keys in the project config', function () {
    File::put(ProjectConfig::path(), "id: abc-123\n");

    ProjectConfig::load();
})->throws(RuntimeException::class, 'missing required keys: name, hostname');

it('throws when the project is not installed', function () {
    ProjectConfig::load();
})->throws(ProjectNotInstalledException::class);

it('writes a tunnel config cloudflared can read', function () {
    $tunnel = new TunnelConfig(new ProjectConfig(id: 'abc-123', name: 'myapp-x1', hostname: 'myapp.example.com'));

    $tunnel->save();

    expect($tunnel->path())->toBe(getenv('HOME').'/.cloudflared/abc-123.yaml')
        ->and(Yaml::parseFile($tunnel->path()))->toBe([
            'tunnel' => 'abc-123',
            'credentials-file' => getenv('HOME').'/.cloudflared/abc-123.json',
            'ingress' => [
                ['hostname' => 'myapp.example.com', 'service' => LOCAL_URL],
                ['service' => 'http_status:404'],
            ],
        ]);
});
