<?php

namespace Cable8mm\Cloudflared\Support;

class Path
{
    /**
     * A path inside cloudflared's config directory (~/.cloudflared).
     */
    public static function cloudflared(string $file = ''): string
    {
        return static::join(getenv('HOME'), '.cloudflared', $file);
    }

    /**
     * The project directory name, used as the default subdomain and tunnel name prefix.
     */
    public static function projectName(): string
    {
        return basename(base_path());
    }

    public static function join(string ...$parts): string
    {
        $path = implode('/', array_filter($parts, fn (string $part) => $part !== ''));

        return preg_replace('#/+#', '/', str_replace('\\', '/', $path));
    }
}
