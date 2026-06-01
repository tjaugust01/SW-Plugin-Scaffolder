<?php

namespace ShopwareScaffolding\Service\Util;

class DockwareTagResolver
{
    private const TAGS_URL = 'https://registry.hub.docker.com/v2/repositories/dockware/dev/tags?page_size=100';

    public function fetchLatestTag(string $majorMinor): ?string
    {
        $json = @file_get_contents(self::TAGS_URL);

        if (!$json) {
            return null;
        }

        $data = json_decode($json, true);

        if (!isset($data['results']) || !is_array($data['results'])) {
            return null;
        }

        $tags = array_column($data['results'], 'name');
        $pattern = '/^' . preg_quote($majorMinor, '/') . '\.[0-9]+(\.[0-9]+)?$/';

        $filteredTags = array_filter($tags, static function ($tag) use ($pattern): bool {
            return is_string($tag) && preg_match($pattern, $tag) === 1;
        });

        if (empty($filteredTags)) {
            return null;
        }

        usort($filteredTags, 'version_compare');

        return end($filteredTags) ?: null;
    }
}