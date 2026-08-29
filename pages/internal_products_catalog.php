<?php
/**
 * Internal Products Catalog — PARTIAL (injected into app shell via app_layout.php)
 * Route key: internal_products_catalog  ·  JS controller: js/pages/internal_products_catalog.js
 *
 * Internal school-staff view of the uniform/products catalogue:
 *   view          — every staff role (status <> archived)
 *   sell          — inventory write roles (inventory.manage / inventory.admin / sysadmin)
 *   manage        — create/edit, publish/unpublish, image upload (same write gate)
 * The public-facing storefront is uniform_catalog.php (guest/parent only).
 */
$appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<div class="container-fluid px-4 py-4">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h4 class="mb-1"><i class="bi bi-shop me-2 text-success"></i>Products Catalog</h4>
            <p class="text-muted mb-0">Internal storefront catalogue — view, sell, and manage uniform products.</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary ipc-manage-only d-none" id="ipcAddProductBtn">
                <i class="bi bi-plus-lg me-1"></i>Add Product
            </button>
            <button class="btn btn-outline-success" id="ipcRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat dsc-blue">
                <div class="dash-stat-value" id="ipcStatTotal">0</div>
                <div class="dash-stat-label">Catalogue Products</div>
                <div class="dash-stat-sub" id="ipcStatTotalSub">All statuses</div>
                <i class="bi bi-collection dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat dsc-green">
                <div class="dash-stat-value" id="ipcStatLive">0</div>
                <div class="dash-stat-label">Live</div>
                <div class="dash-stat-sub">Active &amp; published</div>
                <i class="bi bi-store dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat dsc-amber">
                <div class="dash-stat-value" id="ipcStatDraft">0</div>
                <div class="dash-stat-label">Draft / Unpublished</div>
                <div class="dash-stat-sub">Not visible to buyers</div>
                <i class="bi bi-pencil-square dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat dsc-red">
                <div class="dash-stat-value" id="ipcStatArchived">0</div>
                <div class="dash-stat-label">Archived</div>
                <div class="dash-stat-sub">Hidden from catalogue</div>
                <i class="bi bi-archive dash-stat-icon"></i>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-center">
                <div class="col-md-6 col-lg-5">
                    <input type="search" class="form-control" id="ipcSearch" placeholder="Search by title, code or description…">
                </div>
                <div class="col-md-3 col-lg-3">
                    <select class="form-select" id="ipcStatusFilter">
                        <option value="all">All statuses</option>
                        <option value="active">Active</option>
                        <option value="draft">Draft</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>
                <div class="col-md-3 col-lg-4 text-md-end">
                    <span class="text-muted small" id="ipcCount">0 items shown</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Products table -->
    <div class="bg-white border rounded-3 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="min-width:220px">Product</th>
                        <th>SKU</th>
                        <th>Status</th>
                        <th>Published</th>
                        <th>Sizes &amp; Pricing</th>
                        <th class="text-end" style="min-width:220px">Actions</th>
                    </tr>
                </thead>
                <tbody id="ipcProductsBody">
                    <tr><td colspan="6" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="p-3 border-top text-muted small d-none ipc-manage-only" id="ipcHelpRow">
            You can manage this catalogue (create, publish, upload images, and record internal sales) because you hold an inventory management permission.
        </div>
    </div>

</div>

<!-- Product modal (add / edit) -->
<div class="modal fade" id="ipcProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="ipcProductForm" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="ipcProductModalTitle">Add Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="ipcProductItem">Inventory item <span class="text-danger">*</span></label>
                        <select class="form-select" id="ipcProductItem" required>
                            <option value="">Select inventory item…</option>
                        </select>
                        <input type="hidden" id="ipcProductExistingId" value="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="ipcProductTitle">Catalogue title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="ipcProductTitle" maxlength="150" required
                               placeholder="e.g. Boys navy blazer (Spec 1)">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="ipcProductDesc">Description</label>
                        <textarea class="form-control" id="ipcProductDesc" rows="3" maxlength="500"
                                  placeholder="Visible to buyers in the public storefront."></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="ipcProductStatus">Status</label>
                            <select class="form-select" id="ipcProductStatus">
                                <option value="draft">Draft</option>
                                <option value="active">Active</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" id="ipcProductPublished">
                                <label class="form-check-label" for="ipcProductPublished">Published to buyers</label>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info small mt-3 mb-0">
                        Active + published products appear in the public storefront. Draft/paused items stay internal until you publish them.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="ipcProductSaveBtn">Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Internal sale modal -->
<div class="modal fade" id="ipcSellModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="ipcSellForm" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title">Record Catalogue Purchase</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3 text-muted small" id="ipcSellProductInfo">—</p>
                    <input type="hidden" id="ipcSellProductId" value="0">
                    <div class="mb-3">
                        <label class="form-label" for="ipcSellStudent">Learner <span class="text-danger">*</span></label>
                        <input type="search" class="form-control mb-2" id="ipcSellStudentSearch" placeholder="Type to filter learners…">
                        <select class="form-select" id="ipcSellStudentLarge" size="6" required>
                            <option value="">Loading learners…</option>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-7">
                            <label class="form-label" for="ipcSellSize">Size <span class="text-danger">*</span></label>
                            <select class="form-select" id="ipcSellSize" required>
                                <option value="">Select size…</option>
                            </select>
                        </div>
                        <div class="col-5">
                            <label class="form-label" for="ipcSellQty">Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="ipcSellQty" min="1" value="1" required>
                        </div>
                    </div>
                    <div class="alert alert-light small mt-3 mb-0">
                        Records the sale against the learner. Payment is collected later in the Uniform Sales workflow.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="ipcSellSaveBtn">Create Purchase</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Product image modal -->
<div class="modal fade" id="ipcImageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="ipcImageForm" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title">Upload Product Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="ipcImageProductId" value="0">
                    <p class="text-muted small mb-3" id="ipcImageProductInfo">—</p>
                    <div class="mb-3">
                        <label class="form-label" for="ipcImageFile">Image file <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="ipcImageFile" accept="image/*" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="ipcImageAlt">Alt text</label>
                        <input type="text" class="form-control" id="ipcImageAlt" maxlength="150"
                               placeholder="Short description for accessibility">
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="ipcImagePrimary" checked>
                        <label class="form-check-label" for="ipcImagePrimary">Use as the primary product image</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="ipcImageSaveBtn">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/pages/internal_products_catalog.js'); ?>