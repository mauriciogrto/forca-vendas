<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        return view('usuarios.index', [
            'usuarios' => User::where('empresa_id', $request->user()->empresa_id)->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('usuarios.form', ['usuario' => new User(['perfil' => 'representante', 'ativo' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'perfil' => ['required', Rule::in(array_keys(User::PERFIS))],
            'password' => ['required', Password::min(8)],
        ]);

        User::create($dados + ['empresa_id' => $request->user()->empresa_id, 'ativo' => true]);

        return redirect()->route('usuarios.index')->with('sucesso', 'Usuário criado.');
    }

    public function edit(Request $request, int $id)
    {
        return view('usuarios.form', ['usuario' => $this->daEmpresa($request, $id)]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $usuario = $this->daEmpresa($request, $id);

        $dados = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario->id)],
            'perfil' => ['required', Rule::in(array_keys(User::PERFIS))],
            'password' => ['nullable', Password::min(8)],
        ]);

        $ativo = $request->boolean('ativo');

        if ($usuario->id === $request->user()->id && (! $ativo || $dados['perfil'] !== 'admin')) {
            throw ValidationException::withMessages([
                'perfil' => 'Você não pode desativar nem rebaixar o seu próprio acesso.',
            ]);
        }

        if (empty($dados['password'])) {
            unset($dados['password']);
        }

        $usuario->update($dados + ['ativo' => $ativo]);

        return redirect()->route('usuarios.index')->with('sucesso', 'Usuário atualizado.');
    }

    private function daEmpresa(Request $request, int $id): User
    {
        return User::where('empresa_id', $request->user()->empresa_id)->findOrFail($id);
    }
}
