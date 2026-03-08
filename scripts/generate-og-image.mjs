import { chromium } from 'playwright';
import { execSync } from 'child_process';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const projectRoot = resolve(__dirname, '..');
const outputPath = resolve(projectRoot, 'public', 'og-image.png');

const html = execSync(
    'php artisan tinker --execute="echo view(\'og-image\')->render();"',
    {
        cwd: projectRoot,
        encoding: 'utf-8',
    },
);

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1200, height: 630 } });
await page.setContent(html, { waitUntil: 'networkidle' });
await page.screenshot({ path: outputPath, type: 'png' });
await browser.close();

console.log(`OG image saved to ${outputPath}`);
