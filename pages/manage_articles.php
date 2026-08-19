<?php
/* News Articles — standalone page (split from Website Management). */
$appBase = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<style>
.ws-stat-card { background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px 24px;display:flex;align-items:center;gap:16px; }
.ws-stat-icon { width:48px;height:48px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0; }
.ws-badge { display:inline-block;padding:2px 10px;border-radius:20px;font-size:.72rem;font-weight:600; }
.ws-table td,.ws-table th { vertical-align:middle;font-size:.85rem; }
.ws-img-thumb { width:56px;height:40px;object-fit:cover;border-radius:6px; }
.ws-tag-chip { display:inline-block;padding:2px 8px;border-radius:12px;font-size:.72rem;font-weight:600;border:1px solid; }
.ws-form-group label { font-size:.82rem;font-weight:600;color:#374151;margin-bottom:4px;display:block; }
.ws-form-group input,.ws-form-group select,.ws-form-group textarea { width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:.88rem;outline:none;transition:border-color .15s; }
.ws-form-group input:focus,.ws-form-group select:focus,.ws-form-group textarea:focus { border-color:#198754;box-shadow:0 0 0 3px rgba(25,135,84,.1); }
</style>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-newspaper text-success me-2"></i>News Articles</h4>
      <p class="text-muted small mb-0 mt-1">Write, publish and manage the news articles shown on the public website.</p>
    </div>
    <a href="<?= $appBase ?>/" target="_blank" class="btn btn-sm btn-outline-success rounded-pill px-3">
      <i class="bi bi-box-arrow-up-right me-1"></i>View Public Site
    </a>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="ws-stat-card">
      <div class="ws-stat-icon" style="background:#e8f5e9"><i class="bi bi-newspaper text-success"></i></div>
      <div><div class="fw-bold fs-5 mb-0" id="statTotal">—</div><div class="text-muted small">Total Articles</div></div>
    </div></div>
    <div class="col-6 col-lg-3"><div class="ws-stat-card">
      <div class="ws-stat-icon" style="background:#e8f5e9"><i class="bi bi-eye text-success"></i></div>
      <div><div class="fw-bold fs-5 mb-0" id="statViews">—</div><div class="text-muted small">Total Views</div></div>
    </div></div>
  </div>

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex gap-2 flex-wrap">
      <input type="text" id="newsSearch" class="form-control form-control-sm" placeholder="Search articles…" style="width:200px">
      <select id="newsCatFilter" class="form-select form-select-sm" style="width:160px">
        <option value="">All categories</option>
      </select>
      <select id="newsStatusFilter" class="form-select form-select-sm" style="width:140px">
        <option value="">All statuses</option>
        <option value="published">Published</option>
        <option value="draft">Draft</option>
        <option value="archived">Archived</option>
      </select>
    </div>
    <button class="btn btn-success btn-sm rounded-pill px-3" onclick="articlesOpenModal()">
      <i class="bi bi-plus-lg me-1"></i>New Article
    </button>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <table class="table table-hover ws-table mb-0">
      <thead class="table-light"><tr>
        <th scope="col">Image</th><th scope="col">Title</th><th scope="col">Category</th><th scope="col">Status</th><th scope="col">Views</th><th scope="col">Date</th><th class="text-end">Actions</th>
      </tr></thead>
      <tbody id="newsTableBody"><tr><td colspan="7" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
    </table>
  </div>

</div>

<!-- News Modal -->
<div class="modal fade" id="wsNewsModal" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header border-0 pb-0">
      <h5 class="modal-title fw-bold" id="wsNewsModalTitle">New Article</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="newsEditId">
      <div class="row g-3">
        <div class="col-12 ws-form-group"><label>Title *</label><input type="text" id="newsTitle" placeholder="Article headline"></div>
        <div class="col-md-6 ws-form-group">
          <label>Category *</label>
          <select id="newsCategory">
            <option value="Announcement">Announcement</option>
            <option value="Academic">Academic</option>
            <option value="Sports">Sports</option>
            <option value="Arts">Arts</option>
            <option value="Infrastructure">Infrastructure</option>
            <option value="Community">Community</option>
          </select>
        </div>
        <div class="col-md-6 ws-form-group">
          <label>Status</label>
          <select id="newsStatus"><option value="published">Published</option><option value="draft">Draft</option><option value="archived">Archived</option></select>
        </div>
        <div class="col-12 ws-form-group"><label>Image URL</label><input type="url" id="newsImageUrl" placeholder="https://images.unsplash.com/..."></div>
        <div id="newsImgPreviewWrap" style="display:none" class="col-12">
          <img id="newsImgPreview" class="rounded-3" style="height:140px;width:100%;object-fit:cover">
        </div>
        <div class="col-12 ws-form-group"><label>Excerpt (short summary)</label><textarea id="newsExcerpt" rows="2" placeholder="2–3 sentence summary shown on listing page…"></textarea></div>
        <div class="col-12 ws-form-group"><label>Author</label><input type="text" id="newsAuthor" placeholder="Author name or department"></div>
        <div class="col-12 ws-form-group"><label>Full Content (HTML allowed) *</label><textarea id="newsContent" rows="8" placeholder="Full article HTML content…"></textarea></div>
      </div>
    </div>
    <div class="modal-footer border-0">
      <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
      <button class="btn btn-success btn-sm px-4" id="newsSubmitBtn" onclick="articlesSave()">
        <i class="bi bi-send-fill me-1"></i>Publish Article
      </button>
    </div>
  </div></div>
</div>

<script src="<?= $appBase ?>/js/pages/manage_articles.js?v=<?= filemtime(APP_BASE_PATH . "/js/pages/manage_articles.js") ?>"></script>
