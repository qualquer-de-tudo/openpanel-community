<?php

namespace QOpenPanel\Community\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use QOpenPanel\Community\Models\Resource;
use QOpenPanel\Community\Models\User;
use QOpenPanel\Community\Services\PanelAutomation;
use QOpenPanel\Community\Services\TemplateCatalog;
use QOpenPanel\Community\Services\TemplateInstaller;

class PanelController extends Controller
{
    private function setupDefaults(): array
    {
        $path = storage_path('app/qopenpanel-setup.json');

        return is_file($path) ? json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR) : config('qopenpanel.setup');
    }

    public function login()
    {
        $setup = ! User::exists();

        return Inertia::render('Login', ['setup' => $setup, 'defaults' => $setup ? $this->setupDefaults() : []]);
    }

    public function setup(Request $r)
    {
        $data = $r->validate(['name' => 'required|string|max:80', 'email' => 'required|email|max:255', 'password' => 'required|string|min:12|confirmed']);
        $user = DB::transaction(function () use ($data) {
            DB::table('qopenpanel_setup')->where('id', 1)->lockForUpdate()->first();
            if (User::exists()) {
                abort(403);
            }
            $u = User::create([...$data, 'password' => Hash::make($data['password'])]);
            DB::table('qopenpanel_accounts')->insert(['user_id' => $u->id, 'created_at' => now(), 'updated_at' => now()]);

            return $u;
        });
        Auth::guard('qopenpanel')->login($user);
        $r->session()->regenerate();

        return redirect()->route('qopenpanel.home');
    }

    public function authenticate(Request $r)
    {
        $data = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::guard('qopenpanel')->attempt($data)) {
            throw ValidationException::withMessages(['email' => __('E-mail ou senha incorretos.')]);
        }
        $r->session()->regenerate();

        return redirect()->route('qopenpanel.home');
    }

    public function logout(Request $r)
    {
        Auth::guard('qopenpanel')->logout();
        $r->session()->regenerate(true);
        $r->session()->regenerateToken();

        return redirect()->route('qopenpanel.login');
    }

    public function index(Request $r)
    {
        return Inertia::render('Panel', ['resources' => Resource::where('user_id', $r->user()->id)->latest()->get(), 'account' => DB::table('qopenpanel_accounts')->where('user_id', $r->user()->id)->first(), 'catalog' => config('qopenpanel.catalog'), 'templates' => app(TemplateCatalog::class)->summaries(), 'templateInstallations' => DB::table('qopenpanel_template_installations')->where('user_id', $r->user()->id)->get()->map(fn ($installation) => [...app(TemplateInstaller::class)->inspect($installation->resource_id), 'resource' => app(PanelAutomation::class)->resource($installation->resource_id)->only(['id', 'name', 'revision'])]), 'user' => $r->user()->only('name', 'email'), 'message' => session('message'), 'messageKey' => session('message_key'), 'server' => ['hostname' => gethostname(), 'platform' => PHP_OS_FAMILY, 'php' => PHP_VERSION, 'agent' => false]]);
    }

    public function store(Request $r)
    {
        app(PanelAutomation::class)->save($r->all());

        return back()->with('message', __('ConfiguraÃ§Ã£o salva. ExecuÃ§Ã£o no Linux aguarda a integraÃ§Ã£o do agente.'))->with('message_key', 'ConfiguraÃ§Ã£o salva. ExecuÃ§Ã£o no Linux aguarda a integraÃ§Ã£o do agente.');
    }

    public function destroy(Request $r, Resource $resource)
    {
        abort_unless($resource->user_id === $r->user()->id, 404);
        app(PanelAutomation::class)->remove($resource->id, $resource->revision);

        return back()->with('message', __('ConfiguraÃ§Ã£o removida.'))->with('message_key', 'ConfiguraÃ§Ã£o removida.');
    }
}
