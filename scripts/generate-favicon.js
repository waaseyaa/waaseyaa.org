const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const SVG_PATH = path.join(__dirname, '..', 'public', 'img', 'icon.svg');
const ICO_PATH = path.join(__dirname, '..', 'public', 'favicon.ico');
const APPLE_PATH = path.join(__dirname, '..', 'public', 'img', 'apple-touch-icon.png');

async function main() {
  const svg = fs.readFileSync(SVG_PATH, 'utf-8');
  const html = `<!DOCTYPE html><html><head><style>*{margin:0;padding:0;}body{width:180px;height:180px;overflow:hidden;}</style></head><body>${svg}</body></html>`;

  const browser = await chromium.launch();
  const page = await browser.newPage();

  // Generate apple-touch-icon (180x180)
  await page.setViewportSize({ width: 180, height: 180 });
  await page.setContent(html.replace(/180px/g, '180px'), { waitUntil: 'load' });
  await page.screenshot({ path: APPLE_PATH, type: 'png' });
  console.log(`✓ ${APPLE_PATH}`);

  // Generate favicon.ico as 32x32 PNG (browsers accept PNG in .ico)
  const html32 = html.replace(/180px/g, '32px');
  await page.setViewportSize({ width: 32, height: 32 });
  await page.setContent(html32, { waitUntil: 'load' });
  await page.screenshot({ path: ICO_PATH, type: 'png' });
  console.log(`✓ ${ICO_PATH}`);

  await browser.close();
}

main().catch(err => {
  console.error(err);
  process.exit(1);
});
