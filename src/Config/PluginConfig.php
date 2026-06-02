<?php

namespace ShopwareScaffolding\Config;

class PluginConfig
{
    // Metadaten
    public string $pluginTitle = '';
    public string $pluginName = '';       // z.B. "MyAwesomePlugin" → Ordnername, Klasse
    public string $namespace = '';        // z.B. "MyVendor\MyAwesomePlugin"
    public string $description = '';
    public string $author = '';
    public string $email = '';
    public array $shopwareVersions = [];
    public array $dockwareTags = []; // Mapping: '6.6' => '6.6.1.0'
    public string $path = '.';

    // Tooling
    public bool $withPhpUnit = false;
    public bool $withPhpStan = false;
    public bool $withDocker = false;
    public bool $withTypeScript = false;
    public bool $withJetBrainsRunConfigs = false;
    public bool $withWriterside = false;
    public bool $withGithubActions = false;
    public bool $withMakefile = false;
    public bool $withGitRepository = false;
    public GitBranchNamingConvention $gitBranchNamingConvention = GitBranchNamingConvention::VersionOnly;

    // Architektur
    public bool $withAdminExtension = false;
    public bool $withStorefrontExtension = false;
    public bool $withDatabase = false;
    public bool $withScheduledTask = false;
    public bool $withEventSubscriber = false;
    public bool $withCliCommand = false;
    public bool $withCustomConfig = false;

    // Scaffolding-Tiefe
    public bool $fullScaffolding = false; // true = mit Example DTOs, false = nur Ordnerstruktur

    // Abgeleitete Werte
    public function getVendorName(): string
    {
        return explode('\\', $this->namespace)[0] ?? '';
    }

    public function getComposerPackageName(): string
    {
        $vendor = strtolower($this->getVendorName());
        $plugin = strtolower(preg_replace('/[A-Z]/', '-$0', lcfirst($this->pluginName)));
        return "{$vendor}/{$plugin}";
    }

    public function getPluginDirectory(): string
    {
        return $this->pluginName;
    }
}