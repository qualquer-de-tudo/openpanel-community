<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BlindVault
{
    public function __construct(private PanelAutomation $panel) {}

    public function metadata(string $id): array
    {
        $secret = $this->find($id);

        return ['reference' => $secret->id, 'resource_id' => $secret->resource_id, 'purpose' => $secret->purpose, 'version' => $secret->version, 'created_at' => $secret->created_at, 'updated_at' => $secret->updated_at];
    }

    public function find(string $id): object
    {
        $secret = DB::table('vault_secrets')->where('user_id', Auth::id())->where('id', $id)->first();
        abort_unless($secret, 404);
        $this->panel->resource($secret->resource_id);

        return $secret;
    }

    public function generate(int $resourceId): array
    {
        return DB::transaction(function () use ($resourceId) {
            $this->panel->resource($resourceId, true);
            abort_if(DB::table('vault_secrets')->where('resource_id', $resourceId)->count() >= 100, 409);
            $id = (string) Str::uuid();
            DB::table('vault_secrets')->insert(['id' => $id, 'user_id' => Auth::id(), 'resource_id' => $resourceId, 'purpose' => 'application', 'ciphertext' => Crypt::encryptString(bin2hex(random_bytes(48))), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);

            return $this->metadata($id);
        });
    }

    public function rotate(string $id): array
    {
        return DB::transaction(function () use ($id) {
            $secret = $this->find($id);
            $resource = $this->panel->resource($secret->resource_id, true);
            $secret = $this->find($id);
            abort_unless($secret->purpose === 'application', 422);
            DB::table('vault_secrets')->where('id', $id)->update(['ciphertext' => Crypt::encryptString(bin2hex(random_bytes(48))), 'version' => $secret->version + 1, 'updated_at' => now()]);
            $resource->increment('revision');

            return $this->metadata($id);
        });
    }

    public function bind(int $resourceId, string $variable, ?string $secretId): array
    {
        return DB::transaction(function () use ($resourceId, $variable, $secretId) {
            $resource = $this->panel->resource($resourceId, true);
            if ($secretId) {
                $secret = $this->find($secretId);
                abort_unless($secret->resource_id === $resourceId && $secret->purpose === 'application', 403);
                DB::table('secret_bindings')->updateOrInsert(['resource_id' => $resourceId, 'variable' => $variable], ['user_id' => Auth::id(), 'secret_id' => $secretId, 'created_at' => now(), 'updated_at' => now()]);
            } else {
                DB::table('secret_bindings')->where('resource_id', $resourceId)->where('variable', $variable)->delete();
            }$resource->increment('revision');

            return ['resource_id' => $resourceId, 'variable' => $variable, 'reference' => $secretId, 'revision' => $resource->revision];
        });
    }

    public function importGithub(int $resourceId, string $value): array
    {
        $resource = $this->panel->resource($resourceId);
        abort_unless(preg_match('~^https://github\.com/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+(?:\.git)?$~D', $resource->repository ?? ''), 422);
        $id = (string) Str::uuid();
        DB::table('vault_secrets')->insert(['id' => $id, 'user_id' => Auth::id(), 'resource_id' => $resourceId, 'purpose' => 'github', 'repository' => preg_replace('/\.git$/', '', $resource->repository), 'ciphertext' => Crypt::encryptString($value), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return $this->metadata($id);
    }

    /** Only trusted backend code calls this method; it is never an MCP tool. */
    public function consumeGithub(string $id, int $resourceId): string
    {
        $secret = $this->find($id);
        $resource = $this->panel->resource($resourceId);
        abort_unless($secret->purpose === 'github' && $secret->resource_id === $resourceId && $secret->repository === preg_replace('/\.git$/', '', $resource->repository ?? ''), 403);

        return Crypt::decryptString($secret->ciphertext);
    }

    /** Only the local operator can materialize an application environment file. */
    public function environment(int $resourceId): string
    {
        $this->panel->resource($resourceId);
        $lines = [];
        foreach (DB::table('secret_bindings')->where('resource_id', $resourceId)->where('user_id', Auth::id())->orderBy('variable')->get() as $binding) {
            $secret = $this->find($binding->secret_id);
            abort_unless($secret->purpose === 'application', 403);
            $value = Crypt::decryptString($secret->ciphertext);
            abort_unless(preg_match('/^[a-f0-9]{96}$/D', $value), 422);
            $lines[] = $binding->variable.'='.$value;
        }

        return implode("\n", $lines)."\n";
    }
}
