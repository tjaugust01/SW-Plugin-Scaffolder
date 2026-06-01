<?php

namespace ShopwareScaffolding\Command;

use ShopwareScaffolding\Generator\PluginGenerator;
use ShopwareScaffolding\Service\PluginConfigPrompter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'create:plugin')]
class CreatePluginCommand extends Command
{
    public function __construct(
        private readonly PluginConfigPrompter $configPrompter = new PluginConfigPrompter(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'dir',
                'd',
                InputOption::VALUE_REQUIRED,
                'Target directory for the plugin',
                '.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $rawDir = $input->getOption('dir');
        $targetDirectory = $this->resolvePath($rawDir);

        $io->title('Shopware 6 Plugin Scaffolding');
        $io->note(sprintf('Target directory: %s', $targetDirectory));

        $config = $this->configPrompter->ask($io);

        $this->showSummary($io, $config);

        if (!$io->confirm('Generate plugin now?', true)) {
            $io->warning('Aborted.');

            return Command::SUCCESS;
        }

        if (!$this->ensureDirectoryExists($io, $targetDirectory)) {
            return Command::FAILURE;
        }

        try {
            $generator = new PluginGenerator($config, $targetDirectory);
            $generator->generate();
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success("Plugin '{$config->pluginName}' scaffolded successfully!");

        return Command::SUCCESS;
    }

    private function showSummary(SymfonyStyle $io, object $config): void
    {
        $io->section('Summary');
        $io->definitionList(
            ['Plugin Title' => $config->pluginTitle],
            ['Plugin Name' => $config->pluginName],
            ['Namespace' => $config->namespace],
            ['Composer Package' => $config->getComposerPackageName()],
            ['Shopware Versions' => implode(', ', $config->shopwareVersions)],
            ['Full Scaffolding' => $config->fullScaffolding ? 'Yes' : 'No'],
        );
    }

    private function ensureDirectoryExists(SymfonyStyle $io, string $targetDirectory): bool
    {
        if (is_dir($targetDirectory)) {
            return true;
        }

        if (!mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
            $io->error(sprintf('Directory "%s" was not created', $targetDirectory));

            return false;
        }

        return true;
    }

    /**
     * Hilfsmethode, um den Pfad in einen absoluten Pfad umzuwandeln
     */
    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('#^[A-Za-z]:\\\\#', $path) || str_starts_with($path, '\\\\')) {
            return rtrim($path, DIRECTORY_SEPARATOR);
        }

        $currentWorkingDirectory = getcwd();

        if ($path === '.') {
            return $currentWorkingDirectory;
        }

        return rtrim($currentWorkingDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . rtrim($path, DIRECTORY_SEPARATOR);
    }
}