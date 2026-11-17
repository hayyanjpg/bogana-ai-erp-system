<?php

declare(strict_types=1);

namespace Bogana;

use RuntimeException;

final class View
{
    /**
     * @param array<string, mixed> $shared Data yang tersedia di setiap template.
     */
    public function __construct(
        private readonly string $templatePath,
        private readonly array $shared = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = [], string $layout = 'layout'): string
    {
        $content = $this->renderTemplate($template, array_merge($this->shared, $data));

        return $this->renderTemplate($layout, array_merge($this->shared, [
            'content' => $content,
            'title' => $data['title'] ?? 'Bogana ERP',
        ]));
    }

    /**
     * Render satu template tanpa layout (mis. halaman login).
     *
     * @param array<string, mixed> $data
     */
    public function renderRaw(string $template, array $data = []): string
    {
        return $this->renderTemplate($template, array_merge($this->shared, $data));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderTemplate(string $template, array $data): string
    {
        $file = $this->templatePath . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("Template tidak ditemukan: {$template}");
        }

        extract($data);
        ob_start();
        require $file;

        return (string) ob_get_clean();
    }
}