<?php
/**
 * Legacy alias for the internal Products Catalog.
 * Previously routed staff to the storefront file; now serves the dedicated
 * internal catalogue page so old bookmarks (?route=uniform_catalog) keep
 * working. The canonical route is internal_products_catalog.
 */
require __DIR__ . '/internal_products_catalog.php';