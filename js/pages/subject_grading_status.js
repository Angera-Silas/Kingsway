/**
 * Subject Grading Status Controller - Subject Teacher Grading Status Viewing
 * Role: Subject Teacher (8)
 * Shows only grading status for the teacher's assigned subjects
 * Integrates with AcademicContext for academic year awareness
 */

const subjectGradingStatusCtrl = (() => {
    const state = {
        assessments: [],
        subjects: [],
        years: [],
        terms: [],
        currentAcademicYear: null,
        currentTerm: null
    };

    function toast(msg, type = 'info') {
        const el = document.getElementById('statusToast');
        if (!el) {
            const toast = document.createElement('div');
            toast.id = 'statusToast';
            toast.className = `toast align-items-center text-bg-${type} border-0 position-fixed top-0 end-0 m-3`;
            toast.style.zIndex = '9999';
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">${msg}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            document.body.appendChild(toast);
            new bootstrap.Toast(toast, { delay: 3000 }).show();
        } else {
            el.className = `toast align-items-center text-bg-${type} border-0 position-fixed top-0 end-0 m-3`;
            el.querySelector('.toast-body').textContent = msg;
            new bootstrap.Toast(el, { delay: 3000 }).show();
        }
    }

    async function apiCall(endpoint, method = 'GET', data = null) {
        try {
            return await window.API.apiCall(endpoint, method, data, null, { checkPermission: false });
        } catch (error) {
            console.error('API call failed:', error);
            throw error;
        }
    }

    /** Rows from an unwrapped API payload (list or {data|items|...}). */
    function rowsOf(payload, key = '') {
        if (Array.isArray(payload)) return payload;
        if (key && Array.isArray(payload?.[key])) return payload[key];
        return payload?.data || payload?.items || payload?.assessments || payload?.students || payload?.subjects || payload?.exams || [];
    }

    /** The shared year/term selector; the current year (and running term) by default. */
    async function mountYearFilter() {
        await window.AcademicYearFilter?.mount(document.getElementById('yearFilter'), {
            termSelect: document.getElementById('termFilter'),
            allTermsLabel: 'All terms',
            allTermsDefault: false,
            onChange: (yearId, year, termId) => {
                state.currentAcademicYear = yearId;
                state.currentTerm = termId;
                loadGradingStatus();
            },
        });
    }

    async function loadSubjects() {
        try {
            // Load only subjects assigned to this teacher
            const response = await window.API.apiCall('academic/subjects-list', 'GET', null, {
                subject_teacher_only: true
            }, { checkPermission: false });
            state.subjects = rowsOf(response);
            const select = document.getElementById('subjectFilter');
            if (select) {
                select.innerHTML = '<option value="">All Subjects</option>';
                state.subjects.forEach(subject => {
                    const option = document.createElement('option');
                    option.value = subject.id;
                    option.textContent = subject.subject_name;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Failed to load subjects:', error);
        }
    }

    async function loadGradingStatus() {
        try {
            const yearId = state.currentAcademicYear;
            if (!yearId) return;
            const termId = state.currentTerm || '';
            const subjectId = document.getElementById('subjectFilter')?.value || '';

            // Re-pointed from the non-existent `grading-status` slug (which fell
            // through the router into the subjects-list fallback). `assessments-list`
            // returns per-assessment objects with grading counts baked in, exactly
            // what this page renders. We map its field names onto the page's shape.
            const response = await window.API.apiCall('academic/assessments-list', 'GET', null, {
                academic_year_id: yearId,
                year_id: yearId,
                academic_year_term_id: termId,
                term_id: termId,
                subject_id: subjectId
            }, { checkPermission: false });

            state.assessments = (response.data || []).map(a => ({
                name: a.title,
                subject_name: a.learning_area_name || '—',
                class_name: a.class_name || '—',
                type: a.type_name || (a.is_summative ? 'Summative' : a.is_formative ? 'Formative' : '—'),
                assessment_date: a.assessment_date || '—',
                total_students: a.total_students || 0,
                graded_students: a.graded_count || 0
            }));
            renderGradingStatus();
            updateStats();
        } catch (error) {
            console.error('Failed to load grading status:', error);
            toast('Failed to load grading status', 'error');
        }
    }

    function renderGradingStatus() {
        const tbody = document.getElementById('statusTableBody');
        if (!tbody) return;

        if (!state.assessments.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">
                        No grading status found for your subjects
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = state.assessments.map(assessment => {
            const totalStudents = assessment.total_students || 0;
            const graded = assessment.graded_students || 0;
            const pending = totalStudents - graded;
            const isComplete = pending === 0;
            const statusClass = isComplete ? 'success' : 'warning';
            const statusText = isComplete ? 'Complete' : 'In Progress';

            return `
                <tr>
                    <td><strong>${assessment.name || 'Unnamed Assessment'}</strong></td>
                    <td>${assessment.subject_name || '—'}</td>
                    <td>${assessment.class_name || '—'}</td>
                    <td><span class="badge bg-secondary">${assessment.type || '—'}</span></td>
                    <td>${assessment.assessment_date || '—'}</td>
                    <td><strong>${totalStudents}</strong></td>
                    <td><span class="badge bg-success">${graded}</span></td>
                    <td><span class="badge bg-warning">${pending}</span></td>
                    <td><span class="badge bg-${statusClass}">${statusText}</span></td>
                </tr>
            `;
        }).join('');
        // A completed year stays read-only for users who may not edit it.
        window.YearMode?.refresh(tbody);
    }

    function updateStats() {
        const total = state.assessments.length;
        const graded = state.assessments.filter(a => (a.graded_students || 0) === (a.total_students || 0)).length;
        const pending = total - graded;

        document.getElementById('totalAssessments').textContent = total;
        document.getElementById('gradedAssessments').textContent = graded;
        document.getElementById('pendingAssessments').textContent = pending;
    }

    async function exportStatus() {
        if (!state.assessments.length) {
            toast('No grading status to export', 'warning');
            return;
        }

        // Use PrintManager for CSV export if available
        if (window.PrintManager) {
            const columns = [
                { key: 'assessment_name', label: 'Assessment' },
                { key: 'subject_name', label: 'Subject' },
                { key: 'class_name', label: 'Class' },
                { key: 'type', label: 'Type' },
                { key: 'assessment_date', label: 'Date' },
                { key: 'total_students', label: 'Total Students' },
                { key: 'graded', label: 'Graded' },
                { key: 'pending', label: 'Pending' },
                { key: 'status', label: 'Status' }
            ];

            const rows = state.assessments.map(assessment => {
                const totalStudents = assessment.total_students || 0;
                const graded = assessment.graded_students || 0;
                const pending = totalStudents - graded;
                const isComplete = pending === 0;
                const statusText = isComplete ? 'Complete' : 'In Progress';

                return {
                    assessment_name: assessment.name || 'Unnamed Assessment',
                    subject_name: assessment.subject_name || '—',
                    class_name: assessment.class_name || '—',
                    type: assessment.type || '—',
                    assessment_date: assessment.assessment_date || '—',
                    total_students: totalStudents,
                    graded: graded,
                    pending: pending,
                    status: statusText
                };
            });

            window.PrintManager.exportToCSV({
                filename: `subject_grading_status_${new Date().toISOString().slice(0,10)}.csv`,
                columns: columns,
                rows: rows
            });
        } else {
            toast('PrintManager not available', 'error');
        }
    }

    function bindEvents() {
        document.getElementById('refreshBtn')?.addEventListener('click', loadGradingStatus);
        document.getElementById('exportBtn')?.addEventListener('click', exportStatus);
        document.getElementById('subjectFilter')?.addEventListener('change', loadGradingStatus);
    }

    async function init() {
        await window.AuthContext?.ready();
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }

        // Initialize Academic Context if available
        if (window.AcademicContext) {
            window.AcademicContext.subscribe((context, event, data) => {
                if (event === 'refreshed') loadGradingStatus();
            });

            if (!window.AcademicContext.isLoaded()) {
                await window.AcademicContext.init();
            }
        }

        document.getElementById('statusLoading').style.display = 'none';
        document.getElementById('statusContent').style.display = 'block';

        await Promise.all([loadSubjects()]);

        bindEvents();

        await mountYearFilter(); // loads data for the default year/term

    }

    return { init };
})();

document.addEventListener('DOMContentLoaded', subjectGradingStatusCtrl.init);

window.subjectGradingStatusCtrl = subjectGradingStatusCtrl;
window.APIRealtime?.register?.(subjectGradingStatusCtrl, subjectGradingStatusCtrl);
