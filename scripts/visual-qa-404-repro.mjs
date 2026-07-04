import { chromium } from '@playwright/test';

const BASE = 'https://portal.constructoralosalmendros.cl';
const OUT = 'C:\\Users\\joaqu\\portal-almendros\\scripts';
const CREDS = { email: 'admin@losalmendros.cl', password: 'admin123' };

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1366, height: 900 } });
  const page = await context.newPage();

  // 1) Public login page
  await page.goto(`${BASE}/login.php`, { waitUntil: 'networkidle' });
  await page.screenshot({ path: `${OUT}/visual-qa-01-login.png`, fullPage: true });

  // 2) Login API
  const loginResp = await page.request.post(`${BASE}/auth/login.php`, {
    form: { email: CREDS.email, password: CREDS.password }
  });
  const loginJson = await loginResp.json().catch(() => null);
  console.log('login status', loginResp.status(), loginJson);

  if (loginResp.ok() && loginJson && loginJson.ok) {
    // 3) Logged-in home/dashboard
    await page.goto(`${BASE}/dashboard`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(700);
    await page.screenshot({ path: `${OUT}/visual-qa-02-dashboard.png`, fullPage: true });

    // 4) Common nav targets if present
    for (const href of ['/projects', '/clientes', '/pagos', '/avance']) {
      try {
        const resp = await page.goto(`${BASE}${href}`, { waitUntil: 'domcontentloaded' });
        if (resp && resp.ok()) {
          await page.waitForTimeout(500);
          await page.screenshot({ path: `${OUT}/visual-qa-${href.replace(/\//g,'-')}.png`, fullPage: true });
        }
      } catch (e) {
        console.log('skip', href, e.message);
      }
    }
  } else {
    console.log('Login failed');
  }

  await browser.close();
  console.log('done');
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
