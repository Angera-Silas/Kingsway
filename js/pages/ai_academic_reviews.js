/**
 * AI Academic Reviews Controller
 * Migrated to use AgentTaskPanel with Node gateway
 * Three workflows: coverage review, learning gap review, rubric draft
 */

(function () {
    'use strict';

    const esc = (value) => {
        const node = document.createElement('span');
        node.textContent = String(value ?? '');
        return node.innerHTML;
    };
    const notify = (message, type = 'info') => window.showNotification?.(message, type);
    let activeJobId = null;
    let pollTimer = null;
    let pollAttempts = 0;

    function setJobState(message, type = 'info') {
        const host = document.getElementById('aiAcademicReviews');
        if (!host) return;
        let state = document.getElementById('aiAcademicJobState');
        if (!state) {
            state = document.createElement('div');
            state.id = 'aiAcademicJobState';
            state.className = 'small mb-2';
            state.setAttribute('role', 'status');
            host.before(state);
        }
        state.className = `small mb-2 ${type === 'danger' ? 'text-danger' : type === 'success' ? 'text-success' : 'text-muted'}`;
        state.textContent = message;
    }

    function responseRows(response) {
        const data = response?.data ?? response;
        if (Array.isArray(data)) return data;
        if (Array.isArray(data?.data)) return data.data;
        if (Array.isArray(data?.rows)) return data.rows;
        if (Array.isArray(data?.outcomes)) return data.outcomes;
        return [];
    }

    async function loadRubricLearningAreas() {
        const select = document.getElementById('aiRubricLearningArea');
        if (!select || !window.API?.academic?.listLearningAreas) return;
        const areas = responseRows(await window.API.academic.listLearningAreas({}));
        select.replaceChildren(new Option('Choose learning area', ''));
        areas.forEach((area) => {
            const id = Number(area.id || 0);
            const name = String(area.name || '').trim();
            if (id > 0 && name) select.add(new Option(name, String(id)));
        });
        select.disabled = areas.length === 0;
    }

    async function loadAcademicClassOptions() {
        const selects = [
            document.getElementById('aiCoverageClassId'),
            document.getElementById('aiLearningGapClassId'),
        ].filter(Boolean);
        if (!selects.length || !window.API?.academic?.getAiAcademicClassOptions) return;
        const rows = responseRows(await window.API.academic.getAiAcademicClassOptions());
        selects.forEach((select) => {
            select.replaceChildren(new Option('Choose an authorized class', ''));
            rows.forEach((row) => {
                const id = Number(row.academic_year_class_id || 0);
                if (id < 1) return;
                const label = [row.class_name || row.grade_level || 'Class', row.year_code ? `Academic year ${row.year_code}` : ''].filter(Boolean).join(' · ');
                select.add(new Option(label, String(id)));
            });
            select.disabled = rows.length === 0;
        });
    }

    async function loadRubricOutcomes() {
        const areaSelect = document.getElementById('aiRubricLearningArea');
        const gradeSelect = document.getElementById('aiRubricGrade');
        const outcomeSelect = document.getElementById('aiRubricOutcome');
        const button = document.getElementById('queueAiRubricDraft');
        if (!outcomeSelect || !areaSelect || !gradeSelect) return;
        const areaId = Number(areaSelect.value || 0);
        const grade = String(gradeSelect.value || '');
        outcomeSelect.replaceChildren(new Option(areaId && grade ? 'Loading assigned outcomes…' : 'Choose area and grade first', ''));
        outcomeSelect.disabled = true;
        if (button) button.disabled = true;
        if (!areaId || !grade) return;
        try {
            const outcomes = responseRows(await window.API.academic.getLearningOutcomes({ learning_area_id: areaId, grade_level: grade }));
            outcomeSelect.replaceChildren(new Option(outcomes.length ? 'Choose learning outcome' : 'No assigned outcomes found', ''));
            outcomes.forEach((outcome) => {
                const id = Number(outcome.id || 0);
                const text = String(outcome.outcome || outcome.text || '').trim();
                if (id > 0 && text) outcomeSelect.add(new Option(text, String(id)));
            });
            outcomeSelect.disabled = outcomes.length === 0;
        } catch (error) {
            outcomeSelect.replaceChildren(new Option('Unable to load assigned outcomes', ''));
            notify(error.message || 'Unable to load learning outcomes.', 'danger');
        }
    }


    async function loadReviews() {
        const host = document.getElementById('aiAcademicReviews');
        if (!host || !window.API?.academic) return;
        try {
            const own = await window.API.academic.getAiSchemeDrafts('own');
            let review = { drafts: [] };
            try { review = await window.API.academic.getAiSchemeDrafts('review'); } catch (_) { /* optional review scope */ }
            const seen = new Set();
            const rows = [...(own?.data?.drafts || own?.drafts || []).map((row) => ({ ...row, __review: false })),
                         ...(review?.data?.drafts || review?.drafts || []).map((row) => ({ ...row, __review: true }))]
                .filter((row) => {
                    if (seen.has(row.id)) return false;
                    seen.add(row.id);
                    return ['academic_coverage_review', 'academic_rubric_draft', 'academic_learning_gap_review'].includes(row.metadata?.subject_type);
                });
            host.innerHTML = rows.length ? rows.map(render).join('') : '<div class="col-12 small text-muted">No academic assistant reviews have been requested.</div>';
            const completedDraft = activeJobId && rows.find((row) =>
                Number(row.metadata?.queue_job_id) === activeJobId
                && ['pending_approval', 'approved', 'failed'].includes(row.status)
            );
            if (completedDraft) {
                clearTimeout(pollTimer);
                pollTimer = null;
                pollAttempts = 0;
                activeJobId = null;
                setJobState(
                    completedDraft.status === 'failed' ? 'The academic review could not be prepared. Check the job status or try again.' : 'The academic review is ready for staff review.',
                    completedDraft.status === 'failed' ? 'danger' : 'success'
                );
            }
            host.querySelectorAll('[data-ai-academic-approve]').forEach((button) => button.addEventListener('click', async () => {
                button.disabled = true;
                try {
                    const type = button.dataset.aiAcademicType;
                    const id = button.dataset.aiAcademicApprove;
                    const approve = type === 'academic_rubric_draft'
                        ? window.API.academic.approveAiRubricDraft(id)
                        : type === 'academic_learning_gap_review'
                            ? window.API.academic.approveAiLearningGapReview(id)
                            : window.API.academic.approveAiCoverageReview(id);
                    await approve;
                    notify('Academic review approved for staff editing/guidance.', 'success');
                    await loadReviews();
                } catch (error) {
                    notify(error.message || 'Unable to approve academic review.', 'danger');
                    button.disabled = false;
                }
            }));
            return rows;
        } catch (_) {
            host.innerHTML = '<div class="col-12 small text-warning">Academic assistant reviews are temporarily unavailable.</div>';
            return [];
        }
    }

    function watchJob(jobId) {
        clearTimeout(pollTimer);
        activeJobId = Number(jobId) || null;
        pollAttempts = 0;
        if (!activeJobId) {
            setJobState('The academic review was queued. Refresh this panel to check its status.');
            return;
        }
        setJobState(`Academic review job #${activeJobId} is queued. Checking for progress…`);
        const poll = async () => {
            pollAttempts += 1;
            let job = null;
            try { job = await window.API.nodeJobs?.status?.(activeJobId); } catch (_) { /* Draft listing remains a fallback if job status is temporarily unavailable. */ }
            const status = String(job?.status || '');
            if (['failed', 'cancelled', 'dead_letter'].includes(status)) {
                activeJobId = null;
                pollTimer = null;
                pollAttempts = 0;
                setJobState(status === 'failed' || status === 'dead_letter' ? 'The academic review job failed. Try again or contact support if it repeats.' : 'The academic review was cancelled.', status === 'failed' || status === 'dead_letter' ? 'danger' : 'info');
                await loadReviews();
                return;
            }
            setJobState(status === 'processing' || status === 'running' ? 'Academic review is being prepared…' : `Academic review job #${activeJobId} is queued. Checking for progress…`);
            const rows = await loadReviews();
            if (!activeJobId) return;
            if (status === 'done' || status === 'completed') {
                activeJobId = null;
                pollTimer = null;
                pollAttempts = 0;
                setJobState('The job completed, but its review draft was not returned. Refresh or contact support.', 'danger');
                return;
            }
            if (pollAttempts < 12) pollTimer = setTimeout(poll, 5000);
            else {
                activeJobId = null;
                pollTimer = null;
                pollAttempts = 0;
                setJobState(`Academic review status: ${status || 'unknown'}. Refresh this panel to check again.`);
            }
            return rows;
        };
        pollTimer = setTimeout(poll, 1500);
    }

    async function queueReview(button, enqueue, queuedMessage, failureMessage) {
        if (!button || button.disabled) return;
        button.disabled = true;
        try {
            const result = await enqueue();
            const jobId = result?.data?.job_id ?? result?.job_id;
            notify(queuedMessage, 'info');
            watchJob(jobId);
            await loadReviews();
        } catch (error) {
            setJobState(error.message || failureMessage, 'danger');
            notify(error.message || failureMessage, 'danger');
        } finally {
            button.disabled = false;
        }
    }

    function render(row) {
        const body = row.draft || {};
        const type = row.metadata?.subject_type || '';
        const label = type === 'academic_rubric_draft' ? 'CBC rubric draft' : type === 'academic_learning_gap_review' ? 'Learning-gap review' : 'Curriculum mapping completeness';
        const approve = row.__review && row.status === 'pending_approval'
            ? `<button type="button" class="btn btn-sm btn-success" data-ai-academic-approve="${Number(row.id)}" data-ai-academic-type="${esc(type)}">Approve guidance</button>` : '';
        const nextSteps = Array.isArray(body.next_steps) ? `<ul class="small mb-2">${body.next_steps.map((step) => `<li>${esc(step)}</li>`).join('')}</ul>` : '';
        const criteria = Array.isArray(body.criteria) && body.criteria.length
            ? `<div class="table-responsive mt-2"><table class="table table-sm table-bordered align-middle"><thead><tr><th>Criterion</th>${(body.criteria[0]?.levels || []).map((level) => `<th>${esc(level.label)}</th>`).join('')}</tr></thead><tbody>${body.criteria.map((criterion) => `<tr><th>${esc(criterion.criterion)}</th>${(criterion.levels || []).map((level) => `<td>${esc(level.descriptor)}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`
            : '';
        return `<div class="col-12 col-xl-6"><article class="border rounded p-2 h-100"><div class="d-flex justify-content-between gap-2"><strong>${esc(body.title || label)}</strong><span class="badge text-bg-secondary">${esc(row.status || 'queued')}</span></div><p class="small mt-2 mb-2">${esc(body.body || 'Review is still being generated.')}</p>${criteria}${nextSteps}${approve}${row.status === 'approved' ? '<div class="small text-info mt-2">Approved advisory output only; normal academic editing and approval remain required.</div>' : ''}</article></div>`;
    }

    async function queueCoverageReview() {
        const button = document.getElementById('queueAiCoverageReview');
        if (!button) return;
        const classId = Number(document.getElementById('aiCoverageClassId')?.value || 0);
        if (classId < 1) return notify('Choose an authorized academic class first.', 'warning');
        await queueReview(button,
            () => window.API.academic.queueAiCoverageReview({ academic_year_class_id: classId }),
            'Curriculum mapping review queued for approval.', 'Unable to queue mapping review');
    }

    async function queueLearningGapReview() {
        const button = document.getElementById('queueAiLearningGapReview');
        if (!button) return;
        const classId = Number(document.getElementById('aiLearningGapClassId')?.value || 0);
        if (classId < 1) return notify('Choose an authorized academic class first.', 'warning');
        await queueReview(button,
            () => window.API.academic.queueAiLearningGapReview({ academic_year_class_id: classId }),
            'Aggregate learning-gap review queued for approval.', 'Unable to queue learning-gap review');
    }

    async function queueRubricDraft() {
        const button = document.getElementById('queueAiRubricDraft');
        if (!button) return;
        const outcomeId = Number(document.getElementById('aiRubricOutcome')?.value || 0);
        if (outcomeId < 1) return notify('Select an assigned learning outcome first.', 'warning');
        const intent = String(document.getElementById('aiRubricIntent')?.value || '').trim();
        await queueReview(button,
            () => window.API.academic.queueAiRubricDraft({ learning_outcome_id: outcomeId, teacher_intent: intent, criterion_count: 4, performance_levels: 'Beginning, Developing, Meeting, Exceeding' }),
            'CBC rubric draft queued for approval.', 'Unable to queue rubric draft');
    }

    async function init() {
        document.getElementById('queueAiCoverageReview')?.addEventListener('click', queueCoverageReview);
        document.getElementById('queueAiLearningGapReview')?.addEventListener('click', queueLearningGapReview);
        document.getElementById('queueAiRubricDraft')?.addEventListener('click', queueRubricDraft);
        document.getElementById('aiRubricLearningArea')?.addEventListener('change', loadRubricOutcomes);
        document.getElementById('aiRubricGrade')?.addEventListener('change', loadRubricOutcomes);
        document.getElementById('aiRubricOutcome')?.addEventListener('change', () => {
            const button = document.getElementById('queueAiRubricDraft');
            if (button) button.disabled = !document.getElementById('aiRubricOutcome')?.value;
        });
        try { await loadAcademicClassOptions(); } catch (error) { notify(error.message || 'Unable to load your academic classes.', 'danger'); }
        try { await loadRubricLearningAreas(); } catch (error) { notify(error.message || 'Unable to load learning areas.', 'danger'); }
        await loadReviews();
    }

    window.APIRealtime?.register?.('ai_academic_reviews', {
        refresh: async () => { await loadReviews(); },
    }, ['academic']);

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();
