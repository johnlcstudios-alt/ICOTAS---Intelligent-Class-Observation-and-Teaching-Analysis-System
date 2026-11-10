<?php

namespace App\Core;

final class View
{
    public static function render(string $view, array $pageData = [], ?string $layout = 'layouts/app'): void
    {
        $viewFile = BASE_PATH . '/app/Views/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($viewFile)) {
            throw new \RuntimeException('View not found: ' . $view);
        }

        // Only the page variables are extracted, never a local variable named
        // $data. A view may legitimately receive a variable called "data".
        extract($pageData, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();

        if ($layout === null) {
            echo $content;
            return;
        }

        $layoutFile = BASE_PATH . '/app/Views/' . str_replace('.', '/', $layout) . '.php';
        if (!is_file($layoutFile)) {
            throw new \RuntimeException('Layout not found: ' . $layout);
        }
        require $layoutFile;
    }

    public static function partial(string $view, array $partialData = []): void
    {
        $viewFile = BASE_PATH . '/app/Views/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($viewFile)) {
            throw new \RuntimeException('Partial not found: ' . $view);
        }
        extract($partialData, EXTR_SKIP);
        require $viewFile;
    }
}
