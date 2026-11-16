<?php

declare(strict_types=1);

namespace Bogana;

use RuntimeException;

final class View
{
    public function __construct(private readonly string $templatePath)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = [], string $layout = 'layout'): string
    {
        $content = $this->renderTemplate($template, $data);

        return $this->renderTemplate($layout, [
            'content' => $content,
            'title' => $data['title'] ?? 'Bogana ERP',
        ]);
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