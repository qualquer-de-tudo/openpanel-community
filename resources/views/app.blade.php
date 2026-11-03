<!DOCTYPE html><html lang="{{ str_replace('_', '-', app()->getLocale()) }}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>QOpenPanel</title>
@php($manifest = json_decode(file_get_contents(\QOpenPanel\Community\Providers\QOpenPanelServiceProvider::path('resources/dist/manifest.json')), true, 512, JSON_THROW_ON_ERROR))
@foreach($manifest['resources/js/app.jsx']['css'] ?? [] as $css)
<link rel="stylesheet" href="{{ asset('vendor/qopenpanel/'.$css) }}">
@endforeach
<script type="module" src="{{ asset('vendor/qopenpanel/'.$manifest['resources/js/app.jsx']['file']) }}"></script>
@inertiaHead</head><body data-qopenpanel-path="{{ '/'.trim(config('qopenpanel.path'), '/') }}">@inertia</body></html>
