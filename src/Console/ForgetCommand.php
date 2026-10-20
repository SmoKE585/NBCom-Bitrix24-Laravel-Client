<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Console;

use Illuminate\Console\Command;
use Nbcom\Bitrix24\Capabilities\CapabilityManager;

final class ForgetCommand extends Command
{
    protected $signature = 'bitrix24:forget';

    protected $description = 'Delete the cached Bitrix24 capabilities snapshot.';

    public function handle(CapabilityManager $capabilities): int
    {
        $capabilities->forget();
        $this->info('Bitrix24 capabilities snapshot deleted.');

        return self::SUCCESS;
    }
}
