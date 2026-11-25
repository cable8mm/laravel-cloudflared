<?php

namespace Cable8mm\Cloudflared\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool isInstalled()
 * @method static \Cable8mm\Cloudflared\Data\ProjectConfig projectConfig()
 * @method static \Cable8mm\Cloudflared\Data\TunnelConfig tunnelConfig()
 *
 * @see \Cable8mm\Cloudflared\Cloudflared
 */
class Cloudflared extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Cable8mm\Cloudflared\Cloudflared::class;
    }
}
