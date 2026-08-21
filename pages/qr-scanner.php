<?php
$currentPage = 'qr-scanner';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Scanner — Verification</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/app-common.css?v=<?= asset_version('css/app-common.css') ?>">
    <style>
        :root { --kps-green: #0f5b3b; --kps-gold: #d3ad24; }
        body { background: #f0f4f2; font-family: 'Inter', system-ui, sans-serif; }

        /* Scanner Hero */
        .scanner-hero {
            background: linear-gradient(135deg, var(--kps-green), #0a3d28);
            color: #fff; padding: 1.5rem 0; text-align: center;
        }
        .scanner-hero h1 { font-size: 1.6rem; font-weight: 800; margin-bottom: 0.3rem; }
        .scanner-hero .subtitle { color: var(--kps-gold); font-size: 0.9rem; }

        /* Context Tabs */
        .context-tabs {
            display: flex; gap: 0.5rem; justify-content: center;
            padding: 1rem 0; flex-wrap: wrap;
        }
        .ctx-tab {
            padding: 0.6rem 1.2rem; border-radius: 8px; border: 2px solid #e5e7eb;
            background: #fff; cursor: pointer; font-weight: 600; font-size: 0.85rem;
            transition: all 0.2s;
        }
        .ctx-tab:hover { border-color: var(--kps-green); background: #f0fdf4; }
        .ctx-tab.active {
            border-color: var(--kps-green); background: var(--kps-green);
            color: #fff; box-shadow: 0 2px 8px rgba(15,91,59,0.3);
        }
        .ctx-tab i { margin-right: 0.4rem; }

        /* Input Method Toggle */
        .input-toggle {
            display: flex; gap: 0; justify-content: center;
            margin-bottom: 1rem;
        }
        .input-toggle .btn {
            border-radius: 0; font-size: 0.8rem; font-weight: 600;
        }
        .input-toggle .btn:first-child { border-radius: 8px 0 0 8px; }
        .input-toggle .btn:last-child  { border-radius: 0 8px 8px 0; }

        /* Scanner Container */
        .scanner-wrapper {
            position: relative; max-width: 500px; margin: 0 auto 1.5rem;
            border-radius: 12px; overflow: hidden; background: #000;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        #qr-reader { width: 100%; }
        #qr-reader video { border-radius: 12px; }
        .scanner-overlay {
            position: absolute; inset: 0; pointer-events: none;
            display: flex; align-items: center; justify-content: center;
        }
        .scan-box {
            width: 200px; height: 200px; border: 3px solid var(--kps-gold);
            border-radius: 12px; box-shadow: 0 0 0 4000px rgba(0,0,0,0.3);
        }
        .scanner-controls { padding: 0.8rem; background: #fff; text-align: center; }

        /* HID Input */
        .hid-panel {
            max-width: 500px; margin: 0 auto 1.5rem; display: none;
        }
        .hid-input-group {
            display: flex; gap: 0.5rem; align-items: stretch;
        }
        #hidInput {
            flex: 1; font-size: 1.1rem; padding: 0.8rem 1rem;
            border: 2px solid #e5e7eb; border-radius: 8px;
            font-family: 'Courier New', monospace; letter-spacing: 1px;
        }
        #hidInput:focus { border-color: var(--kps-green); outline: none; box-shadow: 0 0 0 3px rgba(15,91,59,0.15); }
        .hid-status {
            text-align: center; padding: 0.5rem; font-size: 0.8rem;
            color: #6b7280; margin-top: 0.5rem;
        }
        .hid-status.connected { color: var(--kps-green); font-weight: 600; }

        /* Manual Input */
        .manual-panel {
            max-width: 500px; margin: 0 auto 1.5rem; display: none;
        }

        /* Result Card */
        .result-card {
            max-width: 600px; margin: 0 auto 1.5rem;
            border-radius: 12px; overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            transition: all 0.3s; display: none;
        }
        .result-card.show { display: block; animation: slideUp 0.3s ease; }
        .result-header {
            padding: 1rem 1.2rem; display: flex; align-items: center; gap: 1rem;
        }
        .result-header.eligible    { background: #ecfdf5; border-left: 4px solid #22c55e; }
        .result-header.not-eligible { background: #fef2f2; border-left: 4px solid #ef4444; }
        .result-header.info        { background: #eff6ff; border-left: 4px solid #3b82f6; }
        .result-header.warning     { background: #fffbeb; border-left: 4px solid #f59e0b; }
        .result-icon {
            width: 48px; height: 48px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; flex-shrink: 0;
        }
        .result-header.eligible .result-icon    { background: #dcfce7; color: #16a34a; }
        .result-header.not-eligible .result-icon { background: #fecaca; color: #dc2626; }
        .result-header.info .result-icon        { background: #dbeafe; color: #2563eb; }
        .result-header.warning .result-icon     { background: #fef3c7; color: #d97706; }
        .result-title { font-weight: 700; font-size: 1rem; }
        .result-subtitle { font-size: 0.85rem; color: #6b7280; margin-top: 0.2rem; }
        .result-body { padding: 1rem 1.2rem; }
        .result-body .detail-row {
            display: flex; justify-content: space-between; padding: 0.4rem 0;
            border-bottom: 1px solid #f3f4f6; font-size: 0.85rem;
        }
        .result-body .detail-row:last-child { border-bottom: none; }
        .result-body .detail-label { color: #6b7280; }
        .result-body .detail-value { font-weight: 600; }
        .result-body .badge-paid      { background: #dcfce7; color: #16a34a; }
        .result-body .badge-partial   { background: #fef3c7; color: #d97706; }
        .result-body .badge-unpaid    { background: #fecaca; color: #dc2626; }
        .result-body .badge-active    { background: #dcfce7; color: #16a34a; }

        /* Scan History */
        .scan-history {
            max-width: 700px; margin: 0 auto 2rem;
        }
        .scan-history h6 { color: #374151; font-weight: 700; margin-bottom: 0.8rem; }
        .scan-entry {
            display: flex; align-items: center; gap: 0.8rem;
            padding: 0.6rem 0.8rem; border-radius: 8px; margin-bottom: 0.5rem;
            background: #fff; border: 1px solid #f3f4f6; font-size: 0.82rem;
        }
        .scan-entry .scan-dot {
            width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
        }
        .scan-entry .scan-dot.green  { background: #22c55e; }
        .scan-entry .scan-dot.red    { background: #ef4444; }
        .scan-entry .scan-dot.yellow { background: #f59e0b; }
        .scan-entry .scan-dot.blue   { background: #3b82f6; }
        .scan-time { color: #9ca3af; font-size: 0.75rem; white-space: nowrap; }
        .scan-name { font-weight: 600; flex: 1; }
        .scan-msg  { color: #6b7280; flex: 2; }
        .scan-ctx  {
            padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.7rem;
            font-weight: 600; text-transform: uppercase;
        }

        /* Audio indicator */
        .audio-indicator {
            position: fixed; top: 1rem; right: 1rem; z-index: 9999;
            padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600;
            font-size: 0.8rem; display: none;
            animation: fadeInOut 1.5s ease;
        }
        .audio-indicator.success { background: #dcfce7; color: #16a34a; }
        .audio-indicator.error   { background: #fecaca; color: #dc2626; }

        @keyframes slideUp  { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
        @keyframes fadeInOut { 0%{opacity:0} 20%{opacity:1} 80%{opacity:1} 100%{opacity:0} }

        /* Connection status */
        .conn-status {
            display: inline-flex; align-items: center; gap: 0.3rem;
            font-size: 0.75rem; color: #9ca3af;
        }
        .conn-dot { width: 6px; height: 6px; border-radius: 50%; background: #d1d5db; }
        .conn-dot.active { background: #22c55e; }

        @media (max-width: 640px) {
            .scanner-hero h1 { font-size: 1.2rem; }
            .scan-entry { flex-wrap: wrap; }
        }
    </style>
</head>
<body>

<div class="scanner-hero">
    <div class="container">
        <h1><i class="bi bi-qr-code-scan me-2"></i>QR Verification Scanner</h1>
        <div class="subtitle">Scan student IDs for real-time verification</div>
        <div class="d-flex justify-content-center gap-3 mt-2" style="font-size:0.75rem; color:#d1d5db;">
            <span id="cameraStatus" class="conn-status"><span class="conn-dot" id="camDot"></span> Camera</span>
            <span id="hidStatus" class="conn-status"><span class="conn-dot" id="hidDot"></span> USB Scanner</span>
        </div>
    </div>
</div>

<div class="container-fluid px-3 py-3">

    <!-- Context Tabs -->
    <div class="context-tabs" id="contextTabs">
        <button class="ctx-tab active" data-context="transport">
            <i class="bi bi-bus-front"></i>Transport
        </button>
        <button class="ctx-tab" data-context="exam">
            <i class="bi bi-pencil-square"></i>Exams
        </button>
        <button class="ctx-tab" data-context="gate">
            <i class="bi bi-shield-check"></i>Gate
        </button>
        <button class="ctx-tab" data-context="attendance">
            <i class="bi bi-clipboard-check"></i>Attendance
        </button>
    </div>

    <div id="transportScanOptions" class="mx-auto mb-3" style="max-width:500px;">
        <div class="card border-0 shadow-sm"><div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-7"><label class="form-label small fw-semibold mb-1" for="transportAction">Transport action</label>
                    <select class="form-select form-select-sm" id="transportAction">
                        <option value="verify">Verify assignment</option>
                        <option value="picked_up">Record boarding</option>
                        <option value="dropped_off">Record drop-off</option>
                    </select></div>
                <div class="col-5"><label class="form-label small fw-semibold mb-1" for="tripSession">Trip</label>
                    <select class="form-select form-select-sm" id="tripSession">
                        <option value="morning_pickup">Morning</option>
                        <option value="evening_dropoff">Evening</option>
                        <option value="midday_trip">Midday</option>
                        <option value="special_trip">Special</option>
                    </select></div>
            </div>
            <div class="small text-muted mt-2"><i class="bi bi-shield-lock me-1"></i>Transport attendance is separate from school-fee billing.</div>
        </div></div>
    </div>

    <!-- Input Method Toggle -->
    <div class="input-toggle" id="inputToggle">
        <button class="btn btn-outline-secondary active" data-method="camera" id="btnCamera">
            <i class="bi bi-camera"></i> Camera
        </button>
        <button class="btn btn-outline-secondary" data-method="hid" id="btnHID">
            <i class="bi bi-keyboard"></i> USB Scanner
        </button>
        <button class="btn btn-outline-secondary" data-method="manual" id="btnManual">
            <i class="bi bi-keyboard-fill"></i> Manual
        </button>
    </div>

    <!-- Camera Scanner -->
    <div id="cameraPanel" class="scanner-wrapper">
        <div id="qr-reader"></div>
        <div class="scanner-overlay"><div class="scan-box"></div></div>
        <div class="scanner-controls">
            <button class="btn btn-sm btn-outline-secondary me-2" id="btnToggleFlash" title="Toggle Flash" style="display:none;">
                <i class="bi bi-lightning"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger" id="btnStopCamera">
                <i class="bi bi-stop-circle"></i> Stop Camera
            </button>
        </div>
    </div>

    <!-- USB HID Scanner -->
    <div id="hidPanel" class="hid-panel">
        <div class="hid-input-group">
            <input type="text" id="hidInput" placeholder="Scan QR code — data will appear here..." autocomplete="off" autofocus>
            <button class="btn btn-success" id="btnHidSubmit">
                <i class="bi bi-check-lg"></i> Verify
            </button>
        </div>
        <div class="hid-status" id="hidHint">
            <i class="bi bi-info-circle"></i> Point your USB QR scanner at the input field and scan. Data will auto-submit.
        </div>
    </div>

    <!-- Manual Input -->
    <div id="manualPanel" class="manual-panel">
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-qr-code"></i></span>
            <input type="text" class="form-control" id="manualInput"
                   placeholder="Enter student ID, admission no, or QR data..." autocomplete="off">
            <button class="btn btn-success" id="btnManualSubmit">
                <i class="bi bi-search"></i> Verify
            </button>
        </div>
    </div>

    <!-- Result Card -->
    <div class="result-card" id="resultCard">
        <div class="result-header eligible" id="resultHeader">
            <div class="result-icon"><i class="bi bi-check-circle-fill" id="resultIcon"></i></div>
            <div>
                <div class="result-title" id="resultTitle">—</div>
                <div class="result-subtitle" id="resultSubtitle">—</div>
            </div>
        </div>
        <div class="result-body" id="resultBody"></div>
    </div>

    <!-- Scan History -->
    <div class="scan-history" id="scanHistory" style="display:none;">
        <h6><i class="bi bi-clock-history me-1"></i> Recent Scans</h6>
        <div id="historyList"></div>
    </div>

</div>

<!-- Audio feedback -->
<div class="audio-indicator" id="audioIndicator"></div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js" crossorigin="anonymous"></script>
<?php asset_script($appBase, 'js/pages/qr_scanner.js'); ?>
</body>
</html>
