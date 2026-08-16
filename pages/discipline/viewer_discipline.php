<?php
/* PARTIAL — no DOCTYPE/html/head/body. Injected into app shell via fetch. */
/**
 * Discipline - Viewer Layout
 * Read-only for Students and Parents
 *
 * Features:
 * - No sidebar
 * - Summary card
 * - Simple list of own discipline records
 * - No actions, read-only
 */
?>

<!-- Summary Card -->
<div class="viewer-summary-card">
    <div class="summary-icon">⚖️</div>
    <div class="summary-stat">
        <span class="summary-value" id="totalCases">0</span>
        <span class="summary-label">Cases</span>
    </div>
    <div class="summary-stat">
        <span class="summary-value" id="resolvedCases">0</span>
        <span class="summary-label">Resolved</span>
    </div>
</div>

<!-- Cases List -->
<div class="viewer-list-container" id="casesContainer">
    <div class="list-header">
        <span class="list-title">Discipline History</span>
    </div>

    <div class="viewer-list" id="casesList">
        <!-- Loaded dynamically -->
    </div>
</div>

<script src="js/pages/discipline.js"></script>


<style>
    /* Viewer-specific styles for discipline list */
    .viewer-list-container {
        max-width: 600px;
        margin: 0 auto;
        padding: 1rem;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }

    .list-header {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #eee;
    }

    .list-title {
        font-weight: 600;
        color: var(--green-700);
    }

    .viewer-list-item {
        display: flex;
        gap: 1rem;
        padding: 1rem;
        border-bottom: 1px solid #f0f0f0;
    }

    .viewer-list-item:last-child {
        border-bottom: none;
    }

    .list-item-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    .bg-yellow {
        background: #fef3c7;
    }

    .bg-orange {
        background: #fed7aa;
    }

    .bg-red {
        background: #fecaca;
    }

    .bg-darkred {
        background: #fca5a5;
    }

    .bg-gray {
        background: #e5e7eb;
    }

    .list-item-content {
        flex: 1;
        min-width: 0;
    }

    .list-item-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
    }

    .list-item-title {
        font-weight: 600;
        text-transform: capitalize;
    }

    .list-item-date {
        font-size: 0.75rem;
        color: #666;
    }

    .list-item-body {
        font-size: 0.9rem;
        color: #444;
        margin-bottom: 0.5rem;
    }

    .list-item-body p {
        margin: 0 0 0.25rem 0;
    }

    .action-taken {
        font-size: 0.85rem;
        color: #666;
        font-style: italic;
    }

    .list-item-footer {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }

    .student-name {
        font-size: 0.75rem;
        color: #666;
    }

    .empty-list,
    .info-card,
    .error-card {
        text-align: center;
        padding: 3rem 2rem;
        color: #666;
    }

    .error-card {
        color: #dc2626;
    }
</style>
