/* Bus gateway UI: USB HID scanner + offline queue + live driver manifest. */
(function () {
    'use strict';
    const queueKey = 'kingsway.transport.scan_queue.v1';
    const state = { manifest: null, busy: false };
    const $ = id => document.getElementById(id);
    const esc = value => String(value == null ? '' : value).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    const today = () => new Date().toISOString().slice(0, 10);
    const getQueue = () => { try { return JSON.parse(localStorage.getItem(queueKey) || '[]'); } catch (_) { return []; } };
    const saveQueue = rows => localStorage.setItem(queueKey, JSON.stringify(rows));
    const notify = (message, type) => window.showNotification ? window.showNotification(message, type) : null;

    function beep(ok) {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = ctx.createOscillator(); const gain = ctx.createGain();
            oscillator.connect(gain); gain.connect(ctx.destination); oscillator.frequency.value = ok ? 880 : 240; gain.gain.value = .12;
            oscillator.start(); oscillator.stop(ctx.currentTime + (ok ? .16 : .32));
        } catch (_) {}
    }
    function setConnectivity() {
        const el = $('operatorConnectivity'); if (!el) return;
        const online = navigator.onLine; el.className = 'operator-status ' + (online ? 'online' : 'offline');
        el.innerHTML = online ? '<i class="bi bi-wifi"></i> Online' : '<i class="bi bi-wifi-off"></i> Offline';
    }
    function updateQueueCount() {
        const n = getQueue().length; if ($('queuedScanCount')) $('queuedScanCount').textContent = n;
        if ($('manifestQueued')) $('manifestQueued').textContent = n;
    }
    function feedback(message, type) { const el = $('operatorScanFeedback'); if (!el) return; el.textContent = message; el.className = 'scan-feedback ' + (type || ''); }

    async function loadManifest() {
        const params = { date: $('operatorDate').value || today(), trip_session: $('operatorTripSession').value };
        try {
            const response = await window.API.transport.getDriverManifest(params);
            state.manifest = response?.data || response || null;
            renderManifest(); setConnectivity();
        } catch (error) {
            setConnectivity(); feedback(error.message || 'Manifest unavailable', 'error');
            if (!state.manifest) $('operatorManifestBody').innerHTML = '<tr><td colspan="5" class="text-center text-danger py-5">Unable to load the route manifest.</td></tr>';
        }
    }
    function renderManifest() {
        const m = state.manifest || {}; const rows = Array.isArray(m.students) ? m.students : []; const summary = m.summary || {};
        $('manifestExpected').textContent = summary.expected || 0; $('manifestBoarded').textContent = summary.boarded || 0; $('manifestRemaining').textContent = summary.remaining || 0;
        $('manifestGeneratedAt').textContent = m.generated_at ? 'Updated ' + new Date(m.generated_at.replace(' ', 'T')).toLocaleTimeString('en-KE',{hour:'2-digit',minute:'2-digit'}) : '—';
        const route = (m.routes || [])[0]; $('manifestRouteLabel').textContent = route ? `${route.name || ''} · ${route.vehicle_registration || 'Vehicle not assigned'}` : 'No active vehicle and route assigned'; $('manifestEmpty').style.display = route ? 'none' : 'block';
        $('operatorManifestBody').innerHTML = rows.length ? rows.map(s => {
            const status = s.boarding_status || 'not_boarded'; const boarded = status === 'boarded'; const dropped = status === 'dropped_off';
            const label = boarded ? 'Boarded' : dropped ? 'Dropped off' : 'Not boarded'; const cls = boarded ? 'status-boarded' : dropped ? 'status-dropped' : 'status-not-boarded';
            return `<tr class="${boarded ? 'row-boarded' : 'row-not-boarded'}"><td><strong>${esc(s.stop_name || 'Unassigned stop')}</strong><small class="d-block text-muted">${esc(s.arrival_time || '')}</small></td><td>${esc((s.first_name || '') + ' ' + (s.last_name || ''))}</td><td>${esc(s.admission_no)}</td><td><span class="status-pill ${cls}">${label}</span></td><td>${esc(s.marked_time || '—')}</td></tr>`;
        }).join('') : '<tr><td colspan="5" class="text-center text-muted py-5">No learners are assigned to this route for the selected date.</td></tr>';
    }
    async function submitScan(raw) {
        if (!raw || state.busy) return; state.busy = true; const session = $('operatorTripSession').value; const action = session === 'evening_dropoff' ? 'dropped_off' : 'picked_up';
        const payload = { qr_data: raw, context: 'transport', action, trip_session: session, client_reference: (window.crypto && window.crypto.randomUUID ? window.crypto.randomUUID() : 'bus-' + Date.now()) };
        try {
            if (!navigator.onLine) throw new Error('offline');
            const response = await window.callAPI('scan/verify', 'POST', payload);
            const data = response?.data || response || {}; const ok = !!data.eligible && !!data.attendance_marked;
            beep(ok); feedback(data.message || (ok ? 'Boarding recorded' : 'Boarding rejected'), ok ? 'success' : 'error');
            if (ok) await loadManifest();
        } catch (error) {
            if (error.message === 'offline' || !navigator.onLine) {
                const queue = getQueue(); queue.push({ payload, queued_at: new Date().toISOString() }); saveQueue(queue); beep(true); feedback('Offline — scan queued for synchronization', 'pending'); updateQueueCount();
            } else { beep(false); feedback(error.message || 'Scan rejected', 'error'); }
        } finally { state.busy = false; setTimeout(() => $('operatorScanInput')?.focus(), 80); }
    }
    async function syncQueue() {
        const queue = getQueue(); if (!queue.length || !navigator.onLine) return;
        const remaining = [];
        for (const item of queue) { try { const response = await window.callAPI('scan/verify','POST',item.payload); const data = response?.data || response || {}; if (!data.eligible || !data.attendance_marked) remaining.push(item); } catch (_) { remaining.push(item); } }
        saveQueue(remaining); updateQueueCount(); if (remaining.length < queue.length) await loadManifest(); feedback(remaining.length ? `${remaining.length} scan(s) still queued` : 'Queued scans synchronized', remaining.length ? 'pending' : 'success');
    }
    async function connectSerialScanner() {
        if (!('serial' in navigator)) { feedback('This browser does not support serial scanners; use HID keyboard mode.', 'error'); return; }
        try {
            const port = await navigator.serial.requestPort(); await port.open({ baudRate: 9600 });
            $('scannerDeviceStatus').innerHTML = '<i class="bi bi-usb-drive-fill"></i> Serial scanner connected';
            const decoder = new TextDecoderStream(); port.readable.pipeTo(decoder.writable).catch(() => {}); const reader = decoder.readable.getReader(); let buffer = '';
            while (true) { const { value, done } = await reader.read(); if (done) break; buffer += value || ''; const chunks = buffer.split(/[\\r\\n]+/); buffer = chunks.pop() || ''; for (const chunk of chunks) if (chunk.trim()) await submitScan(chunk.trim()); }
        } catch (error) { feedback(error.message || 'Unable to connect serial scanner', 'error'); }
    }
    function bind() {
        $('operatorDate').value = today(); $('operatorRefresh').onclick = loadManifest; $('operatorSync').onclick = syncQueue; $('operatorTripSession').onchange = loadManifest; $('operatorDate').onchange = loadManifest;
        $('connectSerialScanner').onclick = connectSerialScanner;
        $('operatorScanInput').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); const raw = e.currentTarget.value.trim(); e.currentTarget.value = ''; submitScan(raw); } });
        window.addEventListener('online', () => { setConnectivity(); syncQueue(); loadManifest(); }); window.addEventListener('offline', setConnectivity);
        setConnectivity(); updateQueueCount(); loadManifest();
        // Static realtime events refresh the manifest immediately. Keep only a
        // five-minute repair poll; offline scan synchronization remains local.
        setInterval(loadManifest, 300000); setInterval(syncQueue, 15000);
    }
    window.TransportOperatorController = { load: loadManifest };
    window.APIRealtime?.register?.('TransportOperatorController', window.TransportOperatorController, ['transport']);
    document.addEventListener('DOMContentLoaded', bind);
})();
