<?php

namespace ShopwareScaffolding\Tests\Integration;

use PHPUnit\Framework\TestCase;
use ShopwareScaffolding\Command\CreatePluginCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class CreatePluginCommandTest extends TestCase
{
    private string $testDir;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/sws-test-' . uniqid();
        $this->filesystem = new Filesystem();
        $this->filesystem->mkdir($this->testDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->testDir);
    }

    public function testExecute(): void
    {
        $application = new Application();
        $application->addCommand(new CreatePluginCommand());

        $command = $application->find('create:plugin');
        $commandTester = new CommandTester($command);

        // Simulate interactive input
        $commandTester->setInputs([
            'My Test Plugin',      // Plugin Title
            'MyTestPlugin',        // Plugin Name
            'MyVendor',            // Vendor Name
            'MyVendor\\Test',      // Namespace
            'A test description',  // Description
            'John Doe',            // Author
            'john@example.com',    // E-Mail
            '6.6',                 // Shopware Version (choice index/value)
            'n',                   // Include PHPUnit?
            'n',                   // Include PHPStan?
            'n',                   // Include Docker config?
            'n',                   // Include TypeScript setup?
            'n',                   // Include JetBrains Run Configurations?
            'n',                   // Include Writerside documentation?
            'n',                   // Include GitHub Actions CI?
            'n',                   // Include Makefile?
            'n',                   // Admin Extension?
            'n',                   // Storefront Extension?
            'n',                   // Custom Database Table?
            'n',                   // Scheduled Task?
            'n',                   // Event Subscriber?
            'n',                   // CLI Command?
            'n',                   // Custom Plugin Config?
            'structure',           // Scaffolding depth
            'y',                   // Generate plugin now?
        ]);

        $commandTester->execute([
            '--dir' => $this->testDir,
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Plugin \'MyTestPlugin\' scaffolded successfully!', $output);
        $this->assertDirectoryExists($this->testDir . '/MyTestPlugin');
        $this->assertFileExists($this->testDir . '/MyTestPlugin/composer.json');
    }

    public function testExecuteWithAllFeatures(): void
    {
        $application = new Application();
        $application->addCommand(new CreatePluginCommand());

        $command = $application->find('create:plugin');
        $commandTester = new CommandTester($command);

        // Simulate interactive input with all options set to yes/active
        $commandTester->setInputs([
            'My Test Plugin',      // Plugin Title
            'MyTestPlugin',        // Plugin Name
            'MyVendor',            // Vendor Name
            'MyVendor\\Test',      // Namespace
            'A test description',  // Description
            'John Doe',            // Author
            'john@example.com',    // E-Mail
            '6.6',                 // Shopware Version (choice index/value)
            'y',                   // Include PHPUnit?
            'y',                   // Include PHPStan?
            'y',                   // Include Docker config?
            'y',                   // Include TypeScript setup?
            'y',                   // Include JetBrains Run Configurations?
            'y',                   // Include Writerside documentation?
            'y',                   // Include GitHub Actions CI?
            'y',                   // Include Makefile?
            'y',                   // Admin Extension?
            'y',                   // Storefront Extension?
            'y',                   // Custom Database Table?
            'y',                   // Scheduled Task?
            'y',                   // Event Subscriber?
            'y',                   // CLI Command?
            'y',                   // Custom Plugin Config?
            'structure',           // Scaffolding depth
            'y',                   // Generate plugin now?
        ]);

        $commandTester->execute([
            '--dir' => $this->testDir,
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Plugin \'MyTestPlugin\' scaffolded successfully!', $output);

        $pluginPath = $this->testDir . '/MyTestPlugin';
        $this->assertDirectoryExists($pluginPath);
        $this->assertFileExists($pluginPath . '/composer.json');
        
        // Tooling
        $this->assertFileExists($pluginPath . '/phpunit.xml');
        $this->assertFileExists($pluginPath . '/phpstan.neon');
        $this->assertFileExists($pluginPath . '/docker-compose.yml');
        $this->assertFileExists($pluginPath . '/tsconfig.json');
        $this->assertFileExists($pluginPath . '/.idea/runConfigurations/Docker_Up.xml');
        $this->assertFileExists($pluginPath . '/docs/writerside.cfg.xml');
        $this->assertFileExists($pluginPath . '/.github/workflows/ci.yml');
        $this->assertFileExists($pluginPath . '/Makefile');

        // Architecture
        $this->assertFileExists($pluginPath . '/src/Core/Content/MyTestPluginEntity/MyTestPluginEntity.php');
        $this->assertFileExists($pluginPath . '/src/Core/Content/MyTestPluginEntity/MyTestPluginCollection.php');
        $this->assertFileExists($pluginPath . '/src/Core/Content/MyTestPluginEntity/MyTestPluginDefinition.php');
        
        $migrationFiles = glob($pluginPath . '/src/Migration/Migration*.php');
        $this->assertCount(1, $migrationFiles);

        $this->assertFileExists($pluginPath . '/src/Resources/config/config.xml');
        $this->assertFileExists($pluginPath . '/src/ScheduledTask/MyTestPluginTask.php');
        $this->assertFileExists($pluginPath . '/src/ScheduledTask/MyTestPluginTaskHandler.php');
        $this->assertFileExists($pluginPath . '/src/Subscriber/MyTestPluginSubscriber.php');
        $this->assertFileExists($pluginPath . '/src/Command/MyTestPluginCommand.php');
        
        // Assert services.xml tags exist
        $servicesXml = file_get_contents($pluginPath . '/src/Resources/config/services.xml');
        $this->assertStringContainsString('my_test_plugin', $servicesXml);
        $this->assertStringContainsString('console.command', $servicesXml);
        $this->assertStringContainsString('kernel.event_subscriber', $servicesXml);
        $this->assertStringContainsString('messenger.message_handler', $servicesXml);
    }

    public function testExecuteWithLegacyShopwareVersion(): void
    {
        $application = new Application();
        $application->addCommand(new CreatePluginCommand());

        $command = $application->find('create:plugin');
        $commandTester = new CommandTester($command);

        // Simulate interactive input with Shopware 6.5 selected
        $commandTester->setInputs([
            'My Test Plugin',      // Plugin Title
            'MyTestPlugin',        // Plugin Name
            'MyVendor',            // Vendor Name
            'MyVendor\\Test',      // Namespace
            'A test description',  // Description
            'John Doe',            // Author
            'john@example.com',    // E-Mail
            '6.5',                 // Shopware Version (choice index/value)
            'n',                   // Include PHPUnit?
            'n',                   // Include PHPStan?
            'n',                   // Include Docker config?
            'n',                   // Include TypeScript setup?
            'n',                   // Include JetBrains Run Configurations?
            'n',                   // Include Writerside documentation?
            'n',                   // Include GitHub Actions CI?
            'n',                   // Include Makefile?
            'n',                   // Admin Extension?
            'n',                   // Storefront Extension?
            'n',                   // Custom Database Table?
            'y',                   // Scheduled Task?
            'n',                   // Event Subscriber?
            'n',                   // CLI Command?
            'n',                   // Custom Plugin Config?
            'structure',           // Scaffolding depth
            'y',                   // Generate plugin now?
        ]);

        $commandTester->execute([
            '--dir' => $this->testDir,
        ]);

        $pluginPath = $this->testDir . '/MyTestPlugin';
        $this->assertDirectoryExists($pluginPath);

        // Assert services.xml uses legacy scheduled_task.handler tag
        $servicesXml = file_get_contents($pluginPath . '/src/Resources/config/services.xml');
        $this->assertStringContainsString('scheduled_task.handler', $servicesXml);
        $this->assertStringNotContainsString('messenger.message_handler', $servicesXml);

        // Assert handler class extends legacy ScheduledTaskHandler
        $handlerClass = file_get_contents($pluginPath . '/src/ScheduledTask/MyTestPluginTaskHandler.php');
        $this->assertStringContainsString('extends ScheduledTaskHandler', $handlerClass);
        $this->assertStringNotContainsString('extends AbstractScheduledTaskHandler', $handlerClass);
        $this->assertStringContainsString('__construct', $handlerClass);
        $this->assertStringContainsString('getHandledMessages', $handlerClass);
    }
}
