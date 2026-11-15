<?php
/**
 * Photo Capture: learner and staff photos for ID cards, security passes and
 * documents.
 *
 * Take photos live from any camera the computer sees (built-in webcam, USB
 * webcam, or a phone connected as a webcam), or upload existing photo files,
 * singly or in bulk (files named by admission or staff number). Photos are
 * cropped to passport shape (3:4) and saved through the existing learner and
 * staff photo uploads. JS: js/pages/photo_capture.js.
 */
?>
<section class="card kw-clean" id="photoCapturePage">
    <div class="card-header">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2">
            <div>
                <h4 class="mb-0">Photo capture</h4>
                <small>Learner and staff photos for ID cards, security passes and documents.</small>
            </div>
            <div class="btn-group d-print-none" role="tablist" aria-label="Whose photos">
                <button type="button" class="btn btn-sm btn-primary" data-pc-kind="learner" aria-pressed="true">Learners</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-pc-kind="staff" aria-pressed="false">Staff</button>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-4">
            <!-- People -->
            <div class="col-lg-5">
                <div class="row g-2 mb-2">
                    <div class="col-6" id="pcClassWrap"><label class="form-label" for="pcClass">Class</label><select class="form-select form-select-sm" id="pcClass"><option value="">Choose a class</option></select></div>
                    <div class="col-6"><label class="form-label" for="pcShow">Show</label>
                        <select class="form-select form-select-sm" id="pcShow"><option value="missing">Without a photo</option><option value="all">Everyone</option></select></div>
                    <div class="col-12"><input type="search" class="form-control form-control-sm" id="pcSearch" placeholder="Name or number"></div>
                </div>
                <div class="small text-muted mb-1" id="pcCount"></div>
                <div class="list-group list-group-flush border rounded" id="pcPeople" style="max-height: 28rem; overflow: auto;">
                    <div class="list-group-item text-muted">Choose a class.</div>
                </div>
            </div>

            <!-- Camera -->
            <div class="col-lg-7">
                <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-2">
                    <div><div class="fw-semibold" id="pcWho">No one selected</div><div class="small text-muted" id="pcWhoMeta">Pick a learner or staff member on the left.</div></div>
                    <div style="min-width: 14rem"><label class="form-label" for="pcCamera">Camera</label><select class="form-select form-select-sm" id="pcCamera"><option value="">Start the camera first</option></select></div>
                </div>
                <div class="pc-stage" id="pcStage">
                    <video id="pcVideo" playsinline muted hidden></video>
                    <canvas id="pcShot" hidden></canvas>
                    <div class="pc-frame" aria-hidden="true"></div>
                    <div class="pc-empty" id="pcEmpty">Camera is off.</div>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-2 d-print-none">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="pcStart"><i class="bi bi-camera-video me-1"></i>Start camera</button>
                    <button type="button" class="btn btn-sm btn-primary" id="pcCapture" disabled><i class="bi bi-camera me-1"></i>Take photo</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="pcRetake" hidden>Retake</button>
                    <button type="button" class="btn btn-sm btn-success" id="pcSave" disabled data-year-write><i class="bi bi-check2 me-1"></i>Save photo</button>
                    <label class="btn btn-sm btn-outline-secondary mb-0"><i class="bi bi-image me-1"></i>Use a file<input type="file" accept="image/*" id="pcFile" hidden></label>
                </div>
                <p class="small text-muted mt-2 mb-0">A phone works as a camera when connected as a webcam (it then appears in the camera list), or open this page on the phone itself.</p>

                <hr>
                <div class="fw-semibold mb-1">Upload existing photos in bulk</div>
                <p class="small text-muted mb-2">Choose many photos named by admission number (learners, e.g. KPS1488.jpg) or staff number (staff). Each is matched and uploaded.</p>
                <input type="file" accept="image/*" multiple class="form-control form-control-sm" id="pcBulk">
                <div class="small mt-2" id="pcBulkLog"></div>
            </div>
        </div>
    </div>
</section>

<style>
    #photoCapturePage [hidden] { display: none !important; }
    #photoCapturePage .pc-stage { position: relative; background: #1f2a44; border-radius: 8px; aspect-ratio: 4 / 3; overflow: hidden; display: grid; place-items: center; }
    #photoCapturePage .pc-stage video, #photoCapturePage .pc-stage canvas { width: 100%; height: 100%; object-fit: cover; }
    #photoCapturePage .pc-stage canvas { object-fit: contain; background: #1f2a44; }
    #photoCapturePage .pc-frame { position: absolute; height: 86%; aspect-ratio: 3 / 4; border: 2px dashed rgba(255, 255, 255, .7); border-radius: 6px; pointer-events: none; }
    #photoCapturePage .pc-empty { position: absolute; color: #c7cede; font-size: .9rem; }
    #photoCapturePage .list-group-item.active { background: #f6f7f9; color: #1f2a44; border-color: #e3e6eb; font-weight: 600; }
    #photoCapturePage .pc-thumb { width: 36px; height: 48px; object-fit: cover; border-radius: 3px; border: 1px solid #e3e6eb; }
</style>

<?php asset_script($appBase, 'js/pages/photo_capture.js'); ?>
