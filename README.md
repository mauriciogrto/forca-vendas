# Força de Vendas

Sistema web (mobile-first, instalável como PWA) para representantes comerciais que trabalham na rua:
consultar clientes, produtos, preços e estoque, e montar orçamentos e pedidos sem depender do escritório.

Funciona com **qualquer ERP**, mesmo sem API: o escritório exporta relatórios em Excel/CSV e importa no sistema
(*modo consulta*). Orçamentos e pedidos nascem e ficam no app; o escritório baixa o CSV de pedidos fechados
para lançar no ERP.

SaaS multi-empresa: cada empresa só enxerga os próprios dados (`empresa_id` + escopo global).

## Requisitos

- PHP 8.2+ com extensões: mbstring, xml, zip, gd, pdo_mysql, fileinfo
- Composer 2
- MySQL 8 (ou MariaDB 10.6+)

## Instalação

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Crie o banco (`CREATE DATABASE forca_vendas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`),
ajuste `DB_*` no `.env` e rode:

```bash
php artisan migrate
php artisan db:seed          # empresa de demonstração + usuários (senha: demo12345)
php artisan serve
```

Acesse http://127.0.0.1:8000. Usuários da demonstração: veja `database/seeders/DemoSeeder.php`.

### Empresa real

```bash
php artisan empresa:criar
```

Cria a empresa e o primeiro administrador. Depois, o admin cadastra os demais usuários em **Usuários**.

## Primeiros passos no sistema

1. **Importações → Perfis → Novo perfil**: diga qual coluna da planilha do ERP alimenta cada campo
   (envie uma planilha de exemplo para escolher as colunas numa lista).
2. **Importações → Nova importação**: envie a planilha, confira a prévia e publique.
3. O representante já consulta clientes/produtos e monta orçamentos pelo celular.
4. **Relatórios**: o escritório baixa os pedidos fechados em CSV para lançar no ERP.

Planilhas de exemplo em `docs/exemplos/` (CSV UTF-8, CSV Windows-1252 e XLSX com cabeçalho na linha 3).

## Perfis de acesso

| Perfil | Pode |
|---|---|
| Representante | Consultar e criar orçamentos/pedidos; vê e edita só os próprios. Se a empresa liberar, cadastra clientes novos em campo |
| Escritório | Vê os orçamentos de todos, importa planilhas, perfis de importação e relatórios; confere os clientes cadastrados em campo |
| Gestor | Vê os orçamentos de todos e os relatórios |
| Administrador | Tudo, inclusive editar qualquer orçamento, usuários e configurações (desconto máximo, validade, rodapé do PDF, cadastro de clientes em campo) |

## Regras importantes

- Preço, descrição e código do item vêm **sempre do cadastro**; o navegador só informa quantidade e desconto.
- Desconto acima do máximo da empresa é recusado pelo servidor.
- Numeração sequencial por empresa.
- Importação: nada é gravado antes de publicar. Publicar atualiza só as colunas mapeadas (upsert por código).
- Cadastro de clientes em campo (opcional, ligado em Configurações; desligado = modo consulta puro):
  o representante cadastra o cliente (nome, CPF/CNPJ, cidade/UF obrigatórios; CPF/CNPJ repetido na empresa é recusado) e ele
  fica **pendente** com código provisório `NOVO-00001`. Já serve para orçamento e pedido. O escritório abre
  *Clientes → Conferir cadastros*, cadastra no ERP e confirma informando o **código do ERP**; depois disso o cadastro é
  do ERP e não se altera mais pelo app. Duplicado ou engano pode ser excluído enquanto não tem documentos.
  Se o código chegar depois na planilha importada, o cadastro é atualizado sem duplicar. O CSV de pedidos ganha a
  coluna *Situação do cliente* avisando quando o cliente ainda precisa ser cadastrado no ERP.

## Testes

```bash
php artisan test
```

- `tests/Unit`: leitor de planilhas, mapeamento, conversão de números, formatação e total do item.
- `tests/Feature`: login e permissões por perfil, isolamento entre empresas, orçamentos (preço sempre do cadastro, desconto máximo, numeração por empresa, enviar/fechar/cancelar, PDF), relatório CSV, busca por relevância, cadastro de clientes em campo (permissões, validação, conferência, exclusão, importação) e todo o fluxo de importação (prévia, publicar, descartar, desativar ausentes).

Os testes usam SQLite em memória (`phpunit.xml`), então não precisam do MySQL.

## Estrutura

- `app/Services/Importacao/` leitor CSV/XLSX sem dependências, mapeamento e importação
- `app/Services/OrcamentoService.php` criação/edição de orçamentos
- `resources/views/` telas Blade; `public/js/app.js` componentes Alpine; `public/sw.js` service worker (PWA)

## Próximos passos sugeridos

- Edição de clientes do ERP quando houver integração (API) ou módulo ERP próprio
- Conectores de API por ERP
- Cache offline de catálogo para uso sem sinal

## Validação da cópia pública

Em 10/11/2026, a suíte desta cópia concluiu 67 testes e 318 asserções. A execução usou PHP 8.5.11 e o nome da aplicação configurado como `Força de Vendas`.

[Estudo de caso e arquitetura](https://grxsistemas.com.br/portifolio/forca-vendas)