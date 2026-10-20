<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Console;

use Illuminate\Console\Command;
use Nbcom\Bitrix24\Capabilities\CapabilityManager;
use Nbcom\Bitrix24\Client\RetryPolicy;
use Nbcom\Bitrix24\Contracts\ClientInterface;
use Nbcom\Bitrix24\Exceptions\Bitrix24Exception;

final class CheckCommand extends Command
{
    protected $signature = 'bitrix24:check
        {--refresh : Re-check scopes and methods even if the snapshot is fresh}
        {--smoke : Run safe read-only calls (im.user.get, im.search.department.list)}
        {--json : Output machine-readable JSON}';

    protected $description = 'Check Bitrix24 webhook scopes and method availability.';

    public function handle(CapabilityManager $capabilities, ClientInterface $client): int
    {
        try {
            $snapshot = $capabilities->snapshot((bool) $this->option('refresh'));
        } catch (Bitrix24Exception $exception) {
            return $this->failWith($exception);
        }

        $smoke = [];
        $smokeErrors = [];

        if ((bool) $this->option('smoke')) {
            foreach ([
                'im.user.get' => [],
                'im.search.department.list' => ['FIND' => '', 'LIMIT' => 1],
            ] as $method => $params) {
                try {
                    $client->call($method, $params, RetryPolicy::none());
                    $smoke[$method] = true;
                } catch (Bitrix24Exception $exception) {
                    $smoke[$method] = false;
                    $smokeErrors[$method] = $exception->getMessage();
                }
            }
        }

        $ok = ! $snapshot->hasMissing() && ! in_array(false, $smoke, true);

        if ((bool) $this->option('json')) {
            $this->output->writeln((string) json_encode([
                'ok' => $ok,
                'snapshot' => $snapshot->toArray(),
                'smoke' => $smoke,
                'smoke_errors' => $smokeErrors,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $ok ? self::SUCCESS : self::FAILURE;
        }

        $this->line('Bitrix24: '.$snapshot->portal.' (user '.$snapshot->userId.')');
        $this->line('Checked:  '.$snapshot->checkedAt->toDateTimeString());
        $this->line('Strategy: '.$snapshot->strategy);
        $this->line('');

        $rows = [];

        foreach ($snapshot->scopes as $scope) {
            $rows[] = ['scope', $scope, 'ok'];
        }

        foreach ($snapshot->missingScopes as $scope) {
            $rows[] = ['scope', $scope, 'MISSING'];
        }

        foreach ($snapshot->methods as $method => $status) {
            $rows[] = [
                'method',
                $method,
                $status->available ? 'ok' : ($status->exists ? 'no access' : 'not found'),
            ];
        }

        $this->table(['Type', 'Name', 'Status'], $rows);

        if ($smoke !== []) {
            $this->line('Smoke checks:');

            foreach ($smoke as $method => $passed) {
                $this->line(sprintf('  %-20s %s', $method, $passed ? 'ok' : 'FAILED'));

                if (isset($smokeErrors[$method])) {
                    $this->line('    '.$smokeErrors[$method]);
                }
            }
        }

        if ($ok) {
            $this->info('Result: OK');

            return self::SUCCESS;
        }

        $this->error('Result: FAILED');
        $this->line('Add the missing scopes to the inbound webhook and run "php artisan bitrix24:check --refresh".');

        return self::FAILURE;
    }

    private function failWith(Bitrix24Exception $exception): int
    {
        if ((bool) $this->option('json')) {
            $this->output->writeln((string) json_encode([
                'ok' => false,
                'error' => [
                    'reason' => $exception->reason()->value,
                    'error_code' => $exception->errorCode(),
                    'message' => $exception->getMessage(),
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->error($exception->getMessage());
        }

        return self::FAILURE;
    }
}
