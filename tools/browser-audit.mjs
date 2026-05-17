#!/usr/bin/env node

import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { chromium } from 'playwright';

const argv = process.argv.slice(2);
const DEFAULT_URL = 'http://slaed.loc/';
const DEFAULT_CDP_URL = 'http://127.0.0.1:9222';
const RELEVANT_RESOURCE_TYPES = new Set(['document', 'stylesheet', 'script', 'xhr', 'fetch', 'font']);

function parseArgs(args) {
  const options = {
    url: process.env.BROWSER_AUDIT_URL || '',
    browser: 'chromium',
    headful: false,
    inspector: false,
    attach: false,
    current: false,
    pageUrl: '',
    pageTitle: '',
    cdpUrl: process.env.BROWSER_AUDIT_CDP_URL || DEFAULT_CDP_URL,
    timeoutMs: 15000,
    waitMs: 2000,
  };

  for (let i = 0; i < args.length; i += 1) {
    const arg = args[i];

    if (arg === '--url' && args[i + 1]) {
      options.url = args[++i];
      continue;
    }

    if (arg === '--browser' && args[i + 1]) {
      options.browser = args[++i].toLowerCase();
      continue;
    }

    if (arg === '--cdp-url' && args[i + 1]) {
      options.cdpUrl = args[++i];
      continue;
    }

    if (arg === '--port' && args[i + 1]) {
      const port = Number(args[++i]);
      if (!Number.isInteger(port) || port <= 0) {
        throw new Error('Invalid --port value');
      }
      options.cdpUrl = `http://127.0.0.1:${port}`;
      continue;
    }

    if (arg === '--timeout' && args[i + 1]) {
      options.timeoutMs = Number(args[++i]);
      continue;
    }

    if (arg === '--wait' && args[i + 1]) {
      options.waitMs = Number(args[++i]);
      continue;
    }

    if (arg === '--headful') {
      options.headful = true;
      continue;
    }

    if (arg === '--headless') {
      options.headful = false;
      continue;
    }

    if (arg === '--inspect' || arg === '--inspector') {
      options.inspector = true;
      continue;
    }

    if (arg === '--attach') {
      options.attach = true;
      continue;
    }

    if (arg === '--current' || arg === '--no-goto') {
      options.current = true;
      continue;
    }

    if (arg === '--page-url' && args[i + 1]) {
      options.pageUrl = args[++i];
      continue;
    }

    if (arg === '--page-title' && args[i + 1]) {
      options.pageTitle = args[++i];
      continue;
    }

    if (!arg.startsWith('--') && options.url === '') {
      options.url = arg;
    }
  }

  return options;
}

function exists(filePath) {
  try {
    return Boolean(filePath) && fs.existsSync(filePath);
  } catch {
    return false;
  }
}

function pickFirst(paths) {
  return paths.find((item) => exists(item)) || '';
}

function findChromiumExe() {
  const envCandidates = [
    process.env.BROWSER_PATH,
    process.env.PLAYWRIGHT_CHROMIUM_PATH,
    process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH,
  ].filter(Boolean);

  const home = os.homedir();
  const playwrightBase = path.join(home, 'AppData', 'Local', 'ms-playwright');
  const localCandidates = [];

  if (exists(playwrightBase)) {
    for (const entry of fs.readdirSync(playwrightBase, { withFileTypes: true })) {
      if (!entry.isDirectory() || !entry.name.startsWith('chromium-')) continue;
      localCandidates.push(
        path.join(playwrightBase, entry.name, 'chrome-win64', 'chrome.exe'),
        path.join(playwrightBase, entry.name, 'chrome-win', 'chrome.exe'),
      );
    }
  }

  const systemCandidates = [
    'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
    'C:\\Program Files\\BraveSoftware\\Brave-Browser\\Application\\brave.exe',
    'C:\\Program Files (x86)\\BraveSoftware\\Brave-Browser\\Application\\brave.exe',
  ];

  return pickFirst([...envCandidates, ...localCandidates, ...systemCandidates]);
}

function chooseExecutable(browserName) {
  if (!['chromium', 'chrome', 'edge', 'brave'].includes(browserName)) {
    throw new Error(`Unsupported browser "${browserName}". Use chromium, chrome, edge, or brave.`);
  }

  const executablePath =
    browserName === 'chrome' || browserName === 'chromium'
      ? findChromiumExe()
      : pickFirst(
          browserName === 'edge'
            ? [
                'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
                'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
              ]
            : [
                'C:\\Program Files\\BraveSoftware\\Brave-Browser\\Application\\brave.exe',
                'C:\\Program Files (x86)\\BraveSoftware\\Brave-Browser\\Application\\brave.exe',
              ],
        );

  if (!executablePath) {
    throw new Error(
      'No Chromium-compatible browser executable found. Set BROWSER_PATH or install Chromium/Edge/Brave.',
    );
  }

  return executablePath;
}

function formatLocation(location) {
  if (!location || (!location.url && !location.lineNumber && !location.columnNumber)) return '';
  const parts = [];
  if (location.url) parts.push(location.url);
  if (location.lineNumber != null) {
    parts.push(`${location.lineNumber + 1}:${(location.columnNumber ?? 0) + 1}`);
  }
  return parts.length ? ` (${parts.join(':')})` : '';
}

function summarizeResponseStatus(status) {
  if (status >= 500) return 'error';
  if (status >= 400) return 'warning';
  return 'info';
}

function createFindingSink(findings) {
  const seen = new Set();

  return function addFinding(message) {
    if (seen.has(message)) return;
    seen.add(message);
    findings.push(message);
  };
}

function isMatchingPage(pageUrl, pageTitle, selector) {
  if (!selector) return false;
  const needle = selector.toLowerCase();
  return pageUrl.toLowerCase().includes(needle) || pageTitle.toLowerCase().includes(needle);
}

async function resolveBrowser(options, executablePath) {
  if (options.attach) {
    return chromium.connectOverCDP(options.cdpUrl);
  }

  return chromium.launch({
    executablePath,
    headless: !options.headful,
  });
}

async function resolveContext(browser) {
  return browser.contexts()[0] || browser.newContext();
}

async function resolvePage(context, options, targetUrl) {
  const pages = context.pages();

  if (!options.attach) {
    return context.newPage();
  }

  const pageInfo = await Promise.all(
    pages.map(async (page) => ({
      page,
      url: page.url(),
      title: await page.title().catch(() => ''),
    })),
  );

  const selectors = [options.pageUrl, options.pageTitle].filter(Boolean);
  for (const selector of selectors) {
    const matched = pageInfo.find(({ url, title }) => isMatchingPage(url, title, selector));
    if (matched) return matched.page;
  }

  if (options.current || !options.url) {
    return pages[0] || context.newPage();
  }

  const exact = pageInfo.find(({ url }) => url === targetUrl);
  if (exact) return exact.page;

  const contains = pageInfo.find(({ url }) => url.includes(targetUrl));
  return contains?.page || context.newPage();
}

async function wirePage(page, options, addFinding, consoleMessages) {
  page.on('console', (message) => {
    const type = message.type();
    if (type !== 'error' && type !== 'warning') return;

    const text = message.text();
    const location = message.location();
    if (type === 'error' && (location?.url?.includes('favicon.ico') || /favicon\.ico/i.test(text))) return;

    const entry = {
      type,
      text,
      location: formatLocation(location),
    };

    consoleMessages.push(entry);
    if (type === 'error') {
      addFinding(`console error${entry.location}: ${entry.text}`);
    }
  });

  page.on('pageerror', (error) => {
    addFinding(`page error: ${error.message}`);
  });

  page.on('requestfailed', (request) => {
    if (!RELEVANT_RESOURCE_TYPES.has(request.resourceType())) return;
    const failure = request.failure();
    addFinding(
      `request failed: ${request.resourceType()} ${request.method()} ${request.url()}${failure?.errorText ? ` (${failure.errorText})` : ''}`,
    );
  });

  page.on('response', (response) => {
    const status = response.status();
    if (status < 400) return;

    const request = response.request();
    if (!RELEVANT_RESOURCE_TYPES.has(request.resourceType())) return;
    const severity = summarizeResponseStatus(status);
    addFinding(
      `${severity} response: ${request.resourceType()} ${request.method()} ${status} ${response.url()}`,
    );
  });

  if (!options.inspector) return;

  const cdp = await context.newCDPSession(page);
  await Promise.all([cdp.send('Log.enable'), cdp.send('Runtime.enable'), cdp.send('Network.enable')]);

  cdp.on('Log.entryAdded', ({ entry }) => {
    if (!entry || !entry.level || !['warning', 'error'].includes(entry.level)) return;
    if (entry.url?.includes('favicon.ico')) return;

    const source = entry.source ? `${entry.source} ` : '';
    const location =
      entry.lineNumber != null
        ? ` (${entry.url ?? 'unknown'}:${entry.lineNumber + 1}:${(entry.columnNumber ?? 0) + 1})`
        : '';
    addFinding(`cdp ${source}${entry.level}${location}: ${entry.text}`);
  });

  cdp.on('Runtime.exceptionThrown', ({ exceptionDetails }) => {
    const text =
      exceptionDetails?.exception?.description ||
      exceptionDetails?.text ||
      'Unspecified runtime exception';
    const url = exceptionDetails?.url
      ? ` (${exceptionDetails.url}:${(exceptionDetails.lineNumber ?? 0) + 1}:${(exceptionDetails.columnNumber ?? 0) + 1})`
      : '';
    addFinding(`cdp runtime exception${url}: ${text}`);
  });

  cdp.on('Network.loadingFailed', (event) => {
    if (!RELEVANT_RESOURCE_TYPES.has(event.type)) return;
    addFinding(
      `cdp load failed: ${event.type} ${event.requestId}${event.errorText ? ` (${event.errorText})` : ''}`,
    );
  });
}

async function main() {
  const options = parseArgs(argv);
  const targetUrl = options.url || DEFAULT_URL;
  const executablePath = options.attach ? '' : chooseExecutable(options.browser);
  const findings = [];
  const consoleMessages = [];
  const addFinding = createFindingSink(findings);
  let browser;

  console.log(`[browser-audit] mode=${options.attach ? 'attach' : 'launch'}`);
  console.log(`[browser-audit] url=${options.attach && options.current && !options.url ? '(current page)' : targetUrl}`);
  if (options.attach) {
    console.log(`[browser-audit] cdp-url=${options.cdpUrl}`);
  } else {
    console.log(`[browser-audit] executable=${executablePath}`);
  }
  console.log(`[browser-audit] inspector=${options.inspector ? 'on' : 'off'}`);

  try {
    browser = await resolveBrowser(options, executablePath);
    const context = await resolveContext(browser);
    const page = await resolvePage(context, options, targetUrl);
    console.log(`[browser-audit] page=${page.url() || '(new page)'}`);

    await wirePage(page, options, addFinding, consoleMessages);

    if (!(options.attach && options.current && !options.url)) {
      await page.goto(targetUrl, {
        waitUntil: 'domcontentloaded',
        timeout: options.timeoutMs,
      });
    }

    await page.waitForTimeout(Math.max(0, options.waitMs));

    console.log(`[browser-audit] console warnings/errors=${consoleMessages.length}`);
    for (const message of consoleMessages) {
      console.log(`[browser-audit] ${message.type}${message.location}: ${message.text}`);
    }

    if (findings.length === 0) {
      console.log('[browser-audit] no issues found');
      return;
    }

    console.log('[browser-audit] issues found:');
    for (const finding of findings) {
      console.log(`[browser-audit] - ${finding}`);
    }
    process.exitCode = 1;
  } catch (error) {
    addFinding(`navigation error: ${error.message}`);
    console.log('[browser-audit] issues found:');
    for (const finding of findings) {
      console.log(`[browser-audit] - ${finding}`);
    }
    process.exitCode = 1;
  } finally {
    if (browser) {
      await browser.close();
    }
  }
}

main().catch((error) => {
  console.error(`[browser-audit] fatal: ${error.message}`);
  process.exitCode = 1;
});
