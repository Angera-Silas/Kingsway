<?php
$appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>
<link rel="stylesheet" href="<?= htmlspecialchars($appBase) ?>/css/pages/product-catalog.css?v=<?= asset_version('css/pages/product-catalog.css') ?>">
<div class="catalog-account-page">
  <header class="catalog-account-head"><div><small>My collection desk</small><h1>Shopping account</h1><p>Manage your cart, saved products, payments and collection orders.</p></div><a class="btn btn-light" href="<?= htmlspecialchars($appBase) ?>/home.php?route=internal_products_catalog">Continue shopping</a></header>
  <div id="icaMessage"></div>
  <nav class="catalog-account-tabs"><button class="active" data-tab="cart">Cart <span id="icaCartBadge">0</span></button><button data-tab="wishlist">Wishlist</button><button data-tab="orders">My orders</button></nav>
  <section id="icaCart" class="catalog-account-panel"></section>
  <section id="icaWishlist" class="catalog-account-panel d-none"></section>
  <section id="icaOrders" class="catalog-account-panel d-none"></section>
</div>
<?php asset_script($appBase, 'js/pages/internal_catalog_account.js'); ?>
