<?php

namespace App\Core;

use Throwable;

/**
 * Plain PHP view rendering with a shared layout.
 */
final class View
{
    public static function render(string $view, array $data = [], string $layout = 'layouts/app'): void
    {
        $viewPath = BASE_PATH . '/app/Views/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($viewPath)) {
            throw new \RuntimeException('View not found: ' . $view);
        }

        $authUser = Auth::user();
        $flashMessages = Flash::messages();
        $validationErrors = Flash::errors();
        $notifications = [];
        $unreadNotifications = 0;

        if ($authUser !== null) {
            try {
                $notifications = \App\Models\Notification::forUser((int) $authUser['user_id'], 6);
                $unreadNotifications = \App\Models\Notification::unreadCount((int) $authUser['user_id']);
            } catch (Throwable) {
                $notifications = [];
                $unreadNotifications = 0;
            }
        }

        $shared = [
            'authUser'           => $authUser,
            'flashMessages'      => $flashMessages,
            'validationErrors'   => $validationErrors,
            'notifications'      => $notifications,
            'unreadNotifications'=> $unreadNotifications,
            'pageTitle'          => $data['pageTitle'] ?? config('app.name'),
            'pageSubtitle'       => $data['pageSubtitle'] ?? '',
            'activeNav'          => $data['activeNav'] ?? '',
            'bodyClass'          => $data['bodyClass'] ?? '',
            'cspNonce'           => csp_nonce(),
        ];

        $data = array_merge($shared, $data);
        extract($data, EXTR_SKIP);

        ob_start();
        require $viewPath;
        $content = (string) ob_get_clean();

        if ($layout === '' || $layout === 'none') {
            echo $content;
            return;
        }

        $layoutPath = BASE_PATH . '/app/Views/' . str_replace('.', '/', $layout) . '.php';
        if (!is_file($layoutPath)) {
            throw new \RuntimeException('Layout not found: ' . $layout);
        }
        require $layoutPath;
    }

    public static function partial(string $view, array $data = []): void
    {
        $path = BASE_PATH . '/app/Views/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($path)) {
            throw new \RuntimeException('Partial not found: ' . $view);
        }
        extract($data, EXTR_SKIP);
        require $path;
    }
}
