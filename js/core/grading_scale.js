/**
 * DB-driven grading scale.
 *
 * Grade boundaries live in the database (`grading_scales` + `grade_rules`)
 * and are served by GET /api/academic/grading-scale. Nothing here is
 * hardcoded — edit the database (via the Grading Scales admin page or SQL)
 * and every page that resolves a CBC grade updates automatically, including
 * newly introduced grades/ranges.
 *
 * Usage:
 *   await GradingScale.preload();       // once per page, before first render
 *   GradingScale.grade(score, maxMarks);        // e.g. "EE2"
 *   GradingScale.gradeName(score, maxMarks);    // e.g. "Exceeding Expectation 2"
 *   GradingScale.remarks(score, maxMarks);      // descriptor text
 *   GradingScale.performanceLevel(score, maxMarks); // e.g. "Exceeding Expectation"
 *   GradingScale.gradePoints(score, maxMarks);
 *   GradingScale.band(code);                    // "EE2" -> "EE" (for CSS classes)
 *   GradingScale.rules();                       // all rows for the active scale
 *
 * The helpers are synchronous and read from a module-level cache (memory →
 * localStorage). `preload()` fills the cache; call it alongside page data
 * loading so renders always have the scale ready. `invalidate()` forces a
 * refetch (also triggered by storage events across tabs).
 */
(function () {
  const STORAGE_KEY = "kingsway:grading-scale";
  const TTL = 60 * 60 * 1000;
  let memory = null;

  function toPercent(score, maxMarks) {
    const s = Number(score) || 0;
    const m = Number(maxMarks) || 0;
    return m > 0 ? (s / m) * 100 : s;
  }

  async function fetchFromApi() {
    const res = await apiCall("academic/grading-scale", "GET");
    const data = res && res.data !== undefined ? res.data : res;
    return {
      scale: data && data.scale ? data.scale : null,
      rules: Array.isArray(data && data.rules) ? data.rules : [],
    };
  }

  function loadCache() {
    if (memory) return memory;
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return null;
      const parsed = JSON.parse(raw);
      if (!parsed || Date.now() - (parsed.fetchedAt || 0) > TTL) return null;
      memory = parsed;
      return memory;
    } catch (e) {
      return null;
    }
  }

  async function preload(source) {
    if (memory) return memory;
    const cached = loadCache();
    if (cached) return cached;
    try {
      const fresh =
        typeof source === "function" ? await source() : await fetchFromApi();
      memory = Object.assign({}, fresh, { fetchedAt: Date.now() });
      try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(memory));
      } catch (e) {
        /* storage unavailable (private mode / quota) — memory cache suffices */
      }
      return memory;
    } catch (e) {
      if (cached) {
        memory = cached;
        return memory;
      }
      memory = { scale: null, rules: [], fetchedAt: Date.now() };
      return memory;
    }
  }

  function ruleFor(score, maxMarks) {
    if (!memory || !Array.isArray(memory.rules)) return null;
    const pct = toPercent(score, maxMarks);
    for (let i = 0; i < memory.rules.length; i++) {
      const r = memory.rules[i];
      if (pct >= Number(r.min_mark) && pct <= Number(r.max_mark)) return r;
    }
    return null;
  }

  function grade(score, maxMarks) {
    const r = ruleFor(score, maxMarks);
    return r ? r.grade_code : "";
  }

  function gradeName(score, maxMarks) {
    const r = ruleFor(score, maxMarks);
    return r ? r.grade_name : "";
  }

  function remarks(score, maxMarks) {
    const r = ruleFor(score, maxMarks);
    return r && r.description ? r.description : "";
  }

  function performanceLevel(score, maxMarks) {
    const r = ruleFor(score, maxMarks);
    return r ? r.performance_level : "";
  }

  function gradePoints(score, maxMarks) {
    const r = ruleFor(score, maxMarks);
    return r ? Number(r.grade_points) || 0 : 0;
  }

  function rules() {
    return memory && Array.isArray(memory.rules) ? memory.rules : [];
  }

  function scale() {
    return memory ? memory.scale : null;
  }

  /** Strip numeric suffixes so styling classes (grade-EE etc.) keep working for codes like EE1/EE2. */
  function band(code) {
    return String(code || "").replace(/[0-9]+$/g, "");
  }

  function invalidate() {
    memory = null;
    try {
      localStorage.removeItem(STORAGE_KEY);
    } catch (e) {
      /* ignore */
    }
  }

  window.addEventListener("storage", function (e) {
    if (e.key === STORAGE_KEY) invalidate();
  });

  window.GradingScale = {
    preload: preload,
    ruleFor: ruleFor,
    grade: grade,
    gradeName: gradeName,
    remarks: remarks,
    performanceLevel: performanceLevel,
    gradePoints: gradePoints,
    rules: rules,
    scale: scale,
    band: band,
    invalidate: invalidate,
  };
})();
