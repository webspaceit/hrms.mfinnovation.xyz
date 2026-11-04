<?php
// ============================================================
// Settings - site-wide preferences. Admin role only.
// Holds the default language (see Lang::defaultLang()).
// ============================================================

namespace App\Controllers;

use App\Core\Controller;

class SettingsController extends Controller {

    public function index(): void {
        \Auth::requireAdmin();

        $this->view('settings/index', [
            // Read through Lang so an unset or invalid row is corrected here
            // too, and the select always shows the language actually in use.
            'defaultLanguage' => \Lang::defaultLang(),
        ]);
    }
}
