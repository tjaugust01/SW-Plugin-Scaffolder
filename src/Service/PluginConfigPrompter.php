<?php

namespace ShopwareScaffolding\Service;

use RuntimeException;
use ShopwareScaffolding\Config\GitBranchNamingConvention;
use ShopwareScaffolding\Config\PluginConfig;
use ShopwareScaffolding\Service\Util\DockwareTagResolver;
use ShopwareScaffolding\Service\Util\ShopwareVersionFetcher;
use Symfony\Component\Console\Style\SymfonyStyle;

class PluginConfigPrompter
{
    public function __construct(
        private readonly DockwareTagResolver $dockwareTagResolver = new DockwareTagResolver(),
    )
    {
    }

    public function ask(SymfonyStyle $io): PluginConfig
    {
        $config = new PluginConfig();

        $io->section('Plugin Metadata');

        $config->pluginTitle = $io->ask('Plugin Title', 'My Awesome Plugin');

        $config->pluginName = $io->ask(
            'Plugin Name (PascalCase, used for classname & folder)',
            'MyAwesomePlugin',
            function (string $value): string {
                if (!preg_match('/^[A-Z][a-zA-Z0-9]+$/', $value)) {
                    throw new \InvalidArgumentException('Plugin name must be PascalCase (e.g. MyAwesomePlugin)');
                }

                return $value;
            }
        );

        $vendorName = $io->ask(
            'Vendor Name (PascalCase)',
            'MyVendor',
            function (string $value): string {
                if (!preg_match('/^[A-Z][a-zA-Z0-9]+$/', $value)) {
                    throw new \InvalidArgumentException('Vendor name must be PascalCase (e.g. MyVendor)');
                }

                return $value;
            }
        );

        $config->namespace = $io->ask('Default Namespace', "{$vendorName}\\{$config->pluginName}");
        $config->description = $io->ask('Description', '');
        $config->author = $io->ask('Author', '');
        $config->email = $io->ask('E-Mail', '');

        $multiVersion = $io->confirm('Multi-Shopware Version Support?', false);
        if ($multiVersion) {
            $config->withGitRepository = true;
            try {
                $io->note('Fetching latest Shopware versions...');
                $versions = ShopwareVersionFetcher::fetchMajorVersion();
            }catch (RuntimeException $e) {
                $io->warning('Failed to fetch Shopware versions, using defaults.');
                $versions = ['6.5', '6.6', '6.7'];
            }
            $config->shopwareVersions = $io->choice(
                'Shopware Versions (comma separated for multiple)',
                $versions,
                '6.6',
                true
            );

            usort($config->shopwareVersions, 'version_compare');
            $config->shopwareVersions = array_reverse($config->shopwareVersions);

            $choice = $io->choice(
                'Git Branch Naming Convention',
                [
                    GitBranchNamingConvention::Main->value => GitBranchNamingConvention::Main->label(),
                    GitBranchNamingConvention::Latest->value => GitBranchNamingConvention::Latest->label(),
                    GitBranchNamingConvention::Master->value => GitBranchNamingConvention::Master->label(),
                    GitBranchNamingConvention::VersionOnly->value => GitBranchNamingConvention::VersionOnly->label(),
                ],
                GitBranchNamingConvention::VersionOnly->value
            );

            $config->gitBranchNamingConvention = GitBranchNamingConvention::from($choice);

        } else {
            $config->withGitRepository = $io->confirm('Create Git Repository?', true);
        }

        $this->askDevTooling($io, $config);
        $this->askPluginArchitecture($io, $config);
        $this->askScaffoldingDepth($io, $config);

        return $config;
    }

    private function askDevTooling(SymfonyStyle $io, PluginConfig $config): void
    {
        $io->section('Dev Tooling');

        $config->withPhpUnit = $io->confirm('Include PHPUnit?', false);
        $config->withPhpStan = $io->confirm('Include PHPStan?', false);
        $config->withDocker = $io->confirm('Include Docker config?', false);

        if ($config->withDocker) {
            $this->resolveDockwareTags($io, $config);
        }

        $config->withTypeScript = $io->confirm('Include TypeScript setup?', false);
        $config->withJetBrainsRunConfigs = $io->confirm('Include JetBrains Run Configurations?', false);
        $config->withWriterside = $io->confirm('Include Writerside documentation?', false);
        $config->withGithubActions = $io->confirm('Include GitHub Actions CI?', false);
        $config->withMakefile = $io->confirm('Include Makefile?', false);
    }

    private function askPluginArchitecture(SymfonyStyle $io, PluginConfig $config): void
    {
        $io->section('Plugin Architecture');

        $config->withAdminExtension = $io->confirm('Admin Extension (Vue components)?', false);
        $config->withStorefrontExtension = $io->confirm('Storefront Extension (Twig/SCSS)?', false);
        $config->withDatabase = $io->confirm('Custom Database Table (Entity, Definition, Migration)?', false);
        $config->withScheduledTask = $io->confirm('Scheduled Task?', false);
        $config->withEventSubscriber = $io->confirm('Event Subscriber?', false);
        $config->withCliCommand = $io->confirm('CLI Command (Symfony Console)?', false);
        $config->withCustomConfig = $io->confirm('Custom Plugin Config (config.xml)?', false);
    }

    private function askScaffoldingDepth(SymfonyStyle $io, PluginConfig $config): void
    {
        $io->section('Scaffolding Depth');

        $config->fullScaffolding = $io->choice(
                'Scaffolding depth',
                [
                    'full' => 'Full (with example DTOs, Services, etc.)',
                    'structure' => 'Structure only (empty folders & base files)',
                ],
                'structure'
            ) === 'full';
    }

    private function resolveDockwareTags(SymfonyStyle $io, PluginConfig $config): void
    {
        $io->note('Fetching latest Dockware tags...');

        foreach ($config->shopwareVersions as $version) {
            $tag = $this->dockwareTagResolver->fetchLatestTag($version);

            if ($tag) {
                $config->dockwareTags[$version] = $tag;
                $io->writeln(sprintf('  - Found tag <info>%s</info> for version <info>%s</info>', $tag, $version));

                continue;
            }

            $config->dockwareTags[$version] = 'latest';
            $io->warning(sprintf('Could not find Dockware tag for version %s, using "latest"', $version));
        }
    }
}