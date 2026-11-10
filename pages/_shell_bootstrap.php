<?php
/**
 * PageShell role-template bootstrap.
 *
 * Role templates (pages/{module}/admin_*.php etc.) are fetched as raw HTML
 * by PageShell.loadRoleTemplate() via XHR, so they bypass home.php and never
 * load the Composer autoloader. This prepended shim loads it once, centrally,
 * so templates can call app_base_path(), asset_version(), and asset_script()
 * exactly as they do inside the app shell — no per-file boilerplate.
 *
 * Everything below is guarded: when the template is included through
 * home.php → app_layout.php the autoloader already exists and none of this runs.
 */

if (!defined('KINGSWAY_AUTOLOAD_READY')) {
    // This shim lives at pages/_shell_bootstrap.php, so the repo root is one
    // level up. Resolved from this file's location so it works under any host
    // layout (docroot or a subdirectory alias).
    $__kingswayRoot = dirname(__DIR__);

    if (is_file($__kingswayRoot . '/vendor/autoload.php')) {
        require_once $__kingswayRoot . '/vendor/autoload.php';
        define('KINGSWAY_AUTOLOAD_READY', 1);
    }
}
