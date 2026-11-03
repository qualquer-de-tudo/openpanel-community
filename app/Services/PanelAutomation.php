<?php

namespace App\Services;

use App\Models\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PanelAutomation
{
    public const SCOPES = ['panel:read', 'panel:write', 'secrets:manage', 'versions:write', 'git:read', 'git:write', 'deploy:plan'];

    public const FIELDS = ['id', 'kind', 'name', 'engine', 'domain', 'repository', 'disk_mb', 'status', 'revision', 'created_at', 'updated_at'];

    public function authorize(string $scope): void
    {
        abort_unless(Auth::check(), 403);
        $grant = request()->attributes->get('mcp_grant');
        if ($grant) {
            abort_unless(in_array($scope, json_decode($grant->scopes, true), true), 403);
            abort_if($grant->revoked_at || now()->gte($grant->expires_at), 403);
        }
    }

    public function resources(): Builder
    {
        $query = Resource::where('user_id', Auth::id());
        $grant = request()->attributes->get('mcp_grant');
        if ($grant && $grant->resource_ids !== null) {
            $query->whereIn('id', json_decode($grant->resource_ids, true));
        }

        return $query;
    }

    public function resource(int $id, bool $lock = false): Resource
    {
        $query = $this->resources()->whereKey($id);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    public function audit(string $action, string $outcome): void
    {
        DB::table('mcp_audit')->insert(['user_id' => Auth::id(), 'grant_id' => request()->attributes->get('mcp_grant')?->id, 'action' => $action, 'outcome' => $outcome, 'created_at' => now()]);
    }

    public function validateConfiguration(array $input, ?Resource $resource = null): array
    {
        $catalog = config('catalog');
        Validator::make($input, ['kind' => ['required', 'string', Rule::in(array_keys($catalog))]])->validate();
        $data = Validator::make($input, [
            'kind' => ['required', 'string', Rule::in(array_keys($catalog))],
            'name' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9][a-z0-9._-]*$/D', Rule::unique('resources')->where(fn ($q) => $q->where('user_id', Auth::id())->where('kind', $input['kind']))->ignore($resource?->id)],
            'engine' => ['required', 'string', Rule::in($catalog[$input['kind']])],
            'domain' => ['nullable', 'string', 'max:253', 'regex:/^(?=.{1,253}$)([a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?\.)+[a-zA-Z]{2,63}$/D'],
            'repository' => ['nullable', 'string', 'max:500', 'url:https'], 'disk_mb' => 'required|integer|min:64|max:1048576',
        ])->validate();
        if (! empty($data['repository'])) {
            $parts = parse_url($data['repository']);
            if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
                throw ValidationException::withMessages(['repository' => __('Use a URL pública do repositório, sem credenciais ou parâmetros.')]);
            }
        }

        return $data;
    }

    public function save(array $input, ?int $id = null, ?int $expected = null): Resource
    {
        $this->authorize('panel:write');

        return DB::transaction(function () use ($input, $id, $expected) {
            $account = DB::table('accounts')->where('user_id', Auth::id())->lockForUpdate()->first();
            abort_unless($account, 403);
            $resource = $id ? $this->resource($id, true) : null;
            if ($resource) {
                abort_unless($expected === $resource->revision, 409);
                abort_unless(($input['kind'] ?? null) === $resource->kind, 422);
            }
            $grant = request()->attributes->get('mcp_grant');
            if (! $resource && $grant && $grant->resource_ids !== null) {
                abort(403);
            }
            $data = $this->validateConfiguration($input, $resource);
            $all = Resource::where('user_id', Auth::id())->when($id, fn ($q) => $q->where('id', '!=', $id));
            if ((clone $all)->sum('disk_mb') + $data['disk_mb'] > $account->disk_mb) {
                throw ValidationException::withMessages(['disk_mb' => __('A reserva ultrapassa o espaço disponível da conta.')]);
            }
            $limit = match ($data['kind']) {
                'sites' => $account->project_limit,'databases' => $account->database_limit,default => 100
            };
            if ((clone $all)->where('kind', $data['kind'])->count() >= $limit) {
                throw ValidationException::withMessages(['name' => __('Limite da conta atingido.')]);
            }
            if ($resource) {
                $resource->fill([...$data, 'revision' => $resource->revision + 1])->save();
            } else {
                $resource = Resource::create([...$data, 'user_id' => Auth::id()]);
                $resource->refresh();
            }

            return $resource;
        });
    }

    public function remove(int $id, int $expected): void
    {
        $this->authorize('panel:write');
        DB::transaction(function () use ($id, $expected) {
            $resource = $this->resource($id, true);
            abort_unless($expected === $resource->revision, 409);
            abort_if(DB::table('secret_bindings')->where('resource_id', $id)->exists(), 409);
            $resource->delete();
        });
    }

    public function manifest(Resource $resource): array
    {
        $template = DB::table('template_installations')->where('resource_id', $resource->id)->where('user_id', Auth::id())->first(['template_id', 'version', 'digest']);

        return ['template' => $template ? (array) $template : null, 'configuration' => $resource->only(['kind', 'name', 'engine', 'domain', 'repository', 'disk_mb']), 'secret_bindings' => DB::table('secret_bindings')->join('vault_secrets', 'secret_bindings.secret_id', '=', 'vault_secrets.id')->where('secret_bindings.user_id', Auth::id())->where('secret_bindings.resource_id', $resource->id)->orderBy('variable')->get(['variable', 'secret_id', 'vault_secrets.version as secret_version'])->map(fn ($b) => (array) $b)->all()];
    }
}
