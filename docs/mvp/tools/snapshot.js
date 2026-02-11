/*
 * AFV visual check — Node half of snapshot.php (do not run directly; use the PHP script).
 *
 * Opens http://127.0.0.1:8123/<url> in headless Chromium with every request intercepted:
 * files under public/ come from disk, app routes are rendered by a PHP worker (snapshot.php --worker).
 * Writes <out>.html, <out>-desktop.png (1440×900) and <out>-mobile.png (390×844).
 */

const fs = require('fs');
const path = require('path');
const readline = require('readline');
const { spawn } = require('child_process');

const config = JSON.parse(process.argv[2]);
const { chromium } = require(config.playwright);

const ORIGIN = 'http://127.0.0.1:8123';
const PUBLIC = path.join(config.root, 'public');

const MIME = {
    '.css': 'text/css', '.js': 'application/javascript', '.mjs': 'application/javascript', '.json': 'application/json',
    '.map': 'application/json', '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.gif': 'image/gif',
    '.svg': 'image/svg+xml', '.webp': 'image/webp', '.ico': 'image/x-icon', '.woff': 'font/woff', '.woff2': 'font/woff2',
    '.ttf': 'font/ttf', '.otf': 'font/otf', '.eot': 'application/vnd.ms-fontobject', '.txt': 'text/plain', '.html': 'text/html',
};

// ---- PHP worker -------------------------------------------------------------------------------
const workerArgs = [config.worker, '--worker'];
if (config.email) workerArgs.push('--email=' + config.email);
if (config.dark) workerArgs.push('--dark');

const php = spawn(config.php, ['-d', 'memory_limit=1G', ...workerArgs], { cwd: config.root, stdio: ['pipe', 'pipe', 'pipe'] });
const pending = new Map();
let nextId = 1;
let ready;
const readyPromise = new Promise((resolve, reject) => { ready = { resolve, reject }; });
const phpErrors = [];

readline.createInterface({ input: php.stdout }).on('line', (line) => {
    let msg;
    try { msg = JSON.parse(line); } catch (e) { return; }
    if (msg.ready) { ready.resolve(msg); return; }
    const job = pending.get(msg.id);
    if (job) { pending.delete(msg.id); job(msg); }
});

php.stderr.on('data', (d) => {
    const text = d.toString();
    text.split('\n').filter((l) => l.startsWith('[php]')).forEach((l) => phpErrors.push(l));
    if (/Refusing|User not found/.test(text)) process.stderr.write(text);
});

php.on('exit', (code) => {
    if (code) ready.reject(new Error('PHP worker exited with code ' + code));
});

function render(method, url, headers, body) {
    return new Promise((resolve) => {
        const id = nextId++;
        pending.set(id, resolve);
        php.stdin.write(JSON.stringify({ id, method, url, headers, body: body ? body.toString('base64') : null }) + '\n');
    });
}

// ---- Browser ----------------------------------------------------------------------------------
async function route(page, savedHtml) {
    await page.route('**/*', async (r) => {
        const req = r.request();
        const u = new URL(req.url());

        if (u.origin === 'http://localhost') return r.continue({ url: ORIGIN + u.pathname + u.search });
        if (u.origin !== ORIGIN) {
            if (config.offline || u.protocol === 'file:') return r.abort();
            return r.continue();
        }

        const file = path.join(PUBLIC, decodeURIComponent(u.pathname));
        if (file.startsWith(PUBLIC) && u.pathname !== '/' && fs.existsSync(file) && fs.statSync(file).isFile() && !file.endsWith('.php')) {
            return r.fulfill({
                status: 200,
                headers: { 'content-type': MIME[path.extname(file).toLowerCase()] || 'application/octet-stream' },
                body: fs.readFileSync(file),
            });
        }

        const headers = req.headers();
        const res = await render(req.method(), u.pathname + u.search, headers, req.postDataBuffer());
        const body = Buffer.from(res.body, 'base64');

        if (req.isNavigationRequest() && req.frame() === page.mainFrame() && savedHtml.value === null) {
            savedHtml.value = body.toString();
            savedHtml.status = res.status;
        }

        const out = {};
        for (const [k, v] of Object.entries(res.headers || {})) {
            if (!/^(content-length|transfer-encoding|content-encoding|set-cookie)$/i.test(k)) out[k] = v;
        }
        return r.fulfill({ status: res.status, headers: out, body });
    });
}

async function shoot(browser, name, viewport, isMobile) {
    const context = await browser.newContext({
        viewport,
        deviceScaleFactor: isMobile ? 2 : 1,
        isMobile,
        hasTouch: isMobile,
        locale: 'bg-BG',
        timezoneId: 'Europe/Sofia',
    });
    const page = await context.newPage();
    const savedHtml = { value: null, status: null };
    const consoleErrors = [];

    page.on('pageerror', (e) => consoleErrors.push('pageerror: ' + e.message));
    page.on('console', (m) => { if (m.type() === 'error') consoleErrors.push('console: ' + m.text()); });

    await route(page, savedHtml);

    try {
        await page.goto(ORIGIN + config.url, { waitUntil: 'load', timeout: 60000 });
        await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
    } catch (e) {
        consoleErrors.push('navigation: ' + e.message);
    }

    // Uncompiled SCSS partials (--scss=) are compiled with the local sass and injected after main.css.
    if (extraCss) await page.addStyleTag({ content: extraCss }).catch(() => {});

    // Hide the preloader overlay (it fades out on window.load) and caret blinking.
    await page.addStyleTag({ content: '.preloader-container{display:none!important} *{caret-color:transparent!important}' }).catch(() => {});
    await page.waitForTimeout(config.wait);

    // Headless Chromium may skip animation frames: finish every Chart.js animation and redraw.
    const redrawCharts = () => page.evaluate(() => {
        if (window.Chart && window.Chart.instances) {
            Object.values(window.Chart.instances).forEach((c) => { try { c.stop(); c.update('none'); } catch (e) {} });
        }
    }).catch(() => {});
    await redrawCharts();

    const file = `${config.out}-${name}.png`;
    if (config.fullPage) {
        // Grow the viewport to the page height so fixed elements (sidebar) span the whole shot.
        const height = await page.evaluate(() => Math.max(document.documentElement.scrollHeight, document.body ? document.body.scrollHeight : 0));
        const full = Math.min(Math.max(height, viewport.height), 6000);
        if (full > viewport.height) {
            await page.setViewportSize({ width: viewport.width, height: full });
            await page.waitForTimeout(400);
            await redrawCharts();
        }
    }
    await page.screenshot({ path: file, animations: 'disabled' });

    const finalUrl = page.url().replace(ORIGIN, '');
    await context.close();

    return { file, html: savedHtml, consoleErrors, finalUrl };
}

let extraCss = '';

function compileScss() {
    const { execFileSync } = require('child_process');
    const sassBin = path.join(config.root, 'node_modules', '.bin', 'sass');
    for (const file of config.scss || []) {
        try {
            extraCss += execFileSync(sassBin, ['--no-source-map', '--load-path=' + path.join(config.root, 'resources/scss'), path.resolve(config.root, file)], { encoding: 'utf8' }) + '\n';
        } catch (e) {
            console.log('SCSS error in ' + file + ':\n' + (e.stderr || e.message));
            process.exit(1);
        }
    }
}

(async () => {
    compileScss();
    const who = await readyPromise;
    const browser = await chromium.launch({ headless: true });
    const results = [];

    if (config.only !== 'mobile') results.push(await shoot(browser, 'desktop', { width: 1440, height: 900 }, false));
    if (config.only !== 'desktop') results.push(await shoot(browser, 'mobile', { width: 390, height: 844 }, true));

    await browser.close();
    php.stdin.end();
    php.kill();

    const html = results.find((r) => r.html.value !== null);
    if (html) {
        const base = `<base href="file://${PUBLIC}/">`;
        const doc = html.html.value
            .replace(/<head([^>]*)>/i, `<head$1>${base}`)
            .split(ORIGIN + '/').join('');
        fs.writeFileSync(`${config.out}.html`, doc);
    }

    console.log(`User: ${who.user} | ${config.url} → HTTP ${html ? html.html.status : '?'}${results[0] && results[0].finalUrl !== config.url ? ' (ended at ' + results[0].finalUrl + ')' : ''}`);
    for (const r of results) console.log('PNG   ' + r.file);
    if (html) console.log('HTML  ' + config.out + '.html');

    const errors = [...new Set([...phpErrors, ...results.flatMap((r) => r.consoleErrors)])];
    if (errors.length) {
        console.log('\nErrors:');
        errors.slice(0, 30).forEach((e) => console.log('  - ' + e.slice(0, 400)));
    }
    process.exit(0);
})().catch((e) => {
    console.error(e);
    php.kill();
    process.exit(1);
});
