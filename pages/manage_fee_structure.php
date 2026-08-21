<?php
/**
 * Manage Fee Structure Page — JWT-Based Role Router
 *
 * Uses PageShell.loadRoleTemplate() to select the correct sub-template
 * based on the current user's permissions. Fully stateless (no PHP session).
 */
?>

<!-- Loading state while determining user role -->
<div id="fee-structure-loading" style="padding: 40px; text-align: center;">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <p class="mt-3">Loading fee structure interface...</p>
</div>

<!-- Container where the correct template will be loaded -->
<div id="fee-structure-content" style="display: none;"></div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    PageShell.loadRoleTemplate({
        loadingId:   'fee-structure-loading',
        contentId:   'fee-structure-content',
        templateDir: '/pages/fee_structure/',
        module:      'Fee Structure',
        // Role templates load their own controller below. Loading the generic
        // umbrella as well can race the role controller and initialize an old
        // aggregate view before the matrix controller is ready.
        scriptSrc:   null,
        afterLoad: function (contentEl, match) {
            var controllers = {
                'admin_fee_structure.php': [
                    '/js/components/fee_structure_matrix.js?v=<?= asset_version("js/components/fee_structure_matrix.js") ?>',
                    '/js/pages/fee_structure_admin.js?v=<?= asset_version("js/pages/fee_structure_admin.js") ?>'
                ],
                'accountant_fee_structure.php': [
                    '/js/components/fee_structure_matrix.js?v=<?= asset_version("js/components/fee_structure_matrix.js") ?>',
                    '/js/pages/fee_structure_accountant.js?v=<?= asset_version("js/pages/fee_structure_accountant.js") ?>'
                ],
                'viewer_fee_structure.php': [
                    '/js/components/fee_structure_matrix.js?v=<?= asset_version("js/components/fee_structure_matrix.js") ?>',
                    '/js/pages/fee_structure_viewer.js?v=<?= asset_version("js/pages/fee_structure_viewer.js") ?>'
                ]
            }[match.file] || [];

            controllers.forEach(function (path) {
                var script = document.createElement('script');
                script.src = (window.APP_BASE || '') + path;
                script.async = false;
                document.body.appendChild(script);
            });
        },
        levels: [
            {
                file: 'admin_fee_structure.php',
                test: function () {
                    return PageShell.hasAny(['fee_structure_manage', 'fee_structure_admin', 'fee_structure_delete', 'fee_structure_create']) ||
                           PageShell.hasRole(['system_administrator', 'director', 'director_owner', 'school_administrator', 'school_admin']);
                },
            },
            {
                file: 'accountant_fee_structure.php',
                test: function () {
                    return PageShell.hasAny(['fee_structure_edit', 'fee_structure_view_financial']) ||
                           PageShell.hasRole(['school_accountant', 'accountant', 'bursar']);
                },
            },
            {
                file: 'viewer_fee_structure.php',
                test: function () {
                    return PageShell.hasAny(['fee_structure_view', 'fee_structure_view_own', 'fees_view']) ||
                           PageShell.hasRole(['headteacher', 'deputy_head_academic', 'deputy_head_discipline', 'class_teacher', 'subject_teacher', 'parent', 'student']);
                },
            },
        ],
    });
});
</script>
