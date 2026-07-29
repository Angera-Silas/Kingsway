<?php
/**
 * Account Settings Page
 * Self-service: password change, 2FA management, backup codes.
 * Controller: js/pages/account_settings.js
 */
?>
<div class="container-fluid py-3" id="accountSettingsPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h3 mb-1">Account Settings</h2>
            <p class="text-muted mb-0">Manage your security and authentication methods</p>
        </div>
    </div>

    <div class="row g-3">
        <!-- ── User Info Summary ───────────────────────────────────── -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body text-center">
                    <div class="app-user-avatar app-user-avatar-xl mx-auto mb-3"
                         style="width:72px;height:72px;font-size:1.75rem;
                                background:var(--kw-primary);color:#fff;
                                border-radius:50%;display:flex;align-items:center;
                                justify-content:center">U</div>
                    <h5 class="mb-1" id="settings-name">User</h5>
                    <p class="text-muted small mb-1" id="settings-email">—</p>
                    <p class="text-muted small mb-2" id="settings-phone">—</p>
                    <span class="badge bg-info" id="settings-role">—</span>
                </div>
            </div>
        </div>

        <!-- ── Security Settings ────────────────────────────────────── -->
        <div class="col-lg-8">
            <!-- ── Password Change ──────────────────────────────────── -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <strong><i class="bi bi-key me-2"></i>Change Password</strong>
                </div>
                <div class="card-body">
                    <form id="passwordChangeForm" autocomplete="off">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold" for="settings-current-password">Current Password</label>
                                <input type="password" class="form-control" id="settings-current-password" required autocomplete="current-password">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold" for="settings-new-password">New Password</label>
                                <input type="password" class="form-control" id="settings-new-password" required minlength="8" autocomplete="new-password">
                                <div class="form-text">At least 8 characters</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold" for="settings-confirm-password">Confirm New Password</label>
                                <input type="password" class="form-control" id="settings-confirm-password" required autocomplete="new-password">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" id="settings-password-btn">
                            <i class="bi bi-check-lg me-1"></i>Update Password
                        </button>
                    </form>
                </div>
            </div>

            <!-- ── Two-Factor Authentication ─────────────────────────── -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong><i class="bi bi-shield-lock me-2"></i>Two-Factor Authentication</strong>
                    <span id="tfa-status-badge" class="badge bg-secondary">Loading...</span>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Add an extra layer of security to your account. You can use an authenticator app,
                        email, or SMS for verification.
                    </p>

                    <!-- Method -->
                    <div class="mb-3">
                        <span class="text-muted small">Current method:</span>
                        <strong id="tfa-method">—</strong>
                    </div>

                    <!-- ── Setup section (shown when 2FA is disabled) ── -->
                    <div id="tfa-setup-section">
                        <p class="fw-semibold mb-2">Choose your 2FA method:</p>
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-outline-primary" id="tfa-setup-totp">
                                <i class="bi bi-phone me-1"></i>Authenticator App
                            </button>
                            <button class="btn btn-outline-primary" id="tfa-setup-email">
                                <i class="bi bi-envelope me-1"></i>Email
                            </button>
                            <button class="btn btn-outline-primary" id="tfa-setup-sms">
                                <i class="bi bi-chat-dots me-1"></i>SMS
                            </button>
                        </div>
                    </div>

                    <!-- ── QR / Verify section ────────────────────────── -->
                    <div id="tfa-verify-section" class="d-none">
                        <hr>
                        <div id="tfa-qr-container" class="d-none mb-3">
                            <p class="small text-muted mb-1">
                                Scan this QR code with your authenticator app (e.g. Google Authenticator, Authy):
                            </p>
                            <div class="bg-light d-inline-block p-3 rounded">
                                <img id="tfa-qr-img" src="" alt="QR Code"
                                     style="width:180px;height:180px;image-rendering:pixelated">
                            </div>
                            <p class="small text-muted mt-2 mb-0">
                                Can't scan? Enter this key manually:
                                <code id="tfa-secret-display" class="user-select-all"></code>
                            </p>
                        </div>
                        <div class="row g-2 align-items-end">
                            <div class="col-auto">
                                <label class="form-label small fw-semibold">
                                    Enter verification code (<span id="tfa-verify-method"></span>)
                                </label>
                                <input type="text" class="form-control" id="tfa-verify-code-input"
                                       placeholder="000000" maxlength="8" inputmode="numeric"
                                       autocomplete="one-time-code">
                            </div>
                            <div class="col-auto">
                                <button class="btn btn-primary" id="tfa-verify-code-btn">
                                    <i class="bi bi-check-circle me-1"></i>Verify & Enable
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ── Manage section (shown when 2FA is enabled) ── -->
                    <div id="tfa-manage-section" class="d-none">
                        <button class="btn btn-outline-danger" id="tfa-disable-btn">
                            <i class="bi bi-shield-slash me-1"></i>Disable 2FA
                        </button>
                        <p class="text-muted small mt-2 mb-0">
                            Disabling requires your password and current 2FA code.
                        </p>
                    </div>
                </div>
            </div>

            <!-- ── Backup Codes ──────────────────────────────────────── -->
            <div id="tfa-backup-section" class="d-none">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <strong><i class="bi bi-files me-2"></i>Backup Codes</strong>
                        <button class="btn btn-sm btn-outline-warning" id="tfa-backup-generate-btn">
                            <i class="bi bi-arrow-repeat me-1"></i>Generate New
                        </button>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-2">
                            Backup codes let you sign in if you lose access to your authenticator app or phone.
                            Each code can be used only once.
                        </p>
                        <div id="tfa-backup-codes-display" class="d-none"></div>
                    </div>
                </div>
            </div>

            <!-- ── Session Info ─────────────────────────────────────── -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <strong><i class="bi bi-info-circle me-2"></i>Session</strong>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-2">
                        Signed in as <strong id="settings-name-2">User</strong>.
                    </p>
                    <button class="btn btn-outline-danger btn-sm" onclick="showLogoutModal()">
                        <i class="bi bi-box-arrow-right me-1"></i>Sign Out
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Disable 2FA Confirmation Modal ──────────────────────────────── -->
<div class="modal fade" id="tfaDisableModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Disable 2FA</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Enter your password and current 2FA code to disable two-factor authentication.
                </p>
                <div class="mb-3">
                    <label class="form-label small fw-semibold" for="tfa-disable-password">Password</label>
                    <input type="password" class="form-control" id="tfa-disable-password" autocomplete="current-password">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold" for="tfa-disable-code">2FA Code</label>
                    <input type="text" class="form-control" id="tfa-disable-code" placeholder="000000" inputmode="numeric">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger" id="tfa-confirm-disable-btn">
                    <i class="bi bi-shield-slash me-1"></i>Disable
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/account_settings.js?v=<?= asset_version('js/pages/account_settings.js') ?>"></script>
