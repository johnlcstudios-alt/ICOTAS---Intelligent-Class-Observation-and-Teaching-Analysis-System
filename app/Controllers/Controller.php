<?php

namespace App\Controllers;

use App\Core\Flash;
use App\Core\Validator;
use App\Core\View;
use App\Services\AuditService;

/**
 * Shared controller conveniences. Business rules live in services.
 */
abstract class Controller
{
    protected function render(string $view, array $data = [], string $layout = 'layouts/app'): void
    {
        View::render($view, $data, $layout);
    }

    /**
     * Validate and redirect back with errors/old input on failure.
     */
    protected function validate(array $data, array $rules, array $labels, string $backPath): array
    {
        $validator = Validator::make($data, $rules, $labels);
        if ($validator->fails()) {
            Flash::withErrors($validator->errors(), $data);
            Flash::error('Please correct the highlighted fields.');
            redirect($backPath);
        }
        return $validator->validated();
    }

    protected function audit(string $action, string $module, ?string $reference = null, ?string $details = null): void
    {
        (new AuditService())->deanAction($action, $module, $reference, $details);
    }

    protected function isAjax(): bool
    {
        return \App\Core\Request::wantsJson();
    }

    protected function pageNumber(): int
    {
        return max(1, (int) \App\Core\Request::query('page', 1));
    }

    /**
     * Normalise a filter array, dropping empty strings and unexpected keys.
     */
    protected function filters(array $keys): array
    {
        $filters = [];
        foreach ($keys as $key) {
            $value = \App\Core\Request::query($key, '');
            if (is_string($value)) {
                $value = trim($value);
            }
            $filters[$key] = $value;
        }
        return $filters;
    }
}
