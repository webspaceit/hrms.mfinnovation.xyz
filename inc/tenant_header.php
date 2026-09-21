<?php
// Legacy-compat wrapper: legacy tenant_*.php files still include
// inc/tenant_header.php, which now delegates to the single MVC partial.
require __DIR__ . '/../app/Views/partials/tenant_header.php';