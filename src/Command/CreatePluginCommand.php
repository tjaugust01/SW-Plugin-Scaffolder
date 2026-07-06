<?php

namespace ShopwareScaffolding\Command;

use ShopwareScaffolding\Config\PluginConfig;
use ShopwareScaffolding\Generator\PluginGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'create:plugin')]
class CreatePluginCommand extends Command
{
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

        $config = new PluginConfig();

        $io->section('Plugin Metadata');

        $config->pluginTitle = $io->ask('Plugin Title', 'My Awesome Plugin');

        $config->pluginName = $io->ask('Plugin Name (PascalCase, used for classname & folder)', 'MyAwesomePlugin', function (string $value): string {
            if (!preg_match('/^[A-Z][a-zA-Z0-9]+$/', $value)) {
                throw new \InvalidArgumentException('Plugin name must be PascalCase (e.g. MyAwesomePlugin)');
            }
            return $value;
        });

        $vendorName = $io->ask('Vendor Name (PascalCase)', 'MyVendor', function (string $value): string {
            if (!preg_match('/^[A-Z][a-zA-Z0-9]+$/', $value)) {
                throw new \InvalidArgumentException('Vendor name must be PascalCase (e.g. MyVendor)');
            }
            return $value;
        });

        $config->namespace = $io->ask('Default Namespace', "{$vendorName}\\{$config->pluginName}");
        $config->description = $io->ask('Description', '');
        $config->author = $io->ask('Author', '');
        $config->email = $io->ask('E-Mail', '');

        $availableVersions = ['6.5', '6.6', '6.7'];
        $config->shopwareVersions = $io->choice(
            'Shopware Versions (comma separated for multiple)',
            $availableVersions,
            '6.6',
            true
        );

        // Sort versions so the highest is first (will be used for master branch)
        usort($config->shopwareVersions, 'version_compare');
        $config->shopwareVersions = array_reverse($config->shopwareVersions);

        $io->section('Dev Tooling');

        $config->withPhpUnit = $io->confirm('Include PHPUnit?', false);
        $config->withPhpStan = $io->confirm('Include PHPStan?', false);
        $config->withDocker = $io->confirm('Include Docker config?', false);

        if ($config->withDocker) {
            $io->note('Fetching latest Dockware tags...');
            foreach ($config->shopwareVersions as $version) {
                $tag = $this->fetchLatestDockwareTag($version);
                if ($tag) {
                    $config->dockwareTags[$version] = $tag;
                    $io->writeln(sprintf('  - Found tag <info>%s</info> for version <info>%s</info>', $tag, $version));
                } else {
                    $config->dockwareTags[$version] = 'latest';
                    $io->warning(sprintf('Could not find Dockware tag for version %s, using "latest"', $version));
                }
            }
        }

        $config->withTypeScript = $io->confirm('Include TypeScript setup?', false);
        $config->withJetBrainsRunConfigs = $io->confirm('Include JetBrains Run Configurations?', false);
        $config->withWriterside = $io->confirm('Include Writerside documentation?', false);
        $config->withGithubActions = $io->confirm('Include GitHub Actions CI?', false);
        $config->withMakefile = $io->confirm('Include Makefile?', false);

        $io->section('Plugin Architecture');

        $config->withAdminExtension = $io->confirm('Admin Extension (Vue components)?', false);
        $config->withStorefrontExtension = $io->confirm('Storefront Extension (Twig/SCSS)?', false);
        $config->withDatabase = $io->confirm('Custom Database Table (Entity, Definition, Migration)?', false);
        $config->withScheduledTask = $io->confirm('Scheduled Task?', false);
        $config->withEventSubscriber = $io->confirm('Event Subscriber?', false);
        $config->withCliCommand = $io->confirm('CLI Command (Symfony Console)?', false);
        $config->withCustomConfig = $io->confirm('Custom Plugin Config (config.xml)?', false);

        $io->section('Scaffolding Depth');

        $config->fullScaffolding = $io->choice(
                'Scaffolding depth',
                [
                    'full' => 'Full (with example DTOs, Services, etc.)',
                    'structure' => 'Structure only (empty folders & base files)',
                ],
                'structure'
            ) === 'full';

        $io->section('Summary');
        $io->definitionList(
            ['Plugin Title' => $config->pluginTitle],
            ['Plugin Name' => $config->pluginName],
            ['Namespace' => $config->namespace],
            ['Composer Package' => $config->getComposerPackageName()],
            ['Shopware Versions' => implode(', ', $config->shopwareVersions)],
            ['Full Scaffolding' => $config->fullScaffolding ? 'Yes' : 'No'],
        );

        if (!$io->confirm('Generate plugin now?', true)) {
            $io->warning('Aborted.');
            return Command::SUCCESS;
        }

        $pluginTargetDirectory = $targetDirectory . DIRECTORY_SEPARATOR . $config->pluginName;

        if (!is_dir($pluginTargetDirectory)) {
            if (!mkdir($pluginTargetDirectory, 0777, true) && !is_dir($pluginTargetDirectory)) {
                $io->error(sprintf('Directory "%s" was not created', $pluginTargetDirectory));
                return Command::FAILURE;
            }
        }

        try {
            $generator = new PluginGenerator($config, $pluginTargetDirectory);
            $generator->generate();
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->success("Plugin '{$config->pluginName}' scaffolded successfully!");

        return Command::SUCCESS;

    }

    private function fetchLatestDockwareTag(string $majorMinor): ?string
    {
        $url = "https://registry.hub.docker.com/v2/repositories/dockware/dev/tags?page_size=100";
        $json = @file_get_contents($url);
        if (!$json) {
            return null;
        }

        $data = json_decode($json, true);
        if (!isset($data['results'])) {
            return null;
        }

        $tags = array_column($data['results'], 'name');
        $pattern = '/^' . preg_quote($majorMinor, '/') . '\.[0-9]+(\.[0-9]+)?$/';

        $filteredTags = array_filter($tags, function ($tag) use ($pattern) {
            return preg_match($pattern, $tag);
        });

        if (empty($filteredTags)) {
            return null;
        }

        usort($filteredTags, 'version_compare');

        return end($filteredTags) ?: null;
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