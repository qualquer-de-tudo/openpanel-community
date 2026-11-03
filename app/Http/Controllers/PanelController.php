<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use App\Models\User;
use App\Services\PanelAutomation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PanelController extends Controller
{
    public function login()
    {
        return Inertia::render('Login', ['setup' => ! User::exists()]);
    }

    public function setup(Request $r)
    {
        $data = $r->validate(['name' => 'required|string|max:80', 'email' => 'required|email|max:255', 'password' => 'required|string|min:12|confirmed']);
        $user = DB::transaction(function () use ($data) {
            if (User::exists()) {
                abort(403);
            }
            $u = User::create([...$data, 'password' => Hash::make($data['password'])]);
            DB::table('accounts')->insert(['user_id' => $u->id, 'created_at' => now(), 'updated_at' => now()]);

            return $u;
        });
        Auth::login($user);
        $r->session()->regenerate();

        return redirect('/');
    }

    public function authenticate(Request $r)
    {
        $data = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($data)) {
            throw ValidationException::withMessages(['email' => 'E-mail ou senha incorretos.']);
        }
        $r->session()->regenerate();

        return redirect('/');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/login');
    }

    public function index(Request $r)
    {
        return Inertia::render('Panel', ['resources' => Resource::where('user_id', $r->user()->id)->latest()->get(), 'account' => DB::table('accounts')->where('user_id', $r->user()->id)->first(), 'catalog' => config('catalog'), 'user' => $r->user()->only('name', 'email'), 'message' => session('message'), 'server' => ['hostname' => gethostname(), 'platform' => PHP_OS_FAMILY, 'php' => PHP_VERSION, 'agent' => false]]);
    }

    public function store(Request $r)
    {
        app(PanelAutomation::class)->save($r->all());

        return back()->with('message', 'Configuração salva. Execução no Linux aguarda a integração do agente.');
    }

    public function destroy(Request $r, Resource $resource)
    {
        abort_unless($resource->user_id === $r->user()->id, 404);
        app(PanelAutomation::class)->remove($resource->id, $resource->revision);

        return back()->with('message', 'Configuração removida.');
    }
}
