(function () {
  'use strict';
  const state = { userId: null, method: null, methods: [], challenge: null, remember: false, identifier: '', password: '', verifying: false, redirecting: false };
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
  const methodLabels = { email:'Email code', sms:'Text message code', whatsapp:'WhatsApp code', totp:'Authenticator app', passkey:'Passkey (fingerprint, face, device or security key)' };
  function methodDescription(method) {
    return ({email:'Enter the six-digit code sent to your registered email address.',sms:'Enter the six-digit code sent to your registered phone number.',whatsapp:'Enter the six-digit code sent to your registered WhatsApp number.',totp:'Enter the current code from your authenticator app, such as Google Authenticator or Microsoft Authenticator.',passkey:'Use an enrolled passkey with your fingerprint, face, device lock, or security key.'})[method] || 'Choose an enrolled method to verify your identity.';
  }
  function updateMethodView(method) {
    state.method=method;
    // `ready` used to gate the whole layout: it was false during any
    // method switch and after any failed send, which replaced the code form
    // with a lone error message and a retry button. The form is now always
    // shown for a code method, so the panel looks identical in every state.

    const passkey=method==='passkey';
    const code=method==='email'||method==='sms'||method==='whatsapp'||method==='totp';
    byId('verificationTitle').textContent=passkey?'Passkey verification':method==='totp'?'Authenticator verification':'Verify your identity';
    byId('verificationDescription').textContent=methodDescription(method);
    // The code entry stays visible before the send resolves: hiding it on
    // failure replaced the whole form with a single button and made the
    // verification step look like two different screens.
    byId('verificationForm').classList.toggle('d-none',!code);
    byId('authVerifyPasskey').classList.toggle('d-none',!passkey);
    byId('authResend').classList.toggle('d-none',!['email','sms','whatsapp'].includes(method));
    byId('authUseRecovery').classList.toggle('d-none',passkey);
    byId('authAutoStatus').classList.toggle('d-none',method==='totp'||passkey);
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
    // The user's configured primary factor is the default. The server already
    // resolves it (users.two_factor_method, honouring two_factor_verified_at),
    // so the UI never asks the user to choose up front.
    state.method=state.methods.includes(result.method)?result.method:state.methods[0];

    // The fallback is a MODAL listing every method on the account. The button
    // is always visible: a user who cannot receive the default code needs a way
    // out even when only one method is enrolled.
    buildTryAnotherList();

    // Send the challenge BEFORE revealing the step, so the panel is only ever
    // seen in its final state. Revealing first and then activating made the
    // "Continue with ..." button and the code box flash in sequence for a
    // second while the request was in flight.
    await activateMethod(state.method);
    byId('signInStep').classList.add('d-none');
    byId('verificationStep').classList.remove('d-none');
  }

  function buildTryAnotherList() {
    const list=byId('tryAnotherList'); if(!list) return;
    list.replaceChildren();
    if(state.methods.length===0) {
      const empty=document.createElement('div');
      empty.className='list-group-item text-muted small';
      empty.textContent='No other verification method is available on this account. Contact the school office to update your sign-in settings.';
      list.appendChild(empty);
      return;
    }
    state.methods.forEach((method)=>{
      const item=document.createElement('button');
      item.type='button';
      item.className='list-group-item list-group-item-action d-flex justify-content-between align-items-center';
      if(method===state.method) {
        item.classList.add('active');
        item.setAttribute('aria-current','true');
        const badge=document.createElement('span');
        badge.className='badge bg-light text-dark';
        badge.textContent='Already tried';
        item.appendChild(badge);
      }
      const label=document.createElement('span');
      const icon=document.createElement('i');
      icon.className='bi ' + ({email:'bi-envelope',sms:'bi-phone',whatsapp:'bi-whatsapp',totp:'bi-shield-lock',passkey:'bi-fingerprint'}[method]||'bi-shield-check') + ' me-2';
      label.appendChild(icon);
      label.appendChild(document.createTextNode(methodLabels[method]||method));
      item.prepend(label);
      item.addEventListener('click',()=>{
        hideTryAnotherModal();
        if(method!==state.method) void activateMethod(method);
      });
      list.appendChild(item);
    });
  }

  function hideTryAnotherModal() {
    const modalEl=byId('tryAnotherModal');
    if(modalEl && window.bootstrap?.Modal) {
      window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
    }
  }

  async function activateMethod(method) {
    if(!state.methods.includes(method)) throw new Error('That verification method is not available for this account.');
    clearMessages(); clearOtp(); updateMethodView(method);
    if(method==='passkey') { await verifyPasskey(); return; }
    try {
      await api('/twofactor/challenge','POST',{challenge_token:state.challenge,method});
      if(['email','sms','whatsapp'].includes(method)) {
        const label=method==='email'?'email address':method==='sms'?'phone':'WhatsApp';
        const el=byId('verifySuccess'); el.textContent=`A verification code was sent to your ${label}. Use the newest one if several arrive.`; el.classList.remove('d-none');
      }
      if(method!=='totp') otpCells()[0]?.focus();
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
    clearMessages(); const code=otpValue(); if(!code||code.length!==6)return;
    const method=state.method; const button=byId('verifySubmit'); button.disabled=true;
    const status=byId('authAutoStatus'); state.verifying=true; otpCells().forEach((cell)=>cell.disabled=true); if(status)status.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>Verifying securely…';
    try { const result=await api('/twofactor/verify','POST',{challenge_token:state.challenge,method,code}); if(!result?.verified)throw new Error('Verification failed.'); await finish(state.userId,state.challenge); }
    catch(error){showError('verifyError',error.message||'The code is invalid or expired.');clearOtp();otpCells()[0]?.focus();}
    finally{state.verifying=false;button.disabled=false;otpCells().forEach((cell)=>cell.disabled=false);if(status)status.innerHTML='<i class="bi bi-lightning-charge-fill"></i> Verification starts automatically when all six digits are entered.';}
  }
  async function resend() {
    clearMessages(); try { await api('/twofactor/challenge','POST',{challenge_token:state.challenge,method:state.method}); const el=byId('verifySuccess');el.textContent=`A new code was sent by ${state.method==='sms'?'text message':state.method==='whatsapp'?'WhatsApp':'email'}. Previous codes are no longer valid.`;el.classList.remove('d-none'); } catch(error){showError('verifyError',error.message||'A new code could not be sent.');}
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
  function reset() { state.userId=null;state.challenge=null;state.methods=[];state.method=null;state.verifying=false;clearMessages();clearOtp();byId('verificationStep').classList.add('d-none');byId('signInStep').classList.remove('d-none');byId('verificationCode').value='';byId('verificationCode').classList.add('d-none');byId('authOtpGrid').classList.remove('d-none');byId('verifySubmit').classList.add('d-none');byId('authPassword').focus(); }
  document.addEventListener('DOMContentLoaded',async()=>{ await window.AuthContext?.ready?.(); if(window.AuthContext?.isAuthenticated?.()){const next = new URLSearchParams(window.location.search).get('next')||undefined; window.location.replace(window.AuthContext.getAfterLoginUrl?.(next)||`${window.APP_BASE||''}/home.php`);return;} setupOtpInputs();byId('authLoginForm').addEventListener('submit',login);byId('verificationForm').addEventListener('submit',verify);byId('authPasswordless').addEventListener('click',passwordless);byId('authVerifyPasskey').addEventListener('click',verifyPasskey);byId('authTryAnother').addEventListener('click',buildTryAnotherList);byId('tryAnotherModal').addEventListener('show.bs.modal',buildTryAnotherList);byId('authResend').addEventListener('click',resend);byId('authBack').addEventListener('click',reset);byId('authTogglePassword').addEventListener('click',()=>{const input=byId('authPassword');input.type=input.type==='password'?'text':'password';}); });
})();
