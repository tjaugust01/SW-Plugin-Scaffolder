<?php declare(strict_types=1);

namespace ShopwareScaffolding\Generator;

use ShopwareScaffolding\Config\PluginConfig;
use ShopwareScaffolding\Template\TemplateRenderer;

class PluginGenerator
{
    private readonly TemplateRenderer $renderer;
    private readonly string $stubsDirectory;
    private string $currentShopwareVersion = '';
    private string $migrationTimestamp = '';

    public function __construct(
        private readonly PluginConfig $config,
        private readonly string $targetDirectory
    ) {
        $this->renderer = new TemplateRenderer();
        $this->stubsDirectory = dirname(__DIR__, 2) . '/templates';
        $this->migrationTimestamp = (string) time();
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

        $this->writeStub(
            'CHANGELOG.md.stub',
            'CHANGELOG.md'
        );

        $this->writeStub(
            'CHANGELOG_de-DE.md.stub',
            'CHANGELOG_de-DE.md'
        );

        $this->writePluginIcon();
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

        if ($this->config->withPhpCsFixer) {
            $this->writeStub('php-cs-fixer.dist.php.stub', '.php-cs-fixer.dist.php');
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

        if ($this->config->withDatabase || $this->config->fullScaffolding) {
            $entityDir = "src/Core/Content/{$this->config->pluginName}Entity";
            $this->writeStub('Entity.php.stub', "{$entityDir}/{$this->config->pluginName}Entity.php");
            $this->writeStub('EntityCollection.php.stub', "{$entityDir}/{$this->config->pluginName}Collection.php");
            $this->writeStub('EntityDefinition.php.stub', "{$entityDir}/{$this->config->pluginName}Definition.php");
            $this->writeStub('Migration.php.stub', "src/Migration/Migration{$this->migrationTimestamp}.php");
        }

        if ($this->config->withCustomConfig || $this->config->fullScaffolding) {
            $this->writeStub('config.xml.stub', 'src/Resources/config/config.xml');
        }

        if ($this->config->withScheduledTask || $this->config->fullScaffolding) {
            $taskDir = 'src/ScheduledTask';
            $this->writeStub('ScheduledTask.php.stub', "{$taskDir}/{$this->config->pluginName}Task.php");
            $this->writeStub('ScheduledTaskHandler.php.stub', "{$taskDir}/{$this->config->pluginName}TaskHandler.php");
        }

        if ($this->config->withEventSubscriber || $this->config->fullScaffolding) {
            $this->writeStub('Subscriber.php.stub', "src/Subscriber/{$this->config->pluginName}Subscriber.php");
        }

        if ($this->config->withCliCommand || $this->config->fullScaffolding) {
            $this->writeStub('Command.php.stub', "src/Command/{$this->config->pluginName}Command.php");
        }

        if ($this->config->fullScaffolding) {
            $this->writeStub('ExampleService.php.stub', "src/Service/ExampleService.php");
            $this->writeStub('ExampleDto.php.stub', "src/Service/Dto/ExampleDto.php");
            $this->writeStub('ExampleController.php.stub', "src/Storefront/Controller/ExampleController.php");
            $this->writeStub('routes.xml.stub', "src/Resources/config/routes.xml");
            $this->writeStub('example.html.twig.stub', "src/Resources/views/storefront/page/example.html.twig");
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

        if ($this->config->withDatabase || $this->config->fullScaffolding) {
            $directories[] = 'src/Core/Content';
            $directories[] = 'src/Migration';
        }

        if ($this->config->fullScaffolding) {
            $directories[] = 'src/Service';
            $directories[] = 'src/Service/Dto';
            $directories[] = 'src/Storefront/Controller';
            $directories[] = 'src/Core/Content';
            $directories[] = 'src/Resources/views/storefront/page';
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
        $requireDevPackages = [];
        if ($this->config->withPhpUnit) {
            $requireDevPackages[] = '"phpunit/phpunit": "^10.0"';
            $requireDevPackages[] = '"symfony/phpunit-bridge": "^6.4"';
        }
        if ($this->config->withPhpStan) {
            $requireDevPackages[] = '"phpstan/phpstan": "^1.10"';
        }
        if ($this->config->withPhpCsFixer) {
            $requireDevPackages[] = '"friendsofphp/php-cs-fixer": "^3.40"';
        }

        if (empty($requireDevPackages)) {
            $requireDev = '';
        } else {
            $requireDev = "\n    \"require-dev\": {\n        " . implode(",\n        ", $requireDevPackages) . "\n    },";
        }

        $makefileExtensions = '';
        $docsDevelopment = '';

        if ($this->config->withDocker) {
            $makefileExtensions .= "\n# Docker shortcuts\n";
            $makefileExtensions .= "up:\n\tdocker-compose up -d\n";
            $makefileExtensions .= "down:\n\tdocker-compose down\n";
            $makefileExtensions .= "ssh:\n\tdocker-compose exec shopware bash\n";
            $makefileExtensions .= "cache-clear:\n\tdocker-compose exec shopware bin/console cache:clear\n";
            $makefileExtensions .= "watch-admin:\n\tdocker-compose exec shopware ./bin/watch-administration.sh\n";
            $makefileExtensions .= "watch-storefront:\n\tdocker-compose exec shopware ./bin/watch-storefront.sh\n";
            $makefileExtensions .= "build-admin:\n\tdocker-compose exec shopware ./bin/build-administration.sh\n";
            $makefileExtensions .= "build-storefront:\n\tdocker-compose exec shopware ./bin/build-storefront.sh\n";
            $makefileExtensions .= "plugin-install:\n\tdocker-compose exec shopware bin/console plugin:refresh\n";
            $makefileExtensions .= "\tdocker-compose exec shopware bin/console plugin:install --activate --clear-cache " . $this->config->pluginName . "\n";
            $makefileExtensions .= "plugin-reinstall:\n\tdocker-compose exec shopware bin/console plugin:refresh\n";
            $makefileExtensions .= "\tdocker-compose exec shopware bin/console plugin:reinstall --activate --clear-cache " . $this->config->pluginName . "\n";

            if ($this->config->withPhpUnit) {
                $makefileExtensions .= "docker-test:\n\tdocker-compose exec shopware bash -c \"cd /var/www/html/custom/plugins/" . $this->config->pluginName . " && php vendor/bin/phpunit\"\n";
            }
            if ($this->config->withPhpStan) {
                $makefileExtensions .= "docker-stan:\n\tdocker-compose exec shopware bash -c \"cd /var/www/html/custom/plugins/" . $this->config->pluginName . " && php vendor/bin/phpstan analyse\"\n";
            }
            if ($this->config->withPhpCsFixer) {
                $makefileExtensions .= "docker-fix:\n\tdocker-compose exec shopware bash -c \"cd /var/www/html/custom/plugins/" . $this->config->pluginName . " && php vendor/bin/php-cs-fixer fix src\"\n";
            }

            $docsDevelopment .= "## Development with Docker (Dockware)\n\n";
            $docsDevelopment .= "1. Run `docker-compose up -d` to start the environment.\n";
            $docsDevelopment .= "2. Access Shopware via `http://localhost:8000`.\n";
            $docsDevelopment .= "3. Your plugin is automatically mapped to the Dockware container.\n\n";
        }

        $isLegacyScheduledTask = version_compare($this->currentShopwareVersion, '6.6', '<');

        $services = [];
        if ($this->config->withDatabase || $this->config->fullScaffolding) {
            $services[] = '        <service id="' . $this->config->namespace . '\\Core\\Content\\' . $this->config->pluginName . 'Entity\\' . $this->config->pluginName . 'Definition">';
            $services[] = '            <tag name="shopware.entity.definition" entity="' . $this->buildEntityNameSnakeCase() . '" />';
            $services[] = '        </service>';
        }
        if ($this->config->withScheduledTask || $this->config->fullScaffolding) {
            $services[] = '        <service id="' . $this->config->namespace . '\\ScheduledTask\\' . $this->config->pluginName . 'Task">';
            $services[] = '            <tag name="shopware.scheduled.task" />';
            $services[] = '        </service>';
            $services[] = '        <service id="' . $this->config->namespace . '\\ScheduledTask\\' . $this->config->pluginName . 'TaskHandler">';
            $services[] = '            <argument type="service" id="scheduled_task.repository" />';
            if ($isLegacyScheduledTask) {
                $services[] = '            <tag name="scheduled_task.handler" />';
            } else {
                $services[] = '            <tag name="messenger.message_handler" />';
            }
            $services[] = '        </service>';
        }
        if ($this->config->withEventSubscriber || $this->config->fullScaffolding) {
            $services[] = '        <service id="' . $this->config->namespace . '\\Subscriber\\' . $this->config->pluginName . 'Subscriber">';
            $services[] = '            <tag name="kernel.event_subscriber" />';
            $services[] = '        </service>';
        }
        if ($this->config->withCliCommand || $this->config->fullScaffolding) {
            $services[] = '        <service id="' . $this->config->namespace . '\\Command\\' . $this->config->pluginName . 'Command">';
            $services[] = '            <tag name="console.command" />';
            $services[] = '        </service>';
        }
        if ($this->config->fullScaffolding) {
            $services[] = '        <service id="' . $this->config->namespace . '\\Service\\ExampleService" />';
            $services[] = '        <service id="' . $this->config->namespace . '\\Storefront\\Controller\\ExampleController" public="true">';
            $services[] = '            <argument type="service" id="' . $this->config->namespace . '\\Service\\ExampleService" />';
            $services[] = '            <call method="setContainer">';
            $services[] = '                <argument type="service" id="service_container" />';
            $services[] = '            </call>';
            $services[] = '        </service>';
        }

        if ($isLegacyScheduledTask) {
            $taskHandlerImports = "use Shopware\\Core\\Framework\\DataAbstractionLayer\\EntityRepository;\nuse Shopware\\Core\\Framework\\MessageQueue\\ScheduledTask\\ScheduledTaskHandler;";
            
            $taskHandlerClassDefinition = "class " . $this->config->pluginName . "TaskHandler extends ScheduledTaskHandler\n{\n" .
                "    public function __construct(EntityRepository \$scheduledTaskRepository)\n" .
                "    {\n" .
                "        parent::__construct(\$scheduledTaskRepository);\n" .
                "    }\n\n" .
                "    public static function getHandledMessages(): iterable\n" .
                "    {\n" .
                "        return [" . $this->config->pluginName . "Task::class];\n" .
                "    }\n\n" .
                "    public function run(): void\n" .
                "    {\n" .
                "        // Do the scheduled task work here\n" .
                "    }\n" .
                "}";
        } else {
            $taskHandlerImports = "use Shopware\\Core\\Framework\\MessageQueue\\ScheduledTask\\AbstractScheduledTaskHandler;\nuse Symfony\\Component\\Messenger\\Attribute\\AsMessageHandler;";
            
            $taskHandlerClassDefinition = "#[AsMessageHandler(handles: " . $this->config->pluginName . "Task::class)]\n" .
                "class " . $this->config->pluginName . "TaskHandler extends AbstractScheduledTaskHandler\n{\n" .
                "    public function run(): void\n" .
                "    {\n" .
                "        // Do the scheduled task work here\n" .
                "    }\n" .
                "}";
        }

        return [
            'pluginName'                 => $this->config->pluginName,
            'pluginTitle'                => $this->config->pluginTitle,
            'namespace'                  => $this->config->namespace,
            'namespaceEscaped'           => str_replace('\\', '\\\\', $this->config->namespace),
            'composerPackageName'        => $this->config->getComposerPackageName(),
            'description'                => $this->config->description,
            'author'                     => $this->config->author,
            'email'                      => $this->config->email,
            'minShopwareVersion'         => $this->currentShopwareVersion,
            'dockwareTag'                => $this->config->dockwareTags[$this->currentShopwareVersion] ?? 'latest',
            'year'                       => (string) date('Y'),
            'makefileExtensions'         => rtrim($makefileExtensions),
            'docsDevelopment'            => rtrim($docsDevelopment),
            'services'                   => implode("\n", $services),
            'entityNameSnakeCase'        => $this->buildEntityNameSnakeCase(),
            'cliCommandName'             => $this->buildCliCommandName(),
            'taskName'                   => $this->buildTaskName(),
            'migrationTimestamp'         => $this->migrationTimestamp,
            'taskHandlerImports'         => $taskHandlerImports,
            'taskHandlerClassDefinition' => $taskHandlerClassDefinition,
            'routeImport'                => $isLegacyScheduledTask ? 'use Symfony\\Component\\Routing\\Annotation\\Route;' : 'use Symfony\\Component\\Routing\\Attribute\\Route;',
            'routeType'                  => $isLegacyScheduledTask ? 'annotation' : 'attribute',
            'pluginNameLower'            => strtolower($this->config->pluginName),
            'requireDev'                 => $requireDev,
        ];
    }

    private function buildEntityNameSnakeCase(): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $this->config->pluginName));
    }

    private function buildCliCommandName(): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $this->config->pluginName)) . ':example';
    }

    private function buildTaskName(): string
    {
        $vendor = strtolower($this->config->getVendorName());
        $plugin = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $this->config->pluginName));
        return "{$vendor}.{$plugin}.example_task";
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

    private function writePluginIcon(): void
    {
        $iconBase64 = 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3gYEBho5U586YwAAAB1pVFh0Q29tbWVudAAAAAAAQ3JlYXRlZCB3aXRoIEdJTVBkLm4EAAAAE0lEQVQ4y2NgGAWjYBSMglEwCgYRAAOcAAHNnL2IAAAAAElFTkSuQmCC';
        $outputPath = $this->targetDirectory . '/src/Resources/config/plugin.png';

        $this->ensureDirectoryExists(dirname($outputPath));

        if (file_put_contents($outputPath, base64_decode($iconBase64)) === false) {
            throw new \RuntimeException(sprintf('Could not write plugin icon: %s', $outputPath));
        }
    }
}
