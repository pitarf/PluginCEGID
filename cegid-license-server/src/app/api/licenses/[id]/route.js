import { NextResponse } from 'next/server';
import { updateLicense, deleteLicense } from '@/lib/license-db';

// Atualiza o status ou dados de uma licença específica
export async function PATCH(request, { params }) {
  try {
    const { id } = await params;
    const body = await request.json();

    const updated = await updateLicense(id, body);

    if (!updated) {
      return NextResponse.json({ success: false, message: "Licença não encontrada." }, { status: 404 });
    }

    return NextResponse.json({ success: true, license: updated });
  } catch (error) {
    console.error("Erro ao atualizar licença:", error);
    return NextResponse.json({ success: false, message: "Erro ao modificar licença." }, { status: 500 });
  }
}

// Exclui uma licença
export async function DELETE(request, { params }) {
  try {
    const { id } = await params;
    const deleted = await deleteLicense(id);

    if (!deleted) {
      return NextResponse.json({ success: false, message: "Licença não encontrada." }, { status: 404 });
    }

    return NextResponse.json({ success: true, message: "Licença deletada com sucesso." });
  } catch (error) {
    console.error("Erro ao excluir licença:", error);
    return NextResponse.json({ success: false, message: "Erro ao excluir licença." }, { status: 500 });
  }
}
