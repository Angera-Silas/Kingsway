/**
 * Role-template soft navigation for `manage_transport`.
 *
 * PageShell.ensureExternalScript() deliberately loads a route controller once
 * and reuses the cached <script> element on later soft navigations. The route
 * config used to try to defeat that with a beforeLoad() hook that marked the
 * cached script as 'error' so a fresh element would be created and the file
 * re-executed. That can never work: transport_admin.js declares
 * `const transportAdminController` at the top level of a classic script, and a
 * second execution throws
 * "SyntaxError: Identifier 'transportAdminController' has already been declared".
 *
 * The correct contract is: load once, then re-run the controller's init()
 * against the freshly injected template DOM. This test pins both halves.
 */
const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

function assert(condition, message) {
    if (!condition) throw new Error(message);
}

const executablePath = ['/usr/bin/google-chrome', '/usr/bin/google-chrome-stable', '/usr/bin/chromium']
    .find(candidate => fs.existsSync(candidate));

(async () => {
    const browser = await puppeteer.launch({
        headless: true,
        ...(executablePath ? { executablePath } : {}),
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
    });

    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(`${error.name}: ${error.message}`));

        const template = fs.readFileSync(
            path.resolve(__dirname, '../../pages/transport/admin_transport.php'),
            'utf8'
        ).replace(/<\?php[\s\S]*?\?>/g, '').replace(/<script[\s\S]*?<\/script>/gi, '');

        // A minimal shell that carries the same transport element ids the
        // controller binds to, so init() has DOM to work against.
        await page.setContent(`<!doctype html><html><body>
            <div id="transport-loading"></div>
            <div id="transport-content">${template}</div>
        </body></html>`);

        await page.evaluate(() => {
            window.__loads = 0;
            window.__inits = 0;
            window.__errors = [];
            window.showNotification = () => {};
            window.confirm = () => true;
            window.KingswayFileLifecycle = { exportText: () => {} };
            window.APP_BASE = '';
            window.REQUESTED_ROUTE = 'manage_transport';
            window.bootstrap = {
                Modal: {
                    getOrCreateInstance: element => ({ show: () => { element.dataset.open = 'true'; } }),
                    getInstance: element => ({ hide: () => { element.dataset.open = 'false'; } }),
                },
            };
            window.APIRealtime = {
                register: () => {},
                attachCurrentRouteControllers: () => {},
            };
            window.API = {
                transport: {
                    getAllRoutes: async () => [{ id: 1, name: 'Londiani', code: 'LON', start_point: 'School', end_point: 'Town', fee: 2500, max_capacity: 40, student_count: 12, stop_count: 3, status: 'active' }],
                    getAllVehicles: async () => [],
                    getAllDrivers: async () => [],
                    getAllStops: async () => [],
                    getBills: async () => [],
                    getBillsSummary: async () => ({ summary: {} }),
                },
            };
        });

        // ---------------------------------------------------------------
        // First visit: full load, so the controller script executes once.
        // ---------------------------------------------------------------
        await page.addScriptTag({ path: path.resolve(__dirname, '../../js/pages/transport_admin.js') });
        console.log("waiting for controller..."); await page.waitForFunction(() => typeof window.transportAdminController !== 'undefined', { timeout: 8000 }); console.log("controller present");
        await page.evaluate(() => window.transportAdminController.init());

        const firstScriptTags = await page.evaluate(() =>
            Array.from(document.scripts).filter((s) => (s.src || '').includes('transport_admin')).length
        );
        assert(firstScriptTags === 1, 'the controller script should be present exactly once after a full load');

        // ---------------------------------------------------------------
        // Soft navigation: the template is replaced, exactly like PageShell.
        // A route config that forces script re-execution dies here.
        // ---------------------------------------------------------------
        const beforeRedeclaration = await page.evaluate(() => {
            window.__before = window.transportAdminController;
            return typeof window.transportAdminController;
        });

        const softNavigated = await page.evaluate(async () => {
            const host = document.getElementById('transport-content');
            // Re-inject the template the way loadRoleTemplate() does.
            host.innerHTML = document.getElementById('transport-content').innerHTML;
            window.__hostHtmlLength = host.innerHTML.length;
            // afterLoad() re-binds the SAME controller to the fresh DOM.
            if (typeof window.transportAdminController !== 'undefined') {
                await window.transportAdminController.init();
                window.__inits += 1;
            }
            return {
                sameSingleton: window.transportAdminController === window.__before,
                hasScriptState: Array.from(document.scripts)
                    .filter((s) => (s.src || '').includes('transport_admin'))
                    .every((s) => s.dataset.kingswayScriptState !== 'error'),
            };
        });

        assert(
            beforeRedeclaration === 'object',
            'the controller should be a global object after the first load'
        );
        assert(
            softNavigated.sameSingleton,
            'soft navigation must reuse the same controller instance'
        );
        assert(
            softNavigated.hasScriptState,
            'the cached script must never be marked as "error" to force re-execution'
        );

        // ---------------------------------------------------------------
        // The route config must not carry a beforeLoad hook any more.
        // ---------------------------------------------------------------
        const routesSource = fs.readFileSync(
            path.resolve(__dirname, '../../js/pages/role_shell_routes.js'),
            'utf8'
        );
        assert(
            !routesSource.includes('beforeLoad'),
            'role_shell_routes.js must not force script re-execution via beforeLoad'
        );
        const transportBlock = routesSource.slice(
            routesSource.indexOf('manage_transport:'),
            routesSource.indexOf('manage_fee_structure:')
        );
        assert(
            /afterLoad: async \(\) => \{ if \(typeof window\.transportAdminController/.test(transportBlock),
            'manage_transport must re-init the existing controller in afterLoad'
        );

        const shellSource = fs.readFileSync(
            path.resolve(__dirname, '../../js/components/PageShell.js'),
            'utf8'
        );
        assert(
            !shellSource.includes('beforeLoad'),
            'PageShell.js must not expose a beforeLoad hook for this'
        );

        assert(
            errors.length === 0,
            `Browser errors: ${errors.join(' | ')}`
        );

        console.log('Transport role-template soft-navigation test passed.');
    } finally {
        await browser.close();
    }
})().catch(error => {
    console.error(`Transport role-template soft-navigation test failed: ${error.message}`);
    process.exit(1);
});
