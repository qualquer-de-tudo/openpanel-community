<?php

namespace App\Http\Controllers;

use App\Services\PanelAutomation;
use App\Services\TemplateCatalog;
use App\Services\TemplateInstaller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use ZipArchive;

class TemplateController extends Controller
{
    public function store(Request $request, TemplateInstaller $installer): RedirectResponse
    {
        $data = $request->validate(['template_id' => 'required|string|max:64', 'version' => ['required', 'string', 'regex:'.TemplateCatalog::VERSION], 'name' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9][a-z0-9._-]*$/D'], 'domain' => 'nullable|string|max:253']);

        return $this->perform(fn () => $installer->install($data['template_id'], $data['version'], $data['name'], $data['domain'] ?? null), 'Template instalado. Os arquivos estão prontos para exportação.');
    }

    public function update(Request $request, int $resource, TemplateInstaller $installer): RedirectResponse
    {
        $data = $request->validate(['version' => ['required', 'string', 'regex:'.TemplateCatalog::VERSION], 'expected_revision' => 'required|integer|min:1']);

        return $this->perform(fn () => $installer->change($resource, (int) $data['expected_revision'], $data['version']), 'Template atualizado com histórico preservado.');
    }

    public function rollback(Request $request, int $resource, TemplateInstaller $installer): RedirectResponse
    {
        $data = $request->validate(['history_id' => 'required|uuid', 'expected_revision' => 'required|integer|min:1']);

        return $this->perform(fn () => $installer->change($resource, (int) $data['expected_revision'], historyId: $data['history_id']), 'Versão anterior restaurada em uma nova revisão.');
    }

    private function perform(callable $operation, string $message): RedirectResponse
    {
        try {
            $operation();
        } catch (HttpExceptionInterface $error) {
            if (in_array($error->getStatusCode(), [409, 422], true)) {
                throw ValidationException::withMessages(['template' => __($error->getStatusCode() === 409 ? 'O projeto mudou. Atualize a página e tente novamente.' : 'A versão selecionada não é compatível com este projeto.')]);
            }
            throw $error;
        }

        return back()->with('message', __($message))->with('message_key', $message);
    }

    public function download(int $resource, TemplateInstaller $installer, TemplateCatalog $catalog): BinaryFileResponse
    {
        $installation = $installer->installation($resource);
        $manifest = $catalog->validate(json_decode($installation->manifest, true, 512, JSON_THROW_ON_ERROR));
        abort_unless(hash_equals($installation->digest, hash('sha256', $catalog->canonical($manifest))), 409);
        $path = tempnam(storage_path('app/private'), 'template-');
        $zip = new ZipArchive;
        try {
            abort_unless($zip->open($path, ZipArchive::OVERWRITE) === true, 500);
            foreach ($manifest['files'] as $name => $content) {
                abort_unless($zip->addFromString($name, $content), 500);
            }$zip->addFromString('qopenpanel-template.lock.json', json_encode(['schema' => 1, 'template_id' => $manifest['id'], 'version' => $manifest['version'], 'digest' => $installation->digest, 'files' => array_map(fn ($content) => hash('sha256', $content), $manifest['files'])], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $zip->close();
        } catch (\Throwable $error) {
            @unlink($path);
            throw $error;
        }
        app(PanelAutomation::class)->audit('template_export', 'success');

        return response()->download($path, $manifest['id'].'-'.$manifest['version'].'.zip')->deleteFileAfterSend(true);
    }
}
