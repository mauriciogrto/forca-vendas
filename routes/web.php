<?php

use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ConfiguracaoController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImportacaoController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\OrcamentoController;
use App\Http\Controllers\PerfilImportacaoController;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\RelatorioController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'form'])->name('login');
    Route::post('/login', [LoginController::class, 'entrar'])->middleware('throttle:10,1')->name('login.entrar');
});

Route::middleware('auth')->group(function () {
    Route::post('/sair', [LoginController::class, 'sair'])->name('sair');
    Route::get('/', [HomeController::class, 'index'])->name('home');

    // Consulta (dados vindos do ERP)
    Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes.index');
    Route::get('/clientes/busca', [ClienteController::class, 'busca'])->name('clientes.busca');

    // Cadastro em campo (liberado por empresa em Configurações) — antes de /clientes/{cliente}
    Route::get('/clientes/novo', [ClienteController::class, 'create'])->name('clientes.create');
    Route::post('/clientes', [ClienteController::class, 'store'])->name('clientes.store');
    Route::get('/clientes/pendentes', [ClienteController::class, 'pendentes'])->middleware('perfil:admin,escritorio')->name('clientes.pendentes');

    Route::get('/clientes/{cliente}', [ClienteController::class, 'show'])->name('clientes.show');
    Route::get('/clientes/{cliente}/editar', [ClienteController::class, 'edit'])->name('clientes.edit');
    Route::put('/clientes/{cliente}', [ClienteController::class, 'update'])->name('clientes.update');
    Route::delete('/clientes/{cliente}', [ClienteController::class, 'destroy'])->name('clientes.destroy');

    Route::get('/produtos', [ProdutoController::class, 'index'])->name('produtos.index');
    Route::get('/produtos/busca', [ProdutoController::class, 'busca'])->name('produtos.busca');
    Route::get('/produtos/{produto}', [ProdutoController::class, 'show'])->name('produtos.show');

    // Orçamentos e pedidos (vivem só no app)
    Route::get('/orcamentos', [OrcamentoController::class, 'index'])->name('orcamentos.index');
    Route::get('/orcamentos/novo', [OrcamentoController::class, 'create'])->name('orcamentos.create');
    Route::post('/orcamentos', [OrcamentoController::class, 'store'])->name('orcamentos.store');
    Route::get('/orcamentos/{orcamento}', [OrcamentoController::class, 'show'])->name('orcamentos.show');
    Route::get('/orcamentos/{orcamento}/editar', [OrcamentoController::class, 'edit'])->name('orcamentos.edit');
    Route::put('/orcamentos/{orcamento}', [OrcamentoController::class, 'update'])->name('orcamentos.update');
    Route::delete('/orcamentos/{orcamento}', [OrcamentoController::class, 'destroy'])->name('orcamentos.destroy');
    Route::get('/orcamentos/{orcamento}/pdf', [OrcamentoController::class, 'pdf'])->name('orcamentos.pdf');
    Route::post('/orcamentos/{orcamento}/enviar', [OrcamentoController::class, 'enviar'])->name('orcamentos.enviar');
    Route::post('/orcamentos/{orcamento}/converter', [OrcamentoController::class, 'converter'])->name('orcamentos.converter');
    Route::post('/orcamentos/{orcamento}/cancelar', [OrcamentoController::class, 'cancelar'])->name('orcamentos.cancelar');

    // Escritório, gestor e administrador
    Route::middleware('perfil:admin,gestor,escritorio')->group(function () {
        Route::get('/relatorios', [RelatorioController::class, 'index'])->name('relatorios.index');
        Route::get('/relatorios/pedidos.csv', [RelatorioController::class, 'pedidosCsv'])->name('relatorios.pedidos');
    });

    // Importação de planilhas do ERP
    Route::middleware('perfil:admin,escritorio')->group(function () {
        Route::get('/importacoes', [ImportacaoController::class, 'index'])->name('importacoes.index');
        Route::get('/importacoes/nova', [ImportacaoController::class, 'create'])->name('importacoes.create');
        Route::post('/importacoes', [ImportacaoController::class, 'store'])->name('importacoes.store');
        Route::get('/importacoes/{importacao}', [ImportacaoController::class, 'show'])->name('importacoes.show');
        Route::post('/importacoes/{importacao}/publicar', [ImportacaoController::class, 'publicar'])->name('importacoes.publicar');
        Route::post('/importacoes/{importacao}/descartar', [ImportacaoController::class, 'descartar'])->name('importacoes.descartar');

        Route::post('/perfis/cabecalhos', [PerfilImportacaoController::class, 'cabecalhos'])->name('perfis.cabecalhos');
        Route::resource('perfis', PerfilImportacaoController::class)
            ->parameters(['perfis' => 'perfil'])
            ->except(['show']);
    });

    // Administração da empresa
    Route::middleware('perfil:admin')->group(function () {
        Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
        Route::get('/usuarios/novo', [UsuarioController::class, 'create'])->name('usuarios.create');
        Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
        Route::get('/usuarios/{id}/editar', [UsuarioController::class, 'edit'])->name('usuarios.edit');
        Route::put('/usuarios/{id}', [UsuarioController::class, 'update'])->name('usuarios.update');

        Route::get('/configuracoes', [ConfiguracaoController::class, 'edit'])->name('configuracoes.edit');
        Route::put('/configuracoes', [ConfiguracaoController::class, 'update'])->name('configuracoes.update');
    });
});
