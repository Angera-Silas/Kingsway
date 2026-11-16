/**
 * Photo capture page: live camera or file, cropped to passport shape (3:4),
 * saved to the selected learner or staff member through the existing photo
 * uploads (learners: /students/photo-upload; staff: /staff/upload-photo/{id}).
 * Bulk upload matches file names to admission / staff numbers.
 */
var PhotoCaptureController = {
  state: { kind: 'learner', people: [], selected: null, stream: null, captured: null, classes: [] },
  OUT_W: 600,
  OUT_H: 800,

  el(id) { return document.getElementById(id); },
  esc(v) { return String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]); },
  unwrap(r) { return r?.data?.data ?? r?.data ?? r; },
  notify(msg, type = 'info') { window.showNotification ? window.showNotification(msg, type) : null; },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    document.querySelectorAll('[data-pc-kind]').forEach((b) => b.addEventListener('click', () => this.setKind(b.dataset.pcKind)));
    this.el('pcClass').addEventListener('change', () => this.loadPeople());
    this.el('pcShow').addEventListener('change', () => this.renderPeople());
    let t = null;
    this.el('pcSearch').addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => (this.state.kind === 'staff' ? this.loadPeople() : this.renderPeople()), 300); });
    this.el('pcPeople').addEventListener('click', (e) => { const b = e.target.closest('[data-pc-id]'); if (b) this.select(b.dataset.pcId); });
    this.el('pcStart').addEventListener('click', () => this.startCamera());
    this.el('pcCamera').addEventListener('change', () => this.startCamera(this.el('pcCamera').value));
    this.el('pcCapture').addEventListener('click', () => this.capture());
    this.el('pcRetake').addEventListener('click', () => this.retake());
    this.el('pcSave').addEventListener('click', () => this.save());
    this.el('pcFile').addEventListener('change', (e) => this.useFile(e.target.files?.[0]));
    this.el('pcBulk').addEventListener('change', (e) => this.bulk([...(e.target.files || [])]));
    window.addEventListener('kingsway:page-will-load', () => this.stopCamera(), { once: true });
    await this.loadClasses();
  },

  setKind(kind) {
    this.state.kind = kind;
    document.querySelectorAll('[data-pc-kind]').forEach((b) => {
      const on = b.dataset.pcKind === kind;
      b.classList.toggle('btn-primary', on); b.classList.toggle('btn-outline-secondary', !on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    this.el('pcClassWrap').hidden = kind === 'staff';
    this.state.selected = null; this.state.people = [];
    this.retake();
    this.showSelected();
    this.loadPeople();
  },

  async loadClasses() {
    try {
      const r = await window.callAPI('/api/academic/classes-list', 'GET');
      const list = this.unwrap(r);
      this.state.classes = Array.isArray(list) ? list : (list?.classes || []);
      this.el('pcClass').innerHTML = '<option value="">Choose a class</option>' + this.state.classes.map((c) => `<option value="${Number(c.id)}">${this.esc(c.name || c.class_name)}</option>`).join('');
    } catch (_) { /* the class list is a convenience; search still works for staff */ }
  },

  hasPhoto(url) { return !!url && !/avatar\.(jpe?g|png)$/i.test(String(url)); },

  async loadPeople() {
    const box = this.el('pcPeople');
    box.innerHTML = '<div class="list-group-item text-muted">Loading…</div>';
    try {
      if (this.state.kind === 'learner') {
        const classId = this.el('pcClass').value;
        if (!classId) { box.innerHTML = '<div class="list-group-item text-muted">Choose a class.</div>'; this.state.people = []; this.el('pcCount').textContent = ''; return; }
        const r = await window.callAPI('/api/students/student', 'GET', null, { class_id: classId, limit: 100, page: 1 });
        const d = this.unwrap(r);
        this.state.people = (d.students || []).map((s) => ({ id: Number(s.id), name: s.full_name || [s.first_name, s.last_name].filter(Boolean).join(' '), number: s.admission_no, meta: [s.class_name, s.stream_name].filter(Boolean).join(' '), photo: s.photo_url }));
      } else {
        const r = await window.callAPI('/api/staff/index', 'GET', null, { page: 1, limit: 100, search: this.el('pcSearch').value.trim() });
        const d = this.unwrap(r);
        this.state.people = (d.staff || []).map((s) => ({ id: Number(s.staff_id), name: s.full_name || [s.first_name, s.last_name].filter(Boolean).join(' '), number: s.staff_no, meta: s.display_position || s.position || s.department_name || '', photo: s.photo_url }));
      }
      this.renderPeople();
    } catch (e) {
      box.innerHTML = `<div class="list-group-item text-danger">${this.esc(e?.message || 'The list could not be loaded.')}</div>`;
    }
  },

  visiblePeople() {
    const q = this.el('pcSearch').value.trim().toLowerCase();
    const missingOnly = this.el('pcShow').value === 'missing';
    return this.state.people.filter((p) => (!missingOnly || !this.hasPhoto(p.photo))
      && (!q || `${p.name} ${p.number}`.toLowerCase().includes(q)));
  },

  renderPeople() {
    const list = this.visiblePeople();
    const missing = this.state.people.filter((p) => !this.hasPhoto(p.photo)).length;
    this.el('pcCount').textContent = this.state.people.length ? `${missing} of ${this.state.people.length} without a photo` : '';
    this.el('pcPeople').innerHTML = list.length ? list.map((p) => `
      <button type="button" class="list-group-item list-group-item-action d-flex align-items-center gap-2 ${this.state.selected?.id === p.id ? 'active' : ''}" data-pc-id="${p.id}">
        ${this.hasPhoto(p.photo) ? `<img class="pc-thumb" src="${this.esc(p.photo)}" alt="">` : '<span class="pc-thumb d-inline-grid text-muted small" style="place-items:center">—</span>'}
        <span><span class="d-block">${this.esc(p.name)}</span><small class="text-muted">${this.esc(p.number || '')}${p.meta ? `, ${this.esc(p.meta)}` : ''}</small></span>
      </button>`).join('') : `<div class="list-group-item text-muted">${this.state.people.length ? 'Everyone here has a photo.' : 'No one found.'}</div>`;
  },

  select(id) {
    this.state.selected = this.state.people.find((p) => String(p.id) === String(id)) || null;
    this.retake();
    this.showSelected();
    this.renderPeople();
  },

  showSelected() {
    const p = this.state.selected;
    this.el('pcWho').textContent = p ? p.name : 'No one selected';
    this.el('pcWhoMeta').textContent = p ? `${p.number || ''}${p.meta ? `, ${p.meta}` : ''}${this.hasPhoto(p.photo) ? '. Has a photo; saving replaces it.' : '. No photo yet.'}` : 'Pick a learner or staff member on the left.';
    this.updateButtons();
  },

  updateButtons() {
    const live = !!this.state.stream && !this.state.captured;
    this.el('pcCapture').disabled = !live;
    this.el('pcSave').disabled = !(this.state.captured && this.state.selected);
    this.el('pcRetake').hidden = !this.state.captured;
  },

  async startCamera(deviceId = '') {
    if (!navigator.mediaDevices?.getUserMedia) { this.notify('This browser cannot use a camera here. Use "Use a file" instead.', 'error'); return; }
    this.stopCamera();
    try {
      this.state.stream = await navigator.mediaDevices.getUserMedia({
        video: deviceId ? { deviceId: { exact: deviceId }, width: { ideal: 1280 }, height: { ideal: 960 } } : { width: { ideal: 1280 }, height: { ideal: 960 } },
        audio: false,
      });
    } catch (e) {
      this.notify(e?.name === 'NotAllowedError' ? 'Camera access was refused. Allow the camera in the browser and try again.' : 'No camera could be opened. Connect a webcam or use "Use a file".', 'error');
      return;
    }
    const video = this.el('pcVideo');
    video.srcObject = this.state.stream;
    await video.play().catch(() => {});
    video.hidden = false; this.el('pcShot').hidden = true; this.el('pcEmpty').hidden = true;
    this.state.captured = null;
    const devices = (await navigator.mediaDevices.enumerateDevices()).filter((d) => d.kind === 'videoinput');
    const current = this.state.stream.getVideoTracks()[0]?.getSettings?.().deviceId || deviceId;
    this.el('pcCamera').innerHTML = devices.map((d, i) => `<option value="${this.esc(d.deviceId)}" ${d.deviceId === current ? 'selected' : ''}>${this.esc(d.label || `Camera ${i + 1}`)}</option>`).join('');
    this.updateButtons();
  },

  stopCamera() {
    this.state.stream?.getTracks().forEach((t) => t.stop());
    this.state.stream = null;
  },

  /** Crop the centre to 3:4 and scale to 600x800. */
  drawPassport(source, sw, sh) {
    const canvas = this.el('pcShot');
    canvas.width = this.OUT_W; canvas.height = this.OUT_H;
    const target = this.OUT_W / this.OUT_H;
    let cw = sw, ch = sh;
    if (sw / sh > target) cw = sh * target; else ch = sw / target;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(source, (sw - cw) / 2, (sh - ch) / 2, cw, ch, 0, 0, this.OUT_W, this.OUT_H);
    canvas.hidden = false; this.el('pcVideo').hidden = true; this.el('pcEmpty').hidden = true;
  },

  capture() {
    const v = this.el('pcVideo');
    if (!v.videoWidth) return;
    this.drawPassport(v, v.videoWidth, v.videoHeight);
    this.state.captured = true;
    this.updateButtons();
  },

  retake() {
    this.state.captured = null;
    this.el('pcShot').hidden = true;
    if (this.state.stream) this.el('pcVideo').hidden = false;
    else this.el('pcEmpty').hidden = false;
    this.updateButtons();
  },

  async useFile(file) {
    if (!file) return;
    const img = await this.loadImage(file);
    this.drawPassport(img, img.naturalWidth, img.naturalHeight);
    this.state.captured = true;
    this.el('pcFile').value = '';
    this.updateButtons();
  },

  loadImage(file) {
    return new Promise((resolve, reject) => {
      const url = URL.createObjectURL(file);
      const img = new Image();
      img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
      img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('That file is not an image.')); };
      img.src = url;
    });
  },

  canvasBlob() {
    return new Promise((resolve) => this.el('pcShot').toBlob(resolve, 'image/jpeg', 0.9));
  },

  async upload(kind, person, blob) {
    const file = new File([blob], `${person.number || person.id}.jpg`, { type: 'image/jpeg' });
    if (kind === 'learner') {
      const fd = new FormData();
      fd.append('photo', file);
      fd.append('student_id', String(person.id));
      return window.API.students.uploadPhoto(fd);
    }
    return window.API.staff.uploadPhoto(person.id, file, { description: 'Passport photo' });
  },

  async save() {
    const p = this.state.selected;
    if (!p || !this.state.captured) return;
    this.el('pcSave').disabled = true;
    try {
      const blob = await this.canvasBlob();
      await this.upload(this.state.kind, p, blob);
      p.photo = URL.createObjectURL(blob);
      this.notify(`Photo saved for ${p.name}.`, 'success');
      // Next person without a photo, to work down a class quickly.
      const next = this.visiblePeople().find((x) => x.id !== p.id && !this.hasPhoto(x.photo));
      if (next) this.select(next.id); else { this.retake(); this.showSelected(); this.renderPeople(); }
    } catch (e) {
      this.notify(e?.message || 'The photo could not be saved.', 'error');
      this.updateButtons();
    }
  },

  /** Files named by admission number (learners) or staff number (staff). */
  async bulk(files) {
    const log = this.el('pcBulkLog');
    if (!files.length) return;
    const kind = this.state.kind;
    log.innerHTML = '';
    const line = (text, cls = '') => { const d = document.createElement('div'); d.className = cls; d.textContent = text; log.appendChild(d); };
    let ok = 0;
    for (const f of files) {
      const key = f.name.replace(/\.[^.]+$/, '').trim();
      try {
        const person = await this.findByNumber(kind, key);
        if (!person) { line(`${f.name}: no ${kind === 'learner' ? 'learner with admission' : 'staff member with staff'} number ${key}`, 'text-danger'); continue; }
        const img = await this.loadImage(f);
        this.drawPassport(img, img.naturalWidth, img.naturalHeight);
        await this.upload(kind, person, await this.canvasBlob());
        ok++;
        line(`${f.name}: saved for ${person.name}`, 'text-success');
      } catch (e) {
        line(`${f.name}: ${e?.message || 'failed'}`, 'text-danger');
      }
    }
    line(`${ok} of ${files.length} photos saved.`, 'fw-semibold');
    this.el('pcBulk').value = '';
    this.retake();
    this.loadPeople();
  },

  async findByNumber(kind, number) {
    const norm = (v) => String(v || '').replace(/[^0-9a-z]/gi, '').toLowerCase();
    if (kind === 'learner') {
      const r = await window.callAPI('/api/students/student', 'GET', null, { search: number, limit: 5, page: 1 });
      const s = (this.unwrap(r).students || []).find((x) => norm(x.admission_no) === norm(number));
      return s ? { id: Number(s.id), name: s.full_name || s.first_name, number: s.admission_no } : null;
    }
    const r = await window.callAPI('/api/staff/index', 'GET', null, { search: number, limit: 5, page: 1 });
    const s = (this.unwrap(r).staff || []).find((x) => norm(x.staff_no) === norm(number));
    return s ? { id: Number(s.staff_id), name: s.full_name || s.first_name, number: s.staff_no } : null;
  },
};

window.PhotoCaptureController = PhotoCaptureController;
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => PhotoCaptureController.init());
else PhotoCaptureController.init();
