<?php
$appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
$productId = max(0, (int)($_GET['id'] ?? 0));
?>
<link rel="stylesheet" href="<?= htmlspecialchars($appBase) ?>/css/pages/product-catalog.css?v=<?= asset_version('css/pages/product-catalog.css') ?>">
<div class="catalog-detail-page" data-product-id="<?= $productId ?>">
  <nav class="catalog-detail-nav">
    <a href="<?= htmlspecialchars($appBase) ?>/home.php?route=internal_products_catalog"><i class="bi bi-arrow-left"></i> Catalogue</a>
    <a href="<?= htmlspecialchars($appBase) ?>/home.php?route=internal_catalog_account"><i class="bi bi-bag"></i> Cart, wishlist &amp; orders <span id="icdCartCount">0</span></a>
  </nav>
  <div id="icdMessage"></div>
  <main id="icdProduct" class="catalog-detail-shell"><div class="catalog-loading"><span class="spinner-border spinner-border-sm"></span> Loading product…</div></main>
  <section class="catalog-reviews-section">
    <div class="catalog-reviews-heading"><div><small>Community feedback</small><h2>Ratings &amp; reviews</h2></div><button class="btn btn-outline-success" id="icdReviewToggle">Write a review</button></div>
    <form id="icdReviewForm" class="catalog-review-form d-none">
      <label>Your rating<select class="form-select" id="icdRating" required><option value="">Choose</option><option value="5">5 — Excellent</option><option value="4">4 — Good</option><option value="3">3 — Average</option><option value="2">2 — Poor</option><option value="1">1 — Very poor</option></select></label>
      <label>Review title<input class="form-control" id="icdReviewTitle" maxlength="120"></label>
      <label class="wide">Your comments<textarea class="form-control" id="icdReviewComment" rows="3" maxlength="2000"></textarea></label>
      <button class="btn btn-success wide">Submit for moderation</button>
    </form>
    <div id="icdReviews" class="catalog-review-grid"></div>
  </section>
</div>
<?php asset_script($appBase, 'js/pages/internal_product_details.js'); ?>
