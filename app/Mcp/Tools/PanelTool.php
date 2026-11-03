<?php

namespace App\Mcp\Tools;

use App\Services\PanelAutomation;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

abstract class PanelTool extends Tool
{
    protected array $allowed = [];

    final public function handle(Request $request): Response
    {
        $grant = request()->attributes->get('mcp_grant');
        if (! $grant) {
            return Response::error('mcp_authentication_required');
        }
        $audit = DB::table('mcp_audit')->insertGetId(['user_id' => $grant->user_id, 'grant_id' => $grant->id, 'action' => $this->name(), 'outcome' => 'started', 'created_at' => now()]);
        try {
            if (array_diff(array_keys($request->all()), $this->allowed)) {
                abort(422);
            }
            $result = $this->execute($request);
            DB::table('mcp_audit')->where('id', $audit)->update(['outcome' => 'success']);

            return Response::json($result);
        } catch (Throwable $error) {
            $code = match (true) {
                $error instanceof ValidationException => 'validation_failed',$error instanceof ModelNotFoundException => 'not_found',$error instanceof HttpExceptionInterface => match ($error->getStatusCode()) {
                    403 => 'forbidden',404 => 'not_found',409 => 'revision_or_state_conflict',422 => 'validation_failed',default => 'operation_failed'
                },default => 'operation_failed'
            };
            DB::table('mcp_audit')->where('id', $audit)->update(['outcome' => $code]);

            return Response::error($code);
        }
    }

    abstract protected function execute(Request $request): array;

    protected function panel(): PanelAutomation
    {
        return app(PanelAutomation::class);
    }
}
