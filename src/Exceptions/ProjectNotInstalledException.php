<?php

namespace Cable8mm\Cloudflared\Exceptions;

use RuntimeException;

class ProjectNotInstalledException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No project configuration found. Run "php artisan cloudflared:install" first.');
    }
}
