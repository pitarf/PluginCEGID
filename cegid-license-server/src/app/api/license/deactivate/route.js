import { NextResponse } from 'next/server';
import { getLicenseByKey, updateLicense } from '@/lib/license-db';

/**
 * Endpoint para desativação voluntária de licença.
 * Libera o domínio associado permitindo que a chave seja ativada em outro ambiente.
 */
export async function POST(request) {
  try {
    const body = await request.json();
    const { key, domain } = body;

    if (!key) {
      return NextResponse.json(
        { success: false, message: "Chave de licença é obrigatória." },
        { status: 400 }
      );
    }

    const license = await getLicenseByKey(key);

    if (!license) {
      return NextResponse.json(
        { success: false, message: "Chave de licença não encontrada." },
        { status: 404 }
      );
    }

    // Se um domínio foi enviado, valida se coincide com o domínio registrado antes de liberar
    if (domain && license.domain) {
      const cleanReqDomain = domain.toLowerCase().replace(/https?:\/\//, '').split('/')[0].split(':')[0].trim();
      const cleanSavedDomain = license.domain.toLowerCase().replace(/https?:\/\//, '').split('/')[0].split(':')[0].trim();

      if (cleanReqDomain !== cleanSavedDomain) {
        return NextResponse.json(
          { success: false, message: "Domínio informado não coincide com o domínio atualmente vinculado." },
          { status: 403 }
        );
      }
    }

    // Libera o vínculo de domínio
    await updateLicense(license.id, { domain: null });

    return NextResponse.json({
      success: true,
      message: "Licença desativada e domínio desvinculado com sucesso.",
    });

  } catch (error) {
    console.error("Erro na rota /api/license/deactivate:", error);
    return NextResponse.json(
      { success: false, message: "Erro interno ao processar a desativação da licença." },
      { status: 500 }
    );
  }
}
