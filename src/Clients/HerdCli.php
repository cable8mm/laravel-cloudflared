<?php

namespace Cable8mm\Cloudflared\Clients;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;

class HerdCli
{
    public function installed(): bool
    {
        return $this->exec('--version')->successful();
    }

    public function link(string $hostname): void
    {
        $this->exec('link', $hostname)->throw();
    }

    public function unlink(string $hostname): void
    {
        $this->exec('unlink', $hostname)->throw();
    }

    protected function exec(string ...$arguments): ProcessResult
    {
        return Process::run(['herd', ...$arguments]);
    }
}
