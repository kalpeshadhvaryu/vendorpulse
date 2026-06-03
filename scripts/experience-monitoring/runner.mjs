#!/usr/bin/env node

import { chromium } from "playwright";

function nowIso() {
  return new Date().toISOString();
}

function toStatusFromError(err) {
  const msg = String(err?.message ?? err ?? "").toLowerCase();
  if (msg.includes("timeout")) return "timeout";
  if (msg.includes("login")) return "failed_login";
  return "error";
}

async function findFirstFrameWithSelector(page, selectors) {
  for (const frame of page.frames()) {
    for (const selector of selectors) {
      const handle = await frame.$(selector);
      if (handle) {
        return { frame, selector };
      }
    }
  }

  return null;
}

async function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function waitForLoginForm(page, usernameSelectors, passwordSelectors, timeoutMs) {
  const started = Date.now();

  while (Date.now() - started < timeoutMs) {
    const usernameLocation = await findFirstFrameWithSelector(page, usernameSelectors);
    const passwordLocation = await findFirstFrameWithSelector(page, passwordSelectors);

    if (usernameLocation && passwordLocation) {
      return {
        frame: usernameLocation.frame ?? passwordLocation.frame ?? page.mainFrame(),
        usernameSelector: usernameLocation.selector,
        passwordSelector: passwordLocation.selector,
      };
    }

    await sleep(300);
  }

  return null;
}

async function captureScreenshotBase64(page) {
  if (!page || page.isClosed()) {
    return null;
  }

  try {
    const screenshotBuffer = await page.screenshot({ type: "png", fullPage: true });
    return screenshotBuffer.toString("base64");
  } catch {
    return null;
  }
}

async function run() {
  const rawInput = process.argv[2] ?? "{}";
  const input = JSON.parse(rawInput);

  const browserType = "chromium";
  const launcher = chromium;

  const timeoutMs = Math.max(1000, Number(input.timeout_ms ?? 30000));
  const startedAt = nowIso();
  const logs = [];
  const consoleErrors = [];
  const failedRequests = [];
  const httpStatusCodes = [];
  const responseTimes = [];
  const requestStarts = new Map();

  let browser;
  let page;
  const overallStart = Date.now();

  try {
    browser = await launcher.launch({
      headless: true,
      args: ["--disable-blink-features=AutomationControlled"],
    });
    const context = await browser.newContext({
      userAgent:
        "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36",
      viewport: { width: 1366, height: 768 },
      locale: "en-US",
      timezoneId: "Asia/Kolkata",
    });
    page = await context.newPage();

    page.on("console", (msg) => {
      const text = msg.text();
      logs.push({ source: "console", level: msg.type(), message: text, at: nowIso() });
      if (msg.type() === "error") {
        consoleErrors.push({ message: text, location: msg.location(), at: nowIso() });
      }
    });

    page.on("requestfailed", (request) => {
      const startedAt = requestStarts.get(request) ?? Date.now();
      requestStarts.delete(request);

      failedRequests.push({
        url: request.url(),
        method: request.method(),
        failure: request.failure(),
        duration_ms: Date.now() - startedAt,
        at: nowIso(),
      });
    });

    page.on("request", (request) => {
      requestStarts.set(request, Date.now());
    });

    page.on("response", (response) => {
      const request = response.request();
      const startedAt = requestStarts.get(request) ?? Date.now();
      requestStarts.delete(request);
      responseTimes.push({
        url: response.url(),
        status: response.status(),
        duration_ms: Date.now() - startedAt,
        at: nowIso(),
      });

      httpStatusCodes.push({
        url: response.url(),
        status: response.status(),
        at: nowIso(),
      });
    });

    const usernameSelectors = [
      '#Email',
      'input[id="Email"]',
      'input[type="email"]',
      'input[name="email"]',
      'input[name="username"]',
      'input[id*="email"]',
      'input[id*="user"]',
      'input[autocomplete="username"]',
      'input[type="text"]',
    ];

    const passwordSelectors = [
      '#Password',
      'input[id="Password"]',
      'input[type="password"]',
      'input[name="password"]',
      'input[autocomplete="current-password"]',
    ];

    const loginStart = Date.now();
    let formReady = null;
    let attempts = 0;
    while (!formReady && attempts < 3) {
      attempts += 1;
      if (attempts === 1) {
        await page.goto(String(input.login_url), { timeout: timeoutMs, waitUntil: "domcontentloaded" });
      } else {
        await page.reload({ timeout: timeoutMs, waitUntil: "domcontentloaded" });
      }

      await page.waitForTimeout(1200);
      formReady = await waitForLoginForm(page, usernameSelectors, passwordSelectors, Math.min(12000, timeoutMs));
    }

    let loginDurationMs = null;
    if (!formReady) {
      const alreadyAuthenticated = await page.evaluate(() => {
        const path = window.location.pathname.toLowerCase();
        const title = document.title.toLowerCase();
        return path.includes("dashboard") || title.includes("dashboard");
      });

      if (!alreadyAuthenticated) {
        throw new Error("Unable to locate username/password fields on login page.");
      }

      loginDurationMs = 0;
    } else {
      const loginFrame = formReady.frame;

      await loginFrame
        .locator(formReady.usernameSelector)
        .first()
        .fill(String(input.login_username ?? ""), { timeout: 8000 });

      await loginFrame
        .locator(formReady.passwordSelector)
        .first()
        .fill(String(input.login_password ?? ""), { timeout: 8000 });

      const submitSelectors = [
        'button[type="submit"]:has-text("Sign in")',
        'button:has-text("Sign in")',
        'button:has-text("Sign In")',
        'button[type="submit"]',
        'input[type="submit"]',
        'button:has-text("Login")',
        'button:has-text("Log in")',
      ];

      let submitted = false;
      for (const selector of submitSelectors) {
        const handle = await loginFrame.$(selector);
        if (!handle) continue;
        await Promise.allSettled([
          page.waitForLoadState("networkidle", { timeout: timeoutMs }),
          loginFrame.click(selector),
        ]);
        submitted = true;
        break;
      }

      if (!submitted) {
        await page.keyboard.press("Enter");
      }

      loginDurationMs = Date.now() - loginStart;
    }

    const dashboardStart = Date.now();
    let dashboardResponse = null;
    if (String(input.dashboard_url ?? "").trim() !== "") {
      dashboardResponse = await page.goto(String(input.dashboard_url), {
        timeout: timeoutMs,
        waitUntil: "networkidle",
      });
    } else {
      await page.waitForLoadState("networkidle", { timeout: timeoutMs });
    }
    const dashboardLoadDurationMs = Date.now() - dashboardStart;

    const screenshotBase64 = await captureScreenshotBase64(page);
    const finishedAt = nowIso();

    const payload = {
      status: consoleErrors.length > 0 ? "js_error" : "ok",
      login_duration_ms: loginDurationMs,
      dashboard_load_duration_ms: dashboardLoadDurationMs,
      total_duration_ms: Date.now() - overallStart,
      http_status: dashboardResponse ? dashboardResponse.status() : null,
      http_status_codes: httpStatusCodes,
      response_times: responseTimes,
      console_errors: consoleErrors,
      failed_requests: failedRequests,
      browser_logs: logs,
      error_message: null,
      screenshot_base64: screenshotBase64,
      screenshot_mime: "image/png",
      started_at: startedAt,
      finished_at: finishedAt,
    };

    process.stdout.write(JSON.stringify(payload));
  } catch (err) {
    const screenshotBase64 = await captureScreenshotBase64(page);
    const finishedAt = nowIso();
    const payload = {
      status: toStatusFromError(err),
      login_duration_ms: null,
      dashboard_load_duration_ms: null,
      total_duration_ms: Date.now() - overallStart,
      http_status: null,
      http_status_codes: httpStatusCodes,
      response_times: responseTimes,
      console_errors: consoleErrors,
      failed_requests: failedRequests,
      browser_logs: logs,
      error_message: String(err?.message ?? err ?? "Playwright run failed"),
      screenshot_base64: screenshotBase64,
      screenshot_mime: "image/png",
      started_at: startedAt,
      finished_at: finishedAt,
    };

    process.stdout.write(JSON.stringify(payload));
  } finally {
    if (browser) {
      await browser.close();
    }
  }
}

run();
