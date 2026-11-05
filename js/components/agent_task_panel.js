/**
 * Reusable Agent Task Panel Component
 * 
 * Uses the Node agent gateway task API:
 * - POST /api/agents/tasks to create task
 * - GET /api/agents/tasks/:id/stream for SSE subscription (component-level)
 * - Falls back to polling (PHP dashboard endpoints) when the gateway origin
 *   is unavailable or SSE drops
 * 
 * Node carries live task events. PHP owns authentication, authorization,
 * durable queue state, and governed school data; Python provides the assistant
 * orchestration when enabled.
 * 
 * Usage:
 *   const panel = new AgentTaskPanel({
 *     container: '#my-panel',
 *     tool: 'drafts.create' | 'reports.aggregate' | 'charts.generate',
 *     question: 'Draft a lesson plan on photosynthesis',
 *     route: 'academics',
 *     module: 'lesson_plans',
 *     targetComponent: '#lesson-plan-editor',
 *     sessionId: 'optional-session-id',
 *     onComplete: (result) => { /* update editor *\/ },
 *     onError: (error) => { /* show notification *\/ },
 *     onProgress: (progress) => { /* update UI *\/ },
 *   });
 *   panel.start();
 */

(function() {
  'use strict';

  class AgentTaskPanel {
    constructor(options = {}) {
      this.container = typeof options.container === 'string' 
        ? document.querySelector(options.container) 
        : options.container;
      if (!this.container) {
        throw new Error('AgentTaskPanel: container element not found');
      }

      // AgentTaskPanel delegates a general routed assistant request. Domain
      // tools must use their PHP-owned workflow adapters and approval paths.
      this.tool = options.tool || 'assistant';
      this.question = options.question || '';
      this.route = options.route || 'dashboard';
      this.module = options.module || 'dashboard';
      this.sessionId = options.sessionId || null;
      this.targetComponent = options.targetComponent || null;
      this.componentId = options.componentId || `agent-task-${this.route}-${this.module}`
        .toLowerCase().replace(/[^a-z0-9._-]+/g, '-').slice(0, 100);
      const currentUser = window.AuthContext?.getUser?.() || {};
      const userKey = String(currentUser.id || currentUser.user_id || 'current');
      this.persistenceKey = `kw.ai.task.${userKey}.${this.componentId}`;
      this.hasCompletionHandler = typeof options.onComplete === 'function';
      this.onComplete = options.onComplete || (() => {});
      this.onError = options.onError || (() => {});
      this.onProgress = options.onProgress || (() => {});

      this.taskId = null;
      this.eventSource = null;
      this.pollInterval = null;
      this.pollDelayMs = 2000;
      this.isDestroyed = false;
      this.isStarting = false;
    }

    async start() {
      if (this.isDestroyed || this.isStarting) return;
      this.isStarting = true;
      
      try {
        const savedTaskId = this.readSavedTaskId();
        if (savedTaskId) {
          const existingResponse = await window.API.dashboard.agentTaskGet(savedTaskId);
          const existing = existingResponse?.data ?? existingResponse;
          if (existing && ['queued', 'processing'].includes(existing.status)) {
            this.taskId = String(existing.task_id || savedTaskId);
            this.showLoading('Reconnecting to your task...');
            await this.subscribeToTaskStream();
            return;
          }
          if (existing?.status === 'completed') {
            this.taskId = String(savedTaskId);
            if (existing.result_available === false) {
              this.handleError({ message: existing.message || 'Your access changed. Start a new task to get a current answer.' });
              return;
            }
            this.handleFinal({ result: existing.result });
            return;
          }
          if (existing?.status === 'failed' || existing?.status === 'cancelled') {
            this.taskId = String(savedTaskId);
            this.handleError({ message: existing.error || (existing.status === 'cancelled' ? 'Task was cancelled' : 'Task failed') });
            return;
          }
          if (!existing) {
            this.clearSavedTaskId();
            this.showError('This task is no longer available. Retry to start a new task.');
            return;
          }
          this.clearSavedTaskId();
        }
        this.showLoading('Creating agent task...');
        const task = await this.createTask();
        this.taskId = String(task.task_id);
        this.saveTaskId(this.taskId);
        await this.subscribeToTaskStream();
        
      } catch (error) {
        this.showError(error.message || 'Failed to start agent task');
        this.onError(error);
      } finally {
        this.isStarting = false;
      }
    }

    async createTask() {
      const response = await window.API.dashboard.agentTaskCreate({
        tool: this.tool,
        question: this.question,
        route: this.route,
        module: this.module,
        session_id: this.sessionId,
      });
      const task = response?.data ?? response;
      
      if (!task?.task_id) {
        throw new Error('Failed to create task: ' + (task?.message || response?.message || 'Unknown error'));
      }
      
      return task;
    }

    async subscribeToTaskStream() {
      if (this.isDestroyed) return;
      
      // Get auth token
      const token = window.AuthContext?.getToken?.() || '';
      if (!token) {
        throw new Error('Authentication required for task stream');
      }

      const gatewayOrigin = String(window.KINGSWAY_REALTIME_ORIGIN || '').replace(/\/+$/, '');
      if (!gatewayOrigin) {
        this.startPollingFallback();
        return;
      }
      
      this.showLoading('Waiting for agent response...');
      
      const streamUrl = `${gatewayOrigin}/api/agents/tasks/${encodeURIComponent(this.taskId)}/stream?token=${encodeURIComponent(token)}`;
      this.eventSource = new EventSource(streamUrl);
      
      this.eventSource.addEventListener('delta', (e) => this.handleStreamEvent(e, 'delta'));
      this.eventSource.addEventListener('status', (e) => this.handleStreamEvent(e, 'status'));
      this.eventSource.addEventListener('final', (e) => this.handleStreamEvent(e, 'final'));
      this.eventSource.addEventListener('error', (e) => this.handleStreamEvent(e, 'error'));
      
      this.eventSource.onerror = () => {
        // SSE failed, fall back to polling
        this.logger('SSE connection lost, falling back to polling');
        this.startPollingFallback();
      };
    }

    handleStreamEvent(event, type) {
      if (this.isDestroyed) return;
      let data;
      try {
        if (typeof event?.data !== 'string' || event.data === '') throw new Error('Missing event payload');
        data = JSON.parse(event.data);
      } catch (error) {
        this.logger('Invalid task stream event:', type, error?.message || error);
        if (type === 'error' || type === 'final') this.startPollingFallback();
        return;
      }
      if (type === 'delta') this.handleDelta(data);
      else if (type === 'status') this.handleStatus(data);
      else if (type === 'final') this.handleFinal(data);
      else this.handleError(data);
    }

    startPollingFallback() {
      if (this.isDestroyed) return;
      if (this.pollInterval) return;
      
      // Close SSE if open
      if (this.eventSource) {
        this.eventSource.close();
        this.eventSource = null;
      }
      
      this.showLoading('Polling for task status...');
      this.pollDelayMs = 2000;
      const poll = async () => {
        this.pollInterval = null;
        if (this.isDestroyed || !this.taskId) return;

        let retry = false;
        try {
          const response = await window.API.dashboard.agentTaskGet(this.taskId);
          const task = response?.data ?? response;
          if (!task) {
            retry = true;
          } else if (task.status === 'completed') {
            if (task.result_available === false) {
              this.handleError({ message: task.message || 'Your access changed. Start a new task to get a current answer.' });
              return;
            }
            this.handleFinal({ result: task.result });
            return;
          } else if (task.status === 'failed') {
            this.handleError({ message: task.error || 'Task failed' });
            return;
          } else if (task.status === 'cancelled') {
            this.handleError({ message: 'Task was cancelled' });
            return;
          } else {
            this.updateStatus(task.message || task.status);
            if (task.progress) this.updateProgress(task.progress);
            this.pollDelayMs = 2000;
          }
        } catch (error) {
          retry = true;
          this.logger('Polling error:', error);
        }

        if (this.isDestroyed || !this.taskId) return;
        if (retry) {
          this.updateStatus('Status check failed. Reconnecting…');
          this.pollDelayMs = Math.min(Math.max(this.pollDelayMs * 2, 2000), 15000);
        }
        // Schedule only after the previous request settles. Slow API responses
        // therefore cannot pile up into concurrent polling requests.
        this.pollInterval = setTimeout(poll, this.pollDelayMs);
      };
      this.pollInterval = setTimeout(poll, this.pollDelayMs);
    }

    handleDelta(data) {
      if (this.isDestroyed) return;
      if (data?.text) {
        this.appendOutput(data.text);
        this.onProgress({ type: 'delta', text: data.text });
      }
    }

    handleStatus(data) {
      if (this.isDestroyed) return;
      if (data?.text) {
        this.updateStatus(data.text);
        this.onProgress({ type: 'status', text: data.text });
      }
    }

    handleFinal(data) {
      if (this.isDestroyed) return;
      this.cleanup();
      this.clearSavedTaskId();
      
      if (data?.result) {
        this.showSuccess(data.result);
        this.onComplete(data.result);
      } else if (data?.answer) {
        this.showSuccess(data.answer);
        this.onComplete(data.answer);
      }
    }

    handleError(data) {
      if (this.isDestroyed) return;
      if (data?.retryable && this.taskId) {
        if (this.eventSource) {
          this.eventSource.close();
          this.eventSource = null;
        }
        this.updateStatus('Live updates paused. Reconnecting…');
        this.startPollingFallback();
        return;
      }
      this.cleanup();
      this.clearSavedTaskId();
      const message = data?.message || data?.error || 'Agent task failed';
      this.showError(message);
      this.onError(new Error(message));
    }

    cleanup() {
      if (this.eventSource) {
        this.eventSource.close();
        this.eventSource = null;
      }
      if (this.pollInterval) {
        clearTimeout(this.pollInterval);
        this.pollInterval = null;
      }
    }

    readSavedTaskId() {
      try { return window.localStorage.getItem(this.persistenceKey) || ''; } catch { return ''; }
    }

    saveTaskId(taskId) {
      try { window.localStorage.setItem(this.persistenceKey, String(taskId)); } catch { /* storage may be disabled */ }
    }

    clearSavedTaskId() {
      try { window.localStorage.removeItem(this.persistenceKey); } catch { /* storage may be disabled */ }
    }

    destroy() {
      this.isDestroyed = true;
      this.cleanup();
      this.container.innerHTML = '';
    }

    // UI Methods
    showLoading(message) {
      this.container.innerHTML = `
        <div class="agent-task-panel loading">
          <div class="d-flex align-items-center gap-2 p-3" role="status" aria-live="polite" aria-atomic="true">
            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
            <span class="text-muted">${this.escapeHtml(message)}</span>
          </div>
          <div class="progress mt-2" role="progressbar" aria-label="Assistant task progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="10" style="height: 4px;">
            <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 10%"></div>
          </div>
          <div class="d-flex justify-content-end mt-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-action="cancel-task" ${this.taskId ? '' : 'disabled'}>Cancel task</button>
          </div>
        </div>
      `;
      this.bindTaskCancellation();
    }

    updateStatus(message) {
      const statusEl = this.container.querySelector('.agent-status');
      if (statusEl) {
        statusEl.textContent = message;
      } else {
        this.container.innerHTML = `
          <div class="agent-task-panel">
            <div class="agent-status small text-muted mb-2" role="status" aria-live="polite" aria-atomic="true">${this.escapeHtml(message)}</div>
            <div class="progress" role="progressbar" aria-label="Assistant task progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="50" style="height: 4px;">
              <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 50%"></div>
            </div>
            <div class="agent-output mt-2 small" style="max-height: 300px; overflow-y: auto;"></div>
            <div class="d-flex justify-content-end mt-2">
              <button type="button" class="btn btn-sm btn-outline-secondary" data-action="cancel-task" ${this.taskId ? '' : 'disabled'}>Cancel task</button>
            </div>
          </div>
        `;
        this.bindTaskCancellation();
      }
    }

    updateProgress(percent) {
      const progressBar = this.container.querySelector('.progress-bar');
      if (progressBar) {
        const value = Math.max(0, Math.min(100, Number(percent) || 0));
        progressBar.style.width = value + '%';
        const progress = progressBar.closest('[role="progressbar"]');
        if (progress) progress.setAttribute('aria-valuenow', String(value));
      }
    }

    appendOutput(text) {
      let outputEl = this.container.querySelector('.agent-output');
      if (!outputEl) {
        this.container.innerHTML = `
          <div class="agent-task-panel">
            <div class="agent-status small text-muted mb-2">Receiving response...</div>
            <div class="progress" style="height: 4px;">
              <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 80%"></div>
            </div>
            <div class="agent-output mt-2 small" style="max-height: 300px; overflow-y: auto;"></div>
          </div>
        `;
        outputEl = this.container.querySelector('.agent-output');
      }
      outputEl.textContent += text;
      outputEl.scrollTop = outputEl.scrollHeight;
    }

    showSuccess(result) {
      const draft = result?.draft;
      const body = [
        result?.answer?.body,
        result?.body,
        typeof draft === 'string' ? draft : draft?.body,
        result?.summary,
      ].find((value) => typeof value === 'string' && value.trim() !== '')
        || JSON.stringify(draft && typeof draft === 'object' ? draft : result, null, 2);
      
      // Callers with an onComplete handler own applying the result. Inject only
      // when the panel is the sole result handler, so editors receive one update.
      if (this.targetComponent && !this.hasCompletionHandler) {
        const target = document.querySelector(this.targetComponent);
        if (target) {
          target.value = body;
          target.dispatchEvent(new Event('input', { bubbles: true }));
        }
      }
      
      this.container.innerHTML = `
        <div class="agent-task-panel success">
          <div class="d-flex align-items-center gap-2 mb-2">
            <i class="bi bi-check-circle-fill text-success"></i>
            <strong>Draft Ready</strong>
          </div>
          <div class="agent-output small p-2 bg-light rounded" style="max-height: 300px; overflow-y: auto; white-space: pre-wrap;">${this.escapeHtml(body)}</div>
          <div class="d-flex gap-2 mt-3">
            <button class="btn btn-sm btn-primary" data-action="copy-draft">Copy draft</button>
            <button class="btn btn-sm btn-outline-secondary" data-action="retry">Retry</button>
          </div>
        </div>
      `;
      this.container.querySelector('[data-action="copy-draft"]')?.addEventListener('click', async (event) => {
        const button = event.currentTarget;
        button.disabled = true;
        try {
          await navigator.clipboard.writeText(body);
          button.textContent = 'Copied';
        } catch (_) {
          button.textContent = 'Copy unavailable';
        } finally {
          button.disabled = false;
        }
      });
      this.container.querySelector('[data-action="retry"]')?.addEventListener('click', () => this.start());
    }

    showError(message) {
      this.container.innerHTML = `
        <div class="agent-task-panel error" role="alert" aria-live="assertive">
          <div class="d-flex align-items-center gap-2 mb-2">
            <i class="bi bi-exclamation-triangle-fill text-danger"></i>
            <strong class="text-danger">Error</strong>
          </div>
          <div class="small text-danger">${this.escapeHtml(message)}</div>
          <div class="d-flex gap-2 mt-3">
            <button type="button" class="btn btn-sm btn-primary" data-action="retry">Retry</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-action="dismiss">Dismiss</button>
          </div>
        </div>
      `;
      this.container.querySelector('[data-action="retry"]')?.addEventListener('click', () => this.start());
      this.container.querySelector('[data-action="dismiss"]')?.addEventListener('click', () => {
        this.cleanup();
        this.container.replaceChildren();
      });
    }

    bindTaskCancellation() {
      this.container.querySelector('[data-action="cancel-task"]')?.addEventListener('click', (event) => {
        this.cancelTask(event.currentTarget);
      });
    }

    async cancelTask(button) {
      if (!this.taskId || !window.API?.dashboard?.agentTaskCancel) return;
      button.disabled = true;
      button.textContent = 'Cancelling…';
      try {
        await window.API.dashboard.agentTaskCancel(this.taskId);
        this.cleanup();
        this.clearSavedTaskId();
        this.taskId = null;
        this.container.innerHTML = '<div class="agent-task-panel small text-muted" role="status">Task cancelled.</div>';
      } catch (error) {
        button.disabled = false;
        button.textContent = 'Cancel task';
        this.updateStatus(error?.message || 'Could not cancel the task. It may still be running.');
      }
    }

    escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = String(text ?? '');
      return div.innerHTML;
    }

    logger(...args) {
      if (window.console && console.debug) {
        console.debug('[AgentTaskPanel]', ...args);
      }
    }
  }

  // Export for module systems and global
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = AgentTaskPanel;
  } else {
    window.AgentTaskPanel = AgentTaskPanel;
  }
})();
