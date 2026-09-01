<?php
declare(strict_types=1);
$parentInitialSection = $parentInitialSection ?? 'overview';
$allowedParentSections = ['overview','children','fees','attendance','academics','messages','transport','documents','pta','settings'];
if (!in_array($parentInitialSection, $allowedParentSections, true)) $parentInitialSection = 'overview';
$parentPageTitles = ['overview'=>'Dashboard','children'=>'My Children','fees'=>'Fees & Payments','attendance'=>'Attendance','academics'=>'Learning & Results','messages'=>'Messages','transport'=>'Transport','documents'=>'Documents & Reports','pta'=>'PTA & Representatives','settings'=>'Account Settings'];
$parentPageTitle = $parentPageTitles[$parentInitialSection] ?? 'Dashboard';
$appBaseOverride = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
if ($appBaseOverride === '.') $appBaseOverride = '';
require dirname(__DIR__) . '/parent_portal.php';
