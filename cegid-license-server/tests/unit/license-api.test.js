import { describe, it, expect, beforeEach } from 'vitest';
import { POST as activatePOST } from '@/app/api/license/activate/route';
import { POST as verifyPOST } from '@/app/api/license/verify/route';
import { POST as deactivatePOST } from '@/app/api/license/deactivate/route';
import { GET as licensesGET, POST as licensesPOST } from '@/app/api/licenses/route';
import { PATCH as licensePATCH, DELETE as licenseDELETE } from '@/app/api/licenses/[id]/route';
import { createLicense, getLicenseByKey, getAllLicenses } from '@/lib/license-db';

describe('Suíte de Testes de Licenciamento & Regras de Negócio (Vitest)', () => {
  
  beforeEach(() => {
    // Reseta os dados de teste em memória
    global.inMemoryLicenses = [
      {
        id: "test-uuid-active",
        key: "VP-TEST-KEY-ACTIVE",
        domain: "meudominio.com",
        clientName: "Cliente Ativo",
        clientNif: "516542729",
        status: "ACTIVE",
        expiresAt: new Date("2030-12-31T23:59:59Z"),
        createdAt: new Date(),
        updatedAt: new Date()
      },
      {
        id: "test-uuid-unlinked",
        key: "VP-TEST-KEY-UNLINKED",
        domain: null,
        clientName: "Cliente Não Vinculado",
        clientNif: "516542729",
        status: "ACTIVE",
        expiresAt: new Date("2030-12-31T23:59:59Z"),
        createdAt: new Date(),
        updatedAt: new Date()
      },
      {
        id: "test-uuid-suspended",
        key: "VP-TEST-KEY-SUSPENDED",
        domain: "site-suspenso.com",
        clientName: "Cliente Suspenso",
        clientNif: "123456789",
        status: "SUSPENDED",
        expiresAt: new Date("2030-12-31T23:59:59Z"),
        createdAt: new Date(),
        updatedAt: new Date()
      },
      {
        id: "test-uuid-expired",
        key: "VP-TEST-KEY-EXPIRED",
        domain: "site-expirado.com",
        clientName: "Cliente Expirado",
        clientNif: "987654321",
        status: "ACTIVE",
        expiresAt: new Date("2020-01-01T00:00:00Z"),
        createdAt: new Date(),
        updatedAt: new Date()
      }
    ];
  });

  describe('Rota /api/license/activate', () => {
    it('Deve rejeitar requisição sem parâmetros obrigatórios (HTTP 400)', async () => {
      const req = new Request('http://localhost/api/license/activate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ key: '' })
      });
      const res = await activatePOST(req);
      const data = await res.json();
      expect(res.status).toBe(400);
      expect(data.success).toBe(false);
    });

    it('Deve rejeitar chave inexistente (HTTP 404)', async () => {
      const req = new Request('http://localhost/api/license/activate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ key: 'CHAVE-INEXISTENTE', domain: 'exemplo.com' })
      });
      const res = await activatePOST(req);
      const data = await res.json();
      expect(res.status).toBe(404);
      expect(data.success).toBe(false);
    });

    it('Deve rejeitar ativação de licença com status SUSPENDED (HTTP 403)', async () => {
      const req = new Request('http://localhost/api/license/activate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ key: 'VP-TEST-KEY-SUSPENDED', domain: 'site-suspenso.com' })
      });
      const res = await activatePOST(req);
      const data = await res.json();
      expect(res.status).toBe(403);
      expect(data.message).toContain('suspensa');
    });

    it('Deve rejeitar ativação de licença expirada (HTTP 403)', async () => {
      const req = new Request('http://localhost/api/license/activate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ key: 'VP-TEST-KEY-EXPIRED', domain: 'site-expirado.com' })
      });
      const res = await activatePOST(req);
      const data = await res.json();
      expect(res.status).toBe(403);
      expect(data.message).toContain('expirou');
    });

    it('Deve ativar com sucesso e vincular o domínio na primeira ativação', async () => {
      const req = new Request('http://localhost/api/license/activate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ key: 'VP-TEST-KEY-UNLINKED', domain: 'loja-nova.com' })
      });
      const res = await activatePOST(req);
      const data = await res.json();
      expect(res.status).toBe(200);
      expect(data.success).toBe(true);
      expect(data.message).toContain('loja-nova.com');

      // Verifica se gravou no banco/memória
      const saved = await getLicenseByKey('VP-TEST-KEY-UNLINKED');
      expect(saved.domain).toBe('loja-nova.com');
    });

    it('Deve aceitar normalização de domínio com porta, protocolo e maiúsculas', async () => {
      const req = new Request('http://localhost/api/license/activate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ key: 'VP-TEST-KEY-ACTIVE', domain: 'https://MEUDOMINIO.COM:443/caminho/' })
      });
      const res = await activatePOST(req);
      const data = await res.json();
      expect(res.status).toBe(200);
      expect(data.success).toBe(true);
    });

    it('Deve rejeitar quando o domínio for diferente do vinculado (Domain Mismatch - HTTP 403)', async () => {
      const req = new Request('http://localhost/api/license/activate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ key: 'VP-TEST-KEY-ACTIVE', domain: 'outro-dominio-pirata.com' })
      });
      const res = await activatePOST(req);
      const data = await res.json();
      expect(res.status).toBe(403);
      expect(data.message).toContain('já está vinculada e ativa em outro domínio');
    });
  });

  describe('Rota /api/license/verify', () => {
    it('Deve verificar com sucesso licença ativa com domínio correspondente', async () => {
      const req = new Request('http://localhost/api/license/verify', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ key: 'VP-TEST-KEY-ACTIVE', domain: 'meudominio.com' })
      });
      const res = await verifyPOST(req);
      const data = await res.json();
      expect(res.status).toBe(200);
      expect(data.success).toBe(true);
      expect(data.status).toBe('active');
    });

    it('Deve retornar status: expired quando a licença expirou', async () => {
      const req = new Request('http://localhost/api/license/verify', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ key: 'VP-TEST-KEY-EXPIRED', domain: 'site-expirado.com' })
      });
      const res = await verifyPOST(req);
      const data = await res.json();
      expect(data.success).toBe(false);
      expect(data.status).toBe('expired');
      expect(data.message).toContain('expirada');
    });
  });

  describe('Rota /api/license/deactivate (Desativação Remota)', () => {
    it('Deve desvincular o domínio remotamente e liberar a chave para reuso', async () => {
      const req = new Request('http://localhost/api/license/deactivate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ key: 'VP-TEST-KEY-ACTIVE', domain: 'meudominio.com' })
      });
      const res = await deactivatePOST(req);
      const data = await res.json();
      expect(res.status).toBe(200);
      expect(data.success).toBe(true);

      // Verifica se o domínio foi limpo (null)
      const updated = await getLicenseByKey('VP-TEST-KEY-ACTIVE');
      expect(updated.domain).toBeNull();
    });
  });

  describe('Rota CRUD /api/licenses', () => {
    it('Deve listar todas as licenças cadastradas', async () => {
      const res = await licensesGET();
      const data = await res.json();
      expect(res.status).toBe(200);
      expect(data.success).toBe(true);
      expect(Array.isArray(data.licenses)).toBe(true);
      expect(data.licenses.length).toBe(4);
    });

    it('Deve criar nova licença com chave automática no padrão VP-XXXX-XXXX-XXXX', async () => {
      const req = new Request('http://localhost/api/licenses', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          clientName: 'Novo Cliente Vitest',
          clientNif: '500123456',
          expiresAt: '2028-12-31'
        })
      });
      const res = await licensesPOST(req);
      const data = await res.json();
      expect(res.status).toBe(200);
      expect(data.success).toBe(true);
      expect(data.license.key).toMatch(/^VP-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/);
    });

    it('Deve permitir resetar o domínio via PATCH', async () => {
      const req = new Request('http://localhost/api/licenses/test-uuid-active', {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ domain: null })
      });
      const res = await licensePATCH(req, { params: Promise.resolve({ id: 'test-uuid-active' }) });
      const data = await res.json();
      expect(res.status).toBe(200);
      expect(data.success).toBe(true);
      expect(data.license.domain).toBeNull();
    });

    it('Deve permitir excluir licença via DELETE', async () => {
      const res = await licenseDELETE(new Request('http://localhost/api/licenses/test-uuid-expired', { method: 'DELETE' }), {
        params: Promise.resolve({ id: 'test-uuid-expired' })
      });
      const data = await res.json();
      expect(res.status).toBe(200);
      expect(data.success).toBe(true);

      const all = await getAllLicenses();
      expect(all.find(l => l.id === 'test-uuid-expired')).toBeUndefined();
    });
  });
});
