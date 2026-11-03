<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
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
        $catalog = config('catalog');
        $r->validate(['kind' => ['required', 'string', Rule::in(array_keys($catalog))]]);
        $data = $r->validate(['kind' => ['required', Rule::in(array_keys($catalog))], 'name' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9][a-z0-9._-]*$/', Rule::unique('resources')->where(fn ($q) => $q->where('user_id', $r->user()->id)->where('kind', $r->input('kind')))], 'engine' => ['required', Rule::in($catalog[$r->input('kind')] ?? [])], 'domain' => ['nullable', 'string', 'max:253', 'regex:/^(?=.{1,253}$)([a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?\.)+[a-zA-Z]{2,63}$/'], 'repository' => ['nullable', 'url:https', 'max:500'], 'disk_mb' => 'required|integer|min:64|max:1048576']);
        DB::transaction(function () use ($r, $data) {
            $account = DB::table('accounts')->where('user_id', $r->user()->id)->lockForUpdate()->first();
            abort_unless($account, 403);
            $resources = Resource::where('user_id', $r->user()->id);
            if ((clone $resources)->sum('disk_mb') + $data['disk_mb'] > $account->disk_mb) {
                throw ValidationException::withMessages(['disk_mb' => 'A reserva ultrapassa o espaço disponível da conta.']);
            }
            $limit = match ($data['kind']) {
                'sites' => $account->project_limit,'databases' => $account->database_limit,default => 100
            };
            if ((clone $resources)->where('kind', $data['kind'])->count() >= $limit) {
                throw ValidationException::withMessages(['name' => 'Limite da conta atingido.']);
            }
            Resource::create([...$data, 'user_id' => $r->user()->id]);
        });

        return back()->with('message', 'Configuração salva. Execução no Linux aguarda a integração do agente.');
    }

    public function destroy(Request $r, Resource $resource)
    {
        abort_unless($resource->user_id === $r->user()->id, 404);
        $resource->delete();

        return back()->with('message', 'Configuração removida.');
    }
}
