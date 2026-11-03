<?php

namespace QOpenPanel\Community\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use QOpenPanel\Community\Models\Resource;

class ProjectVersionStore
{
    public function capture(Resource $resource, ?string $sourceSha = null): array
    {
        $manifest = app(PanelAutomation::class)->manifest($resource);
        $json = json_encode($manifest, JSON_THROW_ON_ERROR);
        $digest = hash('sha256', $json);
        $previous = DB::table('qopenpanel_project_versions')->where('resource_id', $resource->id)->orderByDesc('revision')->first();
        if ($previous && $previous->digest === $digest && $previous->source_sha === $sourceSha) {
            return ['version_id' => $previous->id, 'revision' => $previous->revision, 'resource_revision' => $resource->revision, 'digest' => $digest];
        }
        $id = (string) Str::uuid();
        $revision = ($previous?->revision ?? 0) + 1;
        DB::table('qopenpanel_project_versions')->insert(['id' => $id, 'user_id' => Auth::guard('qopenpanel')->id(), 'resource_id' => $resource->id, 'revision' => $revision, 'manifest' => $json, 'digest' => $digest, 'source_sha' => $sourceSha, 'created_at' => now()]);

        return ['version_id' => $id, 'revision' => $revision, 'resource_revision' => $resource->revision, 'digest' => $digest];
    }
}
