<?php

namespace ShopwareScaffolding\Config;

enum GitBranchNamingConvention: string
{
    case Main = 'main';
    case Latest = 'latest';
    case Master = 'master';
    case VersionOnly = 'version_only';

    public function label(): string
    {
        return match ($this) {
            self::Main => 'Newest version is called main (e.g. main, 6.6, 6.5)',
            self::Latest => 'Newest version is called latest (e.g. latest, 6.6, 6.5)',
            self::Master => 'Newest version is called master (e.g. master, 6.6, 6.5)',
            self::VersionOnly => 'No special naming (e.g. 6.7, 6.6, 6.5)',
        };
    }

    public function branchNameForNewestVersion(string $version): string
    {
        return match ($this) {
            self::Main => 'main',
            self::Latest => 'latest',
            self::Master => 'master',
            self::VersionOnly => $version,
        };
    }
}