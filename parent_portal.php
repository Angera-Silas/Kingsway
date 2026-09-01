<?php
// Parent Portal — standalone entry point (not inside admin app shell)
$familyStaffMode = $familyStaffMode ?? false;
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$appBase = $appBaseOverride ?? rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
if ($appBase === '.') $appBase = '';
// Public data helpers expose the open intake terms/grades for the in-page
// "Apply for Admission" modal without adding a new API dependency.
require_once __DIR__ . '/public/layout/public_data.php';
$ppTerms = function_exists('kw_academic_terms') ? kw_academic_terms() : [];
$ppGrades = function_exists('kw_active_grades') ? kw_active_grades() : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php asset_script($appBase, 'js/core/console_logger.js'); ?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($parentPageTitle ?? 'Parent Portal', ENT_QUOTES, 'UTF-8') ?> — Kingsway Parent Portal</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= $appBase ?>/css/school-theme.css?v=<?= asset_version('css/school-theme.css') ?>">
  <link rel="stylesheet" href="<?= $appBase ?>/css/app-common.css?v=<?= asset_version('css/app-common.css') ?>">
  <link rel="stylesheet" href="<?= $appBase ?>/css/parent-cpanel.css?v=<?= asset_version('css/parent-cpanel.css') ?>">
  <style>
    /* ===== Kingsway brand palette ===== */
    :root {
      --kw-green:        #0d4f2a;
      --kw-green-light:  #198754;
      --kw-gold:         #f9c80e;
      --kw-cream:        #f5f5dc;
      --kw-green-grad:   linear-gradient(135deg, #0d4f2a 0%, #198754 100%);
    }
    /* Recolor Bootstrap primary (used by JS-injected badges/cards) to Kingsway green */
    .btn-primary, .bg-primary, .alert-primary { background-color: var(--kw-green-light) !important; border-color: var(--kw-green-light) !important; }
    .text-primary { color: var(--kw-green) !important; }
    .btn-primary:hover { background-color: var(--kw-green) !important; border-color: var(--kw-green) !important; }

    body {
      background: linear-gradient(135deg, #0d4f2a 0%, #198754 100%);
      min-height: 100vh;
      font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    }
    .portal-card { max-width: 480px; margin: 0 auto; }
    .portal-logo { width: 72px; height: 72px; object-fit: contain; }
    .child-card { transition: box-shadow .2s, transform .2s; cursor: pointer; border-top: 4px solid var(--kw-green-light) !important; }
    .child-card:hover { box-shadow: 0 8px 24px rgba(13,79,42,.25); transform: translateY(-2px); }
    #portal-loading { display: none; }
    /* Authentication state must outrank layout utility rules declared below.
       Without !important, .portal-layout {display:flex} made the protected
       dashboard visible underneath the login page. */
    .view:not(.active) { display: none !important; }
    .view.active { display: block; }
    .view.active.portal-layout { display: flex; }

    /* Branded auth header */
    .kw-auth-header {
      background: var(--kw-green-grad);
      border-radius: 1rem 1rem 0 0;
      color: #fff;
      text-align: center;
      padding: 2rem 1rem 1.5rem;
      position: relative;
      overflow: hidden;
    }
    .kw-auth-header::after {
      content: ""; position: absolute; left: 0; right: 0; bottom: 0; height: 4px;
      background: linear-gradient(90deg, var(--kw-gold), #ffe27a);
    }
    .kw-auth-name { color: var(--kw-gold); font-weight: 800; letter-spacing: .5px; }
    .kw-auth-motto { color: rgba(255,255,255,.85); font-style: italic; font-size: .85rem; }
    .kw-auth-logo { width: 64px; height: 64px; object-fit: contain; filter: drop-shadow(0 2px 6px rgba(0,0,0,.3)); }

    /* Branded top bar for dashboard/student views */
    .kw-topbar {
      background: var(--kw-green-grad);
      border: 1px solid rgba(255,255,255,.1);
      border-radius: 1rem;
      padding: 1.25rem 1.5rem;
      box-shadow: 0 6px 20px rgba(13,79,42,.25);
    }

    /* Branded gradient cards used by balance summary */
    .kw-balance-card { border-top: 4px solid var(--kw-gold) !important; }

    /* Statement/popup print header */
    .kw-print-head { font-family: inherit; }

    /* Tabs active color */
    .nav-tabs .nav-link.active { color: var(--kw-green) !important; border-color: #dee2e6 #dee2e6 #fff; font-weight: 600; }

    /* Authenticated parent application shell */
    body.portal-authenticated { background: #f4f7f5; color: #20372a; padding: 0 !important; }
    .portal-layout { min-height: 100vh; }
    .portal-sidebar { width: 268px; flex: 0 0 268px; background: #0d4f2a; color: #fff; padding: 1.25rem .9rem; position: sticky; top: 0; height: 100vh; overflow-y: auto; z-index: 20; }
    .portal-brand { padding: .7rem .75rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,.15); margin-bottom: 1rem; }
    .portal-brand img { width: 42px; height: 42px; object-fit: contain; }
    .portal-brand strong { color: var(--kw-gold); font-size: .9rem; letter-spacing: .3px; }
    .portal-nav-label { color: rgba(255,255,255,.55); font-size: .68rem; text-transform: uppercase; letter-spacing: .12em; padding: .8rem .8rem .35rem; }
    .portal-nav .nav-link { color: rgba(255,255,255,.78); border-radius: .7rem; padding: .65rem .75rem; display: flex; align-items: center; gap: .7rem; font-size: .88rem; cursor: pointer; }
    .portal-nav .nav-link:hover, .portal-nav .nav-link.active { color: #fff; background: rgba(255,255,255,.14); }
    .portal-nav .nav-link i { width: 1.1rem; text-align: center; color: #f9c80e; }
    .portal-main { min-width: 0; flex: 1; background: #f4f7f5; }
    .portal-header { height: 74px; background: #fff; border-bottom: 1px solid #e2ebe4; display: flex; align-items: center; justify-content: space-between; padding: 0 2rem; position: sticky; top: 0; z-index: 10; }
    .portal-header h1 { font-size: 1.15rem; margin: 0; font-weight: 750; color: var(--kw-green); }
    .portal-content { padding: 2rem; max-width: 1500px; margin: 0 auto; }
    .portal-footer { padding: 1.25rem 2rem; color: #718278; font-size: .78rem; border-top: 1px solid #e2ebe4; background: #fff; }
    .portal-welcome { background: var(--kw-green-grad); color: #fff; border: 0; border-radius: 1.1rem; box-shadow: 0 10px 28px rgba(13,79,42,.18); }
    .portal-kpi { border: 0; border-radius: 1rem; box-shadow: 0 5px 18px rgba(30,70,45,.07); height: 100%; }
    .portal-kpi .kpi-icon { width: 42px; height: 42px; border-radius: .8rem; display: grid; place-items: center; font-size: 1.2rem; }
    .portal-mobile-toggle { display: none; }
    @media (max-width: 991.98px) { .portal-sidebar { position: fixed; left: -280px; transition: left .25s ease; box-shadow: 8px 0 28px rgba(0,0,0,.2); } .portal-sidebar.open { left: 0; } .portal-mobile-toggle { display: inline-flex; } .portal-header,.portal-content { padding-left: 1rem; padding-right: 1rem; } .portal-content { padding-top: 1.25rem; } }
    @media (max-width: 575.98px) { .portal-header { height: 66px; } .portal-header .portal-user-label { display: none; } }
  </style>
  <script>
    window.APP_BASE = <?= json_encode($appBase) ?>;
    // The parent portal owns an independent family session. Prevent api.js from
    // restoring or redirecting based on an unrelated internal-staff cookie.
    window.KINGSWAY_PUBLIC_PAGE = true;
    window.FAMILY_STAFF_MODE = <?= $familyStaffMode ? 'true' : 'false' ?>;
    window.PARENT_INITIAL_SECTION = <?= json_encode($parentInitialSection ?? 'overview') ?>;
  </script>
</head>
<body class="py-4">

<!-- AUTH VIEW -->
<div id="view-auth" class="view active container">
  <div class="portal-card">
    <div class="card shadow-lg border-0 rounded-4">
      <div class="card-body p-0">
        <div class="kw-auth-header">
          <img src="<?= $appBase ?>/uploads/school_assets/official_school_logo.png" alt="Kingsway Logo" class="kw-auth-logo mb-2" onerror="this.onerror=null;this.src='<?= $appBase ?>/images/official_school_logo.png';">
          <div class="kw-auth-name h5 mb-0">KINGSWAY PREPARATORY SCHOOL</div>
          <div class="kw-auth-motto">"In God We Soar"</div>
          <div class="text-white-50 small mt-2">Parent Portal</div>
        </div>
        <div class="p-4 p-md-5">

        <div class="text-center mb-4"><span class="badge rounded-pill text-bg-success"><i class="bi bi-shield-lock me-1"></i>Password + email verification</span></div>

        <!-- Email Login Form -->
        <div id="tab-email">
          <div class="mb-3">
            <label class="form-label fw-semibold">Email Address</label>
            <input type="email" id="loginEmail" class="form-control" placeholder="parent@email.com">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Password</label>
            <div class="input-group">
              <input type="password" id="loginPassword" class="form-control" placeholder="••••••••">
              <button class="btn btn-outline-secondary" type="button" id="togglePwd"><i class="bi bi-eye"></i></button>
            </div>
          </div>
          <div id="loginError" class="alert alert-danger d-none"></div>
          <button class="btn btn-primary w-100 py-2 fw-semibold" id="btnEmailLogin">
            <span class="spinner-border spinner-border-sm me-2 d-none" id="loginSpinner"></span>
            Sign In
          </button>
        </div>

        <!-- Email OTP Form -->
        <div id="tab-otp" style="display:none">
          <div id="otp-step-1" style="display:none">
            <div class="mb-3">
              <label class="form-label fw-semibold">Email Address</label>
              <input type="email" id="otpEmail" class="form-control" autocomplete="email" placeholder="parent@example.com">
            </div>
            <div id="otpRequestError" class="alert alert-danger d-none"></div>
            <button class="btn btn-primary w-100 py-2 fw-semibold" id="btnRequestOtp">Send OTP</button>
          </div>
          <div id="otp-step-2" style="display:none">
            <p class="text-muted small">Enter the 6-digit code sent to your email.</p>
            <div class="mb-3">
              <label class="form-label fw-semibold">OTP Code</label>
              <input type="text" id="otpCode" class="form-control text-center fw-bold fs-4" maxlength="6" placeholder="------">
            </div>
            <div id="otpVerifyError" class="alert alert-danger d-none"></div>
            <button class="btn btn-success w-100 py-2 fw-semibold" id="btnVerifyOtp">Verify &amp; Sign In</button>
            <button class="btn btn-link w-100 mt-2 text-muted" id="btnResendOtp">Restart secure sign-in</button>
          </div>
        </div>

        <div class="text-center mt-3">
          <small class="text-muted">Having trouble? Contact the school office.</small><br>
          <small class="text-muted">
            <i class="bi bi-telephone me-1"></i>+254 720 113 030 &nbsp;·&nbsp;
            <i class="bi bi-envelope me-1"></i>info@kingswaypreparatoryschool.sc.ke
          </small>
        </div>
        </div><!-- /p-4 p-md-5 -->
      </div>
    </div>
  </div>
</div>

<!-- AUTHENTICATED PARENT APPLICATION SHELL -->
<div id="view-dashboard" class="view portal-layout">
  <aside class="portal-sidebar" id="portalSidebar">
    <div class="portal-brand d-flex align-items-center gap-2"><img src="<?= $appBase ?>/uploads/school_assets/official_school_logo.png" alt="Kingsway" onerror="this.src='<?= $appBase ?>/images/official_school_logo.png'"><div><strong>KINGSWAY PREPARATORY SCHOOL</strong><small class="d-block text-white-50">Parent &amp; Family Centre</small></div></div>
    <nav class="portal-nav">
      <div class="portal-nav-label">Overview</div>
      <a href="<?= $appBase ?>/parents/dashboard.php" class="nav-link" data-portal-section="overview"><i class="bi bi-grid-1x2-fill"></i>Dashboard</a>
      <a href="<?= $appBase ?>/parents/children.php" class="nav-link" data-portal-section="children"><i class="bi bi-people-fill"></i>My Children</a>
      <div class="portal-nav-label">School life</div>
      <a href="<?= $appBase ?>/parents/results.php" class="nav-link" data-portal-section="academics"><i class="bi bi-mortarboard-fill"></i>Learning &amp; Results</a>
      <a href="<?= $appBase ?>/parents/attendance.php" class="nav-link" data-portal-section="attendance"><i class="bi bi-calendar-check-fill"></i>Attendance</a>
      <a href="<?= $appBase ?>/parents/messages.php" class="nav-link" data-portal-section="messages"><i class="bi bi-chat-dots-fill"></i>Messages</a>
      <a href="<?= $appBase ?>/parents/documents.php" class="nav-link" data-portal-section="documents"><i class="bi bi-folder2-open"></i>Documents &amp; Reports</a>
      <div class="portal-nav-label">Accounts &amp; community</div>
      <a href="<?= $appBase ?>/parents/fees.php" class="nav-link" data-portal-section="fees"><i class="bi bi-receipt-cutoff"></i>Fees &amp; Payments</a>
      <a href="<?= $appBase ?>/parents/transport.php" class="nav-link" data-portal-section="transport"><i class="bi bi-bus-front-fill"></i>Transport</a>
      <a href="<?= $appBase ?>/uniform_catalog.php" class="nav-link"><i class="bi bi-bag-heart-fill"></i>Uniform Store</a>
      <a href="<?= $appBase ?>/parents/community.php" class="nav-link" data-portal-section="pta"><i class="bi bi-person-hearts"></i>PTA &amp; Representatives</a>
      <a href="<?= $appBase ?>/parents/account.php" class="nav-link" data-portal-section="settings"><i class="bi bi-gear-fill"></i>Account Settings</a>
    </nav>
    <div class="mt-auto pt-4"><button class="nav-link w-100 border-0 bg-transparent text-start" id="btnLogout"><i class="bi bi-box-arrow-right"></i>Sign out</button></div>
  </aside>
  <section class="portal-main">
    <header class="portal-header"><div class="d-flex align-items-center gap-3"><button class="btn btn-outline-success portal-mobile-toggle" id="portalMenuToggle"><i class="bi bi-list"></i></button><div><h1 id="portalPageTitle">Family dashboard</h1><small class="text-muted">Kingsway Preparatory School · <span id="portalAcademicContext">Parent Centre</span></small></div></div><div class="d-flex align-items-center gap-3"><span class="portal-user-label text-muted small"><i class="bi bi-person-circle me-1"></i><span id="parentName"></span></span><button class="btn btn-warning btn-sm rounded-pill" id="btnApplyAdmission"><i class="bi bi-person-plus me-1"></i>New admission</button></div></header>
    <main class="portal-content"><div id="portal-loading" class="text-center py-5"><div class="spinner-border text-success"></div><p class="text-muted mt-2">Loading your family workspace…</p></div><div class="portal-welcome p-4 mb-4" id="portalWelcome"><div class="row align-items-center g-3"><div class="col-lg-8"><div class="small text-white-50 mb-1">Welcome back</div><h2 class="h3 fw-bold mb-2">Your children, all in one place.</h2><p class="mb-0 text-white-50">Stay connected to learning, wellbeing, payments and the Kingsway community.</p></div><div class="col-lg-4 text-lg-end"><span class="badge bg-warning text-dark rounded-pill px-3 py-2"><i class="bi bi-shield-check me-1"></i>Secure family account</span></div></div></div><div class="row g-3 mb-4" id="portalKpis"></div><div class="d-flex align-items-center justify-content-between mb-3"><div><h3 class="h5 fw-bold mb-1" id="childrenHeading">My children</h3><p class="text-muted small mb-0">Select a child to view their complete school journey.</p></div><a href="<?= $appBase ?>/uniform_catalog.php" class="btn btn-outline-success btn-sm rounded-pill" target="_blank"><i class="bi bi-bag me-1"></i>Visit store</a></div><div class="row" id="childrenCards"></div></main><footer class="portal-footer d-flex flex-wrap justify-content-between gap-2"><span>© <?= date('Y') ?> Kingsway Preparatory School · “In God We Soar”</span><span><a href="<?= $appBase ?>/contact.php" class="text-success text-decoration-none">Contact school office</a> · <a href="<?= $appBase ?>/index.php" class="text-success text-decoration-none">Public website</a></span></footer>
  </section>
</div>

<!-- STUDENT DETAIL VIEW -->
<div id="view-student" class="view portal-layout">
  <aside class="portal-sidebar"><div class="portal-brand d-flex align-items-center gap-2"><img src="<?= $appBase ?>/uploads/school_assets/official_school_logo.png" alt="Kingsway" onerror="this.src='<?= $appBase ?>/images/official_school_logo.png'"><div><strong>KINGSWAY PREPARATORY SCHOOL</strong><small class="d-block text-white-50">Parent &amp; Family Centre</small></div></div><nav class="portal-nav"><a href="<?= $appBase ?>/parents/dashboard.php" class="nav-link active" id="btnBackToDashboard"><i class="bi bi-arrow-left"></i>Family dashboard</a><a class="nav-link" data-tab="fees"><i class="bi bi-receipt"></i>Fees</a><a class="nav-link" data-tab="payments"><i class="bi bi-credit-card"></i>Payments</a><a class="nav-link" data-tab="attendance"><i class="bi bi-calendar-check"></i>Attendance</a><a class="nav-link" data-tab="performance"><i class="bi bi-graph-up-arrow"></i>Learning</a><a class="nav-link" data-tab="report-card"><i class="bi bi-award"></i>Report card</a><a class="nav-link" data-tab="transport"><i class="bi bi-bus-front"></i>Transport</a><a class="nav-link" data-tab="messages"><i class="bi bi-chat-dots"></i>Messages</a><a class="nav-link" data-tab="portfolio"><i class="bi bi-folder2-open"></i>Portfolio</a></nav></aside><section class="portal-main"><header class="portal-header"><div><h1 id="studentDetailName">Child workspace</h1><small class="text-muted" id="studentDetailClass"></small></div><div class="d-flex align-items-center gap-2"><select id="childSwitcher" class="form-select form-select-sm" aria-label="Switch child"></select></div></header><main class="portal-content"><div class="row g-3 mb-4" id="balanceSummaryCards"></div><div class="card border-0 shadow-sm rounded-4"><div class="card-header bg-white border-0 pb-0"><ul class="nav nav-tabs" id="studentDetailTabs"><li class="nav-item"><button class="nav-link active" data-tab="fees">Fee History</button></li><li class="nav-item"><button class="nav-link" data-tab="payments">Payments</button></li><li class="nav-item"><button class="nav-link" data-tab="statement">Statement</button></li><li class="nav-item"><button class="nav-link" data-tab="attendance">Attendance</button></li><li class="nav-item"><button class="nav-link" data-tab="performance">Learning progress</button></li><li class="nav-item"><button class="nav-link" data-tab="report-card">Report Card</button></li><li class="nav-item"><button class="nav-link" data-tab="transport">Transport</button></li><li class="nav-item"><button class="nav-link" data-tab="messages">Messages</button></li><li class="nav-item"><button class="nav-link" data-tab="portfolio">Portfolio</button></li></ul></div><div class="card-body" id="studentDetailContent"><div class="text-center py-4"><div class="spinner-border text-primary"></div></div></div></div></main><footer class="portal-footer">Kingsway Preparatory School · Secure child information workspace</footer></section>
</div>

<!-- M-Pesa Payment Modal -->
<div class="modal fade" id="mpesaPaymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="bi bi-phone me-2"></i>M-Pesa Payment</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div id="mpesaPaymentForm">
          <div class="mb-3">
            <label class="form-label fw-semibold">Payment provider</label>
            <select id="mpesaProvider" class="form-select">
              <option value="daraja">Safaricom Daraja</option>
              <option value="buni">KCB Buni M-Pesa Express</option>
            </select>
            <div class="form-text">Both providers create an M-Pesa prompt; confirmation is recorded by the school system.</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Amount (KES)</label>
            <input type="number" id="mpesaAmount" class="form-control" min="1" step="any">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">M-Pesa Phone Number</label>
            <input type="tel" id="mpesaPhone" class="form-control" placeholder="2547XXXXXXXX">
            <div class="form-text">Enter the phone number registered with M-Pesa</div>
          </div>
          <div id="mpesaError" class="alert alert-danger d-none"></div>
          <button class="btn btn-success w-100 py-2 fw-semibold" id="btnMpesaPay">
            <span class="spinner-border spinner-border-sm me-2 d-none" id="mpesaSpinner"></span>
            <i class="bi bi-send me-2"></i>Pay with M-Pesa
          </button>
        </div>
        <div id="mpesaWaiting" class="text-center py-4" style="display:none">
          <div class="spinner-border text-success mb-3" style="width:3rem;height:3rem"></div>
          <h6>STK Push Sent!</h6>
          <p class="text-muted small">Please check your phone and enter your M-Pesa PIN to complete the payment.</p>
          <div id="mpesaPollingStatus" class="text-muted small">Waiting for confirmation...</div>
          <button class="btn btn-outline-secondary btn-sm mt-3" id="btnMpesaDone"><i class="bi bi-check-lg me-1"></i>I've Completed the Payment</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Apply for Admission Modal (in-page) -->
<div class="modal fade" id="applyAdmissionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Apply for Admission</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <form id="applyAdmissionForm" enctype="multipart/form-data">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Child's Full Name <span class="text-danger">*</span></label>
              <input type="text" name="child_name" class="form-control" placeholder="As on birth certificate" required>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Date of Birth</label>
              <input type="date" name="child_dob" class="form-control">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Gender <span class="text-danger">*</span></label>
              <select name="child_gender" class="form-select" required>
                <option value="">Select</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Grade Applying For <span class="text-danger">*</span></label>
              <select name="grade_applying" id="ppGradeSelect" class="form-select" required>
                <option value="">Select grade</option>
                <?php foreach ($ppGrades ?: ['PP1','PP2','Grade 1','Grade 2','Grade 3','Grade 4','Grade 5','Grade 6','Grade 7','Grade 8','Grade 9'] as $grade): ?>
                <option value="<?= htmlspecialchars((string) $grade, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $grade, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Preferred Start Term</label>
              <select name="preferred_start" id="ppPreferredStart" class="form-select">
                <?php if (!$ppTerms): ?>
                <option value="">No intake terms open right now</option>
                <?php else: foreach ($ppTerms as $term): ?>
                <option value="<?= htmlspecialchars((string) ($term['name'] ?? '') . ' ' . (string) ($term['year'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        data-term-id="<?= (int) ($term['id'] ?? 0) ?>">
                  <?= htmlspecialchars((string) ($term['name'] ?? 'Term') . ' ' . (string) ($term['year'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                </option>
                <?php endforeach; endif; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Day Scholar or Boarding</label>
              <select name="boarding_preference" class="form-select">
                <option value="day">Day Scholar</option>
                <option value="full_boarding">Full Boarding (Mon – Fri)</option>
                <option value="weekly_boarding">Weekly Boarding (Mon – Fri)</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Parent / Guardian Name <span class="text-danger">*</span></label>
              <input type="text" name="parent_name" class="form-control" id="ppParentName" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Relationship to Child <span class="text-danger">*</span></label>
              <select name="parent_relationship" class="form-select" required>
                <option value="">Select</option>
                <option value="Mother">Mother</option>
                <option value="Father">Father</option>
                <option value="Guardian">Guardian</option>
                <option value="Sponsor">Sponsor</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
              <input type="tel" name="parent_phone" class="form-control" id="ppParentPhone" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Email Address</label>
              <input type="email" name="parent_email" class="form-control" id="ppParentEmail">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Residential Address</label>
              <input type="text" name="parent_address" class="form-control" id="ppParentAddress" placeholder="Town, Sub-county, County">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Birth Certificate <span class="text-danger">*</span></label>
              <input type="file" name="doc_birth_certificate" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Passport Photo <span class="text-danger">*</span></label>
              <input type="file" name="doc_passport_photo" class="form-control" accept=".jpg,.jpeg,.png" required>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Special Medical / Learning / Dietary Needs</label>
              <textarea name="special_needs" class="form-control" rows="2" placeholder="Optional. Leave blank if none."></textarea>
            </div>
            <div class="col-12">
              <div class="form-check">
                <input type="checkbox" id="ppDeclaration" class="form-check-input" required>
                <label class="form-check-label small text-muted" for="ppDeclaration">
                  I confirm the information provided is accurate and complete.
                </label>
              </div>
              <div id="ppApplyMsg" class="alert d-none mt-2 mb-0"></div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="submit" form="applyAdmissionForm" class="btn btn-success" id="ppApplySubmit">
          <i class="bi bi-send me-1"></i>Submit Application
        </button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<?php asset_script($appBase, 'js/api.js'); ?>
<?php asset_script($appBase, 'js/core/frontend_logger.js'); ?>
<?php asset_script($appBase, 'js/core/grading_scale.js'); ?>
<?php asset_script($appBase, 'js/pages/parent_portal.js'); ?>
<script>window.AppLogger?.init?.();</script>
</body>
</html>
