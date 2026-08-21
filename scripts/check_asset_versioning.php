<?php
/**
 * check_asset_versioning.php
 *
 * Enforces cache-busting for locally served JavaScript and CSS assets.
 *
 * Scans every PHP template in the render surface (pages, components, layouts,
 * public layouts, and top-level/public page files) for literal <script src> /
 * <link href> tags that point at a LOCAL .js/.css file and are missing a query
 * string version parameter. Exits non-zero when any are found so this can run
 * as a pre-deploy gate or in CI.
 *
 * Emitted-by-PHP tags are intentional and correct: pages that build the tag via
 * asset_script()/asset_version() (which always append ?v=<filemtime>) emit no
 * literal tag and therefore pass this check by design.
 */

$root = dirname(__DIR__);

$dirs = ['pages', 'components', 'layouts', 'public/layout'];
$targets = [];
foreach ($dirs as $dir) {
    $path = $root . '/' . $dir;
    if (!is_dir($path)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
    foreach ($it as $f) {
        if ($f->isFile() && strtolower($f->getExtension()) === 'php') {
            $targets[] = $f->getPathname();
        }
    }
}
// Top-level and public page entry points.
foreach (array_merge(glob($root . '/*.php') ?: [], glob($root . '/public/*.php') ?: []) as $f) {
    $targets[] = $f;
}

$targets = array_values(array_unique($targets));

$violations = [];

foreach ($targets as $file) {
    $content = file_get_contents($file);
    if ($content === false) {
        continue;
    }
    // Whole-file matching so multi-line <script>/<link> tags are caught too
    // ([^>] spans newlines; no /s modifier needed).
    if (preg_match_all('/<script\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $content, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        foreach ($m as $tag) {
            if (isUnversionedLocalAsset($tag[1][0], '.js')) {
                $violations[] = sprintf('%s:%d  <script src="%s">', rel($root, $file), lineAt($content, $tag[0][1]), $tag[1][0]);
            }
        }
    }
    if (preg_match_all('/<link\b[^>]*\bhref\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $content, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        foreach ($m as $tag) {
            if (stripos($tag[0][0], 'stylesheet') !== false && isUnversionedLocalAsset($tag[1][0], '.css')) {
                $violations[] = sprintf('%s:%d  <link href="%s">', rel($root, $file), lineAt($content, $tag[0][1]), $tag[1][0]);
            }
        }
    }
}

if ($violations) {
    fwrite(STDERR, sprintf(
        "Asset versioning check failed: %d unversioned local asset(s).\n\n",
        count($violations)
    ));
    foreach ($violations as $v) {
        fwrite(STDERR, "  - " . $v . "\n");
    }
    fwrite(STDERR, "\nFix: route local scripts through asset_script(\$appBase, 'path/to/file.js') "
        . "or append ?v=<?= asset_version('path/to/file.js') ?>.\n");
    exit(1);
}

echo "Asset versioning check passed: all local JS/CSS references are cache-busted.\n";
exit(0);

function isUnversionedLocalAsset(string $url, string $ext): bool
{
    $url = trim($url);
    // Skip external and data/blob URLs.
    if (preg_match('#^(https?:)?//#i', $url) || preg_match('#^(data:|blob:)#i', $url)) {
        return false;
    }
    // Only relevant when it names the extension we're checking.
    if (stripos($url, $ext) === false) {
        return false;
    }
    // A query-string version param is the accepted cache-busting mechanism.
    return strpos($url, '?v=') === false;
}

function rel(string $root, string $file): string
{
    return str_replace('\\', '/', substr($file, strlen($root) + 1));
}

function lineAt(string $content, int $offset): int
{
    return substr_count(substr($content, 0, $offset), "\n") + 1;
}
