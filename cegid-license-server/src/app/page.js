'use client';

import { useState, useEffect } from 'react';

export default function LicenseManager() {
  const [licenses, setLicenses] = useState([]);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  
  // Estados do formulário
  const [clientName, setClientName] = useState('');
  const [clientNif, setClientNif] = useState('');
  const [customKey, setCustomKey] = useState('');
  const [expiresAt, setExpiresAt] = useState('');
  
  // Sistema de Toasts
  const [toasts, setToasts] = useState([]);

  // Modal de Confirmação Moderno (Substitui o confirm nativo)
  const [confirmModal, setConfirmModal] = useState({
    isOpen: false,
    title: '',
    description: '',
    confirmText: 'Confirmar',
    confirmStyle: 'danger', // 'danger' | 'warning'
    onConfirm: null
  });

  const showToast = (message, type = 'success') => {
    const id = Date.now() + Math.random().toString(36).substr(2, 5);
    setToasts(prev => [...prev, { id, message, type }]);
    setTimeout(() => {
      setToasts(prev => prev.filter(t => t.id !== id));
    }, 4000);
  };

  // Predefine expiração rápida
  const setExpirationPreset = (months) => {
    const d = new Date();
    if (months === 'lifetime') {
      d.setFullYear(d.getFullYear() + 20);
    } else {
      d.setMonth(d.getMonth() + months);
    }
    setExpiresAt(d.toISOString().split('T')[0]);
  };

  // Gera chave formatada VP-XXXX-XXXX-XXXX
  const generateRandomKey = () => {
    const segment = () => Math.random().toString(36).substring(2, 6).toUpperCase();
    setCustomKey(`VP-${segment()}-${segment()}-${segment()}`);
  };

  // Carrega as licenças da API
  const fetchLicenses = async () => {
    try {
      const res = await fetch('/api/licenses');
      const data = await res.json();
      if (data.success) {
        setLicenses(data.licenses);
      } else {
        showToast("Erro ao carregar licenças", "error");
      }
    } catch (err) {
      console.error(err);
      showToast("Falha na conexão com a API", "error");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchLicenses();
  }, []);

  // Cadastro de nova licença
  const handleCreate = async (e) => {
    e.preventDefault();
    if (!clientName || !clientNif || !expiresAt) {
      showToast("Preencha todos os campos obrigatórios.", "error");
      return;
    }

    setSubmitting(true);
    try {
      const res = await fetch('/api/licenses', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
          clientName, 
          clientNif, 
          expiresAt,
          customKey: customKey.trim() || undefined 
        })
      });
      const data = await res.json();
      if (data.success) {
        setLicenses(prev => [data.license, ...prev]);
        showToast("Chave de licença gerada com sucesso!");
        // Limpa campos
        setClientName('');
        setClientNif('');
        setCustomKey('');
        setExpiresAt('');
      } else {
        showToast(data.message || "Erro ao cadastrar licença", "error");
      }
    } catch (err) {
      console.error(err);
      showToast("Erro de rede ao salvar", "error");
    } finally {
      setSubmitting(false);
    }
  };

  // Abre Modal de Reset de Domínio
  const promptResetDomain = (license) => {
    setConfirmModal({
      isOpen: true,
      title: 'Liberar Domínio Vinculado',
      description: `Deseja desvincular o domínio (${license.domain}) da licença de ${license.clientName}? Isso permitirá que a licença seja ativada em outro endereço de site.`,
      confirmText: 'Sim, Liberar Domínio',
      confirmStyle: 'warning',
      onConfirm: () => executeResetDomain(license)
    });
  };

  // Executa o Reset de Domínio
  const executeResetDomain = async (license) => {
    try {
      const res = await fetch(`/api/licenses/${license.id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ domain: null })
      });
      const data = await res.json();
      if (data.success) {
        setLicenses(prev => prev.map(l => l.id === license.id ? { ...l, domain: null } : l));
        showToast(`Domínio desvinculado com sucesso! Licença pronta para reativação.`, 'success');
      } else {
        showToast("Falha ao resetar domínio", "error");
      }
    } catch (err) {
      console.error(err);
      showToast("Erro ao conectar", "error");
    } finally {
      setConfirmModal(prev => ({ ...prev, isOpen: false }));
    }
  };

  // Alterna o status (Ativa / Suspensa)
  const handleToggleStatus = async (license) => {
    const newStatus = license.status === 'ACTIVE' ? 'SUSPENDED' : 'ACTIVE';
    try {
      const res = await fetch(`/api/licenses/${license.id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ status: newStatus })
      });
      const data = await res.json();
      if (data.success) {
        setLicenses(prev => prev.map(l => l.id === license.id ? data.license : l));
        showToast(
          newStatus === 'ACTIVE' 
            ? `Licença de ${license.clientName} reativada!` 
            : `Licença de ${license.clientName} suspensa com sucesso!`,
          newStatus === 'ACTIVE' ? 'success' : 'warning'
        );
      } else {
        showToast("Falha ao modificar status", "error");
      }
    } catch (err) {
      console.error(err);
      showToast("Erro ao conectar", "error");
    }
  };

  // Abre Modal de Exclusão
  const promptDelete = (id, name) => {
    setConfirmModal({
      isOpen: true,
      title: 'Excluir Licença Permanentemente',
      description: `Esta ação não pode ser desfeita. A licença de ${name} será removida do banco de dados e todas as validações ativas deste cliente serão bloqueadas imediatamente.`,
      confirmText: 'Sim, Excluir Licença',
      confirmStyle: 'danger',
      onConfirm: () => executeDelete(id, name)
    });
  };

  // Executa a Exclusão
  const executeDelete = async (id, name) => {
    try {
      const res = await fetch(`/api/licenses/${id}`, {
        method: 'DELETE'
      });
      const data = await res.json();
      if (data.success) {
        setLicenses(prev => prev.filter(l => l.id !== id));
        showToast(`Licença de ${name} excluída permanentemente.`, "info");
      } else {
        showToast("Falha ao excluir licença", "error");
      }
    } catch (err) {
      console.error(err);
      showToast("Erro ao conectar", "error");
    } finally {
      setConfirmModal(prev => ({ ...prev, isOpen: false }));
    }
  };

  // Copia a chave para a área de transferência
  const copyToClipboard = (key) => {
    navigator.clipboard.writeText(key);
    showToast("Chave copiada para a área de transferência!");
  };

  // Métricas
  const now = new Date();
  const activeCount = licenses.filter(l => l.status === 'ACTIVE' && new Date(l.expiresAt) >= now).length;
  const suspendedCount = licenses.filter(l => l.status === 'SUSPENDED').length;
  const expiredCount = licenses.filter(l => l.status === 'ACTIVE' && new Date(l.expiresAt) < now).length;

  // Filtragem da busca
  const filteredLicenses = licenses.filter(l => 
    l.clientName.toLowerCase().includes(searchQuery.toLowerCase()) ||
    l.key.toLowerCase().includes(searchQuery.toLowerCase()) ||
    l.clientNif.includes(searchQuery)
  );

  return (
    <main className="min-h-screen bg-[#0b0f19] text-slate-100 font-sans p-4 sm:p-8 lg:p-10 relative overflow-hidden">
      
      {/* Luzes neon de fundo com efeito blur */}
      <div className="absolute top-[-10%] left-[-10%] w-[40rem] h-[40rem] bg-indigo-900/20 rounded-full blur-[120px] pointer-events-none"></div>
      <div className="absolute bottom-[-10%] right-[-10%] w-[40rem] h-[40rem] bg-purple-900/20 rounded-full blur-[120px] pointer-events-none"></div>

      {/* Container Principal */}
      <div className="max-w-7xl mx-auto relative z-10">
        
        {/* Header */}
        <header className="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 pb-6 border-b border-slate-800/60 gap-4">
          <div className="flex items-center gap-3">
            <span className="text-3xl">🔏</span>
            <div>
              <h1 className="text-xl sm:text-2xl font-bold tracking-tight bg-gradient-to-r from-blue-400 via-indigo-400 to-purple-400 bg-clip-text text-transparent">
                CEGID Sync - License Manager
              </h1>
              <p className="text-xs text-slate-400 mt-1">Servidor de ativação e licenciamento de software para WordPress/WooCommerce</p>
            </div>
          </div>
          <div className="flex items-center gap-2 self-start sm:self-auto">
            <span className="text-xs bg-slate-800/80 px-3.5 py-1.5 rounded-full border border-slate-700/40 text-slate-300 font-semibold flex items-center gap-2">
              <span className="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
              Oracle Cloud VPS [Docker]
            </span>
          </div>
        </header>

        {/* Guia Rápido e Dicas de Uso */}
        <details className="mb-8 bg-slate-900/40 border border-slate-800/60 rounded-xl p-4 sm:p-5 text-sm text-slate-300 transition-all open:bg-slate-900/70 shadow-lg" open>
          <summary className="font-semibold text-slate-200 cursor-pointer flex items-center justify-between select-none">
            <span className="flex items-center gap-2.5">
              <span className="text-xl">💡</span>
              <span className="text-sm sm:text-base font-bold">Guia Rápido: Como Operar o Servidor de Licenças</span>
              <span className="hidden sm:inline-block text-[10px] bg-indigo-500/20 text-indigo-300 font-bold px-2.5 py-0.5 rounded-full border border-indigo-500/30 tracking-wider">DICAS DE USO</span>
            </span>
            <span className="text-xs text-slate-400 font-normal">Ocultar / Mostrar ▼</span>
          </summary>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5 mt-4 pt-4 border-t border-slate-800/60 text-xs">
            <div className="bg-slate-950/60 border border-slate-800/40 p-3.5 rounded-xl">
              <div className="font-bold text-slate-200 mb-1.5 flex items-center gap-2">
                <span className="w-5 h-5 bg-indigo-600 text-white rounded-full flex items-center justify-center text-[10px] font-bold">1</span>
                <span>Gerar Nova Licença</span>
              </div>
              <p className="text-slate-400 leading-relaxed">Preencha o Nome e NIF da empresa. Use "🎲 Gerar Chave" para o padrão <code className="text-indigo-300">VP-XXXX-XXXX-XXXX</code> ou digite uma chave personalizada.</p>
            </div>
            <div className="bg-slate-950/60 border border-slate-800/40 p-3.5 rounded-xl">
              <div className="font-bold text-slate-200 mb-1.5 flex items-center gap-2">
                <span className="w-5 h-5 bg-indigo-600 text-white rounded-full flex items-center justify-center text-[10px] font-bold">2</span>
                <span>Vínculo de Domínio</span>
              </div>
              <p className="text-slate-400 leading-relaxed">Deixe o domínio vazio no cadastro. Quando o cliente digitar a chave no WordPress, ela se vinculará de forma transparente ao domínio dele.</p>
            </div>
            <div className="bg-slate-950/60 border border-slate-800/40 p-3.5 rounded-xl">
              <div className="font-bold text-slate-200 mb-1.5 flex items-center gap-2">
                <span className="w-5 h-5 bg-indigo-600 text-white rounded-full flex items-center justify-center text-[10px] font-bold">3</span>
                <span>Resetar Domínio</span>
              </div>
              <p className="text-slate-400 leading-relaxed">Se o cliente migrar de domínio ou servidor, toque em "Resetar" para liberar a chave sem precisar emitir uma nova licença comercial.</p>
            </div>
            <div className="bg-slate-950/60 border border-slate-800/40 p-3.5 rounded-xl">
              <div className="font-bold text-slate-200 mb-1.5 flex items-center gap-2">
                <span className="w-5 h-5 bg-indigo-600 text-white rounded-full flex items-center justify-center text-[10px] font-bold">4</span>
                <span>Status e Ações</span>
              </div>
              <p className="text-slate-400 leading-relaxed">Suspenda licenças de clientes inadimplentes com 1 toque. Todas as ações críticas solicitam confirmação com modal moderno.</p>
            </div>
          </div>
        </details>

        {/* Grade de Métricas */}
        <section className="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-8">
          <div className="bg-slate-900/50 border border-slate-800/80 p-4 sm:p-5 rounded-2xl backdrop-blur-md">
            <span className="text-xs text-slate-400 font-medium">Total de Licenças</span>
            <div className="text-2xl sm:text-3xl font-extrabold mt-1 text-slate-100">{licenses.length}</div>
          </div>
          <div className="bg-slate-900/50 border border-slate-800/80 p-4 sm:p-5 rounded-2xl backdrop-blur-md">
            <span className="text-xs text-emerald-400 font-medium flex items-center gap-1.5">
              <span className="w-2 h-2 bg-emerald-500 rounded-full"></span> Ativas
            </span>
            <div className="text-2xl sm:text-3xl font-extrabold mt-1 text-emerald-400">{activeCount}</div>
          </div>
          <div className="bg-slate-900/50 border border-slate-800/80 p-4 sm:p-5 rounded-2xl backdrop-blur-md">
            <span className="text-xs text-rose-400 font-medium flex items-center gap-1.5">
              <span className="w-2 h-2 bg-rose-500 rounded-full"></span> Suspensas
            </span>
            <div className="text-2xl sm:text-3xl font-extrabold mt-1 text-rose-400">{suspendedCount}</div>
          </div>
          <div className="bg-slate-900/50 border border-slate-800/80 p-4 sm:p-5 rounded-2xl backdrop-blur-md">
            <span className="text-xs text-amber-400 font-medium flex items-center gap-1.5">
              <span className="w-2 h-2 bg-amber-500 rounded-full"></span> Expiradas
            </span>
            <div className="text-2xl sm:text-3xl font-extrabold mt-1 text-amber-400">{expiredCount}</div>
          </div>
        </section>

        {/* Layout Principal: Formulário + Listagem */}
        <section className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
          
          {/* Card de Criação de Licença */}
          <div className="lg:col-span-4 bg-slate-900/60 border border-slate-800/80 rounded-2xl p-5 sm:p-6 backdrop-blur-md shadow-xl">
            <h2 className="text-base sm:text-lg font-bold mb-1 flex items-center gap-2">
              <span>✨</span> Gerar Nova Licença
            </h2>
            <p className="text-xs text-slate-400 mb-6">Crie uma chave comercial para o cliente ativar no plugin WordPress.</p>

            <form onSubmit={handleCreate} className="space-y-4 text-xs">
              <div>
                <label className="block text-slate-300 font-semibold mb-1.5">Nome do Cliente / Empresa *</label>
                <input 
                  type="text" 
                  value={clientName}
                  onChange={(e) => setClientName(e.target.value)}
                  placeholder="Ex: Vale do País Lda" 
                  className="w-full bg-[#111625] border border-slate-800 focus:border-indigo-500 rounded-xl px-3.5 py-2.5 text-xs text-slate-100 focus:outline-none transition-all placeholder:text-slate-600"
                  required
                />
              </div>

              <div>
                <label className="block text-slate-300 font-semibold mb-1.5">NIF do Cliente *</label>
                <input 
                  type="text" 
                  value={clientNif}
                  onChange={(e) => setClientNif(e.target.value)}
                  placeholder="Ex: 516542729" 
                  className="w-full bg-[#111625] border border-slate-800 focus:border-indigo-500 rounded-xl px-3.5 py-2.5 text-xs text-slate-100 focus:outline-none transition-all placeholder:text-slate-600"
                  required
                />
              </div>

              <div>
                <div className="flex justify-between items-center mb-1.5">
                  <label className="text-slate-300 font-semibold">Chave da Licença (Opcional)</label>
                  <button 
                    type="button" 
                    onClick={generateRandomKey}
                    className="text-[11px] text-indigo-400 hover:text-indigo-300 font-bold flex items-center gap-1 active:scale-95 py-0.5"
                  >
                    🎲 Gerar Chave
                  </button>
                </div>
                <input 
                  type="text" 
                  value={customKey}
                  onChange={(e) => setCustomKey(e.target.value.toUpperCase())}
                  placeholder="VP-XXXX-XXXX-XXXX (Deixe vazio para automático)" 
                  className="w-full bg-[#111625] border border-slate-800 focus:border-indigo-500 rounded-xl px-3.5 py-2.5 text-xs font-mono text-indigo-300 focus:outline-none transition-all placeholder:text-slate-600 uppercase"
                />
              </div>

              <div>
                <div className="flex justify-between items-center mb-1.5">
                  <label className="text-slate-300 font-semibold">Data de Expiração *</label>
                  <span className="text-[10px] text-slate-500">Presets rápidos:</span>
                </div>
                
                {/* Botões de presets táteis */}
                <div className="grid grid-cols-4 gap-1.5 mb-2.5">
                  <button 
                    type="button" 
                    onClick={() => setExpirationPreset(1)}
                    className="py-1.5 rounded-lg bg-slate-800/80 hover:bg-slate-700 text-[11px] font-bold text-slate-300 transition-all active:scale-95 border border-slate-700/50"
                  >
                    1 Mês
                  </button>
                  <button 
                    type="button" 
                    onClick={() => setExpirationPreset(6)}
                    className="py-1.5 rounded-lg bg-slate-800/80 hover:bg-slate-700 text-[11px] font-bold text-slate-300 transition-all active:scale-95 border border-slate-700/50"
                  >
                    6 Meses
                  </button>
                  <button 
                    type="button" 
                    onClick={() => setExpirationPreset(12)}
                    className="py-1.5 rounded-lg bg-slate-800/80 hover:bg-slate-700 text-[11px] font-bold text-slate-300 transition-all active:scale-95 border border-slate-700/50"
                  >
                    1 Ano
                  </button>
                  <button 
                    type="button" 
                    onClick={() => setExpirationPreset('lifetime')}
                    className="py-1.5 rounded-lg bg-indigo-600/30 hover:bg-indigo-600/50 text-[11px] font-bold text-indigo-300 transition-all active:scale-95 border border-indigo-500/40"
                  >
                    Vitalícia
                  </button>
                </div>

                <input 
                  type="date" 
                  value={expiresAt}
                  onChange={(e) => setExpiresAt(e.target.value)}
                  className="w-full bg-[#111625] border border-slate-800 focus:border-indigo-500 rounded-xl px-3.5 py-2.5 text-xs text-slate-100 focus:outline-none transition-all"
                  required
                />
              </div>

              <button 
                type="submit" 
                disabled={submitting}
                className="w-full mt-4 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold py-3 rounded-xl transition-all shadow-lg shadow-indigo-600/25 disabled:opacity-50 active:scale-[0.98] text-xs flex items-center justify-center gap-2"
              >
                {submitting ? (
                  <>
                    <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                    <span>Criando Licença...</span>
                  </>
                ) : (
                  <span>Criar e Emitir Licença</span>
                )}
              </button>
            </form>
          </div>

          {/* Listagem de Licenças: CARDS no Mobile / TABELA no Desktop */}
          <div className="lg:col-span-8">
            <div className="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-4 sm:p-6 backdrop-blur-md shadow-xl">
              
              {/* Barra de Busca e Título */}
              <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                <div>
                  <h2 className="text-base sm:text-lg font-bold flex items-center gap-2">
                    <span>📋</span> Licenças Emitidas
                  </h2>
                  <p className="text-xs text-slate-400 mt-0.5">Gerencie os clientes, domínios e ciclos de vida de ativação.</p>
                </div>

                <div className="relative w-full sm:w-64">
                  <input 
                    type="text" 
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    placeholder="Buscar por cliente, chave ou NIF..." 
                    className="w-full bg-[#111625] border border-slate-800 focus:border-indigo-500/80 rounded-xl pl-9 pr-4 py-2 text-xs text-slate-100 focus:outline-none transition-all placeholder:text-slate-600"
                  />
                  <span className="absolute left-3 top-2.5 text-slate-600 text-xs">🔍</span>
                </div>
              </div>

              {/* Estado de Carregamento e Vazio */}
              {loading ? (
                <div className="flex flex-col items-center justify-center py-20 text-slate-400 gap-3">
                  <span className="w-10 h-10 border-4 border-slate-800 border-t-indigo-500 rounded-full animate-spin"></span>
                  <p className="text-xs">Consultando base de dados...</p>
                </div>
              ) : filteredLicenses.length === 0 ? (
                <div className="py-20 text-center text-slate-500 border border-dashed border-slate-800/60 rounded-xl">
                  <span className="text-3xl block mb-2">📁</span>
                  <p className="text-sm font-semibold">Nenhuma licença encontrada.</p>
                  <p className="text-xs mt-1 text-slate-600">Altere o termo da busca ou cadastre uma nova licença.</p>
                </div>
              ) : (
                <>
                  {/* ======================================================== */}
                  {/* 1. VISUALIZAÇÃO MOBILE & TABLET: CARDS INDIVIDUAIS       */}
                  {/* ======================================================== */}
                  <div className="block lg:hidden space-y-4">
                    {filteredLicenses.map((l) => {
                      const isExpired = new Date(l.expiresAt) < now;
                      return (
                        <div 
                          key={l.id} 
                          className="bg-slate-950/70 border border-slate-800/80 hover:border-slate-700/80 rounded-2xl p-4 shadow-lg transition-all"
                        >
                          {/* Header do Card: Nome e Badge de Status */}
                          <div className="flex items-start justify-between gap-3 mb-3">
                            <div>
                              <h3 className="font-bold text-sm text-slate-100">{l.clientName}</h3>
                              <p className="text-[11px] text-slate-400 mt-0.5">NIF: <span className="text-slate-300 font-mono">{l.clientNif}</span></p>
                            </div>

                            {/* Badge de Status */}
                            {l.status === 'SUSPENDED' ? (
                              <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-500/15 text-rose-400 border border-rose-500/30">
                                <span className="w-1.5 h-1.5 bg-rose-500 rounded-full"></span>
                                Suspensa
                              </span>
                            ) : isExpired ? (
                              <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-500/15 text-amber-400 border border-amber-500/30">
                                <span className="w-1.5 h-1.5 bg-amber-500 rounded-full animate-pulse"></span>
                                Expirada
                              </span>
                            ) : (
                              <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">
                                <span className="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                                Ativa
                              </span>
                            )}
                          </div>

                          {/* Box da Chave de Licença */}
                          <div className="bg-[#111625] border border-slate-800/90 rounded-xl p-2.5 flex items-center justify-between gap-2 mb-3">
                            <code className="text-xs font-mono font-bold text-indigo-300 select-all truncate">
                              {l.key}
                            </code>
                            <button 
                              onClick={() => copyToClipboard(l.key)}
                              className="px-2.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-xs font-semibold flex items-center gap-1 active:scale-95 transition-all shrink-0"
                              title="Copiar Chave"
                            >
                              📋 Copiar
                            </button>
                          </div>

                          {/* Grade de Metadados: Domínio e Expiração */}
                          <div className="grid grid-cols-2 gap-2 text-xs bg-slate-900/40 border border-slate-800/50 rounded-xl p-3 mb-3.5">
                            <div>
                              <span className="text-[10px] text-slate-500 uppercase tracking-wider font-semibold block mb-1">Domínio</span>
                              {l.domain ? (
                                <div className="flex flex-col gap-1 items-start">
                                  <span className="text-slate-200 font-semibold truncate max-w-full">{l.domain}</span>
                                  <button 
                                    onClick={() => promptResetDomain(l)}
                                    className="text-[10px] text-rose-400 hover:text-rose-300 font-bold border border-rose-500/30 bg-rose-500/10 px-2 py-0.5 rounded-md transition-all active:scale-95"
                                  >
                                    Resetar
                                  </button>
                                </div>
                              ) : (
                                <span className="text-slate-500 italic text-[11px]">Pendente ativação</span>
                              )}
                            </div>

                            <div>
                              <span className="text-[10px] text-slate-500 uppercase tracking-wider font-semibold block mb-1">Expiração</span>
                              <span className="text-slate-300 font-semibold block">
                                {new Date(l.expiresAt).toLocaleDateString('pt-PT')}
                              </span>
                              <span className="text-[10px] text-slate-500 mt-0.5 block">
                                {isExpired ? 'Licença vencida' : 'Válida'}
                              </span>
                            </div>
                          </div>

                          {/* Barra de Ações Táteis do Card */}
                          <div className="flex items-center gap-2 pt-1 border-t border-slate-800/60">
                            <button 
                              onClick={() => handleToggleStatus(l)}
                              className={`flex-1 py-2.5 rounded-xl font-bold text-xs transition-all active:scale-95 text-center ${
                                l.status === 'ACTIVE' 
                                  ? 'bg-amber-600/20 text-amber-300 border border-amber-600/30 hover:bg-amber-600/30' 
                                  : 'bg-emerald-600/20 text-emerald-300 border border-emerald-600/30 hover:bg-emerald-600/30'
                              }`}
                            >
                              {l.status === 'ACTIVE' ? '⏸️ Suspender' : '▶️ Reativar'}
                            </button>
                            <button 
                              onClick={() => promptDelete(l.id, l.clientName)}
                              className="py-2.5 px-4 rounded-xl font-bold text-xs bg-rose-600/20 text-rose-300 border border-rose-600/30 hover:bg-rose-600/30 transition-all active:scale-95 text-center"
                            >
                              🗑️ Excluir
                            </button>
                          </div>
                        </div>
                      );
                    })}
                  </div>

                  {/* ======================================================== */}
                  {/* 2. VISUALIZAÇÃO DESKTOP: TABELA COMPLETA (hidden lg:block)*/}
                  {/* ======================================================== */}
                  <div className="hidden lg:block overflow-x-auto">
                    <table className="w-full text-left border-collapse">
                      <thead>
                        <tr className="border-b border-slate-800/60 text-slate-400 text-xs font-bold tracking-wider">
                          <th className="pb-3 pr-4">Cliente / NIF</th>
                          <th className="pb-3 px-4">Chave da Licença</th>
                          <th className="pb-3 px-4">Domínio Vinculado</th>
                          <th className="pb-3 px-4">Status / Expiração</th>
                          <th className="pb-3 pl-4 text-right">Ações</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-slate-800/40">
                        {filteredLicenses.map((l) => {
                          const isExpired = new Date(l.expiresAt) < now;
                          return (
                            <tr key={l.id} className="text-xs group hover:bg-slate-800/20 transition-all">
                              
                              {/* Cliente / NIF */}
                              <td className="py-4 pr-4">
                                <p className="font-semibold text-slate-200">{l.clientName}</p>
                                <p className="text-[10px] text-slate-500 mt-0.5">NIF: {l.clientNif}</p>
                              </td>

                              {/* Chave de Licença */}
                              <td className="py-4 px-4 font-mono">
                                <div className="flex items-center gap-2">
                                  <code className="bg-[#111625] px-2.5 py-1 rounded border border-slate-800 text-indigo-300 font-semibold select-all">
                                    {l.key}
                                  </code>
                                  <button 
                                    onClick={() => copyToClipboard(l.key)}
                                    className="text-slate-500 hover:text-indigo-400 p-1 rounded hover:bg-slate-800/80 active:scale-95 transition-all"
                                    title="Copiar Chave"
                                  >
                                    📋
                                  </button>
                                </div>
                              </td>

                              {/* Domínio */}
                              <td className="py-4 px-4">
                                {l.domain ? (
                                  <div className="flex items-center gap-2">
                                    <span className="text-slate-200 font-semibold">{l.domain}</span>
                                    <button 
                                      onClick={() => promptResetDomain(l)}
                                      className="text-[10px] text-rose-400 hover:text-rose-300 border border-rose-500/20 bg-rose-500/10 px-2 py-0.5 rounded transition-all active:scale-95 font-bold"
                                      title="Desvincular Domínio (permitir ativação em novo site)"
                                    >
                                      Resetar
                                    </button>
                                  </div>
                                ) : (
                                  <span className="text-slate-500 italic">Pendente ativação</span>
                                )}
                              </td>

                              {/* Status / Expiração */}
                              <td className="py-4 px-4">
                                <div className="flex flex-col gap-1">
                                  {l.status === 'SUSPENDED' ? (
                                    <span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20 w-fit">
                                      <span className="w-1.5 h-1.5 bg-rose-500 rounded-full"></span>
                                      Suspensa
                                    </span>
                                  ) : isExpired ? (
                                    <span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20 w-fit">
                                      <span className="w-1.5 h-1.5 bg-amber-500 rounded-full animate-pulse"></span>
                                      Expirada
                                    </span>
                                  ) : (
                                    <span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 w-fit">
                                      <span className="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                                      Ativa
                                    </span>
                                  )}
                                  <span className="text-[10px] text-slate-400">
                                    Expira em: {new Date(l.expiresAt).toLocaleDateString('pt-PT')}
                                  </span>
                                </div>
                              </td>

                              {/* Ações */}
                              <td className="py-4 pl-4 text-right">
                                <div className="flex justify-end gap-2 opacity-90 group-hover:opacity-100 transition-opacity">
                                  <button 
                                    onClick={() => handleToggleStatus(l)}
                                    className={`px-3 py-1.5 rounded-lg font-semibold text-[11px] transition-all active:scale-95 ${
                                      l.status === 'ACTIVE' 
                                        ? 'bg-amber-600/20 text-amber-400 border border-amber-600/30 hover:bg-amber-600/30' 
                                        : 'bg-emerald-600/20 text-emerald-400 border border-emerald-600/30 hover:bg-emerald-600/30'
                                    }`}
                                  >
                                    {l.status === 'ACTIVE' ? 'Suspender' : 'Reativar'}
                                  </button>
                                  <button 
                                    onClick={() => promptDelete(l.id, l.clientName)}
                                    className="px-3 py-1.5 rounded-lg font-semibold text-[11px] bg-rose-600/15 text-rose-400 border border-rose-600/30 hover:bg-rose-600/25 transition-all active:scale-95"
                                  >
                                    Excluir
                                  </button>
                                </div>
                              </td>

                            </tr>
                          );
                        })}
                      </tbody>
                    </table>
                  </div>
                </>
              )}

            </div>
          </div>

        </section>

      </div>

      {/* Modal de Confirmação Moderno */}
      {confirmModal.isOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm animate-in fade-in duration-150">
          <div className="bg-[#111625] border border-slate-800 rounded-2xl p-6 max-w-md w-full shadow-2xl relative">
            <div className="flex items-center gap-3 mb-3">
              <span className="text-2xl">
                {confirmModal.confirmStyle === 'danger' ? '⚠️' : '🔄'}
              </span>
              <h3 className="text-base font-bold text-slate-100">
                {confirmModal.title}
              </h3>
            </div>
            
            <p className="text-xs text-slate-300 leading-relaxed mb-6">
              {confirmModal.description}
            </p>

            <div className="flex flex-col sm:flex-row justify-end gap-2.5">
              <button
                type="button"
                onClick={() => setConfirmModal(prev => ({ ...prev, isOpen: false }))}
                className="w-full sm:w-auto px-5 py-2.5 rounded-xl text-xs font-semibold text-slate-400 hover:text-slate-200 bg-slate-800/80 hover:bg-slate-800 border border-slate-700/60 transition-all active:scale-95"
              >
                Cancelar
              </button>
              <button
                type="button"
                onClick={confirmModal.onConfirm}
                className={`w-full sm:w-auto px-5 py-2.5 rounded-xl text-xs font-bold transition-all active:scale-95 shadow-lg ${
                  confirmModal.confirmStyle === 'danger'
                    ? 'bg-rose-600 hover:bg-rose-500 text-white shadow-rose-600/20'
                    : 'bg-amber-600 hover:bg-amber-500 text-white shadow-amber-600/20'
                }`}
              >
                {confirmModal.confirmText}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Renderização do Container de Toasts de Notificação */}
      <div className="fixed bottom-6 right-6 z-50 flex flex-col gap-2.5 max-w-sm">
        {toasts.map((t) => {
          let borderCol = 'border-slate-700';
          let textIcon = 'ℹ️';
          if (t.type === 'success') {
            borderCol = 'border-emerald-500/40 bg-emerald-950/40 text-emerald-300';
            textIcon = '✅';
          } else if (t.type === 'error') {
            borderCol = 'border-rose-500/40 bg-rose-950/40 text-rose-300';
            textIcon = '❌';
          } else if (t.type === 'warning') {
            borderCol = 'border-amber-500/40 bg-amber-950/40 text-amber-300';
            textIcon = '⚠️';
          }
          return (
            <div 
              key={t.id} 
              className={`flex items-center gap-2.5 px-4 py-3 rounded-xl border backdrop-blur-md shadow-2xl text-xs font-medium animate-in slide-in-from-bottom-5 duration-200 bg-[#0f1422] ${borderCol}`}
            >
              <span>{textIcon}</span>
              <p className="leading-snug">{t.message}</p>
            </div>
          );
        })}
      </div>

    </main>
  );
}
