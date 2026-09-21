import { NextResponse } from 'next/server';
import { getLicenseByKey, updateLicense } from '@/lib/license-db';

export async function POST(request) {
  try {
    const body = await request.json();
    const { key, domain } = body;

    if (!key || !domain) {
      return NextResponse.json(
        { success: false, message: "Chave de licença e domínio são obrigatórios." },
        { status: 400 }
      );
    }

    const license = await getLicenseByKey(key);

    if (!license) {
      return NextResponse.json(
        { success: false, message: "Chave de licença inválida ou inexistente." },
        { status: 404 }
      );
    }

    // 1. Valida Status Suspenso
    if (license.status === 'SUSPENDED') {
      return NextResponse.json(
        { success: false, message: "Esta licença foi suspensa. Entre em contato com o suporte." },
        { status: 403 }
      );
    }

    // 2. Valida Expiração
    const now = new Date();
    if (new Date(license.expiresAt) < now) {
      return NextResponse.json(
        { success: false, message: "Esta licença expirou em " + new Date(license.expiresAt).toLocaleDateString('pt-PT') + "." },
        { status: 403 }
      );
    }

    // Normaliza o domínio (case-insensitive, sem protocolo, porta ou caminhos)
    const normalizeDomain = (d) => (d || '').toLowerCase().replace(/https?:\/\//, '').split('/')[0].split(':')[0].trim();
    const cleanDomain = normalizeDomain(domain);

    // 3. Primeira ativação (vincula o domínio)
    if (!license.domain) {
      await updateLicense(license.id, { domain: cleanDomain });
      return NextResponse.json({
        success: true,
        message: `Licença ativada com sucesso para o domínio ${cleanDomain}.`,
        expiresAt: license.expiresAt
      });
    }

    // 4. Valida se o domínio corresponde ao cadastrado
    const savedDomain = normalizeDomain(license.domain);
    if (savedDomain !== cleanDomain) {
      return NextResponse.json(
        { success: false, message: `Esta licença já está vinculada e ativa em outro domínio (${savedDomain}).` },
        { status: 403 }
      );
    }

    return NextResponse.json({
      success: true,
      message: "Licença ativa e validada neste domínio.",
      expiresAt: license.expiresAt
    });

  } catch (error) {
    console.error("Erro na rota /api/license/activate:", error);
    return NextResponse.json(
      { success: false, message: "Erro interno no servidor de licenças." },
      { status: 500 }
    );
  }
}
