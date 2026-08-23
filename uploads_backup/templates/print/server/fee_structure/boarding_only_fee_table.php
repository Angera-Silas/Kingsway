<?php
$tplDir = __DIR__;
$grades = is_array($grades ?? null) ? $grades : [];
require_once $tplDir . '/fee_structure_variant_helpers.php';
$sections = fsCanonicalSections($grades, false, true);
$documentTitle = ($yearLabel ?? date('Y')) . ' BOARDER FEE STRUCTURE';
$documentSubtitle = 'PRIMARY & JUNIOR SCHOOL';
include $tplDir . '/fee_structure_simple.php';
