import { chromium } from 'playwright';

const base = 'http://localhost/microgrid-platform';
const outDir = 'V:/Documents/VS CODE/DBMS AND BI/handoff_artifacts';

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 1600, height: 1000 } });
const page = await context.newPage();

await page.goto(`${base}/index.php`, { waitUntil: 'domcontentloaded' });

const csrf = await page.locator('input[name="csrf_token"]').inputValue();
await page.fill('input[name="username"]', 'admin');
await page.fill('input[name="password"]', 'admin123');
await page.evaluate((token) => {
  const csrfInput = document.querySelector('input[name="csrf_token"]');
  if (csrfInput) {
    csrfInput.value = token;
  }
}, csrf);
await page.click('button[type="submit"], input[type="submit"]');
await page.waitForLoadState('networkidle');

const shots = [
  { path: `${base}/dashboard.php`, file: `${outDir}/screenshot_dashboard.png`, wait: '.card' },
  { path: `${base}/alerts.php`, file: `${outDir}/screenshot_alerts.png`, wait: '.table, .card' },
  { path: `${base}/admin/microgrids.php`, file: `${outDir}/screenshot_microgrid_table.png`, wait: '.table' },
  { path: `${base}/logs.php`, file: `${outDir}/screenshot_logs.png`, wait: '.timeline, .table, .card' },
];

for (const s of shots) {
  await page.goto(s.path, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(1200);
  await page.screenshot({ path: s.file, fullPage: true });
}

await browser.close();
console.log('Screenshots captured successfully');
