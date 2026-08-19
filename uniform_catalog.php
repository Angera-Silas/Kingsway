<?php
$appBase    = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');
if ($appBase === '.') $appBase = '';
$pageTitle  = 'Uniform Store';
$activePage = 'uniforms';
$pageScript = 'uniform_catalog';
require_once __DIR__ . '/public/layout/public_data.php';
?>
<?php include __DIR__ . '/public/layout/header.php'; ?>

<div class="page-header">
  <div class="container position-relative" style="z-index:1">
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2">
      <li class="breadcrumb-item"><a href="<?= $appBase ?>/index.php">Home</a></li>
      <li class="breadcrumb-item active">Uniform Store</li>
    </ol></nav>
    <h1 class="page-title">Uniform Store</h1>
    <p class="mt-2" style="color:rgba(255,255,255,.7)">Browse official Kingsway uniforms. Select your size and pay via M-Pesa.</p>
  </div>
</div>

<section class="section">
  <div class="container">

    <div class="row justify-content-center mb-5 reveal">
      <div class="col-lg-6">
        <div class="input-group shadow-sm">
          <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
          <input type="text" id="ucSearch" class="form-control border-start-0 py-3" placeholder="Search shirts, sweaters, tracksuits…">
        </div>
      </div>
      <div class="col-lg-2 d-flex align-items-center justify-content-lg-end mt-2 mt-lg-0">
        <span class="text-muted small" id="ucCount"></span>
      </div>
    </div>

    <div id="ucError" class="alert alert-danger d-none"></div>
    <div id="ucGrid" class="row g-4">
      <div class="col-12 text-center py-5"><div class="spinner-border text-success"></div></div>
    </div>

  </div>
</section>

<?php include __DIR__ . '/public/layout/footer.php'; ?>
