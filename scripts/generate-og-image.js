const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const TEMPLATE_PATH = path.join(__dirname, 'og-template.html');
const OUTPUT_PATH = path.join(__dirname, '..', 'public', 'img', 'og-card.png');

async function main() {
  fs.mkdirSync(path.dirname(OUTPUT_PATH), { recursive: true });

  const template = fs.readFileSync(TEMPLATE_PATH, 'utf-8');

  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1200, height: 630 });
  await page.setContent(template, { waitUntil: 'load' });
  await page.screenshot({ path: OUTPUT_PATH, type: 'png' });
  await browser.close();

  console.log(`✓ ${OUTPUT_PATH}`);
}

main().catch(err => {
  console.error(err);
  process.exit(1);
});
