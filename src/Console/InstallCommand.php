<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Console;

use Illuminate\Console\Command;

final class InstallCommand extends Command
{
    protected $signature = 'bitrix24:install {--force : Overwrite the published config}';

    protected $description = 'Publish the Bitrix24 config and verify the webhook capabilities.';

    public function handle(): int
    {
        $this->call('vendor:publish', [
            '--tag' => 'bitrix24-config',
            '--force' => (bool) $this->option('force'),
        ]);

        return $this->call('bitrix24:check', ['--refresh' => true]);
    }
}
