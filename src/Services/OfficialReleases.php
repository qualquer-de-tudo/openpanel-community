<?php

namespace QOpenPanel\Community\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OfficialReleases
{
    public const API = 'https://api.github.com/repos/qualquer-de-tudo/openpanel-community';

    public function list(string $channel): array
    {
        return Cache::remember('openpanel.releases.'.config('qopenpanel.release.version').'.'.$channel, 300, function () use ($channel) {
            $response = Http::acceptJson()->timeout(10)->withOptions(['allow_redirects' => false])->get(self::API.'/releases', ['per_page' => 30]);
            abort_unless($response->ok() && strlen($response->body()) <= 2097152, 503);
            $rows = $response->json();
            abort_unless(is_array($rows) && array_is_list($rows), 503);
            $result = [];
            foreach ($rows as $release) {
                if (! is_array($release)) {
                    continue;
                }
                $tag = $release['tag_name'] ?? '';
                if (! is_string($tag) || strlen($tag) > 50 || ! preg_match('/^v(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-(alpha|beta|rc)\.[1-9]\d*)?$/D', $tag) || ($release['draft'] ?? true)) {
                    continue;
                }
                $prerelease = (bool) ($release['prerelease'] ?? true) || str_contains($tag, '-');
                if ($channel === 'stable' && $prerelease) {
                    continue;
                }
                $result[] = ['tag' => $tag, 'prerelease' => $prerelease, 'url' => 'https://github.com/qualquer-de-tudo/openpanel-community/releases/tag/'.$tag, 'newer' => version_compare(substr($tag, 1), config('qopenpanel.release.version'), '>')];
            }

            return $result;
        });
    }

    public function resolve(string $tag, string $channel): string
    {
        $release = collect($this->list($channel))->firstWhere('tag', $tag);
        abort_unless($release && $release['newer'], 422);
        $response = Http::acceptJson()->timeout(10)->withOptions(['allow_redirects' => false])->get(self::API.'/commits/'.$tag);
        $sha = $response->json('sha');
        abort_unless($response->ok() && is_string($sha) && preg_match('/^[a-f0-9]{40}$/D', $sha), 503);

        return $sha;
    }
}
