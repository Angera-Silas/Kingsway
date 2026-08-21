/**
 * activity_achievements.js — Activity Achievements Controller (standalone page).
 * Sports team standings and completed match results.
 */
const activityAchievementsController = {
  state: { standings: [], fixtures: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r?.standings || r?.fixtures || r || []); },

  formBadge(f) {
    const b = { W: 'bg-success', D: 'bg-secondary', L: 'bg-danger' }[f];
    return `<span class="badge ${b}">${this.esc(f)}</span>`;
  },

  async load() {
    const sb = document.getElementById('aaStandings');
    const fb = document.getElementById('aaFixtures');
    sb.innerHTML = '<tr><td colspan="11" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    fb.innerHTML = '<tr><td colspan="6" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const [standRes, fixRes] = await Promise.all([
        this.API('GET', '/activities/sports/standings', null, { limit: 100 }).catch(() => []),
        this.API('GET', '/activities/sports/fixtures', null, { status: 'completed', limit: 100 }).catch(() => []),
      ]);
      this.state.standings = this.norm(standRes);
      this.state.fixtures = this.norm(fixRes);
      this.render();
    } catch (e) { sb.innerHTML = `<tr><td colspan="11" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  render() {
    const sb = document.getElementById('aaStandings');
    if (!this.state.standings.length) { sb.innerHTML = '<tr><td colspan="11" class="text-center py-4 text-muted">No standings available.</td></tr>'; }
    else {
      sb.innerHTML = this.state.standings.map((t, i) => `<tr>
        <td class="small fw-semibold">${this.esc(t.team_name || '\u2014')}</td>
        <td class="small">${this.esc(t.sport_type || '\u2014')}</td>
        <td class="small text-muted">${this.esc(t.season || '\u2014')}</td>
        <td class="small text-center">${this.esc(t.played ?? 0)}</td>
        <td class="small text-center text-success">${this.esc(t.wins ?? 0)}</td>
        <td class="small text-center text-muted">${this.esc(t.draws ?? 0)}</td>
        <td class="small text-center text-danger">${this.esc(t.losses ?? 0)}</td>
        <td class="small text-center">${this.esc(t.goals_for ?? 0)}</td>
        <td class="small text-center">${this.esc(t.goals_against ?? 0)}</td>
        <td class="small fw-bold text-center">${this.esc(t.points ?? 0)}</td>
        <td>${t.form ? this.formBadge(t.form) : '<span class="text-muted small">\u2014</span>'}</td>
      </tr>`).join('');
    }

    const fb = document.getElementById('aaFixtures');
    if (!this.state.fixtures.length) { fb.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No completed fixtures yet.</td></tr>'; }
    else {
      fb.innerHTML = this.state.fixtures.map(f => `<tr>
        <td class="small fw-semibold">${this.esc(f.team_name || '\u2014')}</td>
        <td class="small">${this.esc(f.opponent || '\u2014')}</td>
        <td class="small">${this.esc(f.fixture_date || '\u2014')}</td>
        <td class="small text-muted">${this.esc(f.venue || '\u2014')}</td>
        <td class="small fw-semibold">${this.esc(f.our_score ?? 0)} - ${this.esc(f.opponent_score ?? 0)}</td>
        <td><span class="badge ${f.result === 'win' ? 'bg-success' : f.result === 'draw' ? 'bg-secondary' : 'bg-danger'}">${this.esc(f.result || '\u2014')}</span></td>
      </tr>`).join('');
    }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.load();
  },
};

window.activityAchievementsController = activityAchievementsController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => activityAchievementsController.init().catch(() => {}));
} else {
  activityAchievementsController.init().catch(() => {});
}
