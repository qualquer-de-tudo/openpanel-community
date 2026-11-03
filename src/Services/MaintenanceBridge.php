<?php

namespace QOpenPanel\Community\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MaintenanceBridge
{
    private function authorize(): void
    {
        abort_unless(Auth::guard('qopenpanel')->user()?->is_operator && ! request()->attributes->has('mcp_grant'), 403);
    }

    private function directory(): ?string
    {
        $directory = config('qopenpanel.maintenance_path');

        return is_string($directory) && is_dir($directory) ? $directory : null;
    }

    private function read(string $file): ?array
    {
        $path = $this->directory().'/'.$file;
        if (! $this->directory() || ! is_file($path) || is_link($path) || filesize($path) > 1048576) {
            return null;
        }
        $value = json_decode(file_get_contents($path), true);

        return is_array($value) ? $value : null;
    }

    public function status(): array
    {
        $this->authorize();
        $agent = $this->read('agent.json');
        $fresh = $agent && is_int($agent['timestamp'] ?? null) && abs(time() - $agent['timestamp']) < 120;

        return ['connected' => (bool) $fresh, 'agent' => $agent ? Arr::only($agent, ['timestamp', 'version', 'scanner']) : null, 'security' => $this->read('security.json'), 'jobs' => DB::table('qopenpanel_maintenance_jobs')->latest()->limit(20)->get()->map(fn ($job) => ['id' => $job->id, 'action' => $job->action, 'target' => $job->target, 'created_at' => $job->created_at, 'result' => $job->dispatch_failed ? ['status' => 'failed', 'code' => 'dispatch_failed'] : $this->read('reports/'.$job->id.'.json')])->all()];
    }

    public function request(int $userId, string $action, ?string $target = null, ?string $sha = null): string
    {
        $this->authorize();
        abort_unless(Auth::guard('qopenpanel')->id() === $userId, 403);
        abort_unless($this->status()['connected'], 503);
        abort_unless(in_array($action, ['update', 'scan'], true), 422);
        abort_unless($action === 'scan' ? $target === null && $sha === null : is_string($target) && strlen($target) <= 50 && preg_match('/^v(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-(alpha|beta|rc)\.[1-9]\d*)?$/D', $target) && is_string($sha) && preg_match('/^[a-f0-9]{40}$/D', $sha), 422);
        $id = (string) Str::uuid();
        $path = $this->directory().'/requests/'.$id.'.json';

        return DB::transaction(function () use ($id, $path, $userId, $action, $target, $sha) {
            DB::table('qopenpanel_maintenance_jobs')->insert(['id' => $id, 'user_id' => $userId, 'action' => $action, 'target' => $target, 'expected_sha' => $sha, 'created_at' => now(), 'updated_at' => now()]);
            DB::afterCommit(function () use ($id, $path, $action, $target, $sha) {
                try {
                    $temporary = $path.'.'.bin2hex(random_bytes(8)).'.tmp';
                    $stream = fopen($temporary, 'x');
                    abort_unless($stream !== false, 503);
                    chmod($temporary, 0600);
                    try {
                        $payload = json_encode(['schema' => 1, 'id' => $id, 'action' => $action, 'version' => $target, 'sha' => $sha, 'created_at' => time()], JSON_THROW_ON_ERROR);
                        abort_unless(fwrite($stream, $payload) === strlen($payload), 503);
                    } finally {
                        fclose($stream);
                    }
                    abort_unless(rename($temporary, $path), 503);
                } catch (\Throwable) {
                    DB::table('qopenpanel_maintenance_jobs')->where('id', $id)->update(['dispatch_failed' => true]);
                    throw ValidationException::withMessages(['action' => __('Agente indisponível ou ocupado')]);
                }
            });

            return $id;
        });
    }
}
