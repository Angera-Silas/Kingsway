<?php
/**
 * Library Management — router partial
 * Routes: admin/librarian → admin_library.php, others → viewer_library.php
 * JS controller: js/pages/manage_library.js
 */
/* PARTIAL — no DOCTYPE/html/head/body. Injected into app shell via app_layout.php */
?>
<div id="library-loading" class="text-center py-5">
  <div class="spinner-border text-primary" role="status"></div>
  <p class="text-muted mt-2">Loading library...</p>
</div>
<div id="library-content" style="display:none;"></div>

<script src="<?= $appBase ?>/js/pages/manage_library.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    PageShell.loadRoleTemplate({
      loadingId:   'library-loading',
      contentId:   'library-content',
      templateDir: '/pages/library/',
      module:      'Library',
      levels: [
        {
          file: 'admin_library.php',
          test: function () {
            return PageShell.hasAny(['library.manage', 'library.issue', 'library.create']);
          },
        },
        {
          file: 'viewer_library.php',
          test: function () {
            return PageShell.hasAny(['library.view', 'library_view']) ||
                   PageShell.hasRole(['parent', 'student']);
          },
        },
      ],
      afterLoad: function () {
        if (typeof libraryController !== 'undefined') libraryController.init();
      },
    });
  });
</script>
