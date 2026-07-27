<?php declare(strict_types=1);

namespace ShopwareScaffolding\Service;

use RuntimeException;

class TemplateRenderer
{
    /**
     * @param array<string, string> $variables
     */
    public function render(string $stubContent, array $variables): string
    {
        $search = array_map(
            static fn(string $key): string => '{{' . $key . '}}',
            array_keys($variables)
        );

        return str_replace($search, array_values($variables), $stubContent);
    }

    public function renderFile(string $stubPath, array $variables): string
    {
        if (!file_exists($stubPath)) {
            throw new RuntimeException(sprintf('Stub file not found: %s', $stubPath));
        }

        return $this->render(file_get_contents($stubPath), $variables);
    }
}