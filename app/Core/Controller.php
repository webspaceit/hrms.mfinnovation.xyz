<?php
// ============================================================
// Controller base class - every App controller extends this.
// Responsibilities: auth gating, business/data work, then
// rendering a view template with the prepared data.
// ============================================================

namespace App\Core;

abstract class Controller {

    /**
     * Render a view template from app/Views/{template}.php with $data.
     * $pageTitle and $extraJs defaults keep the shared partials safe.
     */
    protected function view(string $template, array $data = []): void {
        $viewFile = __DIR__ . '/../Views/' . $template . '.php';
        if (!is_file($viewFile)) {
            http_response_code(500);
            echo 'View not found: ' . e($template);
            return;
        }
        $data += [
            'pageTitle' => t('app_name'),
            'extraJs'   => '',
        ];
        extract($data, EXTR_SKIP);
        require $viewFile;
    }
}