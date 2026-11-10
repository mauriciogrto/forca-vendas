/* Força de Vendas — comportamento das telas (Alpine.js) */

const moeda = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const numero = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 3 });

function atrasar(funcao, ms) {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => funcao(...args), ms); };
}

document.addEventListener('alpine:init', () => {
  /**
   * Formulário de orçamento: escolhe o cliente, adiciona produtos e calcula o total.
   * Preço, código e descrição são só exibição: o servidor refaz tudo a partir do cadastro.
   */
  Alpine.data('orcamentoForm', (inicial, descontoMaximo, urls) => ({
    clienteId: inicial.clienteId,
    clienteNome: inicial.clienteNome || '',
    buscaCliente: '',
    sugestoesCliente: [],
    carregandoCliente: false,

    buscaProduto: '',
    sugestoesProduto: [],
    carregandoProduto: false,

    itens: (inicial.itens || []).map(i => ({ ...i })),
    descontoMaximo,
    moeda,

    init() {
      this.buscarClientes = atrasar(this.buscarClientes.bind(this), 250);
      this.buscarProdutos = atrasar(this.buscarProdutos.bind(this), 250);
    },

    async buscarClientes() {
      const q = this.buscaCliente.trim();
      if (q.length < 2) { this.sugestoesCliente = []; return; }
      this.carregandoCliente = true;
      try {
        const r = await fetch(`${urls.clientes}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
        this.sugestoesCliente = r.ok ? await r.json() : [];
      } catch (e) { this.sugestoesCliente = []; }
      this.carregandoCliente = false;
    },
    escolherCliente(c) {
      this.clienteId = c.id;
      this.clienteNome = c.nome;
      this.buscaCliente = '';
      this.sugestoesCliente = [];
    },
    clienteAvulso() {
      this.clienteId = null;
      this.clienteNome = this.buscaCliente.trim();
      this.buscaCliente = '';
      this.sugestoesCliente = [];
    },
    trocarCliente() {
      this.clienteId = null;
      this.clienteNome = '';
    },

    async buscarProdutos() {
      const q = this.buscaProduto.trim();
      if (q.length < 2) { this.sugestoesProduto = []; return; }
      this.carregandoProduto = true;
      try {
        const r = await fetch(`${urls.produtos}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
        this.sugestoesProduto = r.ok ? await r.json() : [];
      } catch (e) { this.sugestoesProduto = []; }
      this.carregandoProduto = false;
    },
    adicionarProduto(p) {
      const existente = this.itens.find(i => i.produto_id === p.id);
      if (existente) {
        existente.quantidade = Number(existente.quantidade) + 1;
      } else {
        this.itens.push({
          produto_id: p.id, codigo: p.codigo, descricao: p.descricao, unidade: p.unidade,
          preco: p.preco, quantidade: 1, desconto: 0,
        });
      }
      this.buscaProduto = '';
      this.sugestoesProduto = [];
    },
    remover(indice) { this.itens.splice(indice, 1); },

    totalItem(i) {
      const q = Number(i.quantidade) || 0;
      const d = Math.min(Math.max(Number(i.desconto) || 0, 0), 100);
      return Math.round(q * i.preco * (1 - d / 100) * 100) / 100;
    },
    get total() { return this.itens.reduce((soma, i) => soma + this.totalItem(i), 0); },
    descontoInvalido(i) { return (Number(i.desconto) || 0) > this.descontoMaximo || (Number(i.desconto) || 0) < 0; },
    get podeSalvar() {
      return this.clienteNome.trim() !== ''
        && this.itens.length > 0
        && this.itens.every(i => Number(i.quantidade) > 0 && !this.descontoInvalido(i));
    },
  }));

  /** Perfil de importação: lê o cabeçalho de uma planilha de exemplo e oferece as colunas. */
  Alpine.data('perfilForm', (inicial, urlCabecalhos) => ({
    entidade: inicial.entidade,
    linha: inicial.linha,
    mapeamento: { ...inicial.mapeamento },
    cabecalhos: [],
    lendo: false,
    erro: '',

    async lerCabecalhos(evento) {
      const arquivo = evento.target.files[0];
      if (!arquivo) return;
      this.lendo = true; this.erro = '';
      const dados = new FormData();
      dados.append('arquivo', arquivo);
      dados.append('linha', this.linha);
      dados.append('_token', document.querySelector('meta[name=csrf-token]').content);
      try {
        const r = await fetch(urlCabecalhos, { method: 'POST', body: dados, headers: { Accept: 'application/json' } });
        const json = await r.json();
        if (!r.ok) { this.erro = json.erro || json.message || 'Não foi possível ler a planilha.'; this.cabecalhos = []; }
        else { this.cabecalhos = json.cabecalhos; this.sugerir(); }
      } catch (e) { this.erro = 'Não foi possível ler a planilha.'; }
      this.lendo = false;
    },

    /** Pré-seleciona a coluna cujo nome lembra o campo. */
    sugerir() {
      const pistas = {
        codigo: ['codigo', 'cod'], nome: ['nome', 'razao', 'cliente'], descricao: ['descricao', 'produto', 'item'],
        preco: ['preco', 'valor', 'venda'], unidade: ['unidade', 'unid', 'un'], categoria: ['grupo', 'categoria', 'familia'],
        estoque: ['estoque', 'saldo', 'qtd', 'quantidade'], documento: ['cnpj', 'cpf', 'documento'],
        telefone: ['telefone', 'fone', 'celular'], email: ['email', 'e mail'], endereco: ['endereco', 'logradouro'],
        cidade: ['cidade', 'municipio'], uf: ['uf', 'estado'],
      };
      const norm = s => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, ' ').trim();
      document.querySelectorAll('[data-campo]').forEach(el => {
        const campo = el.dataset.campo;
        if (this.mapeamento[campo]) return;
        const achada = this.cabecalhos.find(c => (pistas[campo] || []).some(p => norm(c).includes(p)));
        if (achada) this.mapeamento[campo] = achada;
      });
    },
  }));
});

/** Compartilha o PDF pelo menu do celular (WhatsApp etc.); no computador, abre o PDF. */
async function compartilharPdf(url, nome) {
  try {
    const r = await fetch(url, { credentials: 'same-origin' });
    const blob = await r.blob();
    const arquivo = new File([blob], nome, { type: 'application/pdf' });
    if (navigator.canShare && navigator.canShare({ files: [arquivo] })) {
      await navigator.share({ files: [arquivo], title: nome });
      return;
    }
  } catch (e) { /* cai no fallback */ }
  window.open(url, '_blank');
}

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}
