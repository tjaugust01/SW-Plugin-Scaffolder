<?php

namespace ShopwareScaffolding\Service\Util;

use RuntimeException;

class ShopwareVersionFetcher
{
    /**
     * @return array<string> Returns an array of major Shopware versions.
     */
    public static function fetchMajorVersion(): array
    {
        $url = 'https://releases.shopware.com/changelog/index.json';
        $jsonString = file_get_contents($url);
        if (!$jsonString) {
            throw new RuntimeException('Failed to fetch Shopware versions.');
        }
        $data = json_decode($jsonString, true);
        $majorVersions = [];

        foreach ($data as $version) {
            if (preg_match('/^(\d+\.\d+)/', $version, $matches)) {
                $majorString= $matches[1];

                if (!in_array($majorString, $majorVersions)) {
                    $majorVersions[] = $majorString;
                }
            }
        }
        return $majorVersions;
    }
}