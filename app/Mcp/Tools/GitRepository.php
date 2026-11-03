<?php

namespace App\Mcp\Tools;

use App\Services\BlindVault;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('git_repository')]
#[Description('Use a human-authorized GitHub repository through a credential broker. List branches and commit SHAs, create branches/tags, or commit one source file with a pinned expected head. GitHub tokens never appear in arguments/results. No shell, force push, workflows or credential files.')]
class GitRepository extends PanelTool
{
    protected array $allowed = ['action', 'resource_id', 'reference', 'branch', 'name', 'source_sha', 'expected_head_sha', 'path', 'content'];

    protected function execute(Request $request): array
    {
        $data = $request->validate(['action' => 'required|in:branches,commits,tree,create_branch,create_tag,commit_file', 'resource_id' => 'required|integer|min:1', 'reference' => 'required|uuid', 'branch' => ['sometimes', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/D'], 'name' => ['required_if:action,create_branch,create_tag', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/D'], 'source_sha' => ['required_if:action,create_branch,create_tag,tree', 'string', 'regex:/^[a-f0-9]{40}$/D'], 'expected_head_sha' => ['required_if:action,commit_file', 'string', 'regex:/^[a-f0-9]{40}$/D'], 'path' => 'required_if:action,commit_file|string|max:200', 'content' => 'required_if:action,commit_file|string|max:65536']);
        $this->panel()->authorize(in_array($data['action'], ['branches', 'commits', 'tree'], true) ? 'git:read' : 'git:write');
        $resource = $this->panel()->resource($data['resource_id']);
        abort_unless(preg_match('~^https://github\.com/([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+?)(?:\.git)?$~D', $resource->repository ?? '', $matches), 422);
        foreach (['branch', 'name'] as $key) {
            if (isset($data[$key])) {
                abort_if(str_contains($data[$key], '..') || str_contains($data[$key], '//') || str_ends_with($data[$key], '.lock') || str_ends_with($data[$key], '/'), 422);
            }
        }
        $token = app(BlindVault::class)->consumeGithub($data['reference'], $resource->id);
        $http = Http::baseUrl('https://api.github.com')->withToken($token)->acceptJson()->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])->timeout(20)->connectTimeout(5)->withoutRedirecting();
        $base = '/repos/'.rawurlencode($matches[1]).'/'.rawurlencode($matches[2]);
        if ($data['action'] === 'tree') {
            $tree = $this->call($http, 'GET', $base.'/git/trees/'.$data['source_sha'], ['recursive' => 1]);
            $entries = $tree['tree'] ?? [];

            return ['source_sha' => $data['source_sha'], 'entries' => array_map(fn ($entry) => ['path' => $entry['path'], 'type' => $entry['type'], 'sha' => $entry['sha']], array_slice($entries, 0, 200)), 'truncated' => ($tree['truncated'] ?? false) || count($entries) > 200];
        }
        if ($data['action'] === 'branches') {
            $rows = $this->call($http, 'GET', $base.'/branches', ['per_page' => 50]);

            return ['branches' => array_map(fn ($b) => ['name' => $b['name'], 'sha' => $b['commit']['sha']], $rows), 'limit' => 50];
        }
        if ($data['action'] === 'commits') {
            $rows = $this->call($http, 'GET', $base.'/commits', ['per_page' => 30, 'sha' => $data['branch'] ?? 'HEAD']);

            return ['commits' => array_map(fn ($c) => ['sha' => $c['sha'], 'date' => $c['commit']['committer']['date']], $rows), 'limit' => 30];
        }
        if (in_array($data['action'], ['create_branch', 'create_tag'], true)) {
            $this->call($http, 'POST', $base.'/git/refs', ['ref' => 'refs/'.($data['action'] === 'create_branch' ? 'heads/' : 'tags/').$data['name'], 'sha' => $data['source_sha']]);

            return ['created' => true, 'name' => $data['name'], 'source_sha' => $data['source_sha']];
        }
        abort_unless(isset($data['branch']), 422);
        $path = $data['path'];
        abort_unless(preg_match('~^[A-Za-z0-9_-][A-Za-z0-9_./-]*\.(php|jsx|tsx|js|ts|css|html|json|md|txt|yaml|yml)$~D', $path), 422);
        abort_if(str_contains($path, '..') || str_contains($path, '//') || preg_match('~(^|/)(\.\w+|env|credentials|secrets)(/|\.)~i', $path), 422);
        $refPath = $base.'/git/refs/heads/'.str_replace('%2F', '/', rawurlencode($data['branch']));
        $head = $this->call($http, 'GET', $refPath);
        abort_unless(($head['object']['sha'] ?? null) === $data['expected_head_sha'], 409);
        $parent = $this->call($http, 'GET', $base.'/git/commits/'.$data['expected_head_sha']);
        $tree = $this->call($http, 'POST', $base.'/git/trees', ['base_tree' => $parent['tree']['sha'], 'tree' => [['path' => $path, 'mode' => '100644', 'type' => 'blob', 'content' => $data['content']]]]);
        $commit = $this->call($http, 'POST', $base.'/git/commits', ['message' => 'Update '.$path.' via QOpenPanel MCP', 'tree' => $tree['sha'], 'parents' => [$data['expected_head_sha']]]);
        $this->call($http, 'PATCH', $refPath, ['sha' => $commit['sha'], 'force' => false]);

        return ['committed' => true, 'sha' => $commit['sha'], 'parent' => $data['expected_head_sha'], 'path' => $path, 'branch' => $data['branch']];
    }

    private function call(PendingRequest $http, string $method, string $path, array $data = []): array
    {
        $response = $http->send($method, $path, [$method === 'GET' ? 'query' : 'json' => $data]);
        abort_unless($response->successful(), $response->status() === 409 || $response->status() === 422 ? 409 : 403);
        $json = $response->json();
        abort_unless(is_array($json), 422);

        return $json;
    }

    public function schema(JsonSchema $schema): array
    {
        return ['action' => $schema->string()->enum(['branches', 'commits', 'tree', 'create_branch', 'create_tag', 'commit_file'])->required(), 'resource_id' => $schema->integer()->min(1)->required(), 'reference' => $schema->string()->required()->description(__('UUID of a GitHub credential imported by the operator, scoped to this exact repository.')), 'branch' => $schema->string(), 'name' => $schema->string(), 'source_sha' => $schema->string(), 'expected_head_sha' => $schema->string(), 'path' => $schema->string(), 'content' => $schema->string()->description(__('New source file contents; never include secrets.'))];
    }
}
