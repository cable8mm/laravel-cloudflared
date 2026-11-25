<?php

use Illuminate\Support\Facades\Process;

it('fails with a helpful message when the project is not installed', function () {
    Process::fake();

    $this->artisan('cloudflared:uninstall')
        ->expectsOutputToContain('No project configuration found')
        ->assertFailed();
});
