const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

function assert(condition, message) {
    if (!condition) throw new Error(message);
}

(async () => {
    const executablePath = process.env.PUPPETEER_EXECUTABLE_PATH
        || ['/usr/bin/google-chrome', '/usr/bin/google-chrome-stable', '/usr/bin/chromium']
            .find(candidate => fs.existsSync(candidate));
    const browser = await puppeteer.launch({
        headless: true,
        ...(executablePath ? { executablePath } : {}),
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
    });

    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));

        const templatePath = path.resolve(__dirname, '../../pages/transport/admin_transport.php');
        const template = fs.readFileSync(templatePath, 'utf8')
            .replace(/<\?php[\s\S]*?\?>/g, '')
            .replace(/<script[\s\S]*?<\/script>/gi, '');
        await page.setContent(`<!doctype html><html><body>${template}</body></html>`);

        await page.evaluate(() => {
            window.__calls = [];
            window.showNotification = () => {};
            window.confirm = () => true;
            window.KingswayFileLifecycle = { exportText: () => window.__calls.push('export') };
            window.bootstrap = {
                Modal: {
                    getOrCreateInstance: element => ({ show: () => element.dataset.open = 'true' }),
                    getInstance: element => ({ hide: () => element.dataset.open = 'false' }),
                },
            };
            const record = name => async payload => { window.__calls.push(name); return { data: payload?.id || 99 }; };
            window.APIRealtime = { register: () => {} };
            window.API = { transport: {
                getAllRoutes: async () => [{ id: 1, name: 'Londiani', code: 'LON', start_point: 'School', end_point: 'Town', fee: 2500, max_capacity: 40, student_count: 12, stop_count: 3, status: 'active' }],
                getAllVehicles: async () => [{ id: 2, registration_number: 'KAA 001A', type: 'Bus', capacity: 40, status: 'active' }],
                getAllDrivers: async () => [{ id: 3, first_name: 'Test', last_name: 'Driver', staff_no: 'DRV001', status: 'active' }],
                getAllStops: async () => [{ id: 4, name: 'Market', route_id: 1, sequence: 1, status: 'active' }],
                getBills: async () => [{ first_name: 'Test', last_name: 'Learner', route_name: 'Londiani', amount_due: 2500, amount_paid: 1000, balance: 1500, payment_status: 'partial' }],
                getBillsSummary: async () => ({ summary: { total_due: 2500, total_paid: 1000, total_outstanding: 1500, total_bills: 1 } }),
                createRoute: record('createRoute'), updateRoute: record('updateRoute'), deleteRoute: record('deleteRoute'),
                syncRouteStops: record('syncRouteStops'),
                createVehicle: record('createVehicle'), updateVehicle: record('updateVehicle'), deleteVehicle: record('deleteVehicle'), assignVehicle: record('assignVehicle'),
                syncVehicleRoutes: record('syncVehicleRoutes'),
                createDriver: record('createDriver'), updateDriver: record('updateDriver'), deleteDriver: record('deleteDriver'),
                syncDriverRoutes: record('syncDriverRoutes'),
                createStop: record('createStop'), updateStop: record('updateStop'), deleteStop: record('deleteStop'),
            }};
        });

        await page.addScriptTag({ path: path.resolve(__dirname, '../../js/pages/transport_admin.js') });
        await page.waitForFunction(() => document.querySelector('[data-kpi="routes"]')?.textContent === '1');

        const initial = await page.evaluate(() => ({
            routeRows: document.querySelectorAll('#transportRoutesBody tr').length,
            vehicleRows: document.querySelectorAll('#transportVehiclesBody tr').length,
            driverRows: document.querySelectorAll('#transportDriversBody tr').length,
            stopRows: document.querySelectorAll('#transportStopsBody tr').length,
            month: document.getElementById('transportBillingMonth').value,
        }));
        assert(Object.values(initial).every(Boolean), 'Initial transport data did not render completely.');

        await page.click('#transportAddRoute');
        assert(await page.$eval('#transportEditorModal', el => el.dataset.open === 'true'), 'Add-route modal did not open.');
        await page.type('[name="name"]', 'Test Route');
        await page.type('[name="description"]', 'Londiani Road, Market and Tuluet junction');
        await page.click('#transportAddRouteStop');
        await page.type('[data-stop-field="name"]', 'Londiani Junction');
        await page.click('#transportEditorSave');
        await page.waitForFunction(() => window.__calls.includes('createRoute'));
        await page.waitForFunction(() => window.__calls.includes('syncRouteStops'));

        for (const [tab, panel, add, expectedField] of [
            ['vehiclesPanel', 'vehiclesPanel', 'transportAddVehicle', 'registration_number'],
            ['driversPanel', 'driversPanel', 'transportAddDriver', 'first_name'],
            ['stopsPanel', 'stopsPanel', 'transportAddStop', 'name'],
        ]) {
            await page.click(`[data-panel="${tab}"]`);
            assert(await page.$eval(`#${panel}`, el => el.classList.contains('active')), `${tab} did not activate.`);
            await page.click(`#${add}`);
            assert(await page.$(`[name="${expectedField}"]`), `${tab} modal fields were not rendered.`);
        }

        await page.click('[data-panel="billingPanel"]');
        await page.click('#transportLoadBilling');
        await page.waitForFunction(() => document.querySelectorAll('#transportBillingBody tr').length === 1);
        assert(await page.$eval('#transportBillingSummary', el => el.textContent.includes('2,500.00')), 'Billing summary did not render.');

        await page.click('[data-panel="routesPanel"]');
        await page.click('#transportExport');
        assert(await page.evaluate(() => window.__calls.includes('export')), 'CSV export was not invoked.');
        assert(errors.length === 0, `Browser errors: ${errors.join(' | ')}`);
        console.log('Transport UI smoke test passed.');
    } finally {
        await browser.close();
    }
})().catch(error => {
    console.error(`Transport UI smoke test failed: ${error.message}`);
    process.exit(1);
});
