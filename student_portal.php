<?php
/**
 * ID Card Verification - Role-Based Learner Check
 *
 * Landing page for the QR code on a learner's school ID card. This is NOT a
 * student-facing portal (learners are under 15 and do not use the system) and
 * it is NOT the parents' portal. It serves adult verifiers:
 * - Public (no login, e.g. a security guard or parent scanning): name + class only
 * - Drivers (role 23) / transport scope: transport subscription + ride details,
 *   so the bus crew can allow or deny boarding and see pick-up/drop-off points
 * - Security Staff (role 33): authorization details (name, class, card status)
 * - Teachers (roles 7, 8): academic details
 * - Accountant (role 10): financial + academic details
 * - Admin/Director/Headteacher/Deputy (roles 2,3,4,5,6,63): full details
 * - Department-registered scanning devices pin one department via ?scope=,
 *   e.g. transport, sports, medical, security, academic or financial
 */

// Register the Composer autoloader (Config, Database and all services resolve
// through it rather than manual require_once of class files).
require_once __DIR__ . '/vendor/autoload.php';

// Load Config for BASE_URL
\App\Config\Config::init();

// Dual-auth pattern: session for initial page load, JWT via AuthMiddleware for API-backed requests.
// - $_SESSION['user'] is set by the login form (session-based auth for browser page loads).
// - $_SERVER['auth_user'] is set by AuthMiddleware when a valid JWT Bearer token is present.
// - For unauthenticated visitors (e.g. QR code scanning), the page renders a limited public view.
session_start();

if (!isset($_SESSION['user']) && !isset($_SERVER['auth_user'])) {
    // For QR code scanning, we allow public access but with limited info
    // For now, we'll show basic info to anyone, but sensitive info requires auth
    $isAuthenticated = false;
} else {
    $isAuthenticated = true;
    $currentUser = $_SESSION['user'] ?? $_SERVER['auth_user'];
}

// Get student ID from URL
$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

if (!$studentId) {
    header('HTTP/1.0 404 Not Found');
    echo 'Student not found';
    exit;
}

// Get database connection
$db = \App\Database\Database::getInstance()->getConnection();

// Get student details (identity from persons; current class via enrollment chain)
$stmt = $db->prepare("
    SELECT
        s.id,
        p.first_name,
        p.last_name,
        p.gender,
        p.photo_url,
        p.dob AS date_of_birth,
        s.admission_no,
        s.status,
        s.admission_date,
        c.name AS class_name,
        c.grade_level,
        st.name AS stream_name,
        YEAR(s.admission_date) AS year_joined,
        (YEAR(s.admission_date) + IFNULL(CAST(REGEXP_REPLACE(c.grade_level, '[^0-9]', '') AS UNSIGNED), 0)) AS expected_graduation_year
    FROM students s
    JOIN persons p ON p.id = s.person_id
    LEFT JOIN (
        SELECT sae.student_id, aycs.academic_year_class_id, aycs.stream_id,
               ROW_NUMBER() OVER (PARTITION BY sae.student_id ORDER BY ay.start_date DESC) AS rn
        FROM student_academic_enrollments sae
        JOIN academic_years ay ON ay.id = sae.academic_year_id
        JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
        WHERE sae.enrollment_status = 'active'
    ) cur ON cur.student_id = s.id AND cur.rn = 1
    LEFT JOIN academic_year_classes ayc ON ayc.id = cur.academic_year_class_id
    LEFT JOIN classes c ON c.id = ayc.class_id
    LEFT JOIN streams st ON st.id = cur.stream_id
    WHERE s.id = ?
");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    header('HTTP/1.0 404 Not Found');
    echo 'Student not found';
    exit;
}

// Get current user and role
$currentUser = $isAuthenticated ? ($currentUser ?? null) : null;
$userRole = $currentUser['role'] ?? 'guest';
$userDepartment = $currentUser['department'] ?? '';

// For public access (QR scanning), show only basic info
if (!$isAuthenticated) {
    $userRole = 'public';
}

// Numeric role IDs (authoritative; AuthMiddleware exposes role_ids / role_names).
$userRoleIds = $isAuthenticated ? array_map('intval', (array)($currentUser['role_ids'] ?? [])) : [];
$userRoleNames = $isAuthenticated ? array_map('strtolower', (array)($currentUser['role_names'] ?? [])) : [];

// Fallback name-keyed map (legacy session shape, e.g. parent/teacher/...).
$rolePermissions = [
    'public' => ['basic'], // QR scanners (no login) see only the learner's name + class
    'parent' => ['all'],
    'admin' => ['all'],
    'director' => ['all'],
    'headteacher' => ['all'],
    'deputy_headteacher' => ['all'],
    'teacher' => ['academic', 'basic'],
    'security' => ['authorization', 'basic'],
    'driver' => ['transportation', 'basic'],
    'accountant' => ['financial', 'academic', 'basic'],
    'finance_manager' => ['financial', 'academic', 'basic'],
    'transport_manager' => ['transportation', 'basic'],
    'medical_staff' => ['medical', 'basic'],
    'sports_coordinator' => ['sports', 'basic'],
    'librarian' => ['library', 'basic'],
    'guidance_counselor' => ['guidance', 'basic'],
];

// Determine which sections to show based on the signed-in role (by numeric ID).
if ($isAuthenticated && !empty($userRoleIds)) {
    $allowedSections = ['basic'];
    $adminRoleIds = [2, 3, 4, 5, 6, 63]; // System Admin, Director, School Admin, Headteacher, Deputy Heads
    if (array_intersect($userRoleIds, $adminRoleIds)) {
        $allowedSections = ['all'];
    } else {
        if (in_array(23, $userRoleIds, true)) { $allowedSections[] = 'transportation'; }             // Driver
        if (in_array(33, $userRoleIds, true)) { $allowedSections[] = 'authorization'; }              // Security Staff
        if (in_array(7, $userRoleIds, true) || in_array(8, $userRoleIds, true)) { $allowedSections[] = 'academic'; } // Teachers
        if (in_array(10, $userRoleIds, true)) { $allowedSections[] = 'financial'; $allowedSections[] = 'academic'; } // Accountant
        if (in_array(21, $userRoleIds, true)) { $allowedSections[] = 'sports'; }                     // Talent Development
        $allowedSections = array_values(array_unique($allowedSections));
    }
} else {
    $allowedSections = $rolePermissions[$userRole] ?? ['basic'];
}

// Department-registered scanning devices may pin one department via ?scope=
// (e.g. a transport scanner, a sports scanner, a parents' kiosk). Phones without
// a scope follow the signed-in role. A scope never widens a public scan.
$scope = strtolower(trim((string)($_GET['scope'] ?? '')));
if ($scope !== '' && $isAuthenticated) {
    $scopeSections = [
        'transport' => ['transportation', 'basic'],
        'sports' => ['sports', 'basic'],
        'medical' => ['medical', 'basic'],
        'security' => ['authorization', 'basic'],
        'authorization' => ['authorization', 'basic'],
        'academic' => ['academic', 'basic'],
        'financial' => ['financial', 'basic'],
        'finance' => ['financial', 'basic'],
        'library' => ['library', 'basic'],
        'guidance' => ['guidance', 'basic'],
    ];
    $allowedSections = $scopeSections[$scope] ?? ['basic'];
}

// Human-readable role label for the header badge (prefer the numeric-ID names).
$viewingAs = $userRole;
if ($isAuthenticated && !empty($userRoleNames)) {
    $viewingAs = implode(' / ', array_map('ucwords', $userRoleNames));
}

// Helper function to check if section should be shown
function canShowSection($section, $allowedSections) {
    return in_array('all', $allowedSections) || in_array($section, $allowedSections);
}

// Get additional student data based on sections
$additionalData = [];

if (canShowSection('financial', $allowedSections)) {
    // Get financial data (obligations + payments via the shipped fee-ledger view)
    $stmt = $db->prepare("
        SELECT term_code AS term, academic_year, amount_due AS amount,
               payment_status AS status, balance
        FROM vw_student_fee_ledger
        WHERE student_id = ?
        ORDER BY academic_year DESC, term_code DESC
        LIMIT 5
    ");
    $stmt->execute([$studentId]);
    $additionalData['fees'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (canShowSection('transportation', $allowedSections)) {
    // Transportation data: latest assignment (active/suspended) joined to route,
    // vehicle, driver and pick-up/drop-off points, plus the latest monthly bill.
    $stmt = $db->prepare("
        SELECT sta.id, sta.month, sta.year, sta.status,
               sta.pickup_time, sta.dropoff_time,
               tr.name AS route_name, tr.code AS route_code,
               tv.registration_number AS vehicle_number, tv.type AS vehicle_type,
               CONCAT(driver_p.first_name, ' ', driver_p.last_name) AS driver_name,
               tsp.name AS pickup_point, tsd.name AS dropoff_point
        FROM student_transport_assignments sta
        JOIN transport_routes tr ON tr.id = sta.route_id
        LEFT JOIN transport_vehicle_routes tvr ON tvr.route_id = tr.id AND tvr.status = 'active'
        LEFT JOIN transport_vehicles tv ON tv.id = tvr.vehicle_id
        LEFT JOIN staff ds ON ds.id = tv.driver_id
        LEFT JOIN persons driver_p ON driver_p.id = ds.person_id
        LEFT JOIN transport_stops tsp ON tsp.id = sta.pickup_stop_id
        LEFT JOIN transport_stops tsd ON tsd.id = sta.dropoff_stop_id
        WHERE sta.student_id = ?
          AND sta.status IN ('active', 'suspended')
        ORDER BY sta.year DESC, sta.month DESC, sta.id DESC
        LIMIT 1
    ");
    $stmt->execute([$studentId]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

    $billStmt = $db->prepare("
        SELECT billing_month, amount_due, payment_status, due_date
        FROM transport_monthly_bills
        WHERE student_id = ?
        ORDER BY billing_month DESC
        LIMIT 1
    ");
    $billStmt->execute([$studentId]);
    $bill = $billStmt->fetch(PDO::FETCH_ASSOC);

    // Eligibility for the current month - what the driver/conductor must know.
    $curMonth = (int) date('n');
    $curYear = (int) date('Y');
    $eligibility = [
        'code' => 'not_subscribed',
        'label' => 'Not Subscribed',
        'allowed' => false,
        'reason' => 'This learner has no transport subscription.',
    ];
    if ($assignment) {
        $isCurrent = ((int) $assignment['month'] === $curMonth && (int) $assignment['year'] === $curYear);
        if ($assignment['status'] === 'suspended') {
            $eligibility = [
                'code' => 'suspended',
                'label' => 'Subscription Suspended',
                'allowed' => false,
                'reason' => 'The transport subscription is currently suspended.',
            ];
        } elseif ($isCurrent) {
            $eligibility = [
                'code' => 'subscribed',
                'label' => 'Subscribed - Eligible',
                'allowed' => true,
                'reason' => 'Active transport subscription for ' . date('F Y') . '.',
            ];
            if ($bill && $bill['payment_status'] !== 'paid') {
                $eligibility['label'] = 'Subscribed (Payment ' . ucfirst($bill['payment_status']) . ')';
                $eligibility['reason'] = 'Subscription is active, but the ' . $bill['billing_month'] . ' bill (KES ' . number_format((float) $bill['amount_due'], 2) . ') is ' . $bill['payment_status'] . '.';
            }
        } else {
            $eligibility = [
                'code' => 'expired',
                'label' => 'Subscription Expired',
                'allowed' => false,
                'reason' => 'Subscription covered ' . $assignment['month'] . '/' . $assignment['year'] . '; renew for the current month.',
            ];
        }
    }

    $additionalData['transport'] = $assignment ? array_merge($assignment, ['eligibility' => $eligibility]) : null;
    $additionalData['transport_bill'] = $bill;
}

if (canShowSection('medical', $allowedSections)) {
    // Get medical data
    $stmt = $db->prepare("
        SELECT created_at AS date, notes
        FROM student_health_records
        WHERE student_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$studentId]);
    $additionalData['medical'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (canShowSection('sports', $allowedSections)) {
    // Get sports data (activity participation for the student's enrollment)
    $stmt = $db->prepare("
        SELECT act.title AS sport_name, ap.role AS position, act.end_date AS date, ap.status
        FROM activity_participants ap
        JOIN activities act ON act.id = ap.activity_id
        JOIN student_academic_enrollments sae ON sae.id = ap.student_academic_enrollment_id
        WHERE sae.student_id = ?
          AND ap.status = 'active'
        ORDER BY act.end_date DESC
        LIMIT 5
    ");
    $stmt->execute([$studentId]);
    $additionalData['sports'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (canShowSection('library', $allowedSections)) {
    // Get library data (issued books)
    $stmt = $db->prepare("
        SELECT lb.title AS book_title, li.due_date, li.status
        FROM library_issues li
        JOIN library_books lb ON lb.id = li.book_id
        WHERE li.borrower_type = 'student'
          AND li.borrower_id = ?
        ORDER BY li.due_date ASC
        LIMIT 5
    ");
    $stmt->execute([$studentId]);
    $additionalData['library'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (canShowSection('guidance', $allowedSections)) {
    // Get guidance/counseling data (sessions for the student's open cases)
    $stmt = $db->prepare("
        SELECT cs.session_date AS date, cs.summary AS notes, cs.session_type
        FROM counseling_sessions cs
        JOIN counseling_cases cc ON cc.id = cs.case_id
        WHERE cc.counselee_type = 'student'
          AND cc.student_id = ?
        ORDER BY cs.session_date DESC
        LIMIT 5
    ");
    $stmt->execute([$studentId]);
    $additionalData['guidance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (canShowSection('academic', $allowedSections)) {
    // Get academic performance (latest assessment results via the shipped detail view)
    $stmt = $db->prepare("
        SELECT year_code AS academic_year, term_name AS term, grade_band AS grade,
               percentage, marks_obtained
        FROM vw_assessment_results_detail
        WHERE student_id = ?
        ORDER BY year_code DESC, term_number DESC
        LIMIT 5
    ");
    $stmt->execute([$studentId]);
    $additionalData['academic'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get authorization/security data (for security personnel)
if (canShowSection('authorization', $allowedSections)) {
    $stmt = $db->prepare("
        SELECT * FROM student_id_cards 
        WHERE student_id = ? 
        AND status IN ('issued', 'printed')
        ORDER BY issue_date DESC
        LIMIT 1
    ");
    $stmt->execute([$studentId]);
    $additionalData['id_card'] = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Web-relative base path (this file lives at the web root).
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$appBase = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
if ($appBase === '.') $appBase = '';
$isPublic = !$isAuthenticated;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID Card Verification - <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?> | Kingsway Preparatory School</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= $appBase ?>/css/app-common.css?v=<?= asset_version('css/app-common.css') ?>">
    <style>
        :root {
            --kw-green: #0d4f2a;
            --kw-green-light: #198754;
            --kw-gold: #f9c80e;
        }
        body {
            background: #f5f5f5;
            padding: 20px;
        }
        .student-header {
            background: linear-gradient(135deg, var(--kw-green) 0%, var(--kw-green-light) 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        .student-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid white;
            object-fit: cover;
        }
        .info-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .info-card h4 {
            color: var(--kw-green);
            margin-bottom: 15px;
            border-bottom: 2px solid var(--kw-green);
            padding-bottom: 10px;
        }
        .info-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .info-label {
            font-weight: 600;
            width: 150px;
            color: #555;
        }
        .info-value {
            color: #333;
            flex: 1;
        }
        .role-badge {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="student-header">
            <div class="row align-items-center">
                <div class="col-md-2 text-center">
                    <?php $photo = !empty($student['photo_url']) ? $student['photo_url'] : $appBase . '/uploads/students/avatar.jpg'; ?>
                    <img src="<?php echo htmlspecialchars($photo); ?>"
                         alt="Learner Photo"
                         class="student-photo"
                         onerror="this.onerror=null;this.src='<?= $appBase ?>/images/official_school_logo.png';">
                </div>
                <div class="col-md-10">
                    <div class="mb-1 text-white-50 small"><i class="bi bi-shield-check me-1"></i>Kingsway Preparatory School</div>
                    <h2 class="fw-bold mb-0"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h2>
                    <p class="mb-2">
                        <strong>Class:</strong> <?php echo htmlspecialchars($student['class_name'] . ' - ' . $student['stream_name']); ?>
                        <?php if (!$isPublic): ?>
                            | <strong>Admission No:</strong> <?php echo htmlspecialchars($student['admission_no']); ?>
                        <?php endif; ?>
                    </p>
                    <p class="mb-0">
                        <?php if (!$isPublic): ?>
                            <span class="role-badge">
                                <i class="bi bi-person-shield"></i> Viewing as: <?php echo htmlspecialchars($viewingAs); ?>
                                <?php if ($scope !== ''): ?><span class="ms-1 small">(scope: <?php echo htmlspecialchars($scope); ?>)</span><?php endif; ?>
                            </span>
                        <?php else: ?>
                            <span class="role-badge">
                                <i class="bi bi-qr-code"></i> Scanned from ID card - <a href="<?= $appBase ?>/index.php" style="color: #fff; text-decoration: underline;">staff sign-in for role-based details</a>
                            </span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Basic Information (name + class for the public scan view; full details when signed in) -->
        <div class="info-card">
            <h4><i class="bi bi-person"></i> Basic Information</h4>
            <div class="info-row">
                <div class="info-label">Full Name:</div>
                <div class="info-value"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Class:</div>
                <div class="info-value"><?php echo htmlspecialchars($student['class_name'] . ' - ' . $student['stream_name']); ?></div>
            </div>
            <?php if (!$isPublic): ?>
            <div class="info-row">
                <div class="info-label">Admission No:</div>
                <div class="info-value"><?php echo htmlspecialchars($student['admission_no']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Gender:</div>
                <div class="info-value"><?php echo htmlspecialchars($student['gender']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Date of Birth:</div>
                <div class="info-value"><?php echo htmlspecialchars($student['date_of_birth']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Status:</div>
                <div class="info-value">
                    <span class="badge bg-<?php echo $student['status'] === 'active' ? 'success' : 'warning'; ?>">
                        <?php echo ucfirst($student['status']); ?>
                    </span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Academic Information (for teachers, accountants, etc.) -->
        <?php if (canShowSection('academic', $allowedSections)): ?>
        <div class="info-card">
            <h4><i class="bi bi-mortarboard"></i> Academic Information</h4>
            <div class="info-row">
                <div class="info-label">Year Joined:</div>
                <div class="info-value"><?php echo htmlspecialchars($student['year_joined']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Expected Graduation:</div>
                <div class="info-value"><?php echo htmlspecialchars($student['expected_graduation_year']); ?></div>
            </div>
            <?php if (!empty($additionalData['academic'])): ?>
                <h5 class="mt-3">Recent Performance</h5>
                <?php foreach ($additionalData['academic'] as $perf): ?>
                    <div class="info-row">
                        <div class="info-label"><?php echo htmlspecialchars($perf['term'] . ' ' . $perf['academic_year']); ?>:</div>
                        <div class="info-value"><?php echo htmlspecialchars($perf['grade'] ?? 'N/A'); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Financial Information (for accountants, finance managers) -->
        <?php if (canShowSection('financial', $allowedSections)): ?>
        <div class="info-card">
            <h4><i class="bi bi-cash-wave"></i> Financial Information</h4>
            <?php if (!empty($additionalData['fees'])): ?>
                <?php foreach ($additionalData['fees'] as $fee): ?>
                    <div class="info-row">
                        <div class="info-label"><?php echo htmlspecialchars($fee['term'] . ' ' . $fee['academic_year']); ?>:</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($fee['amount'] ?? '0'); ?> - 
                            <span class="badge bg-<?php echo ($fee['status'] ?? 'pending') === 'paid' ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst($fee['status'] ?? 'pending'); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted">No financial records found.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Transportation Information (drivers, transport scope) -->
        <?php if (canShowSection('transportation', $allowedSections)): ?>
        <div class="info-card">
            <h4><i class="bi bi-bus-front"></i> Transportation - Ride Check</h4>
            <?php if (!empty($additionalData['transport'])): ?>
                <?php $elig = $additionalData['transport']['eligibility'] ?? null; ?>
                <?php if ($elig): ?>
                    <div class="alert alert-<?php echo $elig['allowed'] ? 'success' : 'danger'; ?> py-2 px-3">
                        <strong><i class="bi bi-<?php echo $elig['allowed'] ? 'check-circle' : 'x-circle'; ?>"></i> <?php echo htmlspecialchars($elig['label']); ?></strong>
                        <div class="small mt-1"><?php echo htmlspecialchars($elig['reason']); ?></div>
                    </div>
                <?php endif; ?>
                <div class="info-row">
                    <div class="info-label">Route:</div>
                    <div class="info-value"><?php echo htmlspecialchars($additionalData['transport']['route_name'] ?? 'N/A'); ?><?php echo !empty($additionalData['transport']['route_code']) ? ' (' . htmlspecialchars($additionalData['transport']['route_code']) . ')' : ''; ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Vehicle:</div>
                    <div class="info-value"><?php echo htmlspecialchars($additionalData['transport']['vehicle_number'] ?? 'N/A'); ?><?php echo !empty($additionalData['transport']['vehicle_type']) ? ' - ' . htmlspecialchars($additionalData['transport']['vehicle_type']) : ''; ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Driver:</div>
                    <div class="info-value"><?php echo htmlspecialchars($additionalData['transport']['driver_name'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Pickup Point:</div>
                    <div class="info-value"><?php echo htmlspecialchars($additionalData['transport']['pickup_point'] ?? 'N/A'); ?><?php echo !empty($additionalData['transport']['pickup_time']) ? ' at ' . htmlspecialchars(substr($additionalData['transport']['pickup_time'], 0, 5)) : ''; ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Drop-off Point:</div>
                    <div class="info-value"><?php echo htmlspecialchars($additionalData['transport']['dropoff_point'] ?? 'N/A'); ?><?php echo !empty($additionalData['transport']['dropoff_time']) ? ' at ' . htmlspecialchars(substr($additionalData['transport']['dropoff_time'], 0, 5)) : ''; ?></div>
                </div>
                <?php if (!empty($additionalData['transport_bill'])): $tb = $additionalData['transport_bill']; ?>
                <div class="info-row">
                    <div class="info-label">Monthly Bill:</div>
                    <div class="info-value"><?php echo htmlspecialchars($tb['billing_month']); ?> - KES <?php echo number_format((float) $tb['amount_due'], 2); ?> <span class="badge bg-<?php echo ($tb['payment_status'] ?? '') === 'paid' ? 'success' : 'warning'; ?>"><?php echo ucfirst($tb['payment_status'] ?? 'N/A'); ?></span></div>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-danger py-2 px-3 mb-0">
                    <strong><i class="bi bi-x-circle"></i> Not Subscribed</strong>
                    <div class="small mt-1">This learner has no transport subscription and should NOT board the school bus.</div>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Authorization Information (for security personnel) -->
        <?php if (canShowSection('authorization', $allowedSections)): ?>
        <div class="info-card">
            <h4><i class="bi bi-person-badge"></i> Authorization Information</h4>
            <?php if (!empty($additionalData['id_card'])): ?>
                <div class="info-row">
                    <div class="info-label">Card Number:</div>
                    <div class="info-value"><?php echo htmlspecialchars($additionalData['id_card']['card_number'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Issue Date:</div>
                    <div class="info-value"><?php echo htmlspecialchars($additionalData['id_card']['issue_date'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Expiry Date:</div>
                    <div class="info-value"><?php echo htmlspecialchars($additionalData['id_card']['expiry_date'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Status:</div>
                    <div class="info-value">
                        <span class="badge bg-success"><?php echo ucfirst($additionalData['id_card']['status'] ?? 'N/A'); ?></span>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-muted">No active ID card found.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Medical Information (for medical staff) -->
        <?php if (canShowSection('medical', $allowedSections)): ?>
        <div class="info-card">
            <h4><i class="bi bi-heartbeat"></i> Medical Information</h4>
            <?php if (!empty($additionalData['medical'])): ?>
                <?php foreach ($additionalData['medical'] as $medical): ?>
                    <div class="info-row">
                        <div class="info-label"><?php echo htmlspecialchars($medical['date']); ?>:</div>
                        <div class="info-value"><?php echo htmlspecialchars($medical['notes'] ?? 'N/A'); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted">No medical records found.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Sports Information (for sports coordinator) -->
        <?php if (canShowSection('sports', $allowedSections)): ?>
        <div class="info-card">
            <h4><i class="bi bi-person-walking"></i> Sports Information</h4>
            <?php if (!empty($additionalData['sports'])): ?>
                <?php foreach ($additionalData['sports'] as $sport): ?>
                    <div class="info-row">
                        <div class="info-label">Sport:</div>
                        <div class="info-value"><?php echo htmlspecialchars($sport['sport_name'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Position:</div>
                        <div class="info-value"><?php echo htmlspecialchars($sport['position'] ?? 'N/A'); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted">No sports records found.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Library Information (for librarian) -->
        <?php if (canShowSection('library', $allowedSections)): ?>
        <div class="info-card">
            <h4><i class="bi bi-book"></i> Library Information</h4>
            <?php if (!empty($additionalData['library'])): ?>
                <?php foreach ($additionalData['library'] as $book): ?>
                    <div class="info-row">
                        <div class="info-label">Book:</div>
                        <div class="info-value"><?php echo htmlspecialchars($book['book_title'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Due Date:</div>
                        <div class="info-value"><?php echo htmlspecialchars($book['due_date'] ?? 'N/A'); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted">No library records found.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Guidance Information (for guidance counselor) -->
        <?php if (canShowSection('guidance', $allowedSections)): ?>
        <div class="info-card">
            <h4><i class="bi bi-chats"></i> Guidance & Counseling</h4>
            <?php if (!empty($additionalData['guidance'])): ?>
                <?php foreach ($additionalData['guidance'] as $guidance): ?>
                    <div class="info-row">
                        <div class="info-label"><?php echo htmlspecialchars($guidance['date']); ?>:</div>
                        <div class="info-value"><?php echo htmlspecialchars($guidance['notes'] ?? 'N/A'); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted">No guidance records found.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="text-center mt-4 mb-4">
            <p class="text-muted">
                <small>
                    ID card verification - role-based details for school staff and guardians |
                    Generated: <?php echo date('Y-m-d H:i:s'); ?> |
                    Kingsway Preparatory School
                </small>
            </p>
        </div>
    </div>
</body>
</html>
