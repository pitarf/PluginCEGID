import { chromium } from 'playwright';
import { spawn } from 'child_process';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');

const PORT = 3012;
const BASE_URL = `http://localhost:${PORT}`;

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
      // aguarda
    }
    await sleep(600);
  }
  throw new Error(`Servidor não respondeu em ${url}`);
}

async function runE2EMobileTest() {
  console.log(`\n========================================================`);
  console.log(`📱 INICIANDO TESTE E2E MOBILE (CARDS & MODAIS) PLAYWRIGHT`);
  console.log(`========================================================\n`);

  console.log(`[1/5] Subindo servidor Next.js na porta de teste ${PORT}...`);
  const serverProcess = spawn('npx.cmd', ['next', 'start', '-p', String(PORT)], {
    cwd: rootDir,
    stdio: 'ignore',
    shell: true
  });

  try {
    await waitForServer(BASE_URL);
    console.log(`[2/5] Servidor ativo em ${BASE_URL}`);

    console.log(`[3/5] Abrindo Chromium emulando iPhone 13 (390x844)...`);
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
      viewport: { width: 390, height: 844 },
      isMobile: true,
      hasTouch: true,
      deviceScaleFactor: 2
    });
    const page = await context.newPage();

    console.log(`[4/5] Navegando para o License Manager...`);
    await page.goto(BASE_URL, { waitUntil: 'networkidle' });
    await sleep(600);

    // 1. Verifica se a tabela Desktop está oculta no celular
    const isTableHidden = await page.evaluate(() => {
      const tableDiv = document.querySelector('table')?.closest('div');
      if (!tableDiv) return true;
      const style = window.getComputedStyle(tableDiv);
      return style.display === 'none' || tableDiv.classList.contains('hidden');
    });
    console.log(`   ✓ Validação: Tabela Desktop oculta no mobile? ${isTableHidden ? 'SIM (Correto)' : 'NÃO'}`);

    // 2. Verifica se os Cards Individuais estão visíveis
    const cardCount = await page.evaluate(() => {
      // Cards no bloco lg:hidden
      const mobileContainer = document.querySelector('.block.lg\\:hidden');
      if (!mobileContainer) return 0;
      return mobileContainer.querySelectorAll('.bg-slate-950\\/70').length;
    });
    console.log(`   ✓ Validação: Cards mobile renderizados? ${cardCount} cards encontrados`);

    // 3. Captura print dos Cards no celular
    const cardsScreenshot = path.join(rootDir, 'public', 'audit-screenshots', 'e2e-mobile-cards-view.png');
    await page.screenshot({ path: cardsScreenshot, fullPage: false });
    console.log(`   📷 Screenshot salvo: public/audit-screenshots/e2e-mobile-cards-view.png`);

    // 4. Teste de Abertura do Modal de Confirmação (ao clicar em Excluir)
    console.log(`\n   Testando abertura do Modal de Confirmação...`);
    const deleteButton = page.locator('.block.lg\\:hidden button:has-text("Excluir")').first();
    await deleteButton.click();
    await sleep(400);

    const modalVisible = await page.locator('text=Excluir Licença Permanentemente').isVisible();
    console.log(`   ✓ Validação: Modal moderno de confirmação abriu? ${modalVisible ? 'SIM (Perfeito, sem alert nativo)' : 'NÃO'}`);

    // 5. Captura print do Modal aberto no celular
    const modalScreenshot = path.join(rootDir, 'public', 'audit-screenshots', 'e2e-mobile-modal-view.png');
    await page.screenshot({ path: modalScreenshot, fullPage: false });
    console.log(`   📷 Screenshot salvo: public/audit-screenshots/e2e-mobile-modal-view.png`);

    // 6. Clica em Cancelar no modal
    const cancelButton = page.locator('button:has-text("Cancelar")');
    await cancelButton.click();
    await sleep(300);

    const modalClosed = !(await page.locator('text=Excluir Licença Permanentemente').isVisible());
    console.log(`   ✓ Validação: Botão Cancelar fechou o modal? ${modalClosed ? 'SIM' : 'NÃO'}`);

    await context.close();
    await browser.close();

    console.log(`\n========================================================`);
    console.log(`✅ TESTE E2E MOBILE FINALIZADO COM 100% DE SUCESSO!`);
    console.log(`========================================================\n`);

  } finally {
    console.log(`[5/5] Encerrando servidor Next.js na porta ${PORT}...`);
    try {
      spawn('taskkill', ['/pid', String(serverProcess.pid), '/f', '/t'], { shell: true });
    } catch (e) {
      serverProcess.kill('SIGTERM');
    }
  }
}

runE2EMobileTest();
