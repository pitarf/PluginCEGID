import prisma from './prisma';

// Inicializa licenças de teste padrão em memória caso o banco de dados PostgreSQL não esteja configurado
if (!global.inMemoryLicenses) {
  global.inMemoryLicenses = [
    {
      id: "test-uuid-1",
      key: "VP-VALEDOPAIS-TEST-KEY-12345",
      domain: "valedopais.local:10004",
      clientName: "Vale do País Teste (Local)",
      clientNif: "516542729",
      status: "ACTIVE",
      expiresAt: new Date("2030-12-31T23:59:59Z"),
      createdAt: new Date(),
      updatedAt: new Date()
    },
    {
      id: "test-uuid-2",
      key: "VP-SUSPENDED-KEY-999",
      domain: "site-suspenso.com",
      clientName: "Cliente Suspenso (Exemplo)",
      clientNif: "123456789",
      status: "SUSPENDED",
      expiresAt: new Date("2030-12-31T23:59:59Z"),
      createdAt: new Date(),
      updatedAt: new Date()
    },
    {
      id: "test-uuid-3",
      key: "VP-EXPIRED-KEY-888",
      domain: "site-expirado.com",
      clientName: "Cliente Expirado (Exemplo)",
      clientNif: "987654321",
      status: "ACTIVE",
      expiresAt: new Date("2020-01-01T00:00:00Z"),
      createdAt: new Date(),
      updatedAt: new Date()
    }
  ];
}

/**
 * Verifica se o banco de dados PostgreSQL está disponível e com migrações ativas
 */
async function isDatabaseAvailable() {
  if (!process.env.DATABASE_URL) {
    return false;
  }
  try {
    // Ping simples no banco
    await prisma.$queryRaw`SELECT 1`;
    return true;
  } catch (err) {
    console.warn("PostgreSQL indisponível. Usando fallback em memória (In-Memory Mock Database).");
    return false;
  }
}

export async function getAllLicenses() {
  if (await isDatabaseAvailable()) {
    try {
      return await prisma.license.findMany({
        orderBy: { createdAt: 'desc' }
      });
    } catch (e) {
      console.error("Erro ao ler Prisma, usando dados em memória:", e);
    }
  }
  // Retorna os dados em memória (copiados para evitar mutações de referência indesejadas)
  return [...global.inMemoryLicenses].sort((a, b) => b.createdAt - a.createdAt);
}

export async function getLicenseByKey(key) {
  if (await isDatabaseAvailable()) {
    try {
      return await prisma.license.findUnique({
        where: { key }
      });
    } catch (e) {
      console.error("Erro ao ler Prisma por chave, usando dados em memória:", e);
    }
  }
  return global.inMemoryLicenses.find(l => l.key === key) || null;
}

export async function createLicense(data) {
  if (await isDatabaseAvailable()) {
    try {
      return await prisma.license.create({
        data: {
          key: data.key,
          domain: data.domain || null,
          clientName: data.clientName,
          clientNif: data.clientNif,
          expiresAt: new Date(data.expiresAt),
          status: data.status || "ACTIVE"
        }
      });
    } catch (e) {
      console.error("Erro ao criar no Prisma, gravando em memória:", e);
    }
  }

  const newLicense = {
    id: "uuid-" + Math.random().toString(36).substr(2, 9),
    key: data.key,
    domain: data.domain || null,
    clientName: data.clientName,
    clientNif: data.clientNif,
    status: data.status || "ACTIVE",
    expiresAt: new Date(data.expiresAt),
    createdAt: new Date(),
    updatedAt: new Date()
  };

  global.inMemoryLicenses.push(newLicense);
  return newLicense;
}

export async function updateLicense(id, data) {
  if (await isDatabaseAvailable()) {
    try {
      return await prisma.license.update({
        where: { id },
        data: data
      });
    } catch (e) {
      console.error("Erro ao atualizar no Prisma, atualizando em memória:", e);
    }
  }

  const index = global.inMemoryLicenses.findIndex(l => l.id === id);
  if (index !== -1) {
    global.inMemoryLicenses[index] = {
      ...global.inMemoryLicenses[index],
      ...data,
      updatedAt: new Date()
    };
    return global.inMemoryLicenses[index];
  }
  return null;
}

export async function deleteLicense(id) {
  if (await isDatabaseAvailable()) {
    try {
      return await prisma.license.delete({
        where: { id }
      });
    } catch (e) {
      console.error("Erro ao deletar no Prisma, deletando em memória:", e);
    }
  }

  const index = global.inMemoryLicenses.findIndex(l => l.id === id);
  if (index !== -1) {
    const deleted = global.inMemoryLicenses.splice(index, 1);
    return deleted[0];
  }
  return null;
}
