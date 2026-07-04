import { chromium } from '@playwright/test';

const BASE = 'https://portal.constructoralosalmendros.cl';
const OUT = 'C:\\Users\\joaqu\\portal-almendros\\screenshots';

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1366, height: 900 } });
  const page = await context.newPage();

  await page.goto(`${BASE}/login.php`, { waitUntil: 'networkidle' });
  await page.screenshot({ path: `${OUT}/visual-qa-login.png`, fullPage: true });

  console.log('captured', `${OUT}/visual-qa-login.png`);
  await browser.close();
})();
