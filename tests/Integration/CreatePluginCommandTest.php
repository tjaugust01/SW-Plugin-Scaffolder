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
}
