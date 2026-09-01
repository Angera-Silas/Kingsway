<?php
$appBase    = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');
if ($appBase === '.') $appBase = '';
$pageTitle  = 'Uniform Store';
$activePage = 'uniforms';
$pageScript = 'uniform_catalog';
require_once __DIR__ . '/public/layout/public_data.php';
?>
<?php include __DIR__ . '/public/layout/header.php'; ?>

<link rel="stylesheet" href="<?= $appBase ?>/css/pages/product-catalog.css?v=<?= asset_version('css/pages/product-catalog.css') ?>">

<section class="catalog-cover">
  <div class="container catalog-cover-inner">
    <div class="catalog-cover-copy">
      <span class="catalog-edition">Official school collection · 2026</span>
      <h1>Uniform<br><em>Catalogue</em></h1>
      <p>Smart, practical school wear for learning, games and every Kingsway day. Preview approved designs while the school confirms sizes, prices and stock.</p>
      <a href="#uniformCollection" class="catalog-cover-cta">Explore the collection <i class="bi bi-arrow-down"></i></a>
    </div>
    <div class="catalog-cover-mark" aria-hidden="true">
      <img src="<?= $appBase ?>/uploads/school_assets/official_school_logo.png" alt="">
      <strong>KINGSWAY</strong><span>Preparatory School</span>
    </div>
    <div class="catalog-cover-index" aria-hidden="true">01 — UNIFORMS</div>
  </div>
</section>

<section class="section catalog-shop" id="uniformCollection">
  <div class="container">
    <div class="catalog-section-head reveal">
      <div><span class="catalog-kicker">The Kingsway look</span><h2>Preview the uniform collection</h2><p>The collection is coming soon. Inventory staff will publish confirmed sizes, prices and stock here.</p></div>
      <div class="catalog-search"><i class="bi bi-search"></i><input type="search" id="ucSearch" placeholder="Search the collection…"><span id="ucCount"></span></div>
    </div>
    <div class="catalog-tabs" id="ucCategories" aria-label="Catalogue categories"></div>

    <div id="ucError" class="alert alert-danger d-none"></div>
    <div id="ucGrid" class="catalog-product-grid">
      <div class="col-12 text-center py-5"><div class="spinner-border text-success"></div></div>
    </div>

  </div>
</section>

<?php include __DIR__ . '/public/layout/footer.php'; ?>
