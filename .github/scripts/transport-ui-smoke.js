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
        page.on('console', message => {
            if (message.type() === 'error') errors.push(`console.error: ${message.text()}`);
        });

        const template = fs.readFileSync(
            path.resolve(__dirname, '../../pages/transport/admin_transport.php'),
            'utf8'
        ).replace(/<\?php[\s\S]*?\?>/g, '').replace(/<script[\s\S]*?<\/script>/gi, '');

        await page.setContent(`<!doctype html><html><body><div id="transport-content">${template}</div></body></html>`);

        await page.evaluate(() => {
            window.__calls = [];
            window.__notifications = [];
            window.showNotification = (message, kind) => window.__notifications.push(`${kind}:${message}`);
            window.confirm = () => true;
            window.KingswayFileLifecycle = { exportText: () => window.__calls.push('export-csv') };
            const rows = [{ id: 7, name: 'Londiani Town Route', code: 'R-LON-01', start_point: 'Londiani', end_point: 'Kingsway', morning_departure: '06:30', afternoon_departure: '16:30', vehicle_registration: 'KCB 123A', student_count: 24, stop_count: 3, status: 'active' }];
            const record = name => async (data) => { window.__calls.push(`${name} ${JSON.stringify(data || {})}`); return { data: { id: 99 } }; };
            // The controller must commit through the canonical wrappers, not
            // through hand-built `/api/transport/<type>s` paths.
            window.API = {
                transport: {
                    getAllRoutes: async () => { window.__calls.push('getAllRoutes'); return { data: rows }; },
                    getAllVehicles: async () => { window.__calls.push('getAllVehicles'); return { data: [{ id: 3, registration_number: 'KCB 123A', type: 'Bus', model: 'Hiace', capacity: 40, status: 'active' }] }; },
                    getAllDrivers: async () => { window.__calls.push('getAllDrivers'); return { data: [] }; },
                    getAllStops: async () => { window.__calls.push('getAllStops'); return { data: [] }; },
                    getBills: async (params) => { window.__calls.push(`getBills ${params.month}`); return { data: [] }; },
                    getBillsSummary: async (params) => { window.__calls.push(`getBillsSummary ${params.month}`); return { data: { summary: { total_due: 2500 } } }; },
                    createRoute: record('createRoute'),
                    updateRoute: record('updateRoute'),
                    createVehicle: record('createVehicle'),
                    updateVehicle: record('updateVehicle'),
                    createDriver: record('createDriver'),
                    updateDriver: record('updateDriver'),
                    createStop: record('createStop'),
                    updateStop: record('updateStop'),
                },
            };
            window.bootstrap = {
                Modal: {
                    getInstance: element => ({ hide: () => { element.dataset.open = 'false'; } }),
                },
                Modal2: null,
            };
            // openEditor() constructs a modal directly; Bootstrap's real global
            // exposes Modal as a constructor alongside getInstance.
            window.bootstrap.Modal = class Modal {
                constructor(element) { this.element = element; }
                show() { this.element.dataset.open = 'true'; }
                hide() { this.element.dataset.open = 'false'; }
                static getInstance(element) { return new Modal(element); }
            };
            window.APIRealtime = { register: () => {}, attachCurrentRouteControllers: () => {} };
        });

        await page.addScriptTag({ path: path.resolve(__dirname, '../../js/pages/transport_admin.js') });
        await page.waitForFunction(() => typeof window.transportAdminController !== 'undefined', { timeout: 10000 });

        await page.evaluate(() => window.transportAdminController.init());
        await page.waitForFunction(() => document.querySelectorAll('#transportRoutesBody tr').length > 0, { timeout: 10000 });

        // --- 1. The script must parse and the controller must exist -----------
        assert(errors.length === 0, `Page errors after load: ${errors.join(' | ')}`);

        // --- 2. KPI cards must be filled from the loaded state ---------------
        const kpis = await page.evaluate(() => ({
            routes: document.querySelector('[data-kpi="routes"]')?.textContent,
            vehicles: document.querySelector('[data-kpi="vehicles"]')?.textContent,
            learners: document.querySelector('[data-kpi="learners"]')?.textContent,
            attention: document.querySelector('[data-kpi="attention"]')?.textContent,
        }));
        assert(kpis.routes === '1', `KPI routes should be 1, got "${kpis.routes}"`);
        assert(kpis.vehicles === '1', `KPI vehicles should be 1, got "${kpis.vehicles}"`);
        assert(kpis.learners === '24', `KPI learners should be 24, got "${kpis.learners}"`);
        assert(kpis.attention === '0', `KPI attention should be 0, got "${kpis.attention}"`);

        // --- 3. Tabs must switch panels -------------------------------------
        await page.click('[data-panel="vehiclesPanel"]');
        const tabbed = await page.evaluate(() => ({
            vehicles: document.getElementById('vehiclesPanel')?.classList.contains('active'),
            routes: document.getElementById('routesPanel')?.classList.contains('active'),
        }));
        assert(tabbed.vehicles === true, 'Vehicles tab did not activate its panel');
        assert(tabbed.routes === false, 'Routes panel stayed active after a tab switch');
        assert(errors.length === 0, `Page errors after tab switch: ${errors.join(' | ')}`);

        // --- 4. The Add route button must open the editor --------------------
        await page.click('[data-panel="routesPanel"]');
        await page.click('#transportAddRoute');
        const editor = await page.evaluate(() => ({
            open: document.getElementById('transportEditorModal')?.dataset.open,
            title: document.getElementById('transportEditorTitle')?.textContent,
            fields: document.getElementById('transportEditorBody')?.querySelectorAll('input,select').length,
            vehicleOptions: document.getElementById('traRouteVehicleSelect')?.options.length,
            driverOptions: document.getElementById('traRouteDriverSelect')?.options.length,
        }));
        assert(editor.open === 'true', 'Add route did not open the editor modal');
        assert(/Route/i.test(editor.title || ''), 'Editor title did not describe a route');
        assert(editor.fields >= 5, 'Editor form did not render its fields');
        assert(editor.vehicleOptions >= 1, 'Vehicle select was not populated with real field names');
        assert(editor.driverOptions >= 1, 'Driver select was not populated with real field names');

        // --- 5. Save must be wired and must reach the API --------------------
        await page.type('#traRouteName', 'Test Route');
        await page.type('#traRouteMorning', '07:15');
        await page.click('#transportEditorSave');
        await page.waitForFunction(() => window.__calls.some(c => c.startsWith('createRoute')), { timeout: 10000 });
        assert(
            await page.evaluate(() => document.getElementById('transportEditorModal')?.dataset.open === 'false'),
            'Editor modal did not close after a successful save'
        );
        assert(
            await page.evaluate(() => window.__notifications.some(n => n.startsWith('success:'))),
            'A success toast was not raised after saving'
        );

        // --- 6. Row action buttons must open the editor for that record ------
        await page.click('[data-panel="vehiclesPanel"]');
        await page.click('#transportVehiclesBody tr:first-child button');
        const editModal = await page.evaluate(() => ({
            open: document.getElementById('transportEditorModal')?.dataset.open,
            title: document.getElementById('transportEditorTitle')?.textContent,
            reg: document.getElementById('traVehicleReg')?.value,
        }));
        assert(editModal.open === 'true', 'Row action button did not open the editor');
        assert(/Vehicle/i.test(editModal.title || ''), 'Row action opened the wrong editor');
        assert(editModal.reg === 'KCB 123A', `Editor was not pre-filled (got "${editModal.reg}")`);

        // --- 7. CSV export must be wired ------------------------------------
        await page.click('[data-panel="routesPanel"]');
        await page.click('#transportExport');
        assert(
            await page.evaluate(() => window.__calls.includes('export-csv')),
            'Export CSV button was not wired'
        );

        // --- 8. Collections Load must be wired -------------------------------
        await page.click('[data-panel="billingPanel"]');
        await page.evaluate(() => { document.getElementById('transportBillingMonth').value = '2026-11'; });
        await page.click('#transportLoadBilling');
        await page.waitForFunction(() => window.__calls.includes('getBills 2026-11'), { timeout: 10000 });

        // --- 9. Search filtering must not throw ------------------------------
        await page.click('[data-panel="routesPanel"]');
        await page.type('#transportSearch', 'londiani');
        await new Promise(resolve => setTimeout(resolve, 200));
        const filtered = await page.evaluate(() => ({
            visible: Array.from(document.querySelectorAll('#transportRoutesBody tr')).filter(r => r.style.display !== 'none').length,
        }));
        assert(filtered.visible === 1, 'Search filter hid the matching row');

        assert(errors.length === 0, `Final page errors: ${errors.join(' | ')}`);
        console.log('Transport workspace smoke test passed.');
    } finally {
        await browser.close();
    }
})().catch(error => {
    console.error(`Transport workspace smoke test failed: ${error.message}`);
    process.exit(1);
});
