<?php

namespace Cable8mm\Cloudflared\Exceptions;

use RuntimeException;

class CommandCancelledException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Cancelled.');
    }
}
