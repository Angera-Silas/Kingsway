/* =============================================================================
   Kingsway Public Website — Interactions & Animations
   ============================================================================= */

/* PublicUI — shared scroll-reveal + count-up observers. Public page controllers
 * render their content asynchronously from the REST API, so after injecting
 * markup they re-call PublicUI.observeReveals(container) and
 * PublicUI.observeCounters(container) to wire up the newly added elements. */
window.PublicUI = (() => {
  'use strict';

  function observeReveals(root) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); io.unobserve(e.target); } });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    (root || document).querySelectorAll('.reveal').forEach(el => io.observe(el));
  }

  function observeCounters(root) {
    const counterIO = new IntersectionObserver((entries) => {
      entries.forEach(e => {
        if (!e.isIntersecting) return;
        const el = e.target;
        const target = +el.dataset.target;
        const suffix = el.dataset.suffix || '';
        const prefix = el.dataset.prefix || '';
        const duration = 2000;
        const start = performance.now();
        counterIO.unobserve(el);
        const tick = (now) => {
          const elapsed = Math.min(1, (now - start) / duration);
          const ease = 1 - Math.pow(1 - elapsed, 4);
          el.textContent = prefix + Math.round(ease * target).toLocaleString() + suffix;
          if (elapsed < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
      });
    }, { threshold: 0.5 });
    (root || document).querySelectorAll('[data-target]').forEach(el => counterIO.observe(el));
  }

  return { observeReveals, observeCounters };
})();

document.addEventListener('DOMContentLoaded', () => {

  // WebAuthn wire-format helpers. The PHP relying-party library returns
  // base64url values; the browser API requires ArrayBuffers.
  window.arrayBufferToBase64 = (buffer) => {
    let binary = ''; const bytes = new Uint8Array(buffer);
    bytes.forEach((b) => { binary += String.fromCharCode(b); });
    return btoa(binary);
  };
  window.recursiveBase64StrToArrayBuffer = (value, key = '') => {
    if (Array.isArray(value)) return value.map((v) => window.recursiveBase64StrToArrayBuffer(v, key));
    if (value && typeof value === 'object') { Object.keys(value).forEach((k) => { value[k] = window.recursiveBase64StrToArrayBuffer(value[k], k); }); return value; }
    if (typeof value === 'string' && ['challenge', 'id'].includes(key)) {
      const normalized = value.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - value.length % 4) % 4);
      const binary = atob(normalized); const bytes = new Uint8Array(binary.length);
      for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
      return bytes;
    }
    return value;
  };

  window.PublicUI.observeReveals(document);
  window.PublicUI.observeCounters(document);

  /* ── Navbar scroll behaviour ──────────────────────────────────────────────── */
  const nav = document.querySelector('.site-nav');
  if (nav) {
    const onScroll = () => nav.classList.toggle('scrolled', window.scrollY > 40);
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ── Announcement ticker pause on hover ──────────────────────────────────── */
  const ticker = document.querySelector('.ticker-track');
  if (ticker) {
    ticker.addEventListener('mouseenter', () => ticker.style.animationPlayState = 'paused');
    ticker.addEventListener('mouseleave', () => ticker.style.animationPlayState = 'running');
  }

  /* ── Active nav link ─────────────────────────────────────────────────────── */
  const currentPage = location.pathname.split('/').pop() || 'index.php';
  document.querySelectorAll('.site-nav .nav-link').forEach(link => {
    const href = link.getAttribute('href') || '';
    if (href && (href === currentPage || href.endsWith(currentPage))) {
      link.classList.add('active');
    }
  });

  /* ── Login modal show/hide ─────────────────────────────────────────────────── */
  const togglePwd = document.getElementById('togglePassword');
  const pwdInput  = document.getElementById('loginPassword');
  const pwdIcon   = document.getElementById('togglePasswordIcon');
  if (togglePwd && pwdInput && pwdIcon) {
    togglePwd.addEventListener('click', () => {
      const isText = pwdInput.type === 'text';
      pwdInput.type = isText ? 'password' : 'text';
      pwdIcon.classList.toggle('bi-eye', isText);
      pwdIcon.classList.toggle('bi-eye-slash', !isText);
    });
  }

  /* ── Login form submission ─────────────────────────────────────────────────── */
  const loginForm   = document.getElementById('loginForm');
  const loginError  = document.getElementById('loginError');
  const loginErrTxt = document.getElementById('loginErrorText');
  const loginBtnTxt = document.getElementById('loginBtnText');
  const loginSpinner= document.getElementById('loginSpinner');
  const loginBtn    = document.getElementById('loginSubmitBtn');

  function resetLoginBtn() {
    if (loginBtnTxt)  loginBtnTxt.classList.remove('d-none');
    if (loginSpinner) loginSpinner.classList.add('d-none');
    if (loginBtn)     loginBtn.disabled = false;
  }
  // Updates the spinner's text WITHOUT removing the spinner-border element.
  // Keeps the user informed across the multi-second login → dashboard window.
  function setSpinnerLabel(label) {
    if (!loginSpinner) return;
    const spinnerEl = loginSpinner.querySelector('.spinner-border');
    loginSpinner.textContent = '';
    if (spinnerEl) loginSpinner.appendChild(spinnerEl);
    loginSpinner.appendChild(document.createTextNode(' ' + label));
  }
  function showLoginErr(msg) {
    if (loginErrTxt) loginErrTxt.textContent = msg;
    if (loginError)  loginError.classList.remove('d-none');
    resetLoginBtn();
  }

  if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const username = loginForm.querySelector('[name="username"]')?.value;
      const password = loginForm.querySelector('[name="password"]')?.value;
      const rememberMe = Boolean(document.getElementById('rememberMe')?.checked);
      if (loginError)  loginError.classList.add('d-none');
      if (loginBtnTxt) loginBtnTxt.classList.add('d-none');
      if (loginSpinner)loginSpinner.classList.remove('d-none');
      if (loginBtn)    loginBtn.disabled = true;
      setSpinnerLabel('Verifying credentials…');
      try {
        const res = await API.auth.login(username, password, rememberMe);

        // ── 2FA challenge ─────────────────────────────────────────────
        if (res && res.requires_2fa) {
          // Hide login modal, show 2FA modal
          const loginModal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
          loginModal?.hide();
          await showTFAVerification(res, username, password, rememberMe);
          return;
        }
        // ── end 2FA challenge ──────────────────────────────────────────

        if (!res?.token) throw new Error(res?.message || 'Login failed. Check your credentials.');
        setSpinnerLabel('Preparing your dashboard…');
      } catch (err) {
        showLoginErr(err.message || 'Login failed. Please try again.');
      }
    });
  }

  /* ── 2FA Verification ───────────────────────────────────────────────────── */
  let tfaState = null; // { userId, method, challengeToken, rememberMe }

  window.showTFAVerification = async function (res, username, password, rememberMe) {
    tfaState = { userId: res.user_id, method: res.method, challengeToken: res.challenge_token, username, password, rememberMe, onComplete: res.onComplete };

    const modalEl = document.getElementById('tfaModal');
    const methodDesc = document.getElementById('tfaMethodDesc');
    const codeLabel  = document.getElementById('tfaCodeLabel');
    const resendRow  = document.getElementById('tfaResend');
    const resendBtn  = document.getElementById('tfaResendBtn');
    const tfaRecoveryBtn = document.getElementById('tfaRecoveryBtn');
    const tfaCode = document.getElementById('tfaCode');
    const tfaError = document.getElementById('tfaError');
    const tfaErrorText = document.getElementById('tfaErrorText');
    const passkeyBtn = document.getElementById('tfaPasskeyBtn');
    const picker = document.getElementById('tfaMethodPicker');
    const select = document.getElementById('tfaMethodSelect');
    const methods = Array.isArray(res.available_methods) ? res.available_methods : [res.method];
    if (methods.length > 1 && picker && select) {
      const labels = {totp:'Authenticator app',email:'Email',sms:'SMS',whatsapp:'WhatsApp'};
      select.innerHTML = methods.map(m => `<option value="${m}" ${m === res.method ? 'selected' : ''}>${labels[m] || m}</option>`).join('');
      picker.classList.remove('d-none');
      select.onchange = async () => {
        tfaState.method = select.value;
        passkeyBtn?.classList.toggle('d-none', tfaState.method !== 'passkey');
        tfaCode?.classList.toggle('d-none', tfaState.method === 'passkey');
        try { await window.callAPI?.('/twofactor/challenge', 'POST', { challenge_token: tfaState.challengeToken, method: tfaState.method }); showTFASuccess('Verification method selected.'); } catch (e) { showTFAError(e.message || 'Unable to select method'); }
      };
    } else if (picker) picker.classList.add('d-none');
    passkeyBtn?.classList.toggle('d-none', res.method !== 'passkey');
    tfaCode?.classList.toggle('d-none', res.method === 'passkey');

    // Reset
    tfaCode.value = '';
    tfaError.classList.add('d-none');
    hideTFASpinner();

    if (res.method === 'totp') {
      methodDesc.textContent = 'Enter the 6-digit verification code from your authenticator app.';
      codeLabel.textContent = 'Authentication Code (from app)';
      resendRow.classList.add('d-none');
      tfaRecoveryBtn.classList.remove('d-none');
    } else if (res.method === 'email') {
      methodDesc.textContent = 'A verification code has been sent to your email address.';
      codeLabel.textContent = 'Email Verification Code';
      resendRow.classList.remove('d-none');
      tfaRecoveryBtn.classList.add('d-none');
      startTFACountdown();
      // Auto-send OTP
      try {
        await window.callAPI?.('/twofactor/challenge', 'POST', { challenge_token: res.challenge_token });
      } catch (_) { /* ignore — code may already be sent */ }
    } else if (res.method === 'sms' || res.method === 'whatsapp') {
      methodDesc.textContent = 'A verification code has been sent to your phone.';
      codeLabel.textContent = res.method === 'whatsapp' ? 'WhatsApp Verification Code' : 'SMS Verification Code';
      resendRow.classList.remove('d-none');
      tfaRecoveryBtn.classList.add('d-none');
      startTFACountdown();
      try {
        await window.callAPI?.('/twofactor/challenge', 'POST', { challenge_token: res.challenge_token });
      } catch (_) { /* ignore */ }
    }

    const tfaModal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false });
    tfaModal.show();

    // Focus the input after modal opens
    modalEl.addEventListener('shown.bs.modal', () => tfaCode?.focus(), { once: true });
  };

  // Submit 2FA code
  document.getElementById('tfaPasskeyBtn')?.addEventListener('click', async () => {
    if (!tfaState) return;
    try {
      const start = await window.callAPI?.('/twofactor/challenge', 'POST', { challenge_token: tfaState.challengeToken, method: 'passkey' });
      const publicKey = start?.public_key || start?.data?.public_key;
      if (!publicKey || !navigator.credentials?.get) throw new Error('Passkeys are not supported by this browser.');
      const credential = await navigator.credentials.get({ publicKey: recursiveBase64StrToArrayBuffer(publicKey) });
      const encoded = {
        id: credential.id,
        clientDataJSON: arrayBufferToBase64(credential.response.clientDataJSON),
        authenticatorData: arrayBufferToBase64(credential.response.authenticatorData),
        signature: arrayBufferToBase64(credential.response.signature),
        userHandle: credential.response.userHandle ? arrayBufferToBase64(credential.response.userHandle) : null,
      };
      const verified = await window.callAPI?.('/twofactor/verify', 'POST', { challenge_token: tfaState.challengeToken, method: 'passkey', credential: encoded });
      if (!verified?.verified) throw new Error('Passkey verification failed.');
      const loginRes = await window.API.auth.complete2FALogin(tfaState.userId, tfaState.rememberMe, tfaState.challengeToken);
      if (!loginRes?.token) throw new Error(loginRes?.message || 'Login failed');
      if (typeof tfaState.onComplete === 'function') { const done = tfaState.onComplete; tfaState = null; bootstrap.Modal.getInstance(document.getElementById('tfaModal'))?.hide(); done(loginRes); return; }
      window.location.href = (window.APP_BASE || '') + '/home.php';
    } catch (e) { showTFAError(e.message || 'Passkey verification failed'); }
  });

  document.getElementById('tfaSubmitBtn')?.addEventListener('click', async () => {
    if (!tfaState) return;
    const code = document.getElementById('tfaCode')?.value?.trim();
    if (!code || code.length < 4) {
      showTFAError('Please enter a valid verification code.');
      return;
    }

    showTFASpinner();
    try {
      let currentMethod = document.getElementById('tfaRecoveryBtn')?.classList.contains('d-none')
        ? tfaState.method : tfaState.method;

      // If the input looks like a backup code (9 chars with dash), use backup method
      const isBackup = /^[A-Z0-9]{4}-[A-Z0-9]{4}$/i.test(code);
      const method = isBackup ? 'backup' : currentMethod;

      // Verify the code
      const verifyRes = await window.callAPI?.('/twofactor/verify', 'POST', {
        code,
        challenge_token: tfaState.challengeToken,
        method,
      });

      if (!verifyRes?.verified) {
        showTFAError('Invalid verification code. Please try again.');
        hideTFASpinner();
        return;
      }

      // 2FA passed — complete the login
      document.getElementById('tfaBtnText').textContent = 'Completing login…';
      const loginRes = await window.API.auth.complete2FALogin(tfaState.userId, tfaState.rememberMe, tfaState.challengeToken);
      if (!loginRes?.token) throw new Error(loginRes?.message || 'Login failed');

      if (typeof tfaState.onComplete === 'function') {
        const complete = tfaState.onComplete;
        tfaState = null;
        bootstrap.Modal.getInstance(document.getElementById('tfaModal'))?.hide();
        complete(loginRes);
        return;
      }

      // Close 2FA modal
      bootstrap.Modal.getInstance(document.getElementById('tfaModal'))?.hide();

      // Redirect to dashboard
      const dashboardInfo = window.AuthContext?.getDashboardInfo?.();
      const redirect = dashboardInfo?.key
        ? (window.APP_BASE || '') + '/home.php?route=' + dashboardInfo.key
        : (window.APP_BASE || '') + '/home.php';
      window.location.href = redirect;
    } catch (err) {
      showTFAError(err.message || 'Verification failed. Please try again.');
      hideTFASpinner();
    }
  });

  window.requestTFAForSession = function (res) {
    return new Promise((resolve, reject) => {
      window.showTFAVerification({ ...res, onComplete: resolve }, '', '', true).catch(reject);
    });
  };

  // Back to login
  document.getElementById('tfaBackBtn')?.addEventListener('click', () => {
    bootstrap.Modal.getInstance(document.getElementById('tfaModal'))?.hide();
    tfaState = null;
    const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
    loginModal.show();
    resetLoginBtn();
  });

  // Recovery code toggle
  document.getElementById('tfaRecoveryBtn')?.addEventListener('click', () => {
    const codeInput = document.getElementById('tfaCode');
    const label  = document.getElementById('tfaCodeLabel');
    const desc   = document.getElementById('tfaMethodDesc');
    const btn    = document.getElementById('tfaRecoveryBtn');
    codeInput.placeholder = 'XXXX-XXXX';
    codeInput.maxLength = 9;
    codeInput.inputMode = 'text';
    label.textContent = 'Recovery Code';
    desc.textContent = 'Enter one of your backup recovery codes.';
    btn.classList.add('d-none');
    document.getElementById('tfaResend')?.classList.add('d-none');
    codeInput.focus();
  });

  // Resend code
  document.getElementById('tfaResendBtn')?.addEventListener('click', async () => {
    if (!tfaState) return;
    try {
      await window.callAPI?.('/twofactor/challenge', 'POST', {
        challenge_token: tfaState.challengeToken,
      });
      startTFACountdown();
      showTFASuccess('A new code has been sent.');
    } catch (_) {
      showTFAError('Failed to resend. Please try again.');
    }
  });

  // Enter key submits in the 2FA code field
  document.getElementById('tfaCode')?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') document.getElementById('tfaSubmitBtn')?.click();
  });

  function showTFAError(msg) {
    const el = document.getElementById('tfaError');
    const txt = document.getElementById('tfaErrorText');
    if (el && txt) { el.classList.remove('d-none'); txt.textContent = msg; }
  }

  function showTFASuccess(msg) {
    const el = document.getElementById('tfaError');
    const txt = document.getElementById('tfaErrorText');
    if (el && txt) { el.classList.remove('d-none'); el.classList.remove('alert-danger'); el.classList.add('alert-success'); txt.textContent = msg; }
  }

  function showTFASpinner() {
    document.getElementById('tfaBtnText')?.classList.add('d-none');
    document.getElementById('tfaSpinner')?.classList.remove('d-none');
    document.getElementById('tfaSubmitBtn')?.setAttribute('disabled', '');
  }

  function hideTFASpinner() {
    document.getElementById('tfaBtnText')?.classList.remove('d-none');
    document.getElementById('tfaSpinner')?.classList.add('d-none');
    document.getElementById('tfaSubmitBtn')?.removeAttribute('disabled');
  }

  function startTFACountdown() {
    const resendBtn = document.getElementById('tfaResendBtn');
    const resendTimer = document.getElementById('tfaResendTimer');
    const countdownEl = document.getElementById('tfaCountdown');
    if (!resendBtn || !resendTimer || !countdownEl) return;

    resendBtn.classList.add('d-none');
    resendTimer.classList.remove('d-none');
    let seconds = 60;
    countdownEl.textContent = seconds;
    const interval = setInterval(() => {
      seconds--;
      countdownEl.textContent = seconds;
      if (seconds <= 0) {
        clearInterval(interval);
        resendBtn.classList.remove('d-none');
        resendTimer.classList.add('d-none');
      }
    }, 1000);
  }

  /* ── Smooth scroll for anchor links ─────────────────────────────────────────── */
  document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
      const href = a.getAttribute('href');
      // Skip if href is just "#" (common for buttons styled as links)
      if (href === '#') return;
      const target = document.querySelector(href);
      if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });
  });

  /* ── Contact form ──────────────────────────────────────────────────────────── */
  const contactForm = document.getElementById('contactForm');
  if (contactForm) {
    contactForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = contactForm.querySelector('[type="submit"]');
      const orig = btn.innerHTML;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending…';
      btn.disabled = true;
      await new Promise(r => setTimeout(r, 1200));
      contactForm.reset();
      btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Message Sent!';
      btn.classList.add('btn-success');
      setTimeout(() => { btn.innerHTML = orig; btn.disabled = false; btn.classList.remove('btn-success'); }, 3000);
    });
  }

});
