/* Run against the isolated seed.php server only. Requires Playwright and installed Edge. */
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const fixtures = JSON.parse(fs.readFileSync(path.join(root, 'storage/framework/testing/visual-fixtures.json')));
const viewers = JSON.parse(fs.readFileSync(path.join(root, 'storage/framework/testing/visual-viewers.json')));
const output = path.join(root, 'storage/framework/testing/visual-review');
fs.mkdirSync(output, { recursive: true });
const base = 'http://127.0.0.1:8011';
const checks = [];
const errors = [];
let page;
async function check(name, task) {
    await task();
    checks.push(name);
    console.log('PASS', name);
}
async function go(url) {
    const response = await page.goto(base + url);
    assert(response.status() < 400, `${url}: ${response.status()}`);
    await page.waitForLoadState('networkidle');
}
async function login(username) {
    await page.context().clearCookies();
    await go('/login');
    await page.locator('#username').fill(username);
    await page.locator('#password').fill('VisualReview2026!');
    await Promise.all([page.waitForURL('**/expedientes'), page.locator('.access-submit').click()]);
    await page.waitForLoadState('networkidle');
}
async function noOverflow() {
    const overflow = await page.evaluate(() => [...document.querySelectorAll('body *')].filter(el => !el.closest('dialog:not([open]), [popover]:not(:popover-open), .table-scroll') && el.getBoundingClientRect().right > innerWidth + 1).slice(0, 8).map(el => ({ tag: el.tagName, cls: el.className, width: el.getBoundingClientRect().width })));
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Page has horizontal overflow at ${page.url()}: ${JSON.stringify(overflow)}`);
}
async function capture(name) { await page.screenshot({ path: path.join(output, name + '.png'), fullPage: true }); }
(async () => {
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
    page.on('pageerror', error => errors.push(error.message));
    page.on('dialog', dialog => dialog.accept());
    try {
        await check('Login, labels, password toggle and invalid credentials', async () => {
            await go('/login');
            for (const width of [390, 820, 1440]) {
                await page.setViewportSize({ width, height: 1000 });
                await noOverflow(); await capture(`login-${width}`);
            }
            await page.locator('#username').fill('incorrecto');
            await page.locator('#password').fill('incorrecto');
            await page.locator('[data-password-toggle]').click();
            assert.equal(await page.locator('#password').getAttribute('type'), 'text');
            await Promise.all([page.waitForNavigation(), page.locator('.access-submit').click()]);
            assert(await page.locator('[data-error-summary]').isVisible());
            await capture('login-error');
        });
        await login('visual.admin');
        await check('Server pagination, ten records, unbounded tables and row action geometry', async () => {
            await go('/users');
            assert.equal(await page.locator('.data-table tbody tr').count(), 10);
            const style = await page.locator('.table-action').first().evaluate(el => {
                const s = getComputedStyle(el), icon = el.querySelector('svg');
                return { height: el.getBoundingClientRect().height, border: s.borderTopWidth, background: s.backgroundColor, font: s.fontSize, icon: icon.getBoundingClientRect().width, max: getComputedStyle(el.closest('.table-scroll')).maxHeight };
            });
            assert(style.height >= 44); assert.equal(style.border, '0px'); assert.equal(style.background, 'rgba(0, 0, 0, 0)'); assert.equal(style.font, '14px'); assert.equal(style.icon, 18); assert.equal(style.max, 'none');
            await page.locator('.table-pagination a').last().click();
            await page.waitForFunction(() => new URL(location.href).searchParams.get('page') === '2');
            assert.equal(await page.locator('.data-table tbody tr').count(), 6);
        });
        await check('Reactive search, empty state, clear always available, focus returns to search', async () => {
            await page.locator('input[name=q]').fill('no-such-account');
            await page.waitForFunction(() => document.querySelector('[data-listado-total]').textContent.includes('0'));
            assert(await page.locator('.empty-state').isVisible());
            await page.locator('[data-limpiar-filtros]').click();
            assert.equal(await page.locator('input[name=q]').evaluate(el => el === document.activeElement), true);
            await page.waitForFunction(() => document.querySelectorAll('.data-table tbody tr').length === 10);
        });
        await check('Responsive actual screens at 390, 820 and 1440 pixels', async () => {
            for (const width of [390, 820, 1440]) {
                await page.setViewportSize({ width, height: 1000 });
                for (const [name, url] of [['users','/users'], ['user-create','/users/create'], ['user-edit',`/users/${fixtures.reader}/edit`], ['expedientes','/expedientes'], ['detail',`/expedientes/${fixtures.expediente}`], ['permissions',`/expedientes/${fixtures.expediente}/permisos/asignar`], ['reader',`/expedientes/${fixtures.expediente}/permisos/lectores/${fixtures.reader}`]]) {
                    await go(url);
                    await noOverflow();
                    await capture(`${name}-${width}`);
                }
            }
        });
        await check('Document pagination, filters and persistent selection', async () => {
            await go(`/expedientes/${fixtures.expediente}`);
            assert.equal(await page.locator('#tabla-documentos [data-documento]:visible').count(), 10);
            await page.locator('[data-share-document]').first().check();
            await page.locator('[data-pagination-for="tabla-documentos"] [data-page-next]').click();
            assert.equal(await page.locator('#tabla-documentos [data-documento]:visible').count(), 2);
            await page.locator('[data-pagination-for="tabla-documentos"] [data-page-prev]').click();
            assert(await page.locator('[data-share-document]').first().isChecked());
            await page.locator('#tabla-documentos-q').fill('Documento 12');
            assert.equal(await page.locator('#tabla-documentos [data-documento]:visible').count(), 1);
            await page.locator('[data-limpiar-documentos]').click();
            assert(await page.locator('#tabla-documentos-q').evaluate(el => el === document.activeElement));
            await page.locator('details.disclosure summary').click();
            assert.equal(await page.locator('#tabla-bajas [data-pagination-row]:visible').count(), 10);
            await page.locator('[data-pagination-for="tabla-bajas"] [data-page-next]').click();
            assert.equal(await page.locator('#tabla-bajas [data-pagination-row]:visible').count(), 2);
        });
        await check('Permission wizard selection across pages, automatic preview and validation', async () => {
            await go(`/expedientes/${fixtures.expediente}/permisos/asignar`);
            await page.locator('#batch-documents [data-selection-item]').first().check();
            await page.locator('#batch-documents [data-selection-next]').click();
            await page.locator('#batch-documents [data-selection-item]:visible').last().check();
            await page.locator('[data-batch-next]').click();
            await page.locator('#batch-readers [data-selection-item]').first().check();
            await page.locator('[data-batch-next]').click();
            await page.waitForFunction(() => !!document.querySelector('[data-review-token]').value);
            assert.equal(await page.locator('[data-batch-save]').isDisabled(), false);
            assert.equal(await page.locator('dialog[open]').count(), 0);
            await capture('permission-review');
            await page.locator('#batch-starts').fill('2030-01-02T10:00');
            await page.locator('#batch-expires').fill('2030-01-01T10:00');
            await page.waitForFunction(() => document.querySelector('#batch-expires').getAttribute('aria-invalid') === 'true');
            assert(await page.locator('#batch-expires-error').isVisible());
            assert(await page.locator('[data-batch-save]').isDisabled());
            await capture('permission-date-error');
        });
        await check('Delayed loading, failure recovery and session error preserve search input', async () => {
            await go('/users');
            await page.route('**/users?**', async route => {
                await new Promise(resolve => setTimeout(resolve, 700));
                await route.continue().catch(() => {});
            });
            await page.locator('input[name=q]').fill('visual');
            await page.waitForFunction(() => document.querySelector('[data-listado-aviso]').textContent.includes('Actualizando'));
            assert.equal(await page.locator('[data-listado-resultados]').getAttribute('aria-busy'), 'true');
            await page.waitForFunction(() => !document.querySelector('[data-listado-resultados]').hasAttribute('aria-busy'));
            assert(await page.locator('input[name=q]').evaluate(el => el === document.activeElement));
            await page.unroute('**/users?**');
            for (const status of [500, 419]) {
                await page.route('**/users?**', route => route.fulfill({ status, body: 'Unavailable' }));
                await page.locator('input[name=q]').fill('failure-' + status);
                await page.waitForFunction(() => !document.querySelector('[data-listado-aviso]').hidden && !document.querySelector('[data-listado-aviso]').classList.contains('sr-only'));
                assert.equal(await page.locator('input[name=q]').inputValue(), 'failure-' + status);
                assert.equal(await page.locator('.data-table tbody tr').count(), 4);
                await capture('filter-error-' + status);
                await page.unroute('**/users?**');
            }
            await page.locator('[data-limpiar-filtros]').click();
            await page.waitForFunction(() => document.querySelectorAll('.data-table tbody tr').length === 10);
        });
        await login('visual.editor.con.identificador.largo.para.pruebas');
        await check('Editor views, long identity and no decorative account icon', async () => {
            for (const width of [390, 820, 1440]) {
                await page.setViewportSize({ width, height: 1000 });
                await go('/expedientes/create'); await noOverflow(); await capture(`editor-create-${width}`);
                assert.equal(await page.locator('.header-user svg').count(), 0);
                assert.match(await page.locator('.header-user').textContent(), /Editor/);
            }
        });
        await check('Version menu, ten rows, dialog focus, confirmation suspension and Escape', async () => {
            await go(`/expedientes/${fixtures.expediente}`);
            await page.locator(`[popovertarget="doc-${fixtures.document}-menu"]`).click();
            await page.locator(`[data-dialogo-abrir="doc-${fixtures.document}-historial"]`).click();
            const dialog = page.locator(`#doc-${fixtures.document}-historial`);
            assert.equal(await dialog.locator('[data-pagination-row]:visible').count(), 10);
            assert.equal(await page.locator('dialog[open]').count(), 1);
            await dialog.locator('[data-page-next]').click();
            assert.equal(await dialog.locator('[data-pagination-row]:visible').count(), 2);
            await dialog.locator('[data-page-prev]').click();
            await dialog.locator('.row-menu-button').nth(1).click();
            await page.locator('.action-menu:popover-open [data-confirm]').click();
            assert.equal(await page.locator('dialog[open]').count(), 1);
            assert(await page.locator('#modal-confirmacion').isVisible());
            await page.locator('#modal-confirmacion .btn-secondary').click();
            await dialog.waitFor({ state: 'visible' });
            assert(await dialog.isVisible());
            assert.equal(await page.locator('dialog[open]').count(), 1);
            await capture('version-history');
            await page.keyboard.press('Escape');
            assert.equal(await page.locator('dialog[open]').count(), 0);
            assert(await page.locator(`[popovertarget="doc-${fixtures.document}-menu"]`).evaluate(el => el === document.activeElement));
        });
        await check('All editor form dialogs at mobile, keyboard focus and error recovery', async () => {
            await page.setViewportSize({ width: 390, height: 844 });
            await go(`/expedientes/${fixtures.expediente}`);
            for (const suffix of ['version','clasificacion','baja']) {
                await page.locator(`[popovertarget="doc-${fixtures.document}-menu"]`).click();
                await page.locator(`[data-dialogo-abrir="doc-${fixtures.document}-${suffix}"]`).click();
                await noOverflow(); await capture(`dialog-${suffix}-390`);
                assert(await page.evaluate(() => !!document.activeElement.closest('dialog[open]')));
                await page.keyboard.press('Shift+Tab');
                assert(await page.evaluate(() => !!document.activeElement.closest('dialog[open]')));
                await page.keyboard.press('Tab');
                assert(await page.evaluate(() => !!document.activeElement.closest('dialog[open]')));
                await page.keyboard.press('Escape');
            }
            await page.locator('[data-dialogo-abrir="cargar-documentos"]').click();
            await noOverflow(); await capture('dialog-upload-390'); await page.keyboard.press('Escape');
            await page.locator(`[popovertarget="doc-${fixtures.document}-menu"]`).click();
            await page.locator(`[data-dialogo-abrir="doc-${fixtures.document}-version"]`).click();
            await page.locator(`#doc-${fixtures.document}-version-file`).setInputFiles({ name: 'invalid.txt', mimeType: 'text/plain', buffer: Buffer.from('invalid document') });
            await Promise.all([page.waitForNavigation(), page.locator(`#doc-${fixtures.document}-version button[type=submit]`).click()]);
            assert.equal(await page.locator(`#doc-${fixtures.document}-version-file`).getAttribute('aria-invalid'), 'true');
            assert(await page.locator(`#doc-${fixtures.document}-version [data-dialog-error-summary]`).isVisible());
            await capture('dialog-file-error-390'); await page.keyboard.press('Escape');
            await page.locator('[data-dialogo-abrir="editar-datos"]').click();
            await page.locator('#editar-name').fill('');
            await page.locator('#editar-datos form').evaluate(form => { form.noValidate = true; });
            await Promise.all([page.waitForNavigation(), page.locator('#editar-datos button[type=submit]').click()]);
            assert(await page.locator('#editar-name').evaluate(el => el.getAttribute('aria-invalid') === 'true' && el === document.activeElement));
            assert.equal(await page.locator('dialog[open]').count(), 1);
            await page.keyboard.press('Escape');
        });
        await check('CSS zoom 200 percent reflow proxy', async () => {
            await page.setViewportSize({ width: 1440, height: 1000 });
            await go(`/expedientes/${fixtures.expediente}`);
            await page.evaluate(() => { document.documentElement.style.zoom = '2'; });
            await noOverflow(); await capture('detail-css-zoom-200');
            await page.evaluate(() => { document.documentElement.style.zoom = ''; });
            await page.setViewportSize({ width: 390, height: 1000 });
            await page.locator('h1').evaluate(el => { el.textContent = 'A'.repeat(255); });
            await noOverflow(); await capture('unbroken-title-390');
        });
        await login('visual.reader');
        await check('Reader navigation, viewer and download permission preserved', async () => {
            await go(`/expedientes/${fixtures.expediente}`);
            assert.equal(await page.locator('.workspace-sidebar').count(), 0);
            assert.equal(await page.locator('[data-dialogo-abrir]').count(), 0);
            const currentViewer = await page.locator(`#doc-${fixtures.document}-menu a`).first().getAttribute('href');
            await go(new URL(currentViewer).pathname);
            await noOverflow(); await capture('reader-viewer');
            const download = await Promise.all([page.waitForEvent('download'), page.getByRole('link', { name: 'Descargar documento' }).click()]);
            assert.equal(download[0].suggestedFilename(), 'Documento 01.pdf');
            for (const [mode, version] of Object.entries(viewers)) {
                await go(`/versions/${version}/visor`);
                if (mode === 'image') assert(await page.locator('.document-viewer-image').isVisible());
                else assert.match(await page.locator('.notice').textContent(), /no tiene visor integrado/);
                for (const width of [390, 820, 1440]) {
                    await page.setViewportSize({ width, height: 1000 });
                    await noOverflow(); await capture(`viewer-${mode}-${width}`);
                }
            }
        });
        await login('visual.empty');
        await check('Empty reader listing and institutional 403/404 pages', async () => {
            await go('/expedientes'); assert(await page.locator('.empty-state').isVisible()); await capture('empty-reader');
            for (const [url,status] of [['/users',403], ['/no-such-screen',404]]) {
                const response = await page.goto(base + url); assert.equal(response.status(),status);
                assert.equal(await page.locator('h1').count(), 1);
                for (const width of [390, 820, 1440]) {
                    await page.setViewportSize({ width, height: 1000 });
                    await noOverflow(); await capture(`error-${status}-${width}`);
                }
            }
        });
        assert.deepEqual(errors, []);
        console.log(`${checks.length} browser scenarios passed.`);
    } catch (error) {
        await capture('FAILURE');
        console.error('FAILED', error);
        process.exitCode = 1;
    } finally {
        fs.writeFileSync(path.join(output, 'results.json'), JSON.stringify({ checks, errors, browser: browser.version(), date: new Date().toISOString() }, null, 2));
        await browser.close();
    }
})();
