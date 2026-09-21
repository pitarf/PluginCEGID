import { NextResponse } from 'next/server';
import { getAllLicenses, createLicense } from '@/lib/license-db';

// Retorna todas as licenças cadastradas
export async function GET() {
  try {
    const licenses = await getAllLicenses();
    return NextResponse.json({ success: true, licenses });
  } catch (error) {
    console.error("Erro ao listar licenças:", error);
    return NextResponse.json({ success: false, message: "Erro ao ler banco de dados." }, { status: 500 });
  }
}

// Cria uma nova licença
export async function POST(request) {
  try {
    const body = await request.json();
    const { clientName, clientNif, expiresAt, customKey } = body;

    if (!clientName || !clientNif || !expiresAt) {
      return NextResponse.json({ success: false, message: "Nome do cliente, NIF e expiração são obrigatórios." }, { status: 400 });
    }

    // Usa chave personalizada ou gera uma segura no formato VP-XXXX-XXXX-XXXX
    let licenseKey = customKey ? customKey.trim().toUpperCase() : '';
    if (!licenseKey) {
      const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
      const genSegment = () => Array.from({ length: 4 }, () => chars[Math.floor(Math.random() * chars.length)]).join('');
      licenseKey = `VP-${genSegment()}-${genSegment()}-${genSegment()}`;
    }

    const newLicense = await createLicense({
      key: licenseKey,
      clientName,
      clientNif,
      expiresAt: new Date(expiresAt),
      status: "ACTIVE"
    });

    return NextResponse.json({ success: true, license: newLicense });
  } catch (error) {
    console.error("Erro ao criar licença:", error);
    return NextResponse.json({ success: false, message: "Erro ao cadastrar licença." }, { status: 500 });
  }
}
