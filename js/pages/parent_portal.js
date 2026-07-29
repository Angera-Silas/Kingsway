const parentPortalController = {
    BASE: window.APP_BASE || '',
    state: {
        token: null,
        otpSessionId: null,
        selectedStudentId: null,
        activeDetailTab: 'fees',
    },

    async init() {
        if (window.AuthContext?.ready) await window.AuthContext.ready();
        var stored = sessionStorage.getItem('pp_token');
        var expires = sessionStorage.getItem('pp_expires');
        if (stored && expires && new Date(expires) > new Date()) {
            this.state.token = stored;
            this.showView('dashboard');
            this.loadDashboard();
        } else {
            this.clearAuth();
            this.showView('auth');
        }
        this.bindEvents();
    },

    showView(name) {
        document.querySelectorAll('.view').forEach(function (v) { v.classList.remove('active'); });
        var el = document.getElementById('view-' + name);
        if (el) el.classList.add('active');
    },

    apiFetch(path, method, body) {
        var opts = { noRedirect: true };
        if (this.state.token) opts.headers = { Authorization: 'Bearer ' + this.state.token };
        return Promise.resolve(apiCall('/parent-portal' + path, method || 'GET', body, null, opts))
            .then(function (data) { return data; });
    },

    bindEvents() {
        var self = this;
        document.querySelectorAll('#loginTabs .nav-link').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('#loginTabs .nav-link').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                self.setView('tab-email', btn.dataset.tab === 'email');
                self.setView('tab-otp', btn.dataset.tab === 'otp');
            });
        });
        this.on('togglePwd', 'click', function () {
            var pwd = document.getElementById('loginPassword');
            var icon = document.querySelector('#togglePwd i');
            if (pwd.type === 'password') { pwd.type = 'text'; icon.className = 'bi bi-eye-slash'; }
            else { pwd.type = 'password'; icon.className = 'bi bi-eye'; }
        });
        this.on('btnEmailLogin', 'click', function () { self.submitEmailLogin(); });
        this.on('loginPassword', 'keydown', function (e) { if (e.key === 'Enter') self.submitEmailLogin(); });
        this.on('btnRequestOtp', 'click', function () { self.requestOTP(); });
        this.on('btnVerifyOtp', 'click', function () { self.verifyOTP(); });
        this.on('btnResendOtp', 'click', function () { self.setView('otp-step-2', false); self.setView('otp-step-1', true); });
        this.on('btnLogout', 'click', function () { self.logout(); });
        this.on('btnBackToDashboard', 'click', function () { self.showView('dashboard'); });
        document.querySelectorAll('#studentDetailTabs .nav-link').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('#studentDetailTabs .nav-link').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                self.state.activeDetailTab = btn.dataset.tab;
                self.loadStudentTab(btn.dataset.tab);
            });
        });
    },

    submitEmailLogin() {
        var email = this.val('loginEmail');
        var password = this.val('loginPassword');
        var errEl = document.getElementById('loginError');
        var spinner = document.getElementById('loginSpinner');
        var self = this;
        errEl.classList.add('d-none');
        if (!email || !password) { this.showErr(errEl, 'Email and password are required'); return; }
        spinner.classList.remove('d-none');
        this.apiFetch('/login', 'POST', { email: email, password: password })
            .then(function (resp) {
                var d = resp.data || resp;
                self.storeAuth(d.token, d.expires_at, d.parent);
                self.showView('dashboard');
                self.loadDashboard();
            })
            .catch(function (err) { self.showErr(errEl, err.message || 'Login failed'); })
            .finally(function () { spinner.classList.add('d-none'); });
    },

    requestOTP() {
        var phone = this.val('otpPhone');
        var errEl = document.getElementById('otpRequestError');
        var self = this;
        errEl.classList.add('d-none');
        if (!phone) { this.showErr(errEl, 'Phone number required'); return; }
        this.apiFetch('/login-otp-request', 'POST', { phone: phone })
            .then(function (resp) {
                var d = resp.data || resp;
                self.state.otpSessionId = d.otp_session_id;
                self.setView('otp-step-1', false);
                self.setView('otp-step-2', true);
            })
            .catch(function (err) { self.showErr(errEl, err.message || 'Failed to send OTP'); });
    },

    verifyOTP() {
        var code = this.val('otpCode');
        var errEl = document.getElementById('otpVerifyError');
        var self = this;
        errEl.classList.add('d-none');
        if (!code || !this.state.otpSessionId) { this.showErr(errEl, 'Enter the OTP code'); return; }
        this.apiFetch('/login-otp-verify', 'POST', { otp_session_id: this.state.otpSessionId, otp_code: code })
            .then(function (resp) {
                var d = resp.data || resp;
                self.storeAuth(d.token, d.expires_at, d.parent);
                self.showView('dashboard');
                self.loadDashboard();
            })
            .catch(function (err) { self.showErr(errEl, err.message || 'Invalid OTP'); });
    },

    logout() {
        this.apiFetch('/logout', 'POST').catch(function () {});
        this.clearAuth();
        this.showView('auth');
    },

    loadDashboard() {
        var self = this;
        this.setLoading(true);
        this.apiFetch('/dashboard', 'GET')
            .then(function (resp) {
                var d = resp.data || resp;
                var parent = d.parent || {};
                var nameEl = document.getElementById('parentName');
                if (nameEl) nameEl.textContent = parent.first_name || 'Parent';
                self.renderChildren(d.children || []);
            })
            .catch(function (err) {
                document.getElementById('childrenCards').innerHTML =
                    '<div class="col-12"><div class="alert alert-danger">Failed to load dashboard: ' + self.esc(err.message) + '</div></div>';
            })
            .finally(function () { self.setLoading(false); });
    },

    renderChildren(children) {
        var container = document.getElementById('childrenCards');
        if (!children.length) {
            container.innerHTML = '<div class="col-12"><div class="alert alert-info text-center">No children linked to this account. Contact the school office.</div></div>';
            return;
        }
        container.innerHTML = children.map(function (c) {
            var balance = parseFloat(c.current_balance || 0);
            var bc = balance <= 0 ? 'success' : (balance < 5000 ? 'warning' : 'danger');
            var balTxt = balance <= 0 ? 'Fees Cleared' : 'KES ' + balance.toLocaleString() + ' Due';
            return '<div class="col-md-6 col-lg-4 mb-3">' +
                '<div class="card border-0 shadow-sm rounded-4 child-card h-100" onclick="parentPortalController.openStudent(' + c.id + ',\'' + parentPortalController.esc(c.first_name + ' ' + c.last_name) + '\',\'' + parentPortalController.esc(c.class_name || '') + '\')">' +
                '<div class="card-body p-4">' +
                '<div class="d-flex align-items-center mb-3">' +
                '<div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center me-3" style="width:48px;height:48px;font-size:1.4rem;font-weight:700">' +
                parentPortalController.esc((c.first_name || '?')[0].toUpperCase()) + '</div>' +
                '<div><h6 class="mb-0 fw-bold">' + parentPortalController.esc(c.first_name + ' ' + c.last_name) + '</h6>' +
                '<small class="text-muted">' + parentPortalController.esc(c.class_name || '') + ' ' + parentPortalController.esc(c.admission_no || '') + '</small></div></div>' +
                '<div class="d-flex justify-content-between align-items-center">' +
                '<span class="text-muted small">Current Balance</span>' +
                '<span class="badge bg-' + bc + ' px-3 py-2">' + balTxt + '</span></div>' +
                (c.last_payment_date ? '<small class="text-muted d-block mt-2">Last payment: ' + c.last_payment_date.substring(0, 10) + '</small>' : '') +
                '</div></div></div>';
        }).join('');
    },

    openStudent(studentId, studentName, className) {
        this.state.selectedStudentId = studentId;
        this.setText('studentDetailName', studentName);
        this.setText('studentDetailClass', className);
        this.showView('student');
        this.loadBalanceSummary(studentId);
        document.querySelectorAll('#studentDetailTabs .nav-link').forEach(function (b) { b.classList.remove('active'); });
        var first = document.querySelector('#studentDetailTabs .nav-link');
        if (first) first.classList.add('active');
        this.state.activeDetailTab = 'fees';
        this.loadStudentTab('fees');
    },

    loadBalanceSummary(studentId) {
        var self = this;
        this.apiFetch('/fee-balance/' + studentId, 'GET')
            .then(function (resp) {
                var d = resp.data || resp;
                var rows = d.per_term || [];
                var cur = rows[0] || {};
                var total = parseFloat(d.total_balance || 0);
                document.getElementById('balanceSummaryCards').innerHTML = [
                    self.summCard('Total Outstanding', 'KES ' + total.toLocaleString(), total > 0 ? 'danger' : 'success', 'fas fa-wallet'),
                    self.summCard('Current Term Due', 'KES ' + Number(cur.total_due || 0).toLocaleString(), 'primary', 'fas fa-calendar'),
                    self.summCard('Current Term Paid', 'KES ' + Number(cur.total_paid || 0).toLocaleString(), 'info', 'fas fa-check-circle'),
                ].join('');
            })
            .catch(function () {});
    },

    summCard(label, value, color, icon) {
        return '<div class="col-md-4 mb-2"><div class="card border-0 shadow-sm rounded-4 kw-balance-card bg-' + color + ' bg-opacity-10">' +
            '<div class="card-body p-3 d-flex align-items-center">' +
            '<div class="me-3 fs-3 text-' + color + '"><i class="' + icon + '"></i></div>' +
            '<div><div class="text-muted small">' + label + '</div><div class="fw-bold fs-6">' + value + '</div></div>' +
            '</div></div></div>';
    },

    loadStudentTab(tab) {
        var id = this.state.selectedStudentId;
        var content = document.getElementById('studentDetailContent');
        var self = this;
        content.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
        if (tab === 'fees') {
            this.apiFetch('/student-fees/' + id, 'GET')
                .then(function (resp) { self.renderFeeHistory(resp.data || resp); })
                .catch(function (e) { content.innerHTML = '<div class="alert alert-danger">Failed to load fees: ' + self.esc(e.message) + '</div>'; });
        } else if (tab === 'payments') {
            this.apiFetch('/student-payment-history/' + id, 'GET')
                .then(function (resp) { self.renderPaymentHistory(resp.data || resp); })
                .catch(function () { content.innerHTML = '<div class="alert alert-danger">Failed to load payments</div>'; });
        } else if (tab === 'statement') {
            this.renderStatementView(id);
        }
    },

    renderFeeHistory(data) {
        var content = document.getElementById('studentDetailContent');
        var years = data.academic_years || data || [];
        if (!years.length) { content.innerHTML = '<div class="alert alert-info">No fee history found.</div>'; return; }
        content.innerHTML = years.map(function (yr) {
            return '<div class="card mb-3 border-0 shadow-sm">' +
                '<div class="card-header bg-primary text-white fw-bold">Academic Year ' + yr.year + '</div>' +
                '<div class="card-body">' +
                (yr.terms || []).map(function (term) {
                    var rows = (term.obligations || []).map(function (o) {
                        var sc = o.payment_status === 'paid' ? 'success' : (o.payment_status === 'partial' ? 'warning' : 'danger');
                        return '<tr><td>' + parentPortalController.esc(o.fee_type_name || '') + '</td>' +
                            '<td>KES ' + Number(o.amount_due || 0).toLocaleString() + '</td>' +
                            '<td>KES ' + Number(o.amount_paid || 0).toLocaleString() + '</td>' +
                            '<td><strong>KES ' + Number(o.balance || 0).toLocaleString() + '</strong></td>' +
                            '<td><span class="badge bg-' + sc + '">' + parentPortalController.esc(o.payment_status || 'pending') + '</span></td></tr>';
                    }).join('');
                    return '<h6 class="text-muted mb-2">' + parentPortalController.esc(term.term_name || '') + '</h6>' +
                        '<table class="table table-sm table-bordered mb-3"><thead class="table-light"><tr><th>Fee Type</th><th>Billed</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead>' +
                        '<tbody>' + rows + '</tbody>' +
                        '<tfoot class="fw-bold table-light"><tr><td>Total</td>' +
                        '<td>KES ' + Number(term.total_due || 0).toLocaleString() + '</td>' +
                        '<td>KES ' + Number(term.total_paid || 0).toLocaleString() + '</td>' +
                        '<td>KES ' + Number(term.balance || 0).toLocaleString() + '</td><td></td></tr></tfoot></table>';
                }).join('') +
                '</div></div>';
        }).join('');
    },

    renderPaymentHistory(payments) {
        var content = document.getElementById('studentDetailContent');
        if (!payments || !payments.length) { content.innerHTML = '<div class="alert alert-info">No payment records found.</div>'; return; }
        var rows = payments.map(function (p) {
            return '<tr><td>' + (p.payment_date || '').substring(0, 10) + '</td>' +
                '<td><span class="badge bg-secondary">' + parentPortalController.esc(p.payment_method || '') + '</span></td>' +
                '<td>KES ' + Number(p.amount_paid || 0).toLocaleString() + '</td>' +
                '<td>' + parentPortalController.esc(p.receipt_no || '') + '</td>' +
                '<td>' + parentPortalController.esc(p.reference_no || '') + '</td>' +
                '<td>' + parentPortalController.esc(p.term_name || '') + '</td></tr>';
        }).join('');
        document.getElementById('studentDetailContent').innerHTML =
            '<div class="table-responsive"><table class="table table-hover"><thead class="table-light"><tr>' +
            '<th>Date</th><th>Method</th><th>Amount</th><th>Receipt #</th><th>Reference</th><th>Term</th>' +
            '</tr></thead><tbody>' + rows + '</tbody></table></div>';
    },

    renderStatementView(studentId) {
        var content = document.getElementById('studentDetailContent');
        var self = this;
        content.innerHTML = '<div class="text-center py-3">' +
            '<p class="text-muted">Generate a printable fee statement for this student.</p>' +
            '<button class="btn btn-primary" id="btnGenStmt"><i class="fas fa-file-invoice me-2"></i>Generate Statement</button></div>';
        document.getElementById('btnGenStmt').addEventListener('click', function () {
            var btn = this;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
            self.apiFetch('/student-statement/' + studentId, 'GET')
                .then(function (resp) {
                    var d = resp.data || resp;
                    PrintManager.printHtml(self.buildStatementHTML(d), { title: 'Parent Statement' });
                })
                .catch(function (e) {
                    content.innerHTML = '<div class="alert alert-danger">Failed to generate statement: ' + self.esc(e.message) + '</div>';
                })
                .finally(function () { btn.disabled = false; btn.innerHTML = '<i class="fas fa-file-invoice me-2"></i>Generate Statement'; });
        });
    },

    buildStatementHTML(data) {
        var s = data.student || {};
        var fees = (data.fees && data.fees.academic_years) || [];
        var pmts = data.payments || [];
        var self = this;
        var feeRows = fees.map(function (yr) {
            return '<h5>Academic Year ' + yr.year + '</h5>' +
                (yr.terms || []).map(function (t) {
                    return '<p><strong>' + self.esc(t.term_name || '') + '</strong></p>' +
                        '<table border="1" cellpadding="4" style="border-collapse:collapse;width:100%"><thead><tr><th>Fee Type</th><th>Amount Due</th><th>Paid</th><th>Balance</th></tr></thead><tbody>' +
                        (t.obligations || []).map(function (o) {
                            return '<tr><td>' + self.esc(o.fee_type_name || '') + '</td>' +
                                '<td>KES ' + Number(o.amount_due || 0).toLocaleString() + '</td>' +
                                '<td>KES ' + Number(o.amount_paid || 0).toLocaleString() + '</td>' +
                                '<td>KES ' + Number(o.balance || 0).toLocaleString() + '</td></tr>';
                        }).join('') +
                        '</tbody></table>';
                }).join('');
        }).join('');
        var pmtRows = pmts.map(function (p) {
            return '<tr><td>' + (p.payment_date || '').substring(0, 10) + '</td>' +
                '<td>' + self.esc(p.payment_method || '') + '</td>' +
                '<td>KES ' + Number(p.amount_paid || 0).toLocaleString() + '</td>' +
                '<td>' + self.esc(p.receipt_no || '') + '</td></tr>';
        }).join('');
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Fee Statement</title>' +
            '<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">' +
            '</head><body class="p-4">' +
            '<h3 class="text-center">Kingsway Preparatory School</h3>' +
            '<h5 class="text-center text-muted">Fee Statement</h5><hr>' +
            '<p><strong>Student:</strong> ' + self.esc(s.first_name + ' ' + s.last_name) +
            ' &nbsp; <strong>Adm No:</strong> ' + self.esc(s.admission_no || '') +
            ' &nbsp; <strong>Class:</strong> ' + self.esc(s.class_name || '') + '</p>' +
            '<p><strong>Generated:</strong> ' + self.esc(data.generated_at || '') + '</p><hr>' +
            feeRows +
            (pmtRows ? '<h5 class="mt-4">Payment History</h5><table border="1" cellpadding="4" style="border-collapse:collapse;width:100%"><thead><tr><th>Date</th><th>Method</th><th>Amount</th><th>Receipt #</th></tr></thead><tbody>' + pmtRows + '</tbody></table>' : '') +
            '</body></html>';
    },

    storeAuth(token, expiresAt, parent) {
        this.state.token = token;
        sessionStorage.setItem('pp_token', token);
        sessionStorage.setItem('pp_expires', expiresAt || '');
        if (parent) {
            var nameEl = document.getElementById('parentName');
            if (nameEl) nameEl.textContent = parent.first_name || 'Parent';
        }
    },

    clearAuth() {
        this.state.token = null;
        sessionStorage.removeItem('pp_token');
        sessionStorage.removeItem('pp_expires');
    },

    setLoading(on) {
        var el = document.getElementById('portal-loading');
        if (el) el.style.display = on ? 'block' : 'none';
    },

    showErr(el, msg) { el.textContent = msg; el.classList.remove('d-none'); },
    val(id) { return (document.getElementById(id) || {}).value || ''; },
    setText(id, txt) { var el = document.getElementById(id); if (el) el.textContent = txt; },
    on(id, ev, fn) { var el = document.getElementById(id); if (el) el.addEventListener(ev, fn); },
    setView(id, visible) { var el = document.getElementById(id); if (el) el.style.display = visible ? 'block' : 'none'; },
    esc(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { parentPortalController.init().catch(function () {}); });
} else {
    parentPortalController.init().catch(function () {});
}
