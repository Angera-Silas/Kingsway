const accountSettings = {
    initialized: false,
    state: {
        user: null,
        tfaStatus: null,
        pendingSetup: null, // { method: 'totp', secret, qr_code_url } or null
        showBackupCodes: false,
    },

    async init() {
        if (this.initialized) return;
        await window.AuthContext?.ready();
        if (!window.AuthContext?.isAuthenticated?.()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }
        this.initialized = true;
        this.setupEventListeners();
        await this.loadData();
    },

    setupEventListeners() {
        // ── 2FA method selection ──
        document.getElementById('tfa-setup-totp')?.addEventListener('click', () => this.setupTOTP());
        document.getElementById('tfa-setup-email')?.addEventListener('click', () => this.setupEmail());
        document.getElementById('tfa-setup-sms')?.addEventListener('click', () => this.setupSMS());

        // ── 2FA verify setup code ──
        document.getElementById('tfa-verify-code-btn')?.addEventListener('click', () => this.verifySetupCode());
        document.getElementById('tfa-verify-code-input')?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') this.verifySetupCode();
        });

        // ── 2FA disable ──
        document.getElementById('tfa-disable-btn')?.addEventListener('click', () => this.showDisableModal());
        document.getElementById('tfa-confirm-disable-btn')?.addEventListener('click', () => this.disableTFA());
        document.getElementById('tfa-disable-password')?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') this.disableTFA();
        });

        // ── Backup codes ──
        document.getElementById('tfa-backup-generate-btn')?.addEventListener('click', () => this.generateBackupCodes());

        // ── Password change ──
        document.getElementById('passwordChangeForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.changePassword();
        });
    },

    async loadData() {
        try {
            // Get current user info from AuthContext (already available)
            const user = window.AuthContext?.getUser?.() || {};
            this.state.user = user;
            this.renderUserInfo(user);

            // Get 2FA status
            const tfaResp = await window.API.apiCall('/twofactor/status', 'POST');
            this.state.tfaStatus = tfaResp;
            this.renderTFAStatus(tfaResp);
        } catch (e) {
            showNotification('Failed to load account settings', 'danger');
        }
    },

    // ═══════════════════════════════════════════════════════════════════
    //  Render Methods
    // ═══════════════════════════════════════════════════════════════════

    renderUserInfo(user) {
        const u = user || {};
        const el = (id) => document.getElementById(id);
        const name = `${u.first_name || ''} ${u.last_name || ''}`.trim() || u.username || 'User';
        if (el('settings-name')) el('settings-name').textContent = name;
        if (el('settings-name-2')) el('settings-name-2').textContent = name;
        if (el('settings-email')) el('settings-email').textContent = u.email || '—';
        if (el('settings-phone')) el('settings-phone').textContent = u.phone || u.phone_1 || '—';
        const roleNames = (u.roles || []).map(r => typeof r === 'string' ? r : r.name).filter(Boolean);
        if (el('settings-role')) el('settings-role').textContent = roleNames[0] || u.role_name || '—';
    },

    renderTFAStatus(status) {
        const data = status?.data || status || {};
        const enabled = data.enabled || data.tfa_enabled || false;
        const method = data.method || '—';

        const statusBadge = document.getElementById('tfa-status-badge');
        if (statusBadge) {
            statusBadge.textContent = enabled ? 'Enabled' : 'Disabled';
            statusBadge.className = `badge ${enabled ? 'bg-success' : 'bg-secondary'}`;
        }

        const methodDisplay = document.getElementById('tfa-method');
        if (methodDisplay) {
            methodDisplay.textContent = enabled ? method.toUpperCase() : '—';
        }

        // Toggle sections
        document.getElementById('tfa-setup-section')?.classList.toggle('d-none', enabled);
        document.getElementById('tfa-manage-section')?.classList.toggle('d-none', !enabled);
        document.getElementById('tfa-backup-section')?.classList.toggle('d-none', !enabled);
    },

    // ═══════════════════════════════════════════════════════════════════
    //  2FA Setup
    // ═══════════════════════════════════════════════════════════════════

    async setupTOTP() {
        try {
            const resp = await window.API.apiCall('/twofactor/setup/totp', 'POST');
            const d = resp?.data || {};
            this.state.pendingSetup = { method: 'totp', ...d };

            this.showSetupUI('totp', d.secret, d.qr_code_url);
        } catch (e) {
            showNotification(e.message || 'Failed to start TOTP setup', 'danger');
        }
    },

    async setupEmail() {
        try {
            const resp = await window.API.apiCall('/twofactor/setup/email', 'POST');
            const d = resp?.data || {};
            this.state.pendingSetup = { method: 'email', ...d };

            this.showSetupUI('email');
            showNotification(resp?.message || 'Verification code sent to your email', 'success');
        } catch (e) {
            showNotification(e.message || 'Failed to start email setup', 'danger');
        }
    },

    async setupSMS() {
        try {
            const resp = await window.API.apiCall('/twofactor/setup/sms', 'POST');
            const d = resp?.data || {};
            this.state.pendingSetup = { method: 'sms', ...d };

            this.showSetupUI('sms');
            showNotification(resp?.message || 'Verification code sent to your phone', 'success');
        } catch (e) {
            showNotification(e.message || 'Failed to start SMS setup', 'danger');
        }
    },

    showSetupUI(method, secret, qrUrl) {
        const section = document.getElementById('tfa-verify-section');
        if (!section) return;
        section.classList.remove('d-none');

        const methodLabel = document.getElementById('tfa-verify-method');
        if (methodLabel) {
            const names = { totp: 'Authenticator App', email: 'Email', sms: 'SMS' };
            methodLabel.textContent = names[method] || method.toUpperCase();
        }

        // Show QR code for TOTP
        const qrContainer = document.getElementById('tfa-qr-container');
        const secretDisplay = document.getElementById('tfa-secret-display');

        if (method === 'totp' && qrContainer && secretDisplay) {
            qrContainer.classList.remove('d-none');
            secretDisplay.textContent = secret || '';

            // Generate QR code via API or inline
            const qrImg = document.getElementById('tfa-qr-img');
            if (qrImg && qrUrl) {
                qrImg.src = qrUrl;
            } else if (qrUrl) {
                // Fallback: try generating from URL
                qrContainer.innerHTML = `<p class="text-muted small mb-1">Scan with your authenticator app:</p>
                    <pre class="bg-light p-2 rounded small text-center" style="word-break:break-all">${qrUrl}</pre>`;
            }
        } else if (qrContainer) {
            qrContainer.classList.add('d-none');
        }

        document.getElementById('tfa-verify-code-input').value = '';
        document.getElementById('tfa-verify-code-input').focus();
    },

    async verifySetupCode() {
        const code = document.getElementById('tfa-verify-code-input')?.value?.trim();
        if (!code || code.length < 4) {
            showNotification('Please enter a valid verification code', 'warning');
            return;
        }

        const btn = document.getElementById('tfa-verify-code-btn');
        if (btn) btn.disabled = true;

        try {
            const resp = await window.API.apiCall('/twofactor/setup/verify', 'POST', { code });
            const d = resp?.data || {};

            // Show backup codes
            if (d.backup_codes && d.backup_codes.length) {
                this.showBackupCodes(d.backup_codes);
            }

            showNotification(resp?.message || 'Two-factor authentication enabled successfully', 'success');

            // Refresh 2FA status
            this.state.pendingSetup = null;
            document.getElementById('tfa-verify-section')?.classList.add('d-none');
            document.getElementById('tfa-setup-section')?.classList.add('d-none');
            document.getElementById('tfa-manage-section')?.classList.remove('d-none');
            document.getElementById('tfa-backup-section')?.classList.remove('d-none');

            await this.loadData();
        } catch (e) {
            showNotification(e.message || 'Verification failed', 'danger');
        } finally {
            if (btn) btn.disabled = false;
        }
    },

    showBackupCodes(codes) {
        const container = document.getElementById('tfa-backup-codes-display');
        if (!container || !codes || !codes.length) return;

        container.innerHTML = `
            <div class="alert alert-warning">
                <strong>Save these backup codes!</strong> Each code can be used only once.
                If you lose access to your authenticator app, you'll need these to sign in.
            </div>
            <div class="bg-dark text-warning p-3 rounded small font-monospace text-center"
                 style="letter-spacing:0.1em">
                ${codes.map(c => `<div>${c}</div>`).join('')}
            </div>
            <button class="btn btn-sm btn-outline-secondary mt-2" onclick="
                navigator.clipboard.writeText('${codes.join('\\n')}');
                showNotification('Backup codes copied to clipboard', 'success');
            ">
                <i class="bi bi-clipboard me-1"></i>Copy to clipboard
            </button>
        `;
        container.classList.remove('d-none');
    },

    // ═══════════════════════════════════════════════════════════════════
    //  2FA Disable
    // ═══════════════════════════════════════════════════════════════════

    showDisableModal() {
        const modal = document.getElementById('tfaDisableModal');
        if (modal) {
            document.getElementById('tfa-disable-password').value = '';
            document.getElementById('tfa-disable-code').value = '';
            new bootstrap.Modal(modal).show();
        }
    },

    async disableTFA() {
        const password = document.getElementById('tfa-disable-password')?.value;
        const code = document.getElementById('tfa-disable-code')?.value?.trim();

        if (!password) {
            showNotification('Please enter your password', 'warning');
            return;
        }
        if (!code) {
            showNotification('Please enter your 2FA code', 'warning');
            return;
        }

        const btn = document.getElementById('tfa-confirm-disable-btn');
        if (btn) btn.disabled = true;

        try {
            const resp = await window.API.apiCall('/twofactor/disable', 'POST', { password, code });
            showNotification(resp?.message || 'Two-factor authentication disabled', 'success');

            bootstrap.Modal.getInstance(document.getElementById('tfaDisableModal'))?.hide();

            // Refresh state
            document.getElementById('tfa-manage-section')?.classList.add('d-none');
            document.getElementById('tfa-backup-section')?.classList.add('d-none');
            document.getElementById('tfa-setup-section')?.classList.remove('d-none');
            this.state.tfaStatus = { enabled: false };
            this.renderTFAStatus({ enabled: false });
        } catch (e) {
            showNotification(e.message || 'Failed to disable 2FA', 'danger');
        } finally {
            if (btn) btn.disabled = false;
        }
    },

    // ═══════════════════════════════════════════════════════════════════
    //  Backup Codes
    // ═══════════════════════════════════════════════════════════════════

    async generateBackupCodes() {
        if (!(await window.confirmAction('Confirm', 'Generate new backup codes? This will invalidate all existing codes.'))) {
            return;
        }

        const btn = document.getElementById('tfa-backup-generate-btn');
        if (btn) btn.disabled = true;

        try {
            const resp = await window.API.apiCall('/twofactor/backup/generate', 'POST');
            const d = resp?.data || {};

            if (d.backup_codes && d.backup_codes.length) {
                this.showBackupCodes(d.backup_codes);
            }

            showNotification(resp?.message || 'New backup codes generated', 'success');
        } catch (e) {
            showNotification(e.message || 'Failed to generate backup codes', 'danger');
        } finally {
            if (btn) btn.disabled = false;
        }
    },

    // ═══════════════════════════════════════════════════════════════════
    //  Password Change
    // ═══════════════════════════════════════════════════════════════════

    async changePassword() {
        const current = document.getElementById('settings-current-password')?.value;
        const newPass = document.getElementById('settings-new-password')?.value;
        const confirm = document.getElementById('settings-confirm-password')?.value;

        if (!current || !newPass || !confirm) {
            showNotification('Please fill in all password fields', 'warning');
            return;
        }
        if (newPass.length < 8) {
            showNotification('New password must be at least 8 characters', 'warning');
            return;
        }
        if (newPass !== confirm) {
            showNotification('New passwords do not match', 'warning');
            return;
        }

        const btn = document.getElementById('settings-password-btn');
        if (btn) btn.disabled = true;

        try {
            await window.API.apiCall('/users/password-change', 'PUT', {
                current_password: current,
                new_password: newPass,
            });
            showNotification('Password updated successfully', 'success');
            document.getElementById('passwordChangeForm')?.reset();
        } catch (e) {
            showNotification(e.message || 'Failed to update password', 'danger');
        } finally {
            if (btn) btn.disabled = false;
        }
    },
};

// Auto-init after DOM is ready
document.addEventListener('DOMContentLoaded', () => accountSettings.init());
