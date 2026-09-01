<?php
$appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>
<link rel="stylesheet" href="<?= htmlspecialchars($appBase) ?>/css/pages/product-catalog.css?v=<?= asset_version('css/pages/product-catalog.css') ?>">
<div class="commerce-ops-page">
  <header class="commerce-ops-head"><div><small>Uniform store operations</small><h1>Orders, fulfilment &amp; customer activity</h1><p>Monitor online, internal and physical-counter orders from payment through collection.</p></div><div><a class="btn btn-warning me-2" href="<?=htmlspecialchars($appBase)?>/home.php?route=uniform_point_of_sale"><i class="bi bi-upc-scan"></i> POS &amp; Dispatch</a><button class="btn btn-light" id="comRefresh"><i class="bi bi-arrow-clockwise"></i> Refresh</button></div></header>
  <div id="comMessage"></div>
  <section id="comMetrics" class="commerce-metrics"></section>
  <nav class="commerce-filter" id="comFilter"><button class="active" data-status="">All</button><button data-status="pending_payment">Payment pending</button><button data-status="paid">Paid</button><button data-status="preparing">Preparing</button><button data-status="ready">Ready</button><button data-status="fulfilled">Fulfilled</button></nav>
  <section id="comOrders" class="commerce-order-board"></section>
  <section class="mt-5"><div class="catalog-reviews-heading"><div><small>Moderation queue</small><h2>Customer reviews</h2></div></div><div id="comReviews" class="catalog-review-grid"></div></section>
  <section class="mt-5"><div class="catalog-reviews-heading"><div><small>Product intelligence</small><h2>Demand &amp; engagement</h2></div></div><div id="comProducts" class="commerce-order-board"></div></section>
</div>
<?php asset_script($appBase, 'js/pages/catalog_orders_management.js'); ?>
