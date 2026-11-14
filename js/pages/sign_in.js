(function () {
  'use strict';
  const state = { userId: null, method: null, methods: [], challenge: null, remember: false, identifier: '', password: '', verifying: false, redirecting: false, recovery: false, hints: {}, sent: {} };
  const byId = (id) => document.getElementById(id);
  const showError = (id, message) => { const el=byId(id); if(!el)return; el.textContent=message; el.classList.remove('d-none'); };
  const clearMessages = () => ['authError','verifyError','verifySuccess'].forEach((id)=>byId(id)?.classList.add('d-none'));
  // window.API.apiCall RETURNS an error body instead of throwing, so a failed
  // challenge would otherwise flow on as if it succeeded (which is how a
  // passkey attempt reached navigator.credentials.get() with undefined
  // options). Normalise failures into a throw so every caller's catch fires.
  const api = async (path, method='POST', body={}) => {
    const result = await window.API.apiCall(path, method, body, {}, { checkPermission:false });
    if (result && typeof result === 'object' && (result.success === false || result.status === 'error')) {
      throw new Error(result.message || 'The request failed.');
    }
    return result;
  };
  const toB64 = (buffer) => { let value=''; new Uint8Array(buffer).forEach((byte)=>{value+=String.fromCharCode(byte);}); return btoa(value); };
  const decodeOptions = (value, key='') => { if(Array.isArray(value))return value.map((item)=>decodeOptions(item,key)); if(value&&typeof value==='object'){Object.keys(value).forEach((child)=>{value[child]=decodeOptions(value[child],child);});return value;} if(typeof value==='string'&&['challenge','id'].includes(key)){const normalized=value.replace(/-/g,'+').replace(/_/g,'/')+'='.repeat((4-value.length%4)%4);return Uint8Array.from(atob(normalized),(character)=>character.charCodeAt(0));} return value; };
  const otpCells = () => Array.from(document.querySelectorAll('.auth-otp-cell'));
  const otpValue = () => otpCells().map((cell)=>cell.value).join('');
  const clearOtp = () => otpCells().forEach((cell)=>{cell.value='';cell.classList.remove('is-complete');});
  const methodLabels = { email:'Email code', sms:'Text message code', whatsapp:'WhatsApp code', totp:'Authenticator app', passkey:'Passkey (fingerprint, face, device or security key)', backup:'Recovery code (one-time)' };
  const methodIcons = { email:'bi-envelope', sms:'bi-phone', whatsapp:'bi-whatsapp', totp:'bi-shield-lock', passkey:'bi-fingerprint', backup:'bi-key' };
  // GitHub-style headlines: name the place the user has to look, instead of
  // describing a factor in technical words.
  const methodHeadlines = { email:'Check your email', sms:'Check your phone', whatsapp:'Check WhatsApp', totp:'Open your authenticator app', passkey:'Use your passkey', backup:'Use a recovery code' };
  const methodDetails = {
    email:'Enter the six-digit code we sent to your email address.',
    sms:'Enter the six-digit code we sent to your phone.',
    whatsapp:'Enter the six-digit code we sent to your WhatsApp number.',
    totp:'Enter the current six-digit code from your authenticator app.',
    passkey:'Use your fingerprint, face unlock, device PIN or security key.',
    backup:'Enter one of the recovery codes you saved. Each code works once.'
  };
  function methodDescription(method) {
    return methodDetails[method] || 'Choose an enrolled method to verify your identity.';
  }
  /**
   * The secondary line of a method row, and the line under the active
   * method. For the channels that deliver a code it is the masked
   * destination, so the user can see the code went where they expect.
   */
  function methodDetail(method) {
    const hint=state.hints[method];
    if(method==='email'||method==='sms'||method==='whatsapp') return hint||'Not set up on this account';
    if(method==='totp') return 'Google or Microsoft Authenticator';
    if(method==='passkey') return 'Fingerprint, face, device PIN or security key';
    if(method==='backup') return 'One of the codes you saved';
    return '';
  }
  /** Update the line that tells the user where the code went. */
  function renderDestination(method) {
    const el=byId('authMethodDestination'); if(!el) return;
    const hint=state.hints[method];
    if(method==='email'||method==='sms'||method==='whatsapp') {
      el.textContent=hint ? (state.sent[method] ? `Sent to ${hint}` : `Code goes to ${hint}`) : 'Your code is on its way';
      return;
    }
    el.textContent=hint||methodDetail(method);
  }
  function updateMethodView(method) {
    state.method=method;
    // `ready` used to gate the whole layout: it was false during any
    // method switch and after any failed send, which replaced the code form
    // with a lone error message and a retry button. The form is now always
    // shown for a code method, so the panel looks identical in every state.

    const passkey=method==='passkey';
    const recovery=method==='backup';
    state.recovery=recovery;
    const code=method==='email'||method==='sms'||method==='whatsapp'||method==='totp';
    byId('verificationTitle').textContent=methodHeadlines[method]||'Verify your identity';
    byId('verificationDescription').textContent=methodDescription(method);
    // The method card names the channel in use and where the code went.
    const icon=byId('authMethodIcon');
    if(icon) icon.innerHTML=`<i class="bi ${methodIcons[method]||'bi-shield-check'}"></i>`;
    byId('authMethodName').textContent=methodLabels[method]||method;
    renderDestination(method);
    // The code entry stays visible before the send resolves: hiding it on
    // failure replaced the whole form with a single button and made the
    // verification step look like two different screens.
    byId('verificationForm').classList.toggle('d-none',!code&&!recovery);
    byId('authVerifyPasskey').classList.toggle('d-none',!passkey);
    byId('authResend').classList.toggle('d-none',!['email','sms','whatsapp'].includes(method));
    byId('authUseRecovery').classList.toggle('d-none',passkey);
    byId('authAutoStatus').classList.toggle('d-none',method==='totp'||passkey||recovery);
    // A recovery code is typed as one word, so it uses the plain text field
    // instead of the six single-digit boxes.
    byId('authOtpGrid').classList.toggle('d-none',recovery);
    byId('verificationCode').classList.toggle('d-none',!recovery);
    byId('verificationCodeLabel').classList.toggle('d-none',!recovery);
    byId('verifySubmit').classList.toggle('d-none',!recovery);
    if(recovery) {
      byId('verificationCodeLabel').textContent='Enter one of your recovery codes';
      byId('verificationCode').value='';
      byId('verificationCode').focus();
    }
  }

  function dashboard(response) {
    if (response?.password_setup_required && response?.password_setup_url) return response.password_setup_url;
    if (response?.profile_completion_required || response?.dashboard?.key === 'complete_staff_profile') {
      return `${window.APP_BASE || ''}/home.php?route=complete_staff_profile`;
    }
    const next = new URLSearchParams(window.location.search).get('next') || undefined;
    return window.AuthContext?.getAfterLoginUrl?.(next) || `${window.APP_BASE||''}/home.php`;
  }
  async function finish(userId, challenge) {
    const result=await window.API.auth.complete2FALogin(userId,state.remember,challenge);
    if(!result?.token) throw new Error(result?.message||'The authenticated session could not be created.');
    window.location.replace(dashboard(result));
  }
  async function beginVerification(result) {
    state.userId=Number(result.user_id); state.challenge=result.challenge_token;
    state.methods=Array.isArray(result.available_methods)&&result.available_methods.length?result.available_methods:[result.method||'sms'];
    // Recovery codes are offered alongside the enrolled factors, but only
    // when the account still holds unused ones.
    if(Number(result.backup_codes_remaining)>0 && !state.methods.includes('backup')) state.methods.push('backup');
    // The user's configured primary factor is the default. The server already
    // resolves it (users.two_factor_method, honouring two_factor_verified_at),
    // so the UI never asks the user to choose up front.
    state.method=state.methods.includes(result.method)?result.method:state.methods[0];

    // Where each code would be delivered, masked by the server. The screen
    // shows it so the user can see the code went to the address they expect.
    state.hints=(result.method_hints&&typeof result.method_hints==='object')?result.method_hints:{};
    state.sent={};
    // The fallback is an INLINE list of every method on the account. The
    // button is always visible: a user who cannot receive the default code
    // needs a way out even when only one method is enrolled.
    buildMethodList();

    // Send the challenge BEFORE revealing the step, so the panel is only ever
    // seen in its final state. Revealing first and then activating made the
    // "Continue with ..." button and the code box flash in sequence for a
    // second while the request was in flight.
    await activateMethod(state.method);
    byId('signInStep').classList.add('d-none');
    byId('verificationStep').classList.remove('d-none');
  }

  /**
   * Build the inline list of verification methods.
   *
   * It sits in the page, under the active method, instead of behind a
   * dialog: switching factor keeps the sign-in session in view, which is how
   * GitHub, Figma and the rest of the good examples handle it. The active
   * method is marked with a gold ring and a check.
   */
  function buildMethodList() {
    const list=byId('authMethodList'); if(!list) return;
    list.replaceChildren();
    if(state.methods.length===0) {
      const empty=document.createElement('p');
      empty.className='auth-method-empty mb-0';
      empty.textContent='No verification method is available on this account. Contact the school office to update your sign-in settings.';
      list.appendChild(empty);
      return;
    }
    state.methods.forEach((method)=>{
      const item=document.createElement('button');
      item.type='button';
      item.className='auth-method-option';
      item.setAttribute('role','radio');
      item.setAttribute('aria-checked',method===state.method?'true':'false');

      const icon=document.createElement('span');
      icon.className='auth-method-option-icon';
      const glyph=document.createElement('i');
      glyph.className='bi '+(methodIcons[method]||'bi-shield-check');
      icon.appendChild(glyph);

      const label=document.createElement('span');
      label.className='auth-method-option-label';
      const title=document.createElement('strong');
      title.textContent=methodLabels[method]||method;
      const detail=document.createElement('small');
      detail.textContent=methodDetail(method);
      label.append(title,detail);

      const check=document.createElement('span');
      check.className='auth-method-option-check';
      const tick=document.createElement('i');
      tick.className='bi bi-check-lg';
      check.appendChild(tick);

      item.append(icon,label,check);
      item.addEventListener('click',()=>{
        toggleMethodList(false);
        // Any enrolled factor may complete THIS sign-in: the session stays
        // open while the user switches channel, exactly like the standard
        // "use another method" step.
        if(method!==state.method) void activateMethod(method);
      });
      list.appendChild(item);
    });
  }

  /** Open or close the inline method list. */
  function toggleMethodList(expand) {
    const list=byId('authMethodList'); if(!list) return;
    const open=expand===undefined ? list.classList.contains('d-none') : expand;
    list.classList.toggle('d-none',!open);
    byId('authTryAnother')?.setAttribute('aria-expanded',open?'true':'false');
    if(open) {
      buildMethodList();
      list.querySelector('.auth-method-option')?.focus();
    }
  }

  async function activateMethod(method) {
    if(!state.methods.includes(method)) throw new Error('That verification method is not available for this account.');
    clearMessages(); clearOtp(); updateMethodView(method); buildMethodList();
    if(method==='passkey') { await verifyPasskey(); return; }
    if(method==='backup') {
      // A recovery code is entered by the user; nothing is sent, so the
      // challenge is not touched.
      byId('verificationCode')?.focus();
      return;
    }
    try {
      // The challenge belongs to this sign-in session, so switching channel
      // never invalidates the codes already sent on the other channels and
      // never asks for the password again.
      const sent=await api('/twofactor/challenge','POST',{challenge_token:state.challenge,method});
      state.method = method;  // Update method after challenge creation
      if(sent?.destination) state.hints[method]=sent.destination;
      state.sent[method]=true;
      renderDestination(method);
      const el=byId('verifySuccess');
      if(['email','sms','whatsapp'].includes(method)) {
        const where=state.hints[method] ? ` to ${state.hints[method]}` : '';
        el.textContent=`A verification code was sent${where}. It can take a moment to arrive; only the newest code works.`;
        el.classList.remove('d-none');
        startResendCooldown(60);
      } else {
        el.textContent='Enter the current six-digit code from your authenticator app.';
        el.classList.remove('d-none');
      }
      otpCells()[0]?.focus();
    } catch(error) { showError('verifyError',error.message||'This verification method could not be started.'); }
  }
  async function login(event) {
    event.preventDefault(); clearMessages();
    state.identifier=byId('authIdentifier').value.trim(); state.password=byId('authPassword').value; state.remember=byId('authRemember').checked;
    const button=byId('authSubmit'); button.disabled=true; button.querySelector('.spinner-border').classList.remove('d-none');
    try { const result=await window.API.auth.login(state.identifier,state.password,state.remember); if(result?.parent_portal_only){state.redirecting=true;showError('authError',(result.message||'This sign-in is for school staff only. Parents use the Parent Portal.')+' Redirecting to the Parent Portal…');setTimeout(()=>{window.location.replace(result.parent_portal_url||`${window.APP_BASE||''}/parent_portal.php`);},1800);return;} if(result?.requires_2fa){await beginVerification(result);return;} if(result?.token){window.location.replace(dashboard(result));return;} throw new Error(result?.message||'Sign in failed.'); }
    catch(error){if(!state.redirecting)showError('authError',error.message||'Sign in failed.');}
    finally{if(!state.redirecting){button.disabled=false;button.querySelector('.spinner-border').classList.add('d-none');}}
  }
  async function verify(event) {
    event?.preventDefault?.();
    if(state.verifying)return;
    clearMessages();
    const recovery=state.recovery;
    // A recovery code is one word; the normal factors use the six boxes.
    const code=recovery
      ? (byId('verificationCode')?.value||'').trim().replace(/\s+/g,'').toUpperCase()
      : otpValue();
    if(recovery) { if(!code) return; }
    else if(!code||code.length!==6) return;
    const method=recovery?'backup':state.method;
    const button=byId('verifySubmit'); button.disabled=true;
    const status=byId('authAutoStatus'); state.verifying=true; otpCells().forEach((cell)=>cell.disabled=true); if(status)status.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>Verifying securely…';
    try {
      const result=await api('/twofactor/verify','POST',{challenge_token:state.challenge,method,code});
      if(!result?.verified)throw new Error('Verification failed.');
    } catch(error) {
      const message=String(error?.message||'');
      const text = /invalid or expired 2fa challenge|challenge.*expired/i.test(message)
        ? 'Your verification session expired. Go back and sign in again to receive a fresh code.'
        : /too many 2fa attempts/i.test(message)
          ? 'Too many incorrect codes. Go back and sign in again to start a fresh verification session.'
          : recovery
            ? (message || 'That recovery code is not valid. Check the codes you saved, or ask the school office for help.')
            : /expired|invalid|verification/i.test(message)
              ? 'That code is no longer valid. Each new code replaces the one before it, so use the digits from the most recent message, or ask for a new code.'
              : (message || 'The code is invalid or expired.');
      showError('verifyError', text);
      if(recovery) { byId('verificationCode').value=''; byId('verificationCode').focus(); }
      else { clearOtp(); otpCells()[0]?.focus(); }
      return;
    } finally {
      state.verifying=false;button.disabled=false;otpCells().forEach((cell)=>cell.disabled=false);
      if(status)status.innerHTML='<i class="bi bi-lightning-charge-fill"></i> Verification starts automatically when all six digits are entered.';
    }
    // The code was accepted. A failure from this point is a session-creation
    // problem, not a bad code; do not tell the user their code is invalid.
    try {
      await finish(state.userId,state.challenge);
    } catch(error) {
      showError('verifyError', error.message || 'Your code was accepted, but the session could not be started. Please sign in again.');
    }
  }
  let resendTimer = null;

  function startResendCooldown(seconds) {
    const button = byId('authResend');
    clearInterval(resendTimer);
    if (!button) return;
    let remaining = seconds;
    button.disabled = true;
    button.textContent = `Send a new code (${remaining}s)`;
    resendTimer = setInterval(() => {
      remaining -= 1;
      if (remaining <= 0) {
        clearInterval(resendTimer); resendTimer = null;
        button.disabled = false;
        button.textContent = 'Send a new code';
        return;
      }
      button.textContent = `Send a new code (${remaining}s)`;
    }, 1000);
  }

  async function resend() {
    clearMessages();
    // A code was already issued automatically, so block the click inside
    // the 60-second server cooldown rather than letting the user discover
    // it through a bare 429 after another paid SMS was spent.
    if (resendTimer !== null) {
      showError('verifyError', 'A code was just sent. Please wait for the countdown before requesting another one.');
      return;
    }
    try {
      const sent=await api('/twofactor/challenge','POST',{challenge_token:state.challenge,method:state.method});
      if(sent?.destination) state.hints[state.method]=sent.destination;
      state.sent[state.method]=true;
      renderDestination(state.method);
      const el=byId('verifySuccess');
      const where=state.hints[state.method] ? ` to ${state.hints[state.method]}` : '';
      el.textContent=`A new code was sent${where}. Only the newest code works.`;
      el.classList.remove('d-none');
      startResendCooldown(60);
    } catch(error){
      if (/60 seconds/i.test(error.message || '')) startResendCooldown(60);
      showError('verifyError',error.message||'A new code could not be sent.');
    }
  }
  async function passwordless() {
    clearMessages(); if(!window.PublicKeyCredential||!navigator.credentials?.get){showError('authError','Passkeys are not supported on this browser or device.');return;}
    try { const raw=await api('/twofactor/passwordless-options'); if(!raw||!raw.challenge)throw new Error('Passkey sign-in is not available right now.'); const options=decodeOptions(raw); if(!options||!options.challenge)throw new Error('Passkey sign-in could not be started.'); const credential=await navigator.credentials.get({publicKey:options}); const encoded={id:credential.id,clientDataJSON:toB64(credential.response.clientDataJSON),authenticatorData:toB64(credential.response.authenticatorData),signature:toB64(credential.response.signature),userHandle:credential.response.userHandle?toB64(credential.response.userHandle):null}; const verified=await api('/twofactor/passwordless-verify','POST',{credential:encoded}); await finish(verified.user_id,verified.challenge_token); } catch(error){if(error?.name!=='NotAllowedError')showError('authError',error.message||'Passkey sign-in failed.');}
  }
  async function verifyPasskey() {
    clearMessages();
    updateMethodView('passkey');
    try { if(!window.PublicKeyCredential||!navigator.credentials?.get)throw new Error('Passkeys are not supported on this browser or device.'); const start=await api('/twofactor/challenge','POST',{challenge_token:state.challenge,method:'passkey'}); if(!start||!start.public_key||!start.public_key.challenge)throw new Error('Passkey verification is not available for this account.'); const options=decodeOptions(start.public_key); if(!options||!options.challenge)throw new Error('Passkey verification could not be started.'); const credential=await navigator.credentials.get({publicKey:options}); const encoded={id:credential.id,clientDataJSON:toB64(credential.response.clientDataJSON),authenticatorData:toB64(credential.response.authenticatorData),signature:toB64(credential.response.signature),userHandle:credential.response.userHandle?toB64(credential.response.userHandle):null}; const verified=await api('/twofactor/verify','POST',{challenge_token:state.challenge,method:'passkey',credential:encoded,code:'passkey'}); if(!verified?.verified)throw new Error('Passkey verification failed.'); await finish(state.userId,state.challenge); } catch(error){if(error?.name!=='NotAllowedError')showError('verifyError',error.message||'Passkey verification failed.');}
  }
  function setupOtpInputs(){
    const cells=otpCells();
    cells.forEach((cell,index)=>{
      cell.addEventListener('input',()=>{cell.value=cell.value.replace(/\D/g,'').slice(-1);cell.classList.toggle('is-complete',cell.value!=='');if(cell.value&&index<cells.length-1)cells[index+1].focus();if(otpValue().length===6)void verify();});
      cell.addEventListener('keydown',(event)=>{if(event.key==='Backspace'&&!cell.value&&index>0){cells[index-1].value='';cells[index-1].classList.remove('is-complete');cells[index-1].focus();}if(event.key==='ArrowLeft'&&index>0)cells[index-1].focus();if(event.key==='ArrowRight'&&index<cells.length-1)cells[index+1].focus();});
      cell.addEventListener('paste',(event)=>{const digits=(event.clipboardData?.getData('text')||'').replace(/\D/g,'').slice(0,6);if(!digits)return;event.preventDefault();clearOtp();digits.split('').forEach((digit,i)=>{if(cells[i]){cells[i].value=digit;cells[i].classList.add('is-complete');}});cells[Math.min(digits.length,6)-1]?.focus();if(digits.length===6)void verify();});
    });
  }
  /**
   * Recovery codes are typed from a printout or a photo, so the entry is
   * upper-cased and the dash is inserted for the user.
   */
  function setupRecoveryInput(){
    const input=byId('verificationCode'); if(!input) return;
    input.addEventListener('input',()=>{
      const raw=input.value.toUpperCase().replace(/[^A-Z0-9]/g,'').slice(0,8);
      input.value=raw.length>4 ? raw.slice(0,4)+'-'+raw.slice(4) : raw;
    });
  }
  function reset() { clearInterval(resendTimer); resendTimer=null; const rb=byId('authResend'); if(rb){rb.disabled=false;rb.textContent='Send a new code';} state.userId=null;state.challenge=null;state.methods=[];state.method=null;state.verifying=false;state.recovery=false;state.hints={};state.sent={};clearMessages();clearOtp();byId('verificationStep').classList.add('d-none');byId('signInStep').classList.remove('d-none');byId('verificationCode').value='';byId('verificationCode').classList.add('d-none');byId('verificationCodeLabel').classList.add('d-none');byId('authOtpGrid').classList.remove('d-none');byId('verifySubmit').classList.add('d-none');byId('authMethodList')?.classList.add('d-none');byId('authTryAnother')?.setAttribute('aria-expanded','false');byId('authMethodName').textContent='Verification code';byId('authMethodDestination').textContent='Your code is on its way';byId('authPassword').focus(); }
  document.addEventListener('DOMContentLoaded',async()=>{ await window.AuthContext?.ready?.(); if(window.AuthContext?.isAuthenticated?.()){const next = new URLSearchParams(window.location.search).get('next')||undefined; window.location.replace(window.AuthContext.getAfterLoginUrl?.(next)||`${window.APP_BASE||''}/home.php`);return;} setupOtpInputs(); setupRecoveryInput();byId('authLoginForm').addEventListener('submit',login);byId('verificationForm').addEventListener('submit',verify);byId('authPasswordless').addEventListener('click',passwordless);byId('authVerifyPasskey').addEventListener('click',verifyPasskey);byId('authTryAnother').addEventListener('click',()=>toggleMethodList());byId('authResend').addEventListener('click',resend);byId('authBack').addEventListener('click',reset);byId('authTogglePassword').addEventListener('click',()=>{const input=byId('authPassword');input.type=input.type==='password'?'text':'password';}); });
})();
