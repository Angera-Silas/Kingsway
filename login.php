<?php
require_once __DIR__ . '/vendor/autoload.php';
$appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// SCHOOL_PHONE / SCHOOL_EMAIL back the "Contact the system administrator"
// modal below. The constants are only defined by loadSchoolIdentity(), which
// Config::init() calls. Without this the modal renders with no contact
// details at all. Wrapped so a config fault degrades the page instead of
// taking the sign-in screen down with it.
try {
    \App\Config\Config::init();
} catch (\Throwable $configError) {
    error_log('[login] Config::init() failed: ' . $configError->getMessage());
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php asset_script($appBase, 'js/core/console_logger.js'); ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#0b5d3b">
    <title>Secure sign in · Kingsway Preparatory School</title>
    <link rel="icon" href="<?= htmlspecialchars($appBase) ?>/images/favicon/favicon-96x96.png">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBase) ?>/public/vendor/bootstrap/css/bootstrap.min.css?v=<?= asset_version('public/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBase) ?>/css/school-theme.css?v=<?= asset_version('css/school-theme.css') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBase) ?>/css/pages/sign-in.css?v=<?= asset_version('css/pages/sign-in.css') ?>">
    <script>window.APP_BASE=<?= json_encode($appBase) ?>;</script>
</head>
<body class="auth-page">
<main class="auth-layout">
    <section class="auth-story" aria-label="Kingsway Preparatory School">
        <a class="auth-brand" href="<?= htmlspecialchars($appBase) ?>/index.php"><img src="<?= htmlspecialchars($appBase) ?>/uploads/school_assets/official_school_logo.png" alt=""><span><strong>Kingsway Preparatory School</strong><small>In God We Soar</small></span></a>
        <p class="auth-story-foot">Secure access · Kingsway Preparatory School</p>
    </section>
    <section class="auth-workspace">
        <a class="auth-back" href="<?= htmlspecialchars($appBase) ?>/index.php"><i class="bi bi-arrow-left"></i> Back to school website</a>
        <div class="auth-card">
            <div id="signInStep">
                <span class="auth-step-label">Secure school workspace</span><h2>Welcome back</h2><p class="auth-intro">Sign in with your Kingsway username or email.</p>
                <div class="alert alert-danger d-none" id="authError" role="alert"></div>
                <form id="authLoginForm">
                    <label class="form-label" for="authIdentifier">Username, email, or phone</label><div class="input-group mb-3"><span class="input-group-text"><i class="bi bi-person"></i></span><input class="form-control" id="authIdentifier" placeholder="Enter username, email, or phone" required autocomplete="username"></div>
                    <div class="d-flex justify-content-between"><label class="form-label" for="authPassword">Password</label><a href="<?= htmlspecialchars($appBase) ?>/index.php?route=r8f1bc67a3eb2">Forgot password?</a></div><div class="input-group mb-3"><span class="input-group-text"><i class="bi bi-key"></i></span><input type="password" class="form-control" id="authPassword" placeholder="Enter your password" required autocomplete="current-password"><button class="btn auth-password-toggle" type="button" id="authTogglePassword" aria-label="Show password"><i class="bi bi-eye"></i></button></div>
                    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="authRemember"><label class="form-check-label" for="authRemember">Keep me signed in on this device</label></div>
                    <button class="btn btn-warning w-100 auth-submit" id="authSubmit" type="submit"><span>Sign in</span><span class="spinner-border spinner-border-sm d-none"></span></button>
                </form>
                <div class="auth-divider"><span>or</span></div><button class="btn btn-outline-dark w-100" id="authPasswordless" type="button"><i class="bi bi-fingerprint me-2"></i>Sign in with a passkey</button>
                <p class="text-center mt-3 mb-0"><a href="<?= htmlspecialchars($appBase) ?>/parent_portal.php"><i class="bi bi-people me-1"></i>Login as parent or guardian</a></p>
            </div>
            <div class="d-none" id="verificationStep">
                <span class="auth-step-label">Identity verification</span><h2 id="verificationTitle">Two-factor authentication 🔑</h2><p class="auth-intro" id="verificationDescription">Use your device to get the code.</p>
                <input type="hidden" id="verificationMethod" value="">
                <div class="alert alert-danger d-none" id="verifyError" role="alert"></div><div class="alert alert-success d-none" id="verifySuccess" role="status"></div>
                <button class="btn btn-success w-100 mb-3" id="authStartMethod" type="button">Continue with selected method</button>
                <form id="verificationForm"><label class="form-label" id="verificationCodeLabel">Enter the six-digit code</label><div class="auth-otp-grid" id="authOtpGrid" role="group" aria-labelledby="verificationCodeLabel"><input class="auth-otp-cell" inputmode="numeric" autocomplete="one-time-code" maxlength="1" aria-label="Digit 1"><input class="auth-otp-cell" inputmode="numeric" maxlength="1" aria-label="Digit 2"><input class="auth-otp-cell" inputmode="numeric" maxlength="1" aria-label="Digit 3"><input class="auth-otp-cell" inputmode="numeric" maxlength="1" aria-label="Digit 4"><input class="auth-otp-cell" inputmode="numeric" maxlength="1" aria-label="Digit 5"><input class="auth-otp-cell" inputmode="numeric" maxlength="1" aria-label="Digit 6"></div><input class="form-control form-control-lg auth-code d-none" id="verificationCode" autocomplete="one-time-code" maxlength="32"><p class="auth-auto-status" id="authAutoStatus"><i class="bi bi-lightning-charge-fill"></i> Verification starts automatically when all six digits are entered.</p><button class="btn btn-warning w-100 mt-3 auth-submit d-none" id="verifySubmit" type="submit">Verify recovery code</button></form>
                <button class="btn btn-success w-100 d-none" id="authVerifyPasskey" type="button"><i class="bi bi-fingerprint me-2"></i>Verify with this device</button>
                <div class="row g-2 mt-4"><div class="col-12 col-sm-4"><button class="btn btn-outline-secondary btn-sm w-100" id="authBack" type="button"><i class="bi bi-arrow-left me-1"></i>Back to login</button></div><div class="col-12 col-sm-4"><button class="btn btn-outline-primary btn-sm w-100" id="authResend" type="button">Send a new code</button></div><div class="col-12 col-sm-4"><button class="btn btn-outline-danger btn-sm w-100" id="authUseRecovery" type="button" data-bs-toggle="modal" data-bs-target="#noAccessModal">I don't have my 2FA</button></div><div class="col-12 col-sm-4 mt-0"><button class="btn btn-outline-warning btn-sm w-100" id="authTryAnother" type="button" data-bs-toggle="modal" data-bs-target="#tryAnotherModal"><i class="bi bi-arrow-repeat me-1"></i>Try another method</button></div></div>

            </div>
        </div>
        <p class="auth-help"><i class="bi bi-shield-lock"></i> Kingsway will never ask you to share a verification code.</p>
        <p class="auth-soft">Maintained by <a href="https://www.angisoft.co.ke" target="_blank" rel="noopener">AngiSoft Technologies</a></p>
    </section>
</main>
<script src="<?= htmlspecialchars($appBase) ?>/public/vendor/bootstrap/js/bootstrap.bundle.min.js?v=<?= asset_version('public/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= htmlspecialchars($appBase) ?>/js/api.js?v=<?= asset_version('js/api.js') ?>"></script>
<script src="<?= htmlspecialchars($appBase) ?>/js/pages/sign_in.js?v=<?= asset_version('js/pages/sign_in.js') ?>"></script>

<div class="modal fade" id="noAccessModal" tabindex="-1" aria-labelledby="noAccessModalLabel" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h6 class="modal-title" id="noAccessModalLabel">Contact the system administrator</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body">
                        <p class="mb-2">You cannot restore your own sign-in on this system. Verification is arranged by the school, not by the account holder.</p>
                        <p class="mb-2">Contact the system administrator, who can re-enrol your second factor or unlock the account for you.</p>
                        <hr>
                        <ul class="list-unstyled small mb-0">
                          <?php if (defined('SCHOOL_PHONE') && SCHOOL_PHONE !== ''): ?><li class="mb-1"><i class="bi bi-telephone me-2"></i><?= htmlspecialchars(SCHOOL_PHONE, ENT_QUOTES) ?></li><?php endif; ?>
                          <?php if (defined('SCHOOL_EMAIL') && SCHOOL_EMAIL !== ''): ?><li class="mb-1"><i class="bi bi-envelope me-2"></i><a href="mailto:<?= htmlspecialchars(SCHOOL_EMAIL, ENT_QUOTES) ?>"><?= htmlspecialchars(SCHOOL_EMAIL, ENT_QUOTES) ?></a></li><?php endif; ?>
                          <?php if (defined('SCHOOL_NAME') && SCHOOL_NAME !== '' && SCHOOL_NAME !== 'School'): ?><li class="mb-1"><i class="bi bi-building me-2"></i><?= htmlspecialchars(SCHOOL_NAME, ENT_QUOTES) ?> office</li><?php endif; ?>
                        </ul>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                      </div>
                    </div>
                  </div>
                </div>
<div class="modal fade" id="tryAnotherModal" tabindex="-1" aria-labelledby="tryAnotherModalLabel" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h6 class="modal-title" id="tryAnotherModalLabel">Choose how to verify</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body" id="tryAnotherModalBody">
                        <p class="text-muted small mb-3">Pick any method available on this account. Your default was already tried.</p>
                        <div class="list-group" id="tryAnotherList" role="listbox" aria-label="Available verification methods"></div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                      </div>
                    </div>
                  </div>
                </div>
</body></html>
