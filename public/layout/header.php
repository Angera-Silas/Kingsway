<?php
/* Shared public header — included by every public page.
 * Expects: $pageTitle (string), $activePage (string), $appBase (string) */
$pageTitle  = $pageTitle  ?? 'Kingsway Preparatory School';
$activePage = $activePage ?? 'home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> | Kingsway Preparatory School</title>
  <meta name="description" content="Kingsway Preparatory School — Nurturing Excellence, Character &amp; Leadership. Located in Londiani, Kenya.">

  <!-- Favicons -->
  <link rel="icon" type="image/png" href="<?= $appBase ?>/images/favicon/favicon-96x96.png" sizes="96x96">
  <link rel="icon" type="image/svg+xml" href="<?= $appBase ?>/images/favicon/favicon.svg">
  <link rel="shortcut icon" href="<?= $appBase ?>/images/favicon/favicon.ico">
  <link rel="apple-touch-icon" sizes="180x180" href="<?= $appBase ?>/images/favicon/apple-touch-icon.png">

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

  <!-- CSS -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= $appBase ?>/public/css/public.css?v=<?= asset_version('public/css/public.css') ?>">
  <noscript><link rel="stylesheet" href="<?= $appBase ?>/css/no-script.css?v=<?= asset_version('css/no-script.css') ?>"></noscript>
</head>
<body>

<noscript>
  <div class="noscript-overlay">
    <div class="noscript-card">
      <span class="icon">⚠️</span>
      <h2>JavaScript Required</h2>
      <p>Kingsway Preparatory School requires JavaScript for full functionality. Please enable JavaScript in your browser settings and reload the page.</p>
      <span class="badge">Kingsway Preparatory School</span>
    </div>
  </div>
</noscript>

<!-- ═══ NAVBAR ═══════════════════════════════════════════════════════════════ -->
<nav class="site-nav navbar navbar-expand-lg">
  <div class="container">

    <a class="navbar-brand" href="<?= $appBase ?>/index.php">
      <img src="<?= $appBase ?>/uploads/school_assets/official_school_logo.png" alt="Kingsway Logo" class="school-logo" onerror="this.onerror=null;this.src='<?= $appBase ?>/images/official_school_logo.png';">
      <span>Kingsway Prep</span>
    </a>

    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#siteNav" aria-controls="siteNav" aria-expanded="false">
      <i class="bi bi-list text-white fs-3"></i>
    </button>

    <div class="collapse navbar-collapse" id="siteNav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-1">

        <li class="nav-item">
          <a class="nav-link <?= $activePage==='home'?'active':'' ?>" href="<?= $appBase ?>/index.php">Home</a>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= $activePage==='about'?'active':'' ?>" href="#" data-bs-toggle="dropdown">About Us</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="<?= $appBase ?>/about.php#mission"><i class="bi bi-bullseye me-2 text-success"></i>Mission &amp; Vision</a></li>
            <li><a class="dropdown-item" href="<?= $appBase ?>/about.php#history"><i class="bi bi-book me-2 text-success"></i>Our History</a></li>
            <li><a class="dropdown-item" href="<?= $appBase ?>/about.php#leadership"><i class="bi bi-person-badge me-2 text-success"></i>Leadership Team</a></li>
            <li><a class="dropdown-item" href="<?= $appBase ?>/about.php#facilities"><i class="bi bi-buildings me-2 text-success"></i>Facilities</a></li>
          </ul>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= $activePage==='admissions'?'active':'' ?>" href="#" data-bs-toggle="dropdown">Admissions</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="<?= $appBase ?>/admissions.php#process"><i class="bi bi-list-check me-2 text-success"></i>Admission Process</a></li>
            <li><a class="dropdown-item" href="<?= $appBase ?>/admissions.php#requirements"><i class="bi bi-file-earmark-text me-2 text-success"></i>Requirements</a></li>
            <li><a class="dropdown-item" href="<?= $appBase ?>/admissions.php#fees"><i class="bi bi-cash me-2 text-success"></i>Fee Structure</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item fw-semibold text-success" href="<?= $appBase ?>/admissions.php#apply"><i class="bi bi-send me-2"></i>Apply Now</a></li>
          </ul>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= $activePage==='news'?'active':'' ?>" href="<?= $appBase ?>/news.php">
            <i class="bi bi-newspaper me-1"></i>News
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= $activePage==='events'?'active':'' ?>" href="<?= $appBase ?>/events.php">
            <i class="bi bi-calendar-event me-1"></i>Events
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= $activePage==='careers'?'active':'' ?>" href="<?= $appBase ?>/careers.php">Careers</a>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">More</a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="<?= $appBase ?>/downloads.php"><i class="bi bi-download me-2 text-success"></i>Downloads</a></li>
            
            <li><a class="dropdown-item" href="<?= $appBase ?>/uniform_catalog.php"><i class="bi bi-bag-heart me-2 text-success"></i>Uniform Store</a></li>
            <li><a class="dropdown-item" href="<?= $appBase ?>/contact.php"><i class="bi bi-envelope me-2 text-success"></i>Contact Us</a></li>
            <li><a class="dropdown-item" href="<?= $appBase ?>/parents/"><i class="bi bi-people me-2 text-success"></i>Parent Portal</a></li>
          </ul>
        </li>

        <li class="nav-item">
          <a class="nav-link btn-login" href="#" data-bs-toggle="modal" data-bs-target="#loginModal">
            <i class="bi bi-box-arrow-in-right me-1"></i>Login
          </a>
        </li>

      </ul>
    </div>
  </div>
</nav>

<!-- ═══ ANNOUNCEMENT TICKER ══════════════════════════════════════════════════ -->
<div class="ticker-bar d-flex align-items-center gap-3 px-3">
  <span class="ticker-label"><i class="bi bi-megaphone-fill me-1"></i>News</span>
  <div class="overflow-hidden flex-grow-1">
    <div class="ticker-track" id="site-ticker"></div>
  </div>
</div>

<!-- ═══ LOGIN MODAL ══════════════════════════════════════════════════════════ -->
<div class="modal fade modal-login" id="loginModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
    <form class="modal-content" id="loginForm">
      <div class="modal-login-header">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <img src="<?= $appBase ?>/uploads/school_assets/official_school_logo.png" alt="Kingsway Logo" class="logo" onerror="this.onerror=null;this.src='<?= $appBase ?>/images/official_school_logo.png';">
        <h5>Welcome Back</h5>
        <p>Sign in to Kingsway Academy Portal</p>
      </div>
      <div class="p-4">
        <div class="mb-3">
          <label class="form-label small fw-semibold text-muted">Username or Email</label>
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="bi bi-person-circle text-muted"></i></span>
            <input type="text" name="username" id="loginUsername" class="form-control" placeholder="Enter username or email" required autocomplete="username">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold text-muted">Password</label>
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="bi bi-key text-muted"></i></span>
            <input type="password" name="password" id="loginPassword" class="form-control" placeholder="Enter password" required autocomplete="current-password">
            <button type="button" class="btn btn-outline-secondary bg-light" id="togglePassword">
              <i class="bi bi-eye" id="togglePasswordIcon"></i>
            </button>
          </div>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div class="form-check mb-0">
            <input class="form-check-input" type="checkbox" id="rememberMe">
            <label class="form-check-label small text-muted" for="rememberMe">Remember me</label>
          </div>
          <a href="<?= $appBase ?>/forgot_password.php" class="small fw-semibold text-success">Forgot password?</a>
        </div>
        <div id="loginError" class="alert alert-danger d-none py-2 small mb-3">
          <i class="bi bi-exclamation-triangle me-1"></i><span id="loginErrorText"></span>
        </div>
        <button type="submit" class="btn-kw-primary w-100 justify-content-center py-2" id="loginSubmitBtn">
          <span id="loginBtnText"><i class="bi bi-box-arrow-in-right me-2"></i>Sign In</span>
          <span id="loginSpinner" class="d-none"><span class="spinner-border spinner-border-sm me-2"></span>Signing in…</span>
        </button>
      </div>
      <div class="bg-light text-center py-3 px-4 border-top rounded-bottom">
        <small class="text-muted"><i class="bi bi-shield-lock me-1 text-success"></i>Secured with SSL encryption</small>
      </div>
    </form>
  </div>
</div>

<!-- ═══ 2FA VERIFICATION MODAL ════════════════════════════════════════════════ -->
<div class="modal fade modal-login" id="tfaModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
    <div class="modal-content">
      <div class="modal-login-header">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <img src="<?= $appBase ?>/uploads/school_assets/official_school_logo.png" alt="Kingsway Logo" class="logo" onerror="this.onerror=null;this.src='<?= $appBase ?>/images/official_school_logo.png';">
        <h5>Two-Factor Verification</h5>
        <p id="tfaMethodDesc">Enter the verification code from your authenticator app.</p>
        <div id="tfaMethodPicker" class="mb-3 d-none">
          <label for="tfaMethodSelect" class="form-label small fw-semibold text-muted">Verification method</label>
          <select id="tfaMethodSelect" class="form-select"></select>
        </div>
      </div>
      <div class="p-4">
        <div id="tfaError" class="alert alert-danger d-none py-2 small mb-3">
          <i class="bi bi-exclamation-triangle me-1"></i><span id="tfaErrorText"></span>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold text-muted" id="tfaCodeLabel">Authentication Code</label>
          <input type="text" id="tfaCode" class="form-control text-center fw-bold" placeholder="000000" inputmode="numeric" autocomplete="one-time-code" maxlength="6" style="font-size:1.5rem;letter-spacing:8px;font-family:monospace">
          <button type="button" id="tfaPasskeyBtn" class="btn btn-outline-primary w-100 d-none"><i class="bi bi-person-badge me-2"></i>Continue with passkey</button>
        </div>
        <div id="tfaResend" class="text-center small text-muted d-none mb-3">
          <span id="tfaResendTimer">Resend code in <span id="tfaCountdown">60</span>s</span>
          <button type="button" id="tfaResendBtn" class="btn btn-link btn-sm p-0 d-none">Resend code</button>
        </div>
        <button type="button" class="btn-kw-primary w-100 justify-content-center py-2" id="tfaSubmitBtn">
          <span id="tfaBtnText"><i class="bi bi-shield-check me-2"></i>Verify</span>
          <span id="tfaSpinner" class="d-none"><span class="spinner-border spinner-border-sm me-2"></span>Verifying…</span>
        </button>
        <div class="text-center mt-3">
          <button type="button" id="tfaBackBtn" class="btn btn-link btn-sm text-muted">
            <i class="bi bi-arrow-left me-1"></i>Back to login
          </button>
          <button type="button" id="tfaRecoveryBtn" class="btn btn-link btn-sm text-muted ms-2">
            <i class="bi bi-key me-1"></i>Use recovery code
          </button>
        </div>
      </div>
      <div class="bg-light text-center py-3 px-4 border-top rounded-bottom">
        <small class="text-muted"><i class="bi bi-shield-lock me-1 text-success"></i>Secured with SSL encryption</small>
      </div>
    </div>
  </div>
</div>
