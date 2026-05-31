<?php

namespace ShopwareScaffolding\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ShopwareScaffolding\Config\PluginConfig;

class PluginConfigTest extends TestCase
{
    public function testGetVendorName(): void
    {
        $config = new PluginConfig();
        $config->namespace = 'MyVendor\\MyPlugin';
        
        $this->assertEquals('MyVendor', $config->getVendorName());
    }

    public function testGetComposerPackageName(): void
    {
        $config = new PluginConfig();
        $config->namespace = 'MyVendor\\MyPlugin';
        $config->pluginName = 'MyAwesomePlugin';
        
        $this->assertEquals('myvendor/my-awesome-plugin', $config->getComposerPackageName());
    }

    public function testGetPluginDirectory(): void
    {
        $config = new PluginConfig();
        $config->pluginName = 'MyAwesomePlugin';
        
        $this->assertEquals('MyAwesomePlugin', $config->getPluginDirectory());
    }
}
