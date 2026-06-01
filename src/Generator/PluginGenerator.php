<?php declare(strict_types=1);

namespace ShopwareScaffolding\Generator;

use ShopwareScaffolding\Config\PluginConfig;
use ShopwareScaffolding\Service\TemplateRenderer;

class PluginGenerator
{
    private readonly TemplateRenderer $renderer;
    private readonly string $stubsDirectory;
    private string $currentShopwareVersion = '';

    public function __construct(
        private readonly PluginConfig $config,
        private readonly string $targetDirectory
    ) {
        $this->renderer = new TemplateRenderer();
        $this->stubsDirectory = dirname(__DIR__, 2) . '/templates';
    }

    public function generate(): void
    {
        if (empty($this->config->shopwareVersions)) {
            throw new \RuntimeException('No Shopware versions selected.');
        }

        $isMultiVersion = count($this->config->shopwareVersions) > 1;

        if ($isMultiVersion) {
            $this->runGitCommand('init');
            $this->runGitCommand('checkout -b master');
            $this->runGitCommand('config user.email "scaffolder@example.com"');
            $this->runGitCommand('config user.name "Shopware Scaffolder"');
        }

        // shopwareVersions is already sorted highest first in Command
        // So the first one is for 'master'
        foreach ($this->config->shopwareVersions as $index => $version) {
            $this->currentShopwareVersion = $version;
            $branchName = ($index === 0) ? 'master' : $version;

            if ($isMultiVersion) {
                if ($index > 0) {
                    $this->runGitCommand("checkout -b {$branchName}");
                }
            }

            $this->generateBaseFiles();
            $this->generateOptionalFiles();
            $this->generateDirectoryStructure();

            if ($isMultiVersion) {
                $this->runGitCommand('add .');
                $this->runGitCommand("commit -m \"Scaffold plugin for Shopware {$version}\"");

                if ($index < count($this->config->shopwareVersions) - 1) {
                    $this->runGitCommand('checkout master');
                }
            }
        }

        if ($isMultiVersion) {
            $this->runGitCommand('checkout master');
        }
    }

    private function generateBaseFiles(): void
    {
        $this->writeStub(
            'composer.json.stub',
            'composer.json'
        );

        $this->writeStub(
            'MyPlugin.php.stub',
            "src/{$this->config->pluginName}.php"
        );

        $this->writeStub(
            'plugin.xml.stub',
            'plugin.xml'
        );

        $this->writeStub(
            'services.xml.stub',
            'src/Resources/config/services.xml'
        );
    }

    private function generateOptionalFiles(): void
    {
        if ($this->config->withPhpUnit) {
            $this->writeStub('phpunit.xml.stub', 'phpunit.xml');
            $this->ensureDirectoryExists($this->targetDirectory . '/tests');
        }

        if ($this->config->withPhpStan) {
            $this->writeStub('phpstan.neon.stub', 'phpstan.neon');
        }

        if ($this->config->withDocker) {
            $this->writeStub('docker-compose.yml.stub', 'docker-compose.yml');
        }

        if ($this->config->withGithubActions) {
            $this->writeStub('ci.yml.stub', '.github/workflows/ci.yml');
        }

        if ($this->config->withTypeScript) {
            $this->writeStub('tsconfig.json.stub', 'tsconfig.json');
            
            if ($this->config->withAdminExtension || $this->config->fullScaffolding) {
                $this->writeStub('admin-tsconfig.json.stub', 'src/Resources/app/administration/tsconfig.json');
                $this->writeStub('admin-main.ts.stub', 'src/Resources/app/administration/src/main.ts');
            }

            if ($this->config->withStorefrontExtension || $this->config->fullScaffolding) {
                $this->writeStub('storefront-main.ts.stub', 'src/Resources/app/storefront/src/main.ts');
            }
        }

        if ($this->config->withWriterside) {
            $this->writeStub('writerside-cfg.xml.stub', 'docs/writerside.cfg.xml');
            $this->writeStub('writerside-tree.tree.stub', 'docs/p.tree');
            $this->writeStub('writerside-index.md.stub', 'docs/topics/index.md');
            $this->ensureDirectoryExists($this->targetDirectory . '/docs/images');
        }

        if ($this->config->withJetBrainsRunConfigs) {
            $this->writeStub('idea-docker-up.xml.stub', '.idea/runConfigurations/Docker_Up.xml');
            if ($this->config->withPhpUnit) {
                $this->writeStub('idea-phpunit.xml.stub', '.idea/runConfigurations/PHPUnit.xml');
            }
            if ($this->config->withPhpStan) {
                $this->writeStub('idea-phpstan.xml.stub', '.idea/runConfigurations/PHPStan.xml');
            }
        }

        if ($this->config->withMakefile) {
            $this->writeStub('Makefile.stub', 'Makefile');
        }
    }

    private function generateDirectoryStructure(): void
    {
        $directories = [
            'src/Resources/config',
        ];

        if ($this->config->withCliCommand || $this->config->fullScaffolding) {
            $directories[] = 'src/Command';
        }

        if ($this->config->withAdminExtension || $this->config->fullScaffolding) {
            $directories[] = 'src/Resources/app/administration/src';
        }

        if ($this->config->withStorefrontExtension || $this->config->fullScaffolding) {
            $directories[] = 'src/Resources/app/storefront/src';
        }

        if ($this->config->withEventSubscriber || $this->config->fullScaffolding) {
            $directories[] = 'src/Subscriber';
        }

        if ($this->config->withScheduledTask || $this->config->fullScaffolding) {
            $directories[] = 'src/ScheduledTask';
        }

        if ($this->config->fullScaffolding) {
            $directories[] = 'src/Service';
            $directories[] = 'src/Core/Content';
            $directories[] = 'src/Resources/views';
            $directories[] = 'src/Resources/public';
        }

        foreach ($directories as $dir) {
            $this->ensureDirectoryExists($this->targetDirectory . '/' . $dir);
        }
    }

    private function writeStub(string $stubName, string $outputRelativePath): void
    {
        $stubPath = $this->stubsDirectory . '/' . $stubName;
        $outputPath = $this->targetDirectory . '/' . $outputRelativePath;

        $this->ensureDirectoryExists(dirname($outputPath));

        $rendered = $this->renderer->renderFile($stubPath, $this->buildVariables());

        if (file_put_contents($outputPath, $rendered) === false) {
            throw new \RuntimeException(sprintf('Could not write file: %s', $outputPath));
        }
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Directory "%s" could not be created', $directory));
        }
    }

    /**
     * @return array<string, string>
     */
    private function buildVariables(): array
    {
        $makefileExtensions = '';
        $docsDevelopment = '';

        if ($this->config->withDocker) {
            $makefileExtensions .= "\n# Docker shortcuts\n";
            $makefileExtensions .= "up:\n\tdocker-compose up -d\n";
            $makefileExtensions .= "down:\n\tdocker-compose down\n";
            $makefileExtensions .= "ssh:\n\tdocker-compose exec shopware bash\n";

            $docsDevelopment .= "## Development with Docker (Dockware)\n\n";
            $docsDevelopment .= "1. Run `docker-compose up -d` to start the environment.\n";
            $docsDevelopment .= "2. Access Shopware via `http://localhost:8000`.\n";
            $docsDevelopment .= "3. Your plugin is automatically mapped to the Dockware container.\n\n";
        }

        return [
            'pluginName'          => $this->config->pluginName,
            'pluginTitle'         => $this->config->pluginTitle,
            'namespace'           => $this->config->namespace,
            'namespaceEscaped'    => str_replace('\\', '\\\\', $this->config->namespace),
            'composerPackageName' => $this->config->getComposerPackageName(),
            'description'         => $this->config->description,
            'author'              => $this->config->author,
            'email'               => $this->config->email,
            'minShopwareVersion'  => $this->currentShopwareVersion,
            'dockwareTag'         => $this->config->dockwareTags[$this->currentShopwareVersion] ?? 'latest',
            'year'                => (string) date('Y'),
            'makefileExtensions'  => rtrim($makefileExtensions),
            'docsDevelopment'     => rtrim($docsDevelopment),
        ];
    }

    private function runGitCommand(string $command): void
    {
        $process = proc_open("git {$command}", [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, $this->targetDirectory);

        if (is_resource($process)) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $returnCode = proc_close($process);

            if ($returnCode !== 0) {
                throw new \RuntimeException(sprintf(
                    "Git command 'git %s' failed (exit %d) in %s.\nError: %s",
                    $command,
                    $returnCode,
                    $this->targetDirectory,
                    $stderr ?: $stdout
                ));
            }
        }
    }
}
