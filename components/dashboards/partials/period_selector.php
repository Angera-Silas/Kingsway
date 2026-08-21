<?php
/**
 * Reusable period selector for role dashboards.
 * Renders a Bootstrap button group. JS controllers listen for the
 * custom event "dash:period-change" on the element.
 *
 * Required vars (set before include):
 *   $rootId    — dashboard root element ID (e.g. 'accountantDashboard')
 *   $periods   — array of ['key' => ..., 'label' => ...] (optional, defaults below)
 *   $default   — default period key (optional, defaults to 'month')
 */
$_ps_default_periods = [
    ['key' => 'today',   'label' => 'Today'],
    ['key' => 'week',    'label' => 'This Week'],
    ['key' => 'month',   'label' => 'This Month'],
    ['key' => 'term',    'label' => 'This Term'],
    ['key' => 'year',    'label' => 'This Year'],
];
$_ps_periods = $periods ?? $_ps_default_periods;
$_ps_default = $default ?? 'month';
$_ps_id = ($rootId ?? 'roleDashboard') . 'PeriodBar';
?>

<div class="dash-period-bar" id="<?= $_ps_id ?>">
    <div class="btn-group btn-group-sm" role="group" aria-label="Time period">
        <?php foreach ($_ps_periods as $_ps_p): ?>
            <button
                type="button"
                class="btn <?= $_ps_p['key'] === $_ps_default ? 'btn-success' : 'btn-outline-success' ?> dash-period-btn"
                data-period="<?= htmlspecialchars($_ps_p['key']) ?>"
            ><?= htmlspecialchars($_ps_p['label']) ?></button>
        <?php endforeach; ?>
    </div>
    <span class="dash-period-label" id="<?= $_ps_id ?>Label"></span>
</div>
