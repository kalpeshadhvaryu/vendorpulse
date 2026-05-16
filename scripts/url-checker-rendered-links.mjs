#!/usr/bin/env node

import puppeteer from "puppeteer";

function parseArgs(argv) {
  const args = {
    url: "",
    timeoutMs: 45000,
    followExternalFlow: false,
    clickSelectors: [],
    clickTexts: [],
  };

  for (let i = 0; i < argv.length; i += 1) {
    const token = argv[i];
    if (token === "--url") {
      args.url = argv[i + 1] ?? "";
      i += 1;
      continue;
    }
    if (token === "--timeoutMs") {
      const parsed = Number.parseInt(argv[i + 1] ?? "", 10);
      if (Number.isFinite(parsed) && parsed > 0) {
        args.timeoutMs = parsed;
      }
      i += 1;
      continue;
    }
    if (token === "--followExternalFlow") {
      args.followExternalFlow = true;
      continue;
    }
    if (token === "--clickSelector") {
      const selector = (argv[i + 1] ?? "").trim();
      if (selector) {
        args.clickSelectors.push(selector);
      }
      i += 1;
      continue;
    }
    if (token === "--clickText") {
      const text = (argv[i + 1] ?? "").trim();
      if (text) {
        args.clickTexts.push(text);
      }
      i += 1;
    }
  }

  return args;
}

const CHROME_UA =
  "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 " +
  "(KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36";

const MAX_INTERACTION_CLICKS = 8;

async function collectUrlsFromDom(page) {
  return page.evaluate(() => {
    const results = new Set();

    const toAbsolute = (value) => {
      if (typeof value !== "string") return "";
      const candidate = value.trim();
      if (!candidate) return "";
      if (
        candidate.startsWith("javascript:") ||
        candidate.startsWith("mailto:") ||
        candidate.startsWith("tel:") ||
        candidate.startsWith("#") ||
        candidate.startsWith("data:")
      ) {
        return "";
      }

      try {
        const absolute = new URL(candidate, document.baseURI).href;
        if (absolute.startsWith("http://") || absolute.startsWith("https://")) {
          return absolute;
        }
      } catch {
        return "";
      }

      return "";
    };

    const addValue = (value) => {
      const normalized = toAbsolute(value);
      if (normalized) {
        results.add(normalized);
      }
    };

    const urlPattern = /(https?:\/\/[^\s"'<>`]+|\/[A-Za-z0-9_\-./?=&%#+]+)/g;
    const addUrlsFromText = (text) => {
      if (typeof text !== "string" || text.length === 0) return;
      const matches = text.match(urlPattern);
      if (!matches) return;
      for (const value of matches) {
        addValue(value);
      }
    };

    for (const anchor of Array.from(document.querySelectorAll("a[href]"))) {
      if (anchor instanceof HTMLAnchorElement) {
        addValue(anchor.getAttribute("href") ?? anchor.href);
      }
    }

    for (const element of Array.from(
      document.querySelectorAll(
        "button[formaction], input[formaction], form[action], [data-url], [data-href], [data-link], [data-target-url], [data-redirect], [onclick], [href]",
      ),
    )) {
      if (!(element instanceof Element)) continue;

      const directAttrs = [
        "href",
        "formaction",
        "action",
        "data-url",
        "data-href",
        "data-link",
        "data-target-url",
        "data-redirect",
      ];

      for (const attr of directAttrs) {
        const value = element.getAttribute(attr);
        if (value) {
          addValue(value);
        }
      }

      const onclick = element.getAttribute("onclick");
      if (onclick) {
        addUrlsFromText(onclick);
      }
    }

    for (const script of Array.from(document.querySelectorAll("script"))) {
      if (!(script instanceof HTMLScriptElement)) continue;
      const inlineContent = script.textContent ?? "";
      if (inlineContent) {
        addUrlsFromText(inlineContent);
      }
    }

    return Array.from(results);
  });
}

async function markClickableCandidates(page) {
  return page.evaluate((maxClicks) => {
    const selectors = [
      "button",
      "a[role='button']",
      "[onclick]",
      "[data-url]",
      "[data-href]",
      "[data-link]",
      "[data-target-url]",
      "[data-redirect]",
      "input[type='button']",
      "input[type='submit']",
    ];

    const candidates = Array.from(document.querySelectorAll(selectors.join(",")));
    const marked = [];
    let index = 0;

    for (const element of candidates) {
      if (!(element instanceof HTMLElement)) continue;
      if (index >= maxClicks) break;

      const style = window.getComputedStyle(element);
      if (style.display === "none" || style.visibility === "hidden") continue;
      const text = (element.innerText || element.getAttribute("aria-label") || "").trim();
      if (text.length === 0 && !element.getAttribute("onclick") && !element.getAttribute("data-url")) {
        continue;
      }

      const id = `vp-click-${index}`;
      element.setAttribute("data-vp-click-id", id);
      marked.push(id);
      index += 1;
    }

    return marked;
  }, MAX_INTERACTION_CLICKS);
}

async function collectUrlsFromInteractions(page, startUrl, observedUrls) {
  const clickableIds = await markClickableCandidates(page);

  for (const clickId of clickableIds) {
    try {
      await page.evaluate((id) => {
        const element = document.querySelector(`[data-vp-click-id='${id}']`);
        if (element instanceof HTMLElement) {
          element.click();
        }
      }, clickId);

      await Promise.race([
        page.waitForNavigation({ waitUntil: "networkidle2", timeout: 5000 }),
        new Promise((resolve) => setTimeout(resolve, 1800)),
      ]);

      observedUrls.add(page.url());

      const domUrls = await collectUrlsFromDom(page);
      for (const url of domUrls) {
        observedUrls.add(url);
      }

      if (page.url() !== startUrl) {
        await page.goto(startUrl, { waitUntil: "networkidle2", timeout: 30000 });
        await new Promise((resolve) => setTimeout(resolve, 800));
      }
    } catch {
      // Ignore individual interaction failures and continue scanning.
    }
  }
}

async function runTargetedClickPlan(page, clickSelectors, clickTexts, observedUrls, startUrl) {
  const clickPlans = [];

  for (const selector of clickSelectors) {
    clickPlans.push({ type: "selector", value: selector });
  }

  for (const text of clickTexts) {
    clickPlans.push({ type: "text", value: text });
  }

  for (const plan of clickPlans) {
    try {
      const clicked = await page.evaluate((step) => {
        const normalize = (value) => (value || "").replace(/\s+/g, " ").trim().toLowerCase();

        if (step.type === "selector") {
          const element = document.querySelector(step.value);
          if (element instanceof HTMLElement) {
            element.click();
            return true;
          }
          return false;
        }

        const target = normalize(step.value);
        if (!target) return false;

        const candidates = Array.from(
          document.querySelectorAll("button, a, [role='button'], input[type='button'], input[type='submit']"),
        );

        for (const element of candidates) {
          if (!(element instanceof HTMLElement)) continue;
          const text = normalize(element.innerText || element.getAttribute("aria-label") || element.getAttribute("value") || "");
          if (text.includes(target)) {
            element.click();
            return true;
          }
        }

        return false;
      }, plan);

      if (!clicked) {
        continue;
      }

      await Promise.race([
        page.waitForNavigation({ waitUntil: "networkidle2", timeout: 7000 }),
        new Promise((resolve) => setTimeout(resolve, 2000)),
      ]);

      observedUrls.add(page.url());
      const domUrls = await collectUrlsFromDom(page);
      for (const link of domUrls) {
        observedUrls.add(link);
      }

      if (page.url() !== startUrl) {
        await page.goto(startUrl, { waitUntil: "networkidle2", timeout: 30000 });
        await new Promise((resolve) => setTimeout(resolve, 800));
      }
    } catch {
      // Ignore individual targeted click failures.
    }
  }
}

async function followOneExternalFlow(page, startUrl, observedUrls) {
  const startHost = new URL(startUrl).host.toLowerCase();

  const candidates = Array.from(observedUrls).filter((value) => {
    try {
      const parsed = new URL(value);
      return parsed.protocol.startsWith("http") && parsed.host.toLowerCase() !== startHost;
    } catch {
      return false;
    }
  });

  if (candidates.length === 0) {
    return;
  }

  const preferred =
    candidates.find((url) => /plan|book|scan|selection|register|signup|checkout/i.test(url)) ?? candidates[0];

  try {
    await page.goto(preferred, { waitUntil: "networkidle2", timeout: 30000 });
    await new Promise((resolve) => setTimeout(resolve, 1000));
    observedUrls.add(page.url());

    const domUrls = await collectUrlsFromDom(page);
    for (const link of domUrls) {
      observedUrls.add(link);
    }

    await collectUrlsFromInteractions(page, page.url(), observedUrls);
  } catch {
    // Ignore follow-flow failures; base scan data remains valid.
  }
}

async function main() {
  const { url, timeoutMs, followExternalFlow, clickSelectors, clickTexts } = parseArgs(process.argv.slice(2));

  if (!url) {
    console.error(JSON.stringify({ ok: false, error: "Missing --url argument" }));
    process.exit(2);
  }

  const browser = await puppeteer.launch({
    headless: true,
    args: ["--no-sandbox", "--disable-setuid-sandbox"],
  });

  try {
    const page = await browser.newPage();
    await page.setUserAgent(CHROME_UA);
    await page.setExtraHTTPHeaders({
      "Accept-Language": "en-US,en;q=0.9",
    });

    await page.goto(url, {
      waitUntil: "networkidle2",
      timeout: timeoutMs,
    });

    // Allow delayed client-side hydration/rendering before collecting links.
    await new Promise((resolve) => setTimeout(resolve, 1200));

    const observedUrls = new Set();
    page.on("request", (request) => {
      observedUrls.add(request.url());
    });

    const staticLinks = await collectUrlsFromDom(page);
    for (const link of staticLinks) {
      observedUrls.add(link);
    }

    await runTargetedClickPlan(page, clickSelectors, clickTexts, observedUrls, url);

    await collectUrlsFromInteractions(page, url, observedUrls);

    if (followExternalFlow) {
      await followOneExternalFlow(page, url, observedUrls);
    }

    const links = Array.from(observedUrls);

    process.stdout.write(JSON.stringify({ ok: true, links }));
  } finally {
    await browser.close();
  }
}

main().catch((error) => {
  const message = error instanceof Error ? error.message : "Unknown Puppeteer failure";
  process.stderr.write(JSON.stringify({ ok: false, error: message }));
  process.exit(1);
});
