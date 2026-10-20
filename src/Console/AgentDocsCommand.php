<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Console;

use Illuminate\Console\Command;

final class AgentDocsCommand extends Command
{
    protected $signature = 'bitrix24:agent-docs
        {--path= : Destination path, relative to the project root}
        {--force : Overwrite an existing file}
        {--stdout : Print the guide instead of writing a file}';

    protected $description = 'Publish the Bitrix24 guide for AI agents into the application.';

    public function handle(): int
    {
        $source = dirname(__DIR__, 2).'/resources/agent/BITRIX24.md';

        if (! is_file($source)) {
            $this->error("Agent guide not found at [{$source}].");

            return self::FAILURE;
        }

        $contents = (string) file_get_contents($source);

        if ((bool) $this->option('stdout')) {
            $this->output->write($contents);

            return self::SUCCESS;
        }

        $path = (string) ($this->option('path') ?: '.ai/bitrix24.md');
        $target = $this->isAbsolutePath($path) ? $path : base_path($path);

        if (is_file($target) && ! (bool) $this->option('force')) {
            $this->error("File [{$target}] already exists. Use --force to overwrite.");

            return self::FAILURE;
        }

        $directory = dirname($target);

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            $this->error("Cannot create directory [{$directory}].");

            return self::FAILURE;
        }

        if (file_put_contents($target, $contents) === false) {
            $this->error("Cannot write [{$target}].");

            return self::FAILURE;
        }

        $relative = $this->relativePath($target);
        $this->info("Bitrix24 agent guide written to [{$relative}].");
        $this->line('');
        $this->line('Add this line to the project AGENTS.md (or CLAUDE.md):');
        $this->line('@'.$relative);

        return self::SUCCESS;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    private function relativePath(string $path): string
    {
        $base = rtrim(base_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (str_starts_with($path, $base)) {
            return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($base)));
        }

        return $path;
    }
}
