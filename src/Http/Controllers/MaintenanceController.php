<?php

namespace QOpenPanel\Community\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use QOpenPanel\Community\Services\MaintenanceBridge;
use QOpenPanel\Community\Services\OfficialReleases;

class MaintenanceController extends Controller
{
    private function authorize(Request $request): void
    {
        abort_unless($request->user()?->is_operator && ! $request->attributes->has('mcp_grant'), 403);
    }

    public function releases(Request $request, OfficialReleases $releases)
    {
        $this->authorize($request);
        $data = $request->validate(['channel' => 'required|in:stable,beta']);

        return response()->json(['releases' => $releases->list($data['channel'])]);
    }

    public function request(Request $request, MaintenanceBridge $bridge, OfficialReleases $releases)
    {
        $this->authorize($request);
        abort_unless(array_diff(array_keys($request->all()), ['action', 'version', 'channel', 'password', 'acknowledge', '_token']) === [], 422);
        $data = $request->validate(['action' => 'required|in:update,scan', 'version' => 'exclude_unless:action,update|required|string|max:50', 'channel' => 'exclude_unless:action,update|required|in:stable,beta', 'password' => 'exclude_unless:action,update|required|string|max:1024', 'acknowledge' => 'exclude_unless:action,update|required|accepted']);
        if (! $bridge->status()['connected']) {
            throw ValidationException::withMessages(['action' => __('Agente indisponível ou ocupado')]);
        }
        $sha = null;
        if ($data['action'] === 'update') {
            if (! Hash::check($data['password'], $request->user()->password)) {
                throw ValidationException::withMessages(['password' => __('E-mail ou senha incorretos.')]);
            }
            $sha = $releases->resolve($data['version'], $data['channel']);
        }
        $bridge->request($request->user()->id, $data['action'], $data['version'] ?? null, $sha);

        return back();
    }
}
