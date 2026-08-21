/**
 * qr_scanner.js — Dual-input QR scanner controller.
 * Supports: camera (html5-qrcode), USB HID keyboard-wedge, manual entry.
 * Context-aware verification: transport, exam, gate, attendance.
 */
const qrScannerController = {
    state: {
        context: 'transport',
        inputMethod: 'camera',
        scanning: false,
        html5QrCode: null,
        scanHistory: [],
        scanCount: 0,
    },

    API(method, endpoint, data, params, opts) {
        return window.callAPI(endpoint, method, data, params, opts);
    },

    notify(msg, type = 'success') {
        if (window.showNotification) { showNotification(msg, type); return; }
        const el = document.createElement('div');
        el.className = `alert alert-${type === 'error' ? 'danger' : type} position-fixed top-0 end-0 m-3 shadow`;
        el.style.zIndex = 99999;
        el.textContent = msg;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 3000);
    },

    esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); },
    money(n) { return 'KES ' + Number(n || 0).toLocaleString('en-KE', { minimumFractionDigits: 2 }); },

    /* ===========================================================
       INITIALIZATION
       =========================================================== */

    init() {
        this.bindContextTabs();
        this.bindInputToggle();
        this.bindManualInput();
        this.bindHidInput();
        this.startCamera();
        document.getElementById('btnStopCamera')?.addEventListener('click', () => this.stopCamera());
    },

    bindContextTabs() {
        document.querySelectorAll('.ctx-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.ctx-tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                this.state.context = tab.dataset.context;
                const transportOptions = document.getElementById('transportScanOptions');
                if (transportOptions) transportOptions.style.display = this.state.context === 'transport' ? 'block' : 'none';
                this.clearResult();
            });
        });
    },

    bindInputToggle() {
        document.querySelectorAll('#inputToggle .btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('#inputToggle .btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const method = btn.dataset.method;
                this.state.inputMethod = method;

                document.getElementById('cameraPanel').style.display = method === 'camera' ? 'block' : 'none';
                document.getElementById('hidPanel').style.display = method === 'hid' ? 'block' : 'none';
                document.getElementById('manualPanel').style.display = method === 'manual' ? 'block' : 'none';

                if (method === 'camera') this.startCamera();
                else this.stopCamera();

                if (method === 'hid') {
                    const hidInput = document.getElementById('hidInput');
                    setTimeout(() => hidInput?.focus(), 100);
                }
            });
        });
    },

    /* ===========================================================
       CAMERA SCANNING
       =========================================================== */

    async startCamera() {
        if (this.state.scanning) return;
        try {
            this.state.html5QrCode = new Html5Qrcode('qr-reader');
            await this.state.html5QrCode.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 200, height: 200 }, aspectRatio: 1.0 },
                (decodedText) => this.handleScanResult(decodedText),
                () => {} // ignore errors during scanning
            );
            this.state.scanning = true;
            this.updateCameraStatus(true);
        } catch (e) {
            console.warn('Camera start failed:', e);
            this.updateCameraStatus(false);
            this.notify('Camera not available — use USB Scanner or Manual input', 'warning');
        }
    },

    async stopCamera() {
        try {
            if (this.state.html5QrCode && this.state.scanning) {
                await this.state.html5QrCode.stop();
                this.state.scanning = false;
            }
        } catch (e) { console.warn('Camera stop error:', e); }
        this.updateCameraStatus(false);
    },

    updateCameraStatus(connected) {
        const dot = document.getElementById('camDot');
        if (dot) dot.classList.toggle('active', connected);
    },

    /* ===========================================================
       USB HID SCANNER (keyboard wedge)
       =========================================================== */

    bindHidInput() {
        const hidInput = document.getElementById('hidInput');
        if (!hidInput) return;

        let buffer = '';
        let lastKeyTime = 0;
        const SCANNER_SPEED_THRESHOLD = 50; // ms between chars — faster than human typing

        hidInput.addEventListener('input', (e) => {
            const now = Date.now();
            const timeDiff = now - lastKeyTime;

            // Detect rapid input characteristic of USB HID scanners
            if (timeDiff < SCANNER_SPEED_THRESHOLD && buffer.length > 0) {
                // Likely scanner input — accumulate
            }
            lastKeyTime = now;

            const val = hidInput.value;
            // If the value contains a complete QR code (JSON or card number pattern)
            if (this.looksLikeCompleteQr(val)) {
                buffer = '';
                hidInput.value = '';
                this.handleScanResult(val.trim());
                return;
            }

            buffer = val;
        });

        // Handle Enter key (some scanners append Enter)
        hidInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const val = hidInput.value.trim();
                if (val) {
                    hidInput.value = '';
                    buffer = '';
                    this.handleScanResult(val);
                }
            }
        });

        document.getElementById('btnHidSubmit')?.addEventListener('click', () => {
            const val = hidInput.value.trim();
            if (val) {
                hidInput.value = '';
                buffer = '';
                this.handleScanResult(val);
            }
        });

        // Auto-detect USB scanner connection
        this.detectHidScanner();
    },

    looksLikeCompleteQr(val) {
        if (!val) return false;
        // JSON QR payload
        if (val.startsWith('{') && val.endsWith('}')) {
            try { JSON.parse(val); return true; } catch { return false; }
        }
        // Card number pattern: KWA-YYYY-NNNNNN
        if (/^(?:KWA-\d{4}-\d{4,6}|KWA1\.[A-Za-z0-9_-]{32,})$/i.test(val.trim())) return true;
        // Numeric ID
        if (/^\d{3,8}$/.test(val.trim())) return true;
        return false;
    },

    async detectHidScanner() {
        // USB HID scanners appear as keyboard devices
        // We detect them by monitoring rapid sequential keypresses
        let rapidKeys = 0;
        let rapidTimer = null;

        document.addEventListener('keydown', (e) => {
            if (this.state.inputMethod !== 'hid') return;
            rapidKeys++;
            clearTimeout(rapidTimer);
            rapidTimer = setTimeout(() => { rapidKeys = 0; }, 200);
            if (rapidKeys > 5) {
                document.getElementById('hidDot')?.classList.add('active');
                document.getElementById('hidHint').innerHTML =
                    '<i class="bi bi-check-circle text-success"></i> USB scanner detected — ready to scan';
                document.getElementById('hidHint').classList.add('connected');
            }
        });
    },

    /* ===========================================================
       MANUAL INPUT
       =========================================================== */

    bindManualInput() {
        const manualInput = document.getElementById('manualInput');
        document.getElementById('btnManualSubmit')?.addEventListener('click', () => {
            const val = manualInput.value.trim();
            if (val) {
                this.handleScanResult(val);
                manualInput.value = '';
            }
        });
        manualInput?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const val = manualInput.value.trim();
                if (val) {
                    this.handleScanResult(val);
                    manualInput.value = '';
                }
            }
        });
    },

    /* ===========================================================
       SCAN RESULT HANDLER
       =========================================================== */

    async handleScanResult(rawData) {
        if (!rawData || rawData.length < 2) return;

        // Prevent duplicate scans within 2 seconds
        const now = Date.now();
        if (this._lastScanRaw === rawData && (now - (this._lastScanTime || 0)) < 2000) return;
        this._lastScanRaw = rawData;
        this._lastScanTime = now;

        const context = this.state.context;

        // INSTANT feedback — show scanning overlay immediately (< 1ms)
        this.showScanningIndicator();
        this.pulseScanner();

        const t0 = performance.now();

        try {
            const payload = {
                qr_data: rawData,
                context: context,
                client_reference: (window.crypto?.randomUUID ? window.crypto.randomUUID() : `scan-${Date.now()}-${Math.random().toString(16).slice(2)}`),
            };
            if (context === 'transport') {
                payload.action = document.getElementById('transportAction')?.value || 'verify';
                payload.trip_session = document.getElementById('tripSession')?.value || 'morning_pickup';
            }
            const response = await this.API('POST', 'scan/verify', payload);

            const elapsed = Math.round(performance.now() - t0);

            if (response.success) {
                this.renderResult(response, elapsed);
                this.addToHistory(response, elapsed);
                this.playAudio(response.student ? 'success' : 'info');
            } else {
                this.renderError(response.message || 'Verification failed', elapsed);
                this.playAudio('error');
            }

            // Auto-focus HID input for next scan
            if (this.state.inputMethod === 'hid') {
                setTimeout(() => document.getElementById('hidInput')?.focus(), 100);
            }
        } catch (e) {
            const elapsed = Math.round(performance.now() - t0);
            this.renderError(e.message || 'Network error', elapsed);
            this.playAudio('error');
        }
    },

    /* ===========================================================
       RESULT RENDERING
       =========================================================== */

    renderResult(data, elapsed) {
        const card = document.getElementById('resultCard');
        const header = document.getElementById('resultHeader');
        const icon = document.getElementById('resultIcon');
        const title = document.getElementById('resultTitle');
        const subtitle = document.getElementById('resultSubtitle');
        const body = document.getElementById('resultBody');

        const isEligible = data.eligible;
        const ctx = data.context;
        const cached = data.cached ? ' (cached)' : '';

        // Reset classes
        header.className = 'result-header ' + (isEligible ? 'eligible' : 'not-eligible');
        icon.className = isEligible ? 'bi bi-check-circle-fill' : 'bi bi-x-circle-fill';

        const student = data.student || {};
        title.innerHTML = `${this.esc(student.full_name || 'Unknown')} <span class="text-muted" style="font-size:0.8rem;">(${this.esc(student.admission_no || '')})</span>`;
        subtitle.textContent = `${student.class_name || ''} ${student.stream_name ? '- ' + student.stream_name : ''} | ${student.student_type || ''}${cached}`;

        // Elapsed time badge
        const speedBadge = elapsed != null
            ? `<span class="badge ${elapsed < 100 ? 'bg-success' : elapsed < 300 ? 'bg-info' : 'bg-warning'} ms-2" style="font-size:0.7rem;">${elapsed}ms${cached}</span>`
            : '';

        body.innerHTML = `<div class="d-flex justify-content-between align-items-center mb-2">${speedBadge}</div>` + this.buildContextDetails(ctx, data);
        card.classList.add('show');

        // Scroll to result
        card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    },

    buildContextDetails(ctx, data) {
        switch (ctx) {
            case 'transport': return this.renderTransportDetails(data);
            case 'exam':      return this.renderExamDetails(data);
            case 'gate':      return this.renderGateDetails(data);
            case 'attendance': return this.renderAttendanceDetails(data);
            default:          return '<p class="text-muted">Unknown context</p>';
        }
    },

    renderTransportDetails(data) {
        const route = data.route;
        const summary = data.summary;
        const bills = data.bills || [];

        if (!route) {
            return `
                <div class="detail-row"><span class="detail-label">Status</span>
                    <span class="badge bg-danger">Not Assigned</span></div>
                <div class="detail-row"><span class="detail-label">Message</span>
                    <span class="detail-value">${this.esc(data.message)}</span></div>`;
        }

        let html = `
            <div class="detail-row"><span class="detail-label">Route</span>
                <span class="detail-value">${this.esc(route.name)}</span></div>
            <div class="detail-row"><span class="detail-label">From</span>
                <span class="detail-value">${this.esc(route.start_point)}</span></div>
            <div class="detail-row"><span class="detail-label">To</span>
                <span class="detail-value">${this.esc(route.end_point)}</span></div>
            <div class="detail-row"><span class="detail-label">Morning Pickup</span>
                <span class="detail-value">${route.morning_departure || '—'}</span></div>
            <div class="detail-row"><span class="detail-label">Evening Drop-off</span>
                <span class="detail-value">${route.afternoon_departure || '—'}</span></div>
            <div class="detail-row"><span class="detail-label">Monthly Fee</span>
                <span class="detail-value">${this.money(route.fee)}</span></div>
            <div class="detail-row"><span class="detail-label">Transport Status</span>
                <span class="badge badge-active">${this.esc(data.status)}</span></div>`;

        if (summary) {
            const badgeClass = summary.status === 'cleared' ? 'badge-paid' :
                               summary.status === 'partial' ? 'badge-partial' : 'badge-unpaid';
            html += `
                <div class="mt-2 pt-2 border-top">
                    <div class="detail-row"><span class="detail-label">Total Billed</span>
                        <span class="detail-value">${this.money(summary.total_due)}</span></div>
                    <div class="detail-row"><span class="detail-label">Total Paid</span>
                        <span class="detail-value">${this.money(summary.total_paid)}</span></div>
                    <div class="detail-row"><span class="detail-label">Balance</span>
                        <span class="detail-value"><span class="badge ${badgeClass}">${this.money(summary.balance)} (${summary.status})</span></span></div>
                </div>`;
        }

        return html;
    },

    renderExamDetails(data) {
        const fee = data.fee_summary || {};
        const enrollment = data.enrollment || {};
        const isCleared = fee.clearance === 'cleared';
        const badgeClass = isCleared ? 'badge-paid' : 'badge-unpaid';

        return `
            <div class="detail-row"><span class="detail-label">Class</span>
                <span class="detail-value">${this.esc(enrollment.class)} ${enrollment.stream ? '- ' + this.esc(enrollment.stream) : ''}</span></div>
            <div class="detail-row"><span class="detail-label">Academic Year</span>
                <span class="detail-value">${this.esc(enrollment.year)}</span></div>
            <div class="detail-row"><span class="detail-label">Term</span>
                <span class="detail-value">${this.esc(enrollment.term)}</span></div>
            <div class="detail-row"><span class="detail-label">Enrollment</span>
                <span class="badge badge-active">${this.esc(enrollment.status)}</span></div>
            <div class="mt-2 pt-2 border-top">
                <div class="detail-row"><span class="detail-label">Total Billed</span>
                    <span class="detail-value">${this.money(fee.total_billed)}</span></div>
                <div class="detail-row"><span class="detail-label">Total Paid</span>
                    <span class="detail-value">${this.money(fee.total_paid)}</span></div>
                <div class="detail-row"><span class="detail-label">Fee Balance</span>
                    <span class="detail-value"><span class="badge ${badgeClass}">${this.money(fee.balance)} (${fee.clearance})</span></span></div>
                <div class="detail-row"><span class="detail-label">Exam Eligibility</span>
                    <span class="detail-value fw-bold ${isCleared ? 'text-success' : 'text-danger'}">
                        ${isCleared ? 'ELIGIBLE' : 'NOT ELIGIBLE'}
                    </span></div>
                ${data.upcoming_exams > 0 ? `<div class="detail-row"><span class="detail-label">Upcoming Exams</span>
                    <span class="detail-value">${data.upcoming_exams}</span></div>` : ''}
            </div>`;
    },

    renderGateDetails(data) {
        const parents = data.parents || [];
        const attendance = data.today_attendance || [];

        let html = `
            <div class="detail-row"><span class="detail-label">Entry Status</span>
                <span class="badge bg-success">Authorized</span></div>
            <div class="detail-row"><span class="detail-label">Checked In Today</span>
                <span class="detail-value">${data.checked_in_today ? '<span class="text-success fw-bold">Yes</span>' : '<span class="text-muted">No</span>'}</span></div>`;

        if (attendance.length > 0) {
            html += '<div class="mt-2 pt-2 border-top"><strong style="font-size:0.8rem;">Today\'s Attendance</strong>';
            attendance.forEach(a => {
                const color = a.status === 'present' ? 'success' : a.status === 'late' ? 'warning' : 'danger';
                html += `<div class="detail-row"><span class="detail-label">${this.esc(a.session)}</span>
                    <span class="badge bg-${color}">${a.status} ${a.check_in ? '(' + a.check_in + ')' : ''}</span></div>`;
            });
            html += '</div>';
        }

        if (parents.length > 0) {
            html += '<div class="mt-2 pt-2 border-top"><strong style="font-size:0.8rem;">Parent / Guardian</strong>';
            parents.forEach(p => {
                html += `<div class="detail-row"><span class="detail-label">${this.esc(p.relationship)}</span>
                    <span class="detail-value">${this.esc(p.name)} ${p.phone ? '<a href="tel:' + this.esc(p.phone) + '" class="ms-1"><i class="bi bi-telephone"></i> ' + this.esc(p.phone) + '</a>' : ''}</span></div>`;
            });
            html += '</div>';
        }

        if (data.transport_balance > 0) {
            html += `<div class="mt-2 pt-2 border-top">
                <div class="detail-row"><span class="detail-label">Transport Balance</span>
                    <span class="badge badge-partial">${this.money(data.transport_balance)}</span></div></div>`;
        }

        return html;
    },

    renderAttendanceDetails(data) {
        const record = data.record || {};
        const marked = data.attendance_marked;

        return `
            <div class="detail-row"><span class="detail-label">Status</span>
                <span class="badge ${marked ? 'bg-success' : 'bg-info'}">${this.esc(data.status)}</span></div>
            <div class="detail-row"><span class="detail-label">Date</span>
                <span class="detail-value">${this.esc(record.date || new Date().toISOString().slice(0,10))}</span></div>
            <div class="detail-row"><span class="detail-label">Check-in Time</span>
                <span class="detail-value">${this.esc(record.check_in || '—')}</span></div>
            <div class="detail-row"><span class="detail-label">Message</span>
                <span class="detail-value">${this.esc(data.message)}</span></div>
            ${marked ? '<div class="mt-2 text-center"><span class="badge bg-success" style="font-size:0.9rem; padding:0.5rem 1rem;"><i class="bi bi-check-circle me-1"></i>Attendance Recorded</span></div>' : ''}`;
    },

    renderError(message, elapsed) {
        const card = document.getElementById('resultCard');
        const header = document.getElementById('resultHeader');
        const icon = document.getElementById('resultIcon');
        const title = document.getElementById('resultTitle');
        const subtitle = document.getElementById('resultSubtitle');
        const body = document.getElementById('resultBody');

        header.className = 'result-header not-eligible';
        icon.className = 'bi bi-exclamation-triangle-fill';
        title.textContent = 'Verification Failed';
        const timeStr = elapsed != null ? ` <span class="text-muted" style="font-size:0.75rem;">(${elapsed}ms)</span>` : '';
        subtitle.innerHTML = message + timeStr;
        body.innerHTML = `<p class="text-muted mb-0">The scanned code could not be verified. Check that the student ID card is valid and try again.</p>`;
        card.classList.add('show');
    },

    clearResult() {
        document.getElementById('resultCard')?.classList.remove('show');
    },

    showScanningIndicator() {
        const indicator = document.getElementById('audioIndicator');
        if (indicator) {
            indicator.textContent = '⏳ Verifying...';
            indicator.className = 'audio-indicator info';
            indicator.style.display = 'block';
            setTimeout(() => { indicator.style.display = 'none'; }, 1500);
        }
    },

    pulseScanner() {
        const box = document.querySelector('.scan-box');
        if (!box) return;
        box.style.transition = 'border-color 0.1s, box-shadow 0.1s';
        box.style.borderColor = '#22c55e';
        box.style.boxShadow = '0 0 20px rgba(34,197,94,0.4)';
        setTimeout(() => {
            box.style.borderColor = '#d3ad24';
            box.style.boxShadow = 'none';
        }, 400);
    },

    /* ===========================================================
       AUDIO FEEDBACK
       =========================================================== */

    playAudio(type) {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);

            if (type === 'success') {
                osc.frequency.value = 880;
                gain.gain.value = 0.15;
                osc.start();
                osc.frequency.setValueAtTime(880, ctx.currentTime);
                osc.frequency.setValueAtTime(1100, ctx.currentTime + 0.1);
                osc.stop(ctx.currentTime + 0.25);
            } else if (type === 'error') {
                osc.frequency.value = 300;
                gain.gain.value = 0.15;
                osc.start();
                osc.frequency.setValueAtTime(300, ctx.currentTime);
                osc.frequency.setValueAtTime(200, ctx.currentTime + 0.15);
                osc.stop(ctx.currentTime + 0.3);
            } else {
                osc.frequency.value = 660;
                gain.gain.value = 0.1;
                osc.start();
                osc.stop(ctx.currentTime + 0.15);
            }
        } catch (e) { /* audio not supported */ }
    },

    /* ===========================================================
       SCAN HISTORY
       =========================================================== */

    addToHistory(data, elapsed) {
        const student = data.student || {};
        const ctx = data.context;
        const entry = {
            time: new Date().toLocaleTimeString(),
            name: student.full_name || 'Unknown',
            admission: student.admission_no || '',
            context: ctx,
            eligible: data.eligible,
            message: data.message || '',
            elapsed: elapsed || 0,
            cached: data.cached || false,
        };

        this.state.scanHistory.unshift(entry);
        if (this.state.scanHistory.length > 20) this.state.scanHistory.pop();
        this.state.scanCount++;

        this.renderHistory();
    },

    renderHistory() {
        const container = document.getElementById('scanHistory');
        const list = document.getElementById('historyList');
        if (!container || !list) return;

        if (this.state.scanHistory.length === 0) {
            container.style.display = 'none';
            return;
        }

        container.style.display = 'block';
        const ctxColors = { transport: 'primary', exam: 'warning', gate: 'info', attendance: 'success' };

        list.innerHTML = this.state.scanHistory.map(h => {
            const dotClass = h.eligible ? 'green' : 'red';
            const ctxBadge = ctxColors[h.context] || 'secondary';
            const timeColor = h.elapsed < 100 ? 'text-success' : h.elapsed < 300 ? 'text-info' : 'text-warning';
            const cachedTag = h.cached ? ' <span class="text-muted" style="font-size:0.65rem;">[cached]</span>' : '';
            return `
                <div class="scan-entry">
                    <div class="scan-dot ${dotClass}"></div>
                    <span class="scan-time">${this.esc(h.time)}</span>
                    <span class="scan-name">${this.esc(h.name)}</span>
                    <span class="scan-msg">${this.esc(h.message)}</span>
                    <span class="${timeColor}" style="font-size:0.7rem; white-space:nowrap;">${h.elapsed}ms${cachedTag}</span>
                    <span class="scan-ctx badge bg-${ctxBadge}">${this.esc(h.context)}</span>
                </div>`;
        }).join('');
    },
};

window.qrScannerController = qrScannerController;
document.addEventListener('DOMContentLoaded', () => qrScannerController.init());
