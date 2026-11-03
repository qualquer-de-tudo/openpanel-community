<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

class TemplateCatalog
{
    public const VERSION = '/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/D';

    public function canonical(array $manifest): string
    {
        return json_encode($this->sort($manifest), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR);
    }

    private function sort(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map(fn ($item) => $this->sort($item), $value);
    }

    public function validate(array $manifest): array
    {
        Validator::make(['manifest' => $manifest], [
            'manifest' => 'required|array:schema,id,version,name,description,author,license,panel_min,engine,disk_mb,files',
            'manifest.schema' => 'required|integer|in:1', 'manifest.id' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D'],
            'manifest.version' => ['required', 'string', 'max:32', 'regex:'.self::VERSION],
            'manifest.name' => 'required|array', 'manifest.name.en' => 'required|string|max:100', 'manifest.name.pt_BR' => 'required|string|max:100', 'manifest.name.*' => 'required|string|max:100',
            'manifest.description' => 'required|array', 'manifest.description.en' => 'required|string|max:500', 'manifest.description.pt_BR' => 'required|string|max:500', 'manifest.description.*' => 'required|string|max:500',
            'manifest.author' => 'required|string|max:100', 'manifest.license' => 'required|string|max:64',
            'manifest.panel_min' => ['required', 'string', 'max:40', 'regex:/^[0-9]+\.[0-9]+\.[0-9]+(?:-[a-z0-9.-]+)?$/D'],
            'manifest.engine' => ['required', 'string', Rule::in(config('catalog.sites'))], 'manifest.disk_mb' => 'required|integer|min:64|max:1048576', 'manifest.files' => 'required|array|min:1|max:100',
        ])->validate();
        $size = 0;
        $paths = [];
        foreach ($manifest['files'] as $path => $content) {
            if (! is_string($path) || strlen($path) > 180 || ! preg_match('~^[A-Za-z0-9][A-Za-z0-9._/-]*$~D', $path) || str_contains($path, '..') || str_contains($path, '//') || str_ends_with($path, '/') || preg_match('~(^|/)(\.git|\.env|credentials|secrets)(/|\.|$)~i', $path) || ! is_string($content) || str_contains($content, "\0") || strlen($content) > 131072) {
                throw new RuntimeException('invalid_template_file');
            }
            if (preg_match('~(^|/)\.|(^|/)(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|/|$)|\.(pem|key|pfx)$~i', $path) || strtolower($path) === 'qopenpanel-template.lock.json') {
                throw new RuntimeException('reserved_template_path');
            }
            $normalized = strtolower($path);
            if (isset($paths[$normalized])) {
                throw new RuntimeException('duplicate_template_path');
            }$paths[$normalized] = true;
            $size += strlen($content);
        }
        foreach (array_keys($paths) as $path) {
            $parts = explode('/', $path);
            array_pop($parts);
            while ($parts) {
                if (isset($paths[implode('/', $parts)])) {
                    throw new RuntimeException('template_path_conflict');
                }array_pop($parts);
            }
        }
        if ($size > 524288 || strlen($this->canonical($manifest)) > 1048576) {
            throw new RuntimeException('template_too_large');
        }

        return $manifest;
    }

    public function releases(bool $verify = true): array
    {
        $root = config('templates.path');
        $lock = $this->lock();
        $releases = [];
        $files = glob($root.'/*/*.json') ?: [];
        if (count($files) > 500) {
            throw new RuntimeException('catalog_too_large');
        }
        foreach ($files as $file) {
            if (is_link($file) || filesize($file) > 1048576) {
                throw new RuntimeException('invalid_manifest_file');
            }$manifest = $this->validate(json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR));
            $key = $manifest['id'].'@'.$manifest['version'];
            if (basename(dirname($file)) !== $manifest['id'] || basename($file) !== $manifest['version'].'.json' || isset($releases[$key])) {
                throw new RuntimeException('invalid_template_identity');
            }
            $digest = hash('sha256', $this->canonical($manifest));
            if ($verify && (! isset($lock[$key]) || ! hash_equals($lock[$key], $digest))) {
                throw new RuntimeException('template_digest_mismatch');
            }
            $releases[$key] = ['manifest' => $manifest, 'digest' => $digest];
        }
        if ($verify && array_diff_key($lock, $releases)) {
            throw new RuntimeException('released_template_removed');
        }ksort($releases);

        return $releases;
    }

    public function lock(): array
    {
        $path = config('templates.path').'/catalog.lock.json';

        return is_file($path) ? json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR) : [];
    }

    public function release(string $id, string $version): array
    {
        $releases = $this->releases();
        abort_unless(isset($releases[$id.'@'.$version]), 404);

        return $releases[$id.'@'.$version];
    }

    public function summaries(): array
    {
        return array_values(array_map(function ($release) {
            $manifest = $release['manifest'];
            $files = $manifest['files'];
            unset($manifest['files']);

            return [...$manifest, 'digest' => $release['digest'], 'file_count' => count($files), 'file_paths' => array_keys($files), 'compatible' => version_compare(config('release.version'), $manifest['panel_min'], '>=')];
        }, $this->releases()));
    }

    public function updateLock(): void
    {
        $existing = $this->lock();
        $new = [];
        foreach ($this->releases(false) as $key => $release) {
            $new[$key] = $release['digest'];
            if (isset($existing[$key]) && ! hash_equals($existing[$key], $new[$key])) {
                throw new RuntimeException('published_template_is_immutable');
            }
        }if (array_diff_key($existing, $new)) {
            throw new RuntimeException('released_template_removed');
        }file_put_contents(config('templates.path').'/catalog.lock.json', json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    }
}
