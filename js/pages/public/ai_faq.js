(function () {
  'use strict';

  function escapeHtml(value) {
    const node = document.createElement('div');
    node.textContent = String(value ?? '');
    return node.innerHTML;
  }

  // Governed answers carry Markdown (bold, bullets, report codes). Render it
  // through the shared, XSS-safe module instead of escaping it to plain text,
  // and degrade to escaped text if the module is not loaded.
  function renderMarkdown(value) {
    return window.KingswayMarkdown && typeof window.KingswayMarkdown.render === 'function'
      ? window.KingswayMarkdown.render(value)
      : escapeHtml(String(value ?? '').replace(/\n/g, '<br>'));
  }

  function gatewayOrigin() {
    return String(window.KINGSWAY_REALTIME_ORIGIN || '').replace(/\/+$/, '');
  }

  // Anonymous visitor id: random, never tied to a name. The server hashes it;
  // it keys the optional survey and any block on this browser.
  function visitorId() {
    try {
      let id = localStorage.getItem('kingsway_visitor_id') || '';
      if (!/^[a-f0-9]{32}$/.test(id)) {
        const bytes = new Uint8Array(16);
        (window.crypto || window.msCrypto).getRandomValues(bytes);
        id = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
        localStorage.setItem('kingsway_visitor_id', id);
      }
      return id;
    } catch (_) {
      return '';
    }
  }

  const ASKED_KEY = 'kingsway_public_ai_feedback_asked';
  function askedKeys() {
    try {
      const keys = JSON.parse(sessionStorage.getItem(ASKED_KEY) || '[]');
      return Array.isArray(keys) ? keys.filter((k) => /^[a-z_]{1,40}$/.test(k)).slice(0, 12) : [];
    } catch (_) {
      return [];
    }
  }
  function rememberAsked(key) {
    const keys = askedKeys();
    if (!keys.includes(key)) keys.push(key);
    try { sessionStorage.setItem(ASKED_KEY, JSON.stringify(keys)); } catch (_) {}
  }

  async function askAssistant(question, conversation) {
    const payload = { question, conversation, visitor_id: visitorId(), asked_keys: askedKeys() };
    // Prefer the Node gateway when configured; keep the PHP endpoint as the
    // governed fallback so visitors always get an answer.
    const origin = gatewayOrigin();
    if (origin && window.API && typeof window.API.apiCall === 'function') {
      try {
        return await window.API.apiCall(origin + '/api/agents/public-call', 'POST', payload);
      } catch (_) {
        /* fall through to PHP */
      }
    }
    return window.callAPI('/public/ai-faq', 'POST', payload);
  }

  // Marketer mode: one optional, anonymous question with tap-to-answer
  // options (or a short text box) and a Skip. Nothing is required.
  function renderFeedbackPrompt(prompt, target) {
    if (!target || !prompt || !/^[a-z_]{1,40}$/.test(String(prompt.key || ''))) return;
    rememberAsked(prompt.key);
    const card = document.createElement('div');
    card.className = 'public-ai-feedback mt-3 p-2 border rounded';
    const heading = document.createElement('p');
    heading.className = 'small fw-semibold mb-1';
    heading.textContent = String(prompt.question || '');
    const note = document.createElement('p');
    note.className = 'small text-muted mb-2';
    note.textContent = String(prompt.notice || 'Optional and anonymous.');
    const actions = document.createElement('div');
    actions.className = 'd-flex flex-wrap gap-1';
    const thank = (message) => {
      card.replaceChildren();
      const done = document.createElement('p');
      done.className = 'small text-success mb-0';
      done.textContent = message;
      card.appendChild(done);
    };
    const send = async (answer) => {
      actions.querySelectorAll('button, input').forEach((el) => { el.disabled = true; });
      try {
        await window.callAPI('/public/ai-feedback', 'POST', { question_key: prompt.key, answer, visitor_id: visitorId() });
        thank('Thank you for sharing. It helps us serve families better.');
      } catch (_) {
        thank('Thank you.');
      }
    };
    const options = Array.isArray(prompt.options) ? prompt.options.slice(0, 8) : [];
    if (options.length) {
      options.forEach((option) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-outline-success';
        button.textContent = String(option);
        button.addEventListener('click', () => send(String(option)));
        actions.appendChild(button);
      });
    } else {
      const text = document.createElement('input');
      text.type = 'text';
      text.maxLength = 300;
      text.className = 'form-control form-control-sm';
      text.placeholder = 'Your answer (optional)';
      const sendButton = document.createElement('button');
      sendButton.type = 'button';
      sendButton.className = 'btn btn-sm btn-success';
      sendButton.textContent = 'Send';
      sendButton.addEventListener('click', () => { if (text.value.trim()) send(text.value.trim()); });
      actions.append(text, sendButton);
    }
    const skip = document.createElement('button');
    skip.type = 'button';
    skip.className = 'btn btn-sm btn-link text-muted';
    skip.textContent = 'Skip';
    skip.addEventListener('click', () => card.remove());
    actions.appendChild(skip);
    card.append(heading, note, actions);
    target.appendChild(card);
  }

  function renderAnswer(answer, target) {
    const title = escapeHtml(answer.title || (answer.status === 'escalate' ? 'Let us help you' : 'Kingsway assistant'));
    const body = renderMarkdown(answer.body || answer.message || 'Please contact the school for assistance.');
    const sources = Array.isArray(answer.sources) && answer.sources.length
      ? '<div class="public-ai-sources"><strong>Based on published information:</strong> ' + answer.sources.map(escapeHtml).join(', ') + '</div>'
      : '';
    const referral = answer.human_referral ? '<p class="small text-warning mt-2 mb-0"><i class="bi bi-person-check me-1"></i>' + escapeHtml(answer.human_referral) + '</p>' : '';
    target.innerHTML = '<strong>' + title + '</strong><div class="public-ai-answer-body mt-2">' + body + '</div>' + referral + sources;
  }

  function renderHistory(turns, target) {
    if (!target) return;
    target.replaceChildren();
    turns.forEach((turn) => {
      if (!turn || !['user', 'assistant'].includes(turn.role) || !String(turn.content || '').trim()) return;
      const item = document.createElement('div');
      item.className = 'public-ai-message ' + (turn.role === 'user' ? 'public-ai-message-user' : 'public-ai-message-assistant');
      const label = document.createElement('span');
      label.className = 'public-ai-message-label';
      label.textContent = turn.role === 'user' ? 'You' : 'Kingsway assistant';
      const content = document.createElement('p');
      content.className = 'mb-0';
      content.textContent = String(turn.content).slice(0, 5000);
      item.append(label, content);
      target.appendChild(item);
    });
    target.hidden = target.childElementCount === 0;
    if (!target.hidden) target.scrollTop = target.scrollHeight;
  }

  function renderSuggestions(items, container, input, form) {
    if (!container) return;
    container.replaceChildren();
    const questions = Array.isArray(items)
      ? items.map((item) => String(item || '').trim()).filter((item) => item.length > 0 && item.length <= 140).slice(0, 3)
      : [];
    if (!questions.length) {
      container.hidden = true;
      return;
    }
    questions.forEach((question) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'public-ai-suggestion';
      button.textContent = question;
      button.addEventListener('click', () => {
        input.value = question;
        form.requestSubmit();
      });
      container.appendChild(button);
    });
    container.hidden = false;
  }

  document.addEventListener('DOMContentLoaded', function () {
    const assistant = document.getElementById('public-ai-assistant');
    const panel = document.getElementById('public-ai-panel');
    const toggle = document.getElementById('public-ai-toggle');
    const close = document.getElementById('public-ai-close');
    const form = document.getElementById('public-ai-form');
    const input = document.getElementById('public-ai-question');
    const history = document.getElementById('public-ai-history');
    const answer = document.getElementById('public-ai-answer');
    const suggestions = document.getElementById('public-ai-suggestions');
    const status = document.getElementById('public-ai-status');
    const submit = document.getElementById('public-ai-submit');
    const handoff = document.getElementById('public-ai-handoff');
    const handoffOpen = document.getElementById('public-ai-handoff-open');
    const handoffForm = document.getElementById('public-ai-handoff-form');
    const leadName = document.getElementById('public-ai-lead-name');
    const leadPhone = document.getElementById('public-ai-lead-phone');
    const leadEmail = document.getElementById('public-ai-lead-email');
    const leadDetails = document.getElementById('public-ai-lead-details');
    const leadStatus = document.getElementById('public-ai-lead-status');
    const leadSubmit = document.getElementById('public-ai-lead-submit');
    if (!assistant || !panel || !toggle || !form || !input || !answer || !submit || !history) return;

    const features = window.AI_FEATURES && window.AI_FEATURES.publicAssistant;
    if (features && features.enabled === false) {
      assistant.hidden = true;
      return;
    }
    if (handoff) handoff.hidden = Boolean(features && features.handoffEnabled === false);

    const setOpen = (open) => {
      panel.hidden = !open;
      assistant.classList.toggle('is-open', open);
      toggle.hidden = open;
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) input.focus();
    };
    toggle.addEventListener('click', () => setOpen(panel.hidden));
    close?.addEventListener('click', () => setOpen(false));

    let conversation = [];
    try { conversation = JSON.parse(sessionStorage.getItem('kingsway_public_ai_conversation') || '[]'); } catch (_) { conversation = []; }
    if (!Array.isArray(conversation)) conversation = [];
    conversation = conversation.filter((turn) => turn && ['user', 'assistant'].includes(turn.role) && String(turn.content || '').trim()).slice(-40);
    renderHistory(conversation, history);
    const saveConversation = () => {
      // Keep the visible browser session transcript, while the API receives
      // only a smaller recent window below.
      conversation = conversation.slice(-40);
      try { sessionStorage.setItem('kingsway_public_ai_conversation', JSON.stringify(conversation)); } catch (_) {}
    };
    suggestions?.querySelectorAll('.public-ai-suggestion').forEach((button) => {
      button.addEventListener('click', () => { input.value = button.textContent.trim(); form.requestSubmit(); });
    });
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); form.requestSubmit(); }
    });

    handoffOpen?.addEventListener('click', () => {
      if (!handoffForm) return;
      handoffForm.hidden = false;
      handoffOpen.hidden = true;
      leadName?.focus();
    });

    handoffForm?.addEventListener('submit', async function (event) {
      event.preventDefault();
      const name = String(leadName?.value || '').trim();
      const phone = String(leadPhone?.value || '').trim();
      const email = String(leadEmail?.value || '').trim();
      const details = String(leadDetails?.value || '').trim();
      if (!name || !phone || !details) {
        if (leadStatus) leadStatus.textContent = 'Name, phone, and message are required.';
        return;
      }
      if (leadSubmit) leadSubmit.disabled = true;
      if (leadStatus) leadStatus.textContent = 'Sending…';
      try {
        const result = await window.callAPI('/public/leads', 'POST', { name, phone, email, details });
        handoffForm.hidden = true;
        if (leadStatus) leadStatus.textContent = result?.message || 'Thank you. Our admissions team will contact you shortly.';
      } catch (error) {
        if (leadStatus) leadStatus.textContent = error?.message || 'Unable to send the request. Please call the school office.';
      } finally {
        if (leadSubmit) leadSubmit.disabled = false;
      }
    });

    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      const question = input.value.trim();
      if (!question || question.length > 500) {
        status.textContent = 'Enter a question of up to 500 characters.';
        return;
      }
      submit.disabled = true;
      input.value = '';
      renderHistory(conversation, history);
      if (suggestions) suggestions.hidden = true;
      // Keep one loading message only. The answer region is reserved for the
      // returned answer; the status line is the single live progress region.
      status.textContent = 'Preparing an answer…';
      answer.setAttribute('aria-busy', 'true');
      answer.innerHTML = '';
      try {
        const result = await askAssistant(question, conversation.slice(-12));
        renderAnswer(result || {}, answer);
        if (result?.blocked === true) {
          // The server has blocked this session; stop accepting prompts here too.
          input.disabled = true;
          submit.disabled = true;
          input.placeholder = 'This session is blocked.';
          status.textContent = 'Session blocked.';
          return;
        }
        renderFeedbackPrompt(result?.feedback_prompt, answer);
        conversation.push({ role: 'user', content: question });
        conversation.push({ role: 'assistant', content: result?.body || result?.message || '' });
        saveConversation();
        // Keep the latest answer in the rich answer panel and older turns in
        // the transcript, avoiding a duplicate copy of the newest response.
        renderHistory(conversation.slice(0, -2), history);
        renderSuggestions(result?.suggested_questions, suggestions, input, form);
        status.textContent = result?.status === 'escalate' ? 'Human follow-up recommended.' : 'Answer prepared from published sources.';
      } catch (error) {
        input.value = question;
        answer.innerHTML = '<p class="small text-warning mb-0"><i class="bi bi-exclamation-circle me-1"></i>The assistant is temporarily unavailable. Please use Contact Us for help.</p>';
        status.textContent = error?.message || 'Request failed.';
      } finally {
        answer.removeAttribute('aria-busy');
        if (!input.disabled) submit.disabled = false;
      }
    });
  });
})();
