/**
 * Validador de Integridade e Detecção de Adulteração de Código (Tamper Detection).
 * Compara os hashes SHA-256 dos arquivos vitais do plugin com os originais oficiais assinados.
 */

export const OFFICIAL_MANIFESTS = {
  "1.4.1": {
    settings: "646a1efbbd15328bf5f0daa386b5f508103123ea08b13ea497905d875d363d51",
    ajax: "636fb59d55da25ad07f733048434bd6d362432bb0a6e11e6cd0368287e51681d",
    client: "a9373412a5b5d1d399c4d122766a9369b502d1c38c0b45a776e2aeed71356aed",
    core: "9312d3e0368822d3c61056dbfbe67a26e38f2ef8ff5f2b874c08d43b58058117"
  },
  "1.4.2": {
    settings: "646a1efbbd15328bf5f0daa386b5f508103123ea08b13ea497905d875d363d51",
    ajax: "636fb59d55da25ad07f733048434bd6d362432bb0a6e11e6cd0368287e51681d",
    client: "a9373412a5b5d1d399c4d122766a9369b502d1c38c0b45a776e2aeed71356aed",
    core: "1a5a6c6781247a1019f3a511ae35eb21d3c4f464c44b1a0289238fc15eb1ec67"
  },
  "1.4.3": {
    settings: "ae47088cf323716ba1a850c47a11618f70721052b75cce7ffa3d37fbba5767dc",
    ajax: "636fb59d55da25ad07f733048434bd6d362432bb0a6e11e6cd0368287e51681d",
    client: "a9373412a5b5d1d399c4d122766a9369b502d1c38c0b45a776e2aeed71356aed",
    core: "aff94fc26d8bfb8fe9d43d83b36fa03a34fb27039f650ae7ad8036f6786d84df"
  }
};

/**
 * Valida o manifesto criptográfico de integridade enviado pelo plugin.
 *
 * @param {Object} integrity Manifesto com version, settings, ajax, client e core.
 * @returns {{ isValid: boolean, code?: string, message?: string, tamperedFile?: string }}
 */
export function validatePluginIntegrity(integrity) {
  // Se não enviou o manifesto de integridade, bloqueia como adulteração
  if (!integrity || typeof integrity !== 'object') {
    return {
      isValid: false,
      code: "tampered_code",
      message: "Violação de segurança: manifesto de integridade ausente. O código do plugin não pôde ser autenticado."
    };
  }

  const version = integrity.version || "1.4.1";
  const expected = OFFICIAL_MANIFESTS[version];

  if (!expected) {
    return {
      isValid: false,
      code: "unsupported_version",
      message: `Versão ${version} do plugin não é reconhecida pelos servidores de licenciamento.`
    };
  }

  // Compara cada arquivo vital
  const filesToCheck = [
    { key: 'settings', name: 'class-cegid-settings.php' },
    { key: 'ajax', name: 'class-cegid-ajax-handler.php' },
    { key: 'client', name: 'class-cegid-api-client.php' },
    { key: 'core', name: 'wc-cegid-sync.php' }
  ];

  for (const file of filesToCheck) {
    const receivedHash = (integrity[file.key] || '').toLowerCase().trim();
    const expectedHash = (expected[file.key] || '').toLowerCase().trim();

    if (!receivedHash || receivedHash !== expectedHash) {
      console.warn(`[TAMPER DETECTION] Adulteração detectada no arquivo ${file.name}! Esperado: ${expectedHash}, Recebido: ${receivedHash}`);
      return {
        isValid: false,
        code: "tampered_code",
        tamperedFile: file.name,
        message: `Adulteração de código detectada: o arquivo ${file.name} foi modificado ou violado. Acesso bloqueado.`
      };
    }
  }

  return { isValid: true };
}
