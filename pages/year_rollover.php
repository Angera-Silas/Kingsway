<?php /* PARTIAL — no DOCTYPE/html/head/body */ ?>
<div class="container-fluid py-4" id="page-year-rollover">

  <!-- Header -->
  <div class="d-flex flex-wrap justify-content-between align-items-start mb-4 gap-3">
    <div>
      <h3 class="mb-0"><i class="bi bi-arrow-clockwise me-2 text-primary"></i>Year Transition / Rollover</h3>
      <small class="text-muted">Create the next academic year (calendar, classes, streams, learning areas), archive the old year, run promotions, migrate baselines and activate the new year.</small>
    </div>
    <span class="badge bg-warning fs-6 align-self-center" id="yrCurrentYearBadge">Loading…</span>
  </div>

  <!-- Pre-flight Status -->
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <i class="bi bi-calendar-check fs-3 mb-1" id="yrTermsIcon" style="color:gray"></i>
          <div class="fw-semibold" id="yrTermsStatus">Checking…</div>
          <div class="text-muted small">All 3 Terms Closed</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <i class="bi bi-file-earmark-check fs-3 mb-1" id="yrResultsIcon" style="color:gray"></i>
          <div class="fw-semibold" id="yrResultsStatus">Checking…</div>
          <div class="text-muted small">Results Finalised</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <i class="bi bi-person-check fs-3 mb-1" id="yrPromotionsIcon" style="color:gray"></i>
          <div class="fw-semibold" id="yrPromotionsStatus">Checking…</div>
          <div class="text-muted small">Promotions Done</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <i class="bi bi-cash-coin fs-3 mb-1" id="yrFeesIcon" style="color:gray"></i>
          <div class="fw-semibold" id="yrFeesStatus">Checking…</div>
          <div class="text-muted small">Students with Outstanding Fees</div>
        </div>
      </div>
    </div>
  </div>

  <div id="yrNotReady" class="alert alert-warning d-none">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>Not ready to roll over yet:</strong> all terms of the current year must be
    <strong>closed</strong> (use <a href="<?= $appBase ?>/home.php?route=term_transition" class="alert-link">Term Transition</a>) before the new year can be activated.
    You may still create and prepare the new year's structure below.
  </div>

  <div id="yrExistingYear" class="alert alert-info d-none">
    <i class="bi bi-calendar2-plus me-2"></i>
    <strong>New academic year already created.</strong>
    <a href="<?= $appBase ?>/home.php?route=academic_years" class="alert-link">Open Academic Years</a> to view it.
  </div>

  <!-- Workflow Card -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-primary text-white fw-semibold">
      <i class="bi bi-list-ol me-2"></i>Year Transition Steps <span class="float-end small" id="yrWfInfo"></span>
    </div>
    <div class="card-body">
      <!-- Create form -->
      <div id="yrCreateForm">
        <div class="alert alert-light border">
          <h6 class="fw-semibold mb-3"><i class="bi bi-calendar-plus me-2"></i>Step 1 — Create the New Academic Year</h6>
          <p class="small text-muted mb-3">
            This creates the new year, its 3 terms and the full term calendar
            (weeks numbered chronologically per term). Term weeks default to the
            standard <strong>14 / 14 / 10</strong> (Term 1 / Term 2 / Term 3).
            The current year's structure is then cloned one grade ahead for the new year.
          </p>
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label fw-semibold">New Year Code</label>
              <input type="number" id="yrToYear" class="form-control" min="2000" max="2200">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Year Start Date</label>
              <input type="date" id="yrStartDate" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Year End Date</label>
              <input type="date" id="yrEndDate" class="form-control">
            </div>
          </div>
          <div class="table-responsive mt-3">
            <table class="table table-sm align-middle">
              <thead class="table-light">
                <tr>
                  <th>Term</th><th>Start Date</th><th>End Date</th>
                  <th style="width:110px">Weeks</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="fw-semibold">Term 1</td>
                  <td><input type="date" id="yrT1Start" class="form-control"></td>
                  <td><input type="date" id="yrT1End" class="form-control"></td>
                  <td><input type="number" id="yrT1Weeks" class="form-control" value="14" min="1" max="20"></td>
                </tr>
                <tr>
                  <td class="fw-semibold">Term 2</td>
                  <td><input type="date" id="yrT2Start" class="form-control"></td>
                  <td><input type="date" id="yrT2End" class="form-control"></td>
                  <td><input type="number" id="yrT2Weeks" class="form-control" value="14" min="1" max="20"></td>
                </tr>
                <tr>
                  <td class="fw-semibold">Term 3</td>
                  <td><input type="date" id="yrT3Start" class="form-control"></td>
                  <td><input type="date" id="yrT3End" class="form-control"></td>
                  <td><input type="number" id="yrT3Weeks" class="form-control" value="10" min="1" max="20"></td>
                </tr>
              </tbody>
            </table>
          </div>
          <button class="btn btn-primary" id="yrStartBtn" onclick="yearRolloverController.createNewYear()">
            <i class="bi bi-calendar-plus me-1"></i> Create New Academic Year &amp; Calendar
          </button>
          <span class="ms-2 small text-muted" id="yrCreateMsg"></span>
        </div>
      </div>

      <!-- Stage list -->
      <div id="yrStageWrap" class="d-none">
        <div class="list-group" id="yrStages"></div>
      </div>
    </div>
  </div>

  <!-- Rollover Log -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent fw-semibold">
      <i class="bi bi-journal-text me-2"></i>Transition Audit Log
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th scope="col">Stage</th><th scope="col">Status</th><th scope="col">Details</th><th scope="col">Performed</th>
            </tr>
          </thead>
          <tbody id="yrLogBody">
            <tr><td colspan="4" class="text-center text-muted py-3">No transition activity yet.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script src="<?= $appBase ?>/js/pages/year_rollover.js?v=<?= filemtime(APP_BASE_PATH . "/js/pages/year_rollover.js") ?>"></script>
<script>document.addEventListener('DOMContentLoaded', () => yearRolloverController.init());</script>
