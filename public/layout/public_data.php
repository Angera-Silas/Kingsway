<?php
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdn.datatables.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.datatables.net https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data: blob: https://placehold.co https://images.unsplash.com; connect-src 'self' http://localhost:* ws://localhost:*; frame-ancestors 'none'; form-action 'self'");
}

// Public data helper functions (kw_*) moved to config/public_helpers.php, which is
// loaded eagerly via Composer "files" autoload. This file keeps only the
// page-level side effects (security headers) plus autoloader registration.
require_once __DIR__ . '/../../vendor/autoload.php';
