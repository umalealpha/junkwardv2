/**
 * Graphite PDF Service
 * Standalone HTML→PDF microservice backed by headless Chromium (Puppeteer).
 *
 * Endpoints:
 *   GET  /health              - liveness check
 *   POST /render              - render HTML or URL to PDF
 *     body: { html?: string, url?: string, options?: PdfOptions, waitUntil?: string }
 *     returns: application/pdf binary
 *
 * Auth: shared-secret via `X-PDF-API-KEY` header (must match PDF_API_KEY env var).
 *
 * Resource model: single Chromium instance per container, serial request queue.
 * Scale horizontally via ECS desired count.
 */

const express = require('express');
const compression = require('compression');
const morgan = require('morgan');
const puppeteer = require('puppeteer-core');

const PORT = parseInt(process.env.PORT || '3000', 10);
const API_KEY = process.env.PDF_API_KEY || '';
const CHROME_PATH = process.env.CHROME_PATH || '/usr/bin/chromium';
const MAX_RENDER_MS = parseInt(process.env.MAX_RENDER_MS || '120000', 10); // 2 min
const MAX_CONCURRENT = parseInt(process.env.MAX_CONCURRENT || '2', 10);

let browser = null;
let browserLaunching = null;
let activeRequests = 0;
let totalRequests = 0;
let totalErrors = 0;
const queue = [];

/** Launch (or re-launch) shared Chromium browser. Singleton. */
async function getBrowser() {
  if (browser && browser.isConnected()) return browser;
  if (browserLaunching) return browserLaunching;

  browserLaunching = puppeteer.launch({
    executablePath: CHROME_PATH,
    headless: 'new',
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-dev-shm-usage',
      '--disable-gpu',
      '--disable-software-rasterizer',
      '--disable-background-timer-throttling',
      '--disable-backgrounding-occluded-windows',
      '--disable-renderer-backgrounding',
      '--no-first-run',
      '--disable-extensions',
      '--mute-audio',
      '--hide-scrollbars',
      '--font-render-hinting=none',
    ],
  }).then(b => {
    console.log('[pdf-service] Chromium launched');
    b.on('disconnected', () => {
      console.warn('[pdf-service] Chromium disconnected');
      browser = null;
    });
    browser = b;
    browserLaunching = null;
    return b;
  }).catch(err => {
    browserLaunching = null;
    throw err;
  });

  return browserLaunching;
}

/** Simple serial queue with concurrency cap. */
function runQueued(task) {
  return new Promise((resolve, reject) => {
    queue.push({ task, resolve, reject });
    drainQueue();
  });
}

function drainQueue() {
  while (queue.length > 0 && activeRequests < MAX_CONCURRENT) {
    const { task, resolve, reject } = queue.shift();
    activeRequests++;
    task()
      .then(resolve, reject)
      .finally(() => {
        activeRequests--;
        drainQueue();
      });
  }
}

async function renderPdf({ html, url, options = {}, waitUntil = 'networkidle0' }) {
  const b = await getBrowser();
  const page = await b.newPage();
  try {
    // Default viewport matches roughly A4 @ 96dpi
    await page.setViewport({ width: 1240, height: 1754, deviceScaleFactor: 1 });

    const timeout = Math.min(MAX_RENDER_MS, options.timeout || MAX_RENDER_MS);

    if (html) {
      await page.setContent(html, { waitUntil, timeout });
    } else if (url) {
      await page.goto(url, { waitUntil, timeout });
    } else {
      throw new Error('Either `html` or `url` is required');
    }

    // Default PDF options; caller can override
    const pdfOptions = {
      format: options.format || 'A4',
      landscape: options.landscape === true,
      printBackground: options.printBackground !== false, // default true
      margin: options.margin || { top: '10mm', right: '10mm', bottom: '10mm', left: '10mm' },
      preferCSSPageSize: options.preferCSSPageSize === true,
      displayHeaderFooter: options.displayHeaderFooter === true,
      headerTemplate: options.headerTemplate || '<div></div>',
      footerTemplate: options.footerTemplate || '<div></div>',
      scale: options.scale || 1,
      timeout,
    };

    const buf = await page.pdf(pdfOptions);
    return buf;
  } finally {
    try { await page.close(); } catch (e) { /* ignore */ }
  }
}

// ─── HTTP server ──────────────────────────────────────────────────────────────

const app = express();
app.use(morgan('combined'));
app.use(compression());
app.use(express.json({ limit: '50mb' }));

// API key middleware (skips /health)
app.use((req, res, next) => {
  if (req.path === '/health' || req.path === '/') return next();
  if (!API_KEY) return next(); // if unset, allow (dev only)
  const provided = req.header('X-PDF-API-KEY') || req.query.key;
  if (provided !== API_KEY) {
    return res.status(401).json({ error: 'Invalid or missing API key' });
  }
  next();
});

app.get('/', (_req, res) => {
  res.json({ service: 'graphite-pdf-service', version: '1.0.0' });
});

app.get('/health', (_req, res) => {
  res.json({
    ok: true,
    browser: !!browser && browser.isConnected(),
    active: activeRequests,
    queued: queue.length,
    totalRequests,
    totalErrors,
    uptimeSec: Math.round(process.uptime()),
    memoryMb: Math.round(process.memoryUsage().rss / 1024 / 1024),
  });
});

app.post('/render', async (req, res) => {
  totalRequests++;
  const start = Date.now();
  try {
    const { html, url, options, waitUntil } = req.body || {};
    if (!html && !url) {
      return res.status(400).json({ error: 'Provide `html` or `url` in JSON body' });
    }

    const pdfBuf = await runQueued(() => renderPdf({ html, url, options, waitUntil }));

    const filename = (options && options.filename) || 'document.pdf';
    res.set({
      'Content-Type': 'application/pdf',
      'Content-Length': pdfBuf.length,
      'Content-Disposition': `inline; filename="${filename.replace(/[^a-zA-Z0-9._-]/g, '_')}"`,
      'X-Render-Ms': String(Date.now() - start),
    });
    res.send(pdfBuf);
  } catch (err) {
    totalErrors++;
    console.error('[pdf-service] render error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// Warm Chromium on startup
getBrowser().catch(err => console.error('[pdf-service] initial launch failed:', err.message));

const server = app.listen(PORT, '0.0.0.0', () => {
  console.log(`[pdf-service] listening on :${PORT} (api key ${API_KEY ? 'enabled' : 'DISABLED - dev mode'})`);
});

// Graceful shutdown
async function shutdown(sig) {
  console.log(`[pdf-service] ${sig} received, shutting down`);
  server.close();
  try { if (browser) await browser.close(); } catch (e) { /* ignore */ }
  process.exit(0);
}
process.on('SIGTERM', () => shutdown('SIGTERM'));
process.on('SIGINT', () => shutdown('SIGINT'));
