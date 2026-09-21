import { chromium } from 'playwright';
import { spawn } from 'child_process';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');

const PORT = 3011;
const BASE_URL = `http://localhost:${PORT}`;

const VIEWPORTS = [
  { name: 'desktop-1440x900', width: 1440, height: 900, isMobile: false },
  { name: 'tablet-768x1024', width: 768, height: 1024, isMobile: false },
  { name: 'mobile-iphone-390x844', width: 390, height: 844, isMobile: true },
  { name: 'mobile-se-375x667', width: 375, height: 667, isMobile: true },
  { name: 'mobile-compact-320x568', width: 320, height: 568, isMobile: true }
];

async function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

async function waitForServer(url, timeoutMs = 25000) {
  const start = Date.now();
  while (Date.now() - start < timeoutMs) {
    try {
      const res = await fetch(url);
      if (res.ok) return true;
    } catch (e) {
      // continua tentando
    }
    await sleep(600);
  }
  throw new Error(`Servidor não respondeu em ${url} dentro do tempo limite.`);
}

async function runVisualAudit() {
  console.log(`\n========================================================`);
  console.log(`🚀 INICIANDO AUDITORIA VISUAL & RESPONSIVA (PLAYWRIGHT)`);
  console.log(`========================================================\n`);

  console.log(`[1/4] Iniciando servidor Next.js em produção na porta ${PORT}...`);
  const serverProcess = spawn('npx.cmd', ['next', 'start', '-p', String(PORT)], {
    cwd: rootDir,
    stdio: 'ignore',
    shell: true
  });

  try {
    await waitForServer(BASE_URL);
    console.log(`[2/4] Servidor pronto em ${BASE_URL}`);

    console.log(`[3/4] Inicializando Chromium Headless...`);
    const browser = await chromium.launch({ headless: true });

    const auditResults = [];

    for (const vp of VIEWPORTS) {
      console.log(`\n🔍 Testando viewport: ${vp.name} (${vp.width}x${vp.height})...`);
      const context = await browser.newContext({
        viewport: { width: vp.width, height: vp.height },
        isMobile: vp.isMobile,
        deviceScaleFactor: 2
      });
      const page = await context.newPage();

      await page.goto(BASE_URL, { waitUntil: 'networkidle' });
      await sleep(500);

      // 1. Checa Overflow Horizontal (scrollWidth vs clientWidth)
      const overflowMetrics = await page.evaluate(() => {
        const docWidth = document.documentElement.clientWidth;
        const scrollWidth = document.documentElement.scrollWidth;
        const bodyScrollWidth = document.body.scrollWidth;
        const maxScroll = Math.max(scrollWidth, bodyScrollWidth);
        const hasOverflow = maxScroll > docWidth + 1; // margem de 1px para subpixels

        return { docWidth, maxScroll, hasOverflow };
      });

      // 2. Valida ergonomia de Touch Targets nos botões
      const touchTargetCheck = await page.evaluate(() => {
        const buttons = Array.from(document.querySelectorAll('button, a'));
        let smallButtons = 0;
        buttons.forEach(el => {
          const rect = el.getBoundingClientRect();
          if (rect.width > 0 && rect.height > 0) {
            if (rect.width < 24 || rect.height < 24) {
              smallButtons++;
            }
          }
        });
        return { totalButtons: buttons.length, smallButtons };
      });

      // 3. Captura Screenshot FullPage
      const screenshotFilename = `audit-${vp.name}.png`;
      const screenshotPath = path.join(rootDir, 'public', 'audit-screenshots', screenshotFilename);
      await page.screenshot({ path: screenshotPath, fullPage: true });

      const passed = !overflowMetrics.hasOverflow;
      auditResults.push({
        viewport: vp.name,
        resolution: `${vp.width}x${vp.height}`,
        hasOverflow: overflowMetrics.hasOverflow,
        docWidth: overflowMetrics.docWidth,
        maxScroll: overflowMetrics.maxScroll,
        smallButtons: touchTargetCheck.smallButtons,
        screenshot: screenshotFilename,
        status: passed ? 'APROVADO (OK)' : 'REPROVADO (OVERFLOW)'
      });

      console.log(`   - Overflow Horizontal: ${passed ? 'Nenhum (Perfeito)' : `Detectado (${overflowMetrics.maxScroll}px > ${overflowMetrics.docWidth}px)`}`);
      console.log(`   - Touch Targets: ${touchTargetCheck.totalButtons} elementos analisados`);
      console.log(`   - Screenshot salvo: public/audit-screenshots/${screenshotFilename}`);

      await context.close();
    }

    await browser.close();

    console.log(`\n========================================================`);
    console.log(`📊 RESUMO DA AUDITORIA VISUAL`);
    console.log(`========================================================`);
    console.table(auditResults);

    const hasAnyFailure = auditResults.some(r => r.hasOverflow);
    if (hasAnyFailure) {
      console.error(`\n❌ Falha na auditoria visual: detectado overflow horizontal em algum viewport.`);
      process.exitCode = 1;
    } else {
      console.log(`\n✅ SUCESSO: Todos os viewports (Mobile 320px até Desktop 1440px) aprovados com ZERO overflow!`);
    }

  } finally {
    console.log(`\n[4/4] Encerrando servidor Next.js na porta ${PORT}...`);
    try {
      spawn('taskkill', ['/pid', String(serverProcess.pid), '/f', '/t'], { shell: true });
    } catch (e) {
      serverProcess.kill('SIGTERM');
    }
  }
}

runVisualAudit();
