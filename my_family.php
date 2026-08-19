<?php
/* Staff private family workspace. It reuses the complete parent interface but
   authenticates every request with the staff JWT through /api/family/*. */
$familyStaffMode = true;
require __DIR__ . '/parent_portal.php';
