<?php
/** Bus device / driver manifest view. USB QR readers operate as HID input. */
?>
<link rel="stylesheet" href="<?= htmlspecialchars(($appBase ?? '') . '/css/transport-operator.css', ENT_QUOTES, 'UTF-8') ?>">
<main class="transport-operator" id="transportOperatorApp">
    <section class="operator-hero">
        <div><div class="operator-kicker"><i class="bi bi-bus-front-fill"></i> BUS DEVICE</div><h1>Live passenger manifest</h1><p>Connect the USB QR scanner to this device. Every accepted scan appears here immediately.</p></div>
        <div class="operator-status-wrap"><span id="operatorConnectivity" class="operator-status offline"><i class="bi bi-wifi-off"></i> Connecting…</span><span id="scannerDeviceStatus" class="operator-status"><i class="bi bi-usb-drive"></i> USB HID ready</span><button id="connectSerialScanner" class="btn btn-sm btn-light"><i class="bi bi-usb-drive me-1"></i>Connect serial scanner</button></div>
    </section>
    <section class="operator-toolbar card border-0 shadow-sm">
        <div class="operator-toolbar-row">
            <div><label class="form-label small fw-semibold" for="operatorTripSession">Trip</label><select class="form-select" id="operatorTripSession"><option value="morning_pickup">Morning pickup</option><option value="evening_dropoff">Evening drop-off</option><option value="midday_trip">Midday trip</option><option value="special_trip">Special trip</option></select></div>
            <div class="operator-date"><label class="form-label small fw-semibold" for="operatorDate">Date</label><input class="form-control" type="date" id="operatorDate"></div>
            <div class="operator-actions"><button class="btn btn-primary" id="operatorRefresh"><i class="bi bi-arrow-clockwise me-1"></i>Refresh manifest</button><button class="btn btn-outline-secondary" id="operatorSync"><i class="bi bi-cloud-arrow-up me-1"></i>Sync queued scans <span id="queuedScanCount" class="badge text-bg-warning">0</span></button></div>
        </div>
        <div class="operator-scan-row"><i class="bi bi-qr-code-scan fs-4 text-primary"></i><div class="flex-grow-1"><label class="form-label small fw-semibold mb-1" for="operatorScanInput">Scan learner QR code</label><input id="operatorScanInput" class="form-control form-control-lg" autocomplete="off" inputmode="none" placeholder="USB scanner input appears here…" autofocus></div><div id="operatorScanFeedback" class="scan-feedback text-muted">Waiting for scan</div></div>
    </section>
    <section class="operator-summary" id="operatorSummary"><div class="summary-card"><span class="summary-icon blue"><i class="bi bi-people"></i></span><div><strong id="manifestExpected">0</strong><small>Expected</small></div></div><div class="summary-card"><span class="summary-icon green"><i class="bi bi-person-check"></i></span><div><strong id="manifestBoarded">0</strong><small>Boarded</small></div></div><div class="summary-card"><span class="summary-icon orange"><i class="bi bi-person-exclamation"></i></span><div><strong id="manifestRemaining">0</strong><small>Not boarded</small></div></div><div class="summary-card"><span class="summary-icon gray"><i class="bi bi-cloud-slash"></i></span><div><strong id="manifestQueued">0</strong><small>Queued offline</small></div></div></section>
    <section class="card border-0 shadow-sm mb-4"><div class="card-header bg-white d-flex justify-content-between align-items-center"><div><h2 class="h5 mb-1">Today’s route</h2><div id="manifestRouteLabel" class="text-muted small">Loading assigned route…</div></div><div id="manifestGeneratedAt" class="text-muted small">—</div></div><div id="manifestEmpty" class="empty-state">No active vehicle and route are assigned to this driver.</div><div class="table-responsive"><table class="table align-middle operator-table mb-0"><thead><tr><th>Stop</th><th>Learner</th><th>Admission no.</th><th>Boarding</th><th>Time</th></tr></thead><tbody id="operatorManifestBody"><tr><td colspan="5" class="text-center text-muted py-5">Loading manifest…</td></tr></tbody></table></div></section>
</main>
<?php asset_script($appBase, 'js/pages/transport_operator.js'); ?>
