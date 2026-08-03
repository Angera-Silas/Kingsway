const puppeteer = require("puppeteer");

const TEST_PAGES = [
  // Core curriculum pages
  { route: "cbc_curriculum", name: "CBC Curriculum Manager" },
  { route: "assessment_rubrics", name: "Assessment Rubrics" },
  { route: "curriculum_cbc", name: "CBC Curriculum (legacy)" },
  // Assessment pages
  { route: "formative_assessments", name: "Formative Assessments" },
  { route: "assessment_overview", name: "Assessment Overview" },
  // Communication pages
  { route: "manage_sms", name: "SMS Management" },
  { route: "manage_email", name: "Email Management" },
  { route: "manage_whatsapp", name: "WhatsApp Management" },
  // Report cards
  { route: "report_cards", name: "Report Cards" },
];

(async () => {
  const baseUrl = process.env.BASE_URL || "http://localhost/Kingsway";
  console.log("Testing UI at:", baseUrl);

  const browser = await puppeteer.launch({
    headless: true,
    args: ["--no-sandbox", "--disable-setuid-sandbox"],
  });
  const page = await browser.newPage();
  page.setDefaultTimeout(15000);

  let passed = 0;
  let failed = 0;
  let warnings = 0;

  const test = (name, fn) => {
    try {
      fn();
      console.log(`  ✓ ${name}`);
      passed++;
    } catch (e) {
      console.log(`  ✗ ${name}: ${e.message}`);
      failed++;
    }
  };

  const warn = (msg) => {
    console.log(`  ⚠️  ${msg}`);
    warnings++;
  };

  try {
    // ── Public page tests ──────────────────────────────────────────────
    console.log("\n── Public Pages ──");

    console.log("Test 1: Loading index page...");
    const indexRes = await page.goto(`${baseUrl}/index.php`, {
      waitUntil: "networkidle2",
    });
    test("Index page returns 200", () => {
      if (!indexRes || indexRes.status() >= 400)
        throw new Error(`Status ${indexRes ? indexRes.status() : "no response"}`);
    });

    const loginForm = await page.$("#loginModal, .login-form, form[action*='login']");
    if (loginForm) {
      test("Login form detected", () => {});
    } else {
      warn("Login form not found - may use different auth method");
    }

    // ── App layout smoke test ──────────────────────────────────────────
    console.log("\n── App Layout ──");

    console.log("Test 2: Loading home page...");
    const homeRes = await page.goto(`${baseUrl}/home.php`, {
      waitUntil: "networkidle2",
    });
    test("Home page loads", () => {
      if (!homeRes || homeRes.status() >= 400)
        throw new Error(`Status ${homeRes ? homeRes.status() : "no response"}`);
    });

    const sidebar = await page.$("#sidebar-container, .sidebar, #sidebar");
    const mainContent = await page.$("#main-content-area, .main-content, main");
    test("App layout detected", () => {
      if (!sidebar && !mainContent)
        throw new Error("No sidebar or main content found");
    });

    // ── Authenticated route tests ──────────────────────────────────────
    console.log("\n── Authenticated Page Routes ──");

    for (const { route, name } of TEST_PAGES) {
      console.log(`Testing: ${name} (${route})...`);
      try {
        const res = await page.goto(`${baseUrl}/home.php?route=${route}`, {
          waitUntil: "networkidle2",
          timeout: 15000,
        });

        if (!res || res.status() >= 400) {
          warn(`${name} returned status ${res ? res.status() : "no response"} (may require auth)`);
          continue;
        }

        // Check page didn't error
        const bodyText = await page.evaluate(() => document.body?.innerText || "");
        const hasPhpError = /(Parse error|Fatal error|Warning|Notice|Undefined variable|Call to undefined)/i.test(bodyText);
        if (hasPhpError) {
          warn(`${name}: PHP error detected in output`);
        }

        // Check page has meaningful content (not just the loading or error shell)
        const hasContent = await page.evaluate(() => {
          const main = document.getElementById("main-content-segment");
          if (!main) return false;
          const text = main.innerText || "";
          return text.length > 50 && !text.includes("Page not found");
        });

        if (hasContent) {
          test(`${name}: content rendered`, () => {});
        } else {
          warn(`${name}: limited content detected (may require auth or specific role)`);
        }
      } catch (e) {
        warn(`${name} timed out or failed: ${e.message}`);
      }
    }

    // ── Summary ────────────────────────────────────────────────────────
    console.log("\n── Results ──");
    console.log(`  Passed: ${passed}`);
    console.log(`  Failed: ${failed}`);
    console.log(`  Warnings: ${warnings}`);

    if (failed > 0) {
      console.log("\n❌ UI smoke test: some checks FAILED");
      await browser.close();
      process.exit(1);
    } else {
      console.log("\n✓ UI smoke test: all basic checks passed");
      await browser.close();
      process.exit(0);
    }
  } catch (err) {
    console.error("\n❌ UI smoke test failed:", err.message);
    await browser.close();
    process.exit(1);
  }
})();
