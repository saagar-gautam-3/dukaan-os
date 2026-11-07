#!/usr/bin/env node
/**
 * Responsive layout check (Task 3, Step 1).
 *
 * Logs in to the demo server and, for every page x viewport width, checks:
 *  (a) no horizontal PAGE scroll (documentElement.scrollWidth <= innerWidth)
 *  (b) no element's right edge exceeds the viewport unless it sits inside an
 *      overflow-x:auto / .table-responsive container
 *  (c) at <= 768px, primary buttons/links have tap-target height >= 40px
 *  (e) no overlap between topbar / page-header action row children
 *  (f) no word broken across lines inside .btn/.badge
 *  (g) no Devanagari digits in visible text (English locale, shop setting off)
 *  (d) saves a screenshot to D:\dukaan-os\qa\out\responsive\<page>-<width>.png
 *
 * Writes summary.md (+ summary-baseline.md when --baseline is passed) and
 * exits non-zero on any failure. Credentials come from DEMO_EMAIL /
 * DEMO_PASSWORD env vars, else from scripts/qa/.env.local (git-excluded).
 * Never prints credentials.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const REPO = path.resolve(__dirname, '../..');
const BASE = process.env.QA_BASE_URL || 'http://127.0.0.1:8000';
const OUT = 'D:\\dukaan-os\\qa\\out\\responsive';
const WIDTHS = [320, 375, 425, 768, 1024, 1440, 2560];
const HEIGHT = 900;

function readDotEnv(file) {
    const vars = {};
    if (!fs.existsSync(file)) return vars;
    for (const line of fs.readFileSync(file, 'utf8').split(/\r?\n/)) {
        const m = line.match(/^\s*(DEMO_EMAIL|DEMO_PASSWORD)\s*=\s*(.+?)\s*$/);
        if (m) vars[m[1]] = m[2].replace(/^["']|["']$/g, '');
    }
    return vars;
}

const envLocal = readDotEnv(path.join(__dirname, '.env.local'));
const EMAIL = process.env.DEMO_EMAIL || envLocal.DEMO_EMAIL || '';
const PASSWORD = process.env.DEMO_PASSWORD || envLocal.DEMO_PASSWORD || '';

if (!EMAIL || !PASSWORD) {
    console.error('Missing credentials: set DEMO_EMAIL/DEMO_PASSWORD or scripts/qa/.env.local');
    process.exit(2);
}

function fail(msg) { console.error(msg); }

async function firstHref(page, listUrl, pattern) {
    await page.goto(listUrl, { waitUntil: 'domcontentloaded' });
    const href = await page.evaluate((pat) => {
        const re = new RegExp(pat);
        for (const a of document.querySelectorAll('a[href]')) {
            if (re.test(a.getAttribute('href') || '')) return a.href;
        }
        return null;
    }, pattern);
    return href;
}

async function main() {
    fs.mkdirSync(OUT, { recursive: true });
    const browser = await chromium.launch();
    const context = await browser.newContext({ viewport: { width: 1440, height: HEIGHT } });
    const page = await context.newPage();

    // --- login ---
    const loginResp = await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
    if ((loginResp?.status() ?? 0) >= 400) {
        fail(`Login page unreachable (HTTP ${loginResp?.status()}). Is the demo server running at ${BASE}?`);
        await browser.close();
        process.exit(2);
    }
    await page.fill('input[name="email"]', EMAIL);
    await page.fill('input[name="password"]', PASSWORD);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 15000 }),
        page.click('button[type="submit"]'),
    ]).catch(() => {});
    await page.goto(`${BASE}/dashboard`, { waitUntil: 'domcontentloaded' });
    if (page.url().includes('/login')) {
        fail('Login failed (still on /login). Check demo credentials.');
        await browser.close();
        process.exit(2);
    }
    // If we landed on the shop picker, click the first shop switch form.
    if (page.url().includes('/shops/picker')) {
        const switched = await page.evaluate(() => {
            const form = document.querySelector('form[action*="/shops/switch/"]');
            if (form) { form.submit(); return true; }
            const a = document.querySelector('a[href*="/shops/switch/"]');
            if (a) { a.click(); return true; }
            return false;
        });
        if (switched) await page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 15000 }).catch(() => {});
        await page.goto(`${BASE}/dashboard`, { waitUntil: 'domcontentloaded' });
    }

    // --- switch to English locale for the digits check ---
    await page.goto(`${BASE}/locale/en`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await page.goto(`${BASE}/dashboard`, { waitUntil: 'domcontentloaded' });
    const activeLocale = await page.evaluate(() => document.documentElement.lang);
    if (!String(activeLocale).startsWith('en')) {
        fail(`Expected English locale after /locale/en, got "${activeLocale}" — digits check would be invalid.`);
        await browser.close();
        process.exit(2);
    }

    // --- discover detail URLs from list pages (first record) ---
    const listUrls = {
        'products-index': `${BASE}/products`,
        'customers-index': `${BASE}/customers`,
        'sales-index': `${BASE}/sales`,
    };
    const productEdit = await firstHref(page, listUrls['products-index'], '/products/[^/"]+/edit');
    const productId = productEdit?.match(/\/products\/([^/]+)\/edit/)?.[1] || '1';
    const customerShow = await firstHref(page, listUrls['customers-index'], '^/customers/[^/"]+$')
        || await firstHref(page, listUrls['customers-index'], '/customers/[^/"]+');
    const customerId = customerShow?.match(/\/customers\/([^/]+)$/)?.[1] || '1';
    const saleShow = await firstHref(page, listUrls['sales-index'], '/sales/[^/"]+$');
    const collectUrl = `${BASE}/udhar/collect/${customerId}`;

    const PAGES = [
        ['dashboard', `${BASE}/dashboard`],
        ['pos', `${BASE}/pos`],
        ['pos-cart', `${BASE}/pos`, { cart: 3 }],
        ['products-index', listUrls['products-index']],
        ['products-form', productEdit || `${BASE}/products/create`],
        ['product-import', `${BASE}/products/import`],
        ['customers-index', listUrls['customers-index']],
        ['customers-create', `${BASE}/customers/create`],
        ['customer-show', customerShow || `${BASE}/customers/1`],
        ['udhar-collect', collectUrl],
        ['udhar-outstanding', `${BASE}/udhar`],
        ['udhar-ageing', `${BASE}/udhar/ageing`],
        ['sales-index', listUrls['sales-index']],
        ['sales-show', saleShow || `${BASE}/sales/1`],
        ['purchases-index', `${BASE}/purchases`],
        ['stock', `${BASE}/stock`],
        ['expenses', `${BASE}/expenses`],
        ['reports-index', `${BASE}/reports/profit`],
        ['staff-index', `${BASE}/staff`],
        ['shop-settings', `${BASE}/settings/shop`],
        ['shop-picker', `${BASE}/shops/picker`],
    ];

    const results = []; // {page, width, pageScroll, overflow: [...], tap: ...}

    for (const [name, url, opts] of PAGES) {
        // Pre-seed POS cart with >=3 items (persisted via sessionStorage).
        if (opts?.cart) {
            await page.goto(`${BASE}/pos`, { waitUntil: 'domcontentloaded', timeout: 20000 });
            await page.waitForTimeout(400);
            for (let i = 0; i < opts.cart; i++) {
                const added = await page.evaluate((idx) => {
                    const cards = document.querySelectorAll('#productGrid .product-item-card');
                    if (!cards.length) return false;
                    cards[Math.min(idx, cards.length - 1)].click();
                    return true;
                }, i).catch(() => false);
                if (!added) break;
                await page.waitForTimeout(150);
            }
        }
        for (const width of WIDTHS) {
            await page.setViewportSize({ width, height: HEIGHT });
            let status = 0;
            try {
                const resp = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 20000 });
                status = resp?.status() ?? 0;
                await page.waitForTimeout(150);
            } catch (e) {
                results.push({ page: name, width, error: `navigation: ${String(e).split('\n')[0]}` });
                continue;
            }

            const metrics = await page.evaluate(() => {
                const vw = window.innerWidth;
                const scrollWidth = document.documentElement.scrollWidth;
                const over = [];
                const visible = (el) => {
                    const r = el.getBoundingClientRect();
                    return r.width > 0 && r.height > 0 && el.offsetParent !== null;
                };
                const insideScrollable = (el) => {
                    let node = el;
                    while (node && node !== document.body) {
                        const cs = getComputedStyle(node);
                        if (cs.overflowX === 'auto' || cs.overflowX === 'scroll') return true;
                        if (node.classList && node.classList.contains('table-responsive')) return true;
                        node = node.parentElement;
                    }
                    return false;
                };
                for (const el of document.querySelectorAll('*')) {
                    if (!visible(el)) continue;
                    const r = el.getBoundingClientRect();
                    // allow 1px rounding
                    if (r.right > vw + 1 && !insideScrollable(el)) {
                        const tag = el.tagName.toLowerCase();
                        const cls = (el.className && typeof el.className === 'string' ? el.className : '').split(/\s+/).slice(0, 3).join('.');
                        over.push(`${tag}.${cls} right=${Math.round(r.right)} vw=${vw}`);
                        if (over.length >= 12) break;
                    }
                }
                // (c) tap targets: primary buttons/links at <=768
                const tap = [];
                if (vw <= 768) {
                    const sel = 'a.btn, button.btn, input.btn, .btn, .nav-link';
                    for (const el of document.querySelectorAll(sel)) {
                        if (!visible(el)) continue;
                        const r = el.getBoundingClientRect();
                        if (r.height < 40) {
                            const cls = (el.className && typeof el.className === 'string' ? el.className : '').split(/\s+/).slice(0, 3).join('.');
                            tap.push(`${el.tagName.toLowerCase()}.${cls} h=${Math.round(r.height)}`);
                            if (tap.length >= 12) break;
                        }
                    }
                }
                // (a) overlap: topbar flex items + page-header action rows
                const overlaps = [];
                const intersect = (r1, r2) => {
                    const w = Math.min(r1.right, r2.right) - Math.max(r1.left, r2.left);
                    const h = Math.min(r1.bottom, r2.bottom) - Math.max(r1.top, r2.top);
                    return w > 2 && h > 2;
                };
                const textOf = (el) => (el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 40);
                const topbar = document.querySelector('.app-topbar');
                const rows = [];
                if (topbar) rows.push({ label: 'topbar', el: topbar });
                for (const el of document.querySelectorAll('.app-page .d-flex')) {
                    if (el.querySelector('.btn, a.btn, button.btn') && el.closest('.app-page')) {
                        rows.push({ label: 'action-row', el });
                    }
                }
                for (const { label, el } of rows) {
                    const items = [...el.children].filter(visible);
                    for (let i = 0; i < items.length; i++) {
                        for (let j = i + 1; j < items.length; j++) {
                            if (intersect(items[i].getBoundingClientRect(), items[j].getBoundingClientRect())) {
                                overlaps.push(`${label}: "${textOf(items[i])}" x "${textOf(items[j])}"`);
                                if (overlaps.length >= 8) break;
                            }
                        }
                        if (overlaps.length >= 8) break;
                    }
                    if (overlaps.length >= 8) break;
                }
                // (b) word-break inside .btn/.badge (a word split across lines)
                // Skips ellipsis-truncated text (text-overflow: ellipsis) — a
                // clipped word is not a wrapped word.
                const wordBreaks = [];
                for (const el of document.querySelectorAll('.btn, .badge')) {
                    if (!visible(el)) continue;
                    const cs = getComputedStyle(el);
                    if (cs.textOverflow === 'ellipsis' && el.scrollWidth > el.clientWidth) continue;
                    for (const node of el.childNodes) {
                        if (node.nodeType !== Node.TEXT_NODE) continue;
                        const text = node.textContent || '';
                        let offset = 0;
                        for (const word of text.split(/\s+/)) {
                            const at = text.indexOf(word, offset);
                            if (word.length < 3 || at < 0) { offset = at + word.length; continue; }
                            const range = document.createRange();
                            range.setStart(node, at);
                            range.setEnd(node, at + word.length);
                            if (range.getClientRects().length > 1) {
                                wordBreaks.push(`${el.tagName.toLowerCase()}.${(el.className || '').split(/\s+/).slice(0, 2).join('.')}: "${word}"`);
                                break;
                            }
                            offset = at + word.length;
                        }
                        if (wordBreaks.length >= 8) break;
                    }
                    if (wordBreaks.length >= 8) break;
                }
                // (c) Devanagari digits in visible text (en locale, setting off)
                const devDigits = [];
                const seen = document.body ? document.body.innerText || '' : '';
                const matches = seen.match(/[०-९]+/g) || [];
                for (const m of [...new Set(matches)].slice(0, 5)) devDigits.push(m);
                return { vw, scrollWidth, over, tap, overlaps, wordBreaks, devDigits };
            });

            const pageScroll = metrics.scrollWidth > metrics.vw;
            results.push({
                page: name, width, status,
                pageScroll, scrollWidth: metrics.scrollWidth, vw: metrics.vw,
                overflow: metrics.over, tap: metrics.tap,
                overlaps: metrics.overlaps ?? [], wordBreaks: metrics.wordBreaks ?? [],
                devDigits: metrics.devDigits ?? [],
                url,
            });

            await page.screenshot({ path: path.join(OUT, `${name}-${width}.png`), fullPage: false }).catch(() => {});
        }
    }

    await browser.close();

    // --- summary ---
    const fails = results.filter((r) => r.error || r.pageScroll || r.overflow.length > 0 || r.tap.length > 0
        || r.overlaps.length > 0 || r.wordBreaks.length > 0 || r.devDigits.length > 0);
    const lines = [];
    lines.push('# Responsive check summary');
    lines.push('');
    lines.push(`Base URL: ${BASE}`);
    lines.push(`Generated: ${new Date().toISOString()}`);
    lines.push('');
    lines.push('| Page | Width | HTTP | Page scroll-x | Off-viewport elements | Tap targets <40px (≤768) | Overlaps | Word-breaks | Dev digits |');
    lines.push('|------|-------|------|---------------|------------------------|---------------------------|----------|-------------|----------|');
    for (const r of results) {
        const scroll = r.error ? 'ERR' : r.pageScroll ? `YES (scrollWidth=${r.scrollWidth})` : 'no';
        lines.push(`| ${r.page} | ${r.width} | ${r.status ?? '-'} | ${scroll} | ${r.overflow?.length ?? r.error ?? 0} | ${r.tap?.length ?? 0} | ${r.overlaps?.length ?? 0} | ${r.wordBreaks?.length ?? 0} | ${r.devDigits?.length ?? 0} |`);
    }
    lines.push('');
    lines.push('## Off-viewport elements (detail)');
    let any = false;
    for (const r of results) {
        if (r.overflow?.length) { any = true; lines.push(`### ${r.page} @ ${r.width}`); for (const o of r.overflow) lines.push(`- ${o}`); }
    }
    if (!any) lines.push('None.');
    lines.push('');
    lines.push('## Tap targets <40px (detail)');
    any = false;
    for (const r of results) {
        if (r.tap?.length) { any = true; lines.push(`### ${r.page} @ ${r.width}`); for (const t of r.tap) lines.push(`- ${t}`); }
    }
    if (!any) lines.push('None.');
    lines.push('');
    lines.push('## Overlaps (detail)');
    any = false;
    for (const r of results) {
        if (r.overlaps?.length) { any = true; lines.push(`### ${r.page} @ ${r.width}`); for (const o of r.overlaps) lines.push(`- ${o}`); }
    }
    if (!any) lines.push('None.');
    lines.push('');
    lines.push('## Word-breaks in buttons/badges (detail)');
    any = false;
    for (const r of results) {
        if (r.wordBreaks?.length) { any = true; lines.push(`### ${r.page} @ ${r.width}`); for (const o of r.wordBreaks) lines.push(`- ${o}`); }
    }
    if (!any) lines.push('None.');
    lines.push('');
    lines.push('## Devanagari digits (en locale, setting off) (detail)');
    any = false;
    for (const r of results) {
        if (r.devDigits?.length) { any = true; lines.push(`### ${r.page} @ ${r.width}: ${r.devDigits.join(', ')}`); }
    }
    if (!any) lines.push('None.');
    lines.push('');
    lines.push(`## Counts`);
    const byWidth = {};
    for (const w of WIDTHS) {
        const rs = results.filter((r) => r.width === w);
        byWidth[w] = {
            scroll: rs.filter((r) => r.pageScroll).length,
            overflow: rs.filter((r) => r.overflow?.length).length,
            tap: rs.filter((r) => r.tap?.length).length,
            overlap: rs.filter((r) => r.overlaps?.length).length,
            word: rs.filter((r) => r.wordBreaks?.length).length,
            digits: rs.filter((r) => r.devDigits?.length).length,
            errors: rs.filter((r) => r.error).length,
        };
    }
    for (const w of WIDTHS) {
        lines.push(`- ${w}px: page-scroll=${byWidth[w].scroll} overflow-pages=${byWidth[w].overflow} tap-pages=${byWidth[w].tap} overlap-pages=${byWidth[w].overlap} word-pages=${byWidth[w].word} digit-pages=${byWidth[w].digits} errors=${byWidth[w].errors}`);
    }
    lines.push(`- **Total failures:** ${fails.length} / ${results.length} checks`);
    lines.push('');

    const md = lines.join('\n');
    process.argv.includes('--baseline')
        ? fs.writeFileSync(path.join(OUT, 'summary-baseline.md'), md)
        : fs.writeFileSync(path.join(OUT, 'summary.md'), md);
    if (process.argv.includes('--baseline')) fs.writeFileSync(path.join(OUT, 'summary.md'), md);

    console.log(md);
    console.log(fails.length > 0 ? `FAILED: ${fails.length} checks` : 'ALL PASSED');

    process.exit(fails.length > 0 ? 1 : 0);
}

main().catch((e) => { fail(String(e)); process.exit(2); });
