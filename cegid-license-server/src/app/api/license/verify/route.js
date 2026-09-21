import { NextResponse } from 'next/server';
import { getLicenseByKey } from '@/lib/license-db';

export async function POST(request) {
  try {
    const body = await request.json();
    const { key, domain } = body;

    if (!key || !domain) {
      return NextResponse.json(
        { success: false, status: "invalid", message: "Chave e domínio são obrigatórios." },
        { status: 400 }
      );
    }

    const license = await getLicenseByKey(key);

    if (!license) {
      return NextResponse.json({
        success: false,
        status: "invalid",
        message: "Licença inexistente."
      });
    }

    if (license.status === 'SUSPENDED') {
      return NextResponse.json({
        success: false,
        status: "suspended",
        message: "Licença suspensa por motivos comerciais."
      });
    }

    const now = new Date();
    if (new Date(license.expiresAt) < now) {
      return NextResponse.json({
        success: false,
        status: "expired",
        message: "Licença expirada."
      });
    }

    // Normaliza o domínio (case-insensitive, sem protocolo, porta ou caminhos)
    const normalizeDomain = (d) => (d || '').toLowerCase().replace(/https?:\/\//, '').split('/')[0].split(':')[0].trim();
    const cleanDomain = normalizeDomain(domain);
    const savedDomain = normalizeDomain(license.domain);

    if (savedDomain !== cleanDomain) {
      return NextResponse.json({
        success: false,
        status: "invalid",
        message: "Licença ativa em outro domínio."
      });
    }

    return NextResponse.json({
      success: true,
      status: "active",
      expiresAt: license.expiresAt
    });

  } catch (error) {
    console.error("Erro na rota /api/license/verify:", error);
    return NextResponse.json(
      { success: false, message: "Erro interno no servidor de licenças." },
      { status: 500 }
    );
  }
}
