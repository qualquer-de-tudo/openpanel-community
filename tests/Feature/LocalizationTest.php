<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['pt-BR', 'pt_BR', 'O campo E-mail é obrigatório.'])]
    #[TestWith(['en-US', 'en', 'The Email field is required.'])]
    #[TestWith(['fr-FR', 'fr', 'Le champ E-mail est obligatoire.'])]
    #[TestWith(['es-ES', 'es', 'El campo Correo electrónico es obligatorio.'])]
    #[TestWith(['zh-CN', 'zh_CN', '电子邮件 为必填项。'])]
    #[TestWith(['ja-JP', 'ja', 'メール は必須です。'])]
    public function test_requested_language_localizes_pages_and_validation(string $language, string $locale, string $message): void
    {
        $this->withHeader('Accept-Language', $language)->get('/login')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Login', false)->where('locale', $locale));
        $this->postJson('/login', [])->assertUnprocessable()->assertJsonPath('errors.email.0', $message);
    }

    public function test_saved_locale_has_priority_over_browser_language(): void
    {
        $this->withUnencryptedCookie('qopenpanel_locale', 'es')->withHeader('Accept-Language', 'en-US')->get('/login')->assertOk()->assertSee('lang="es"', false)->assertInertia(fn (AssertableInertia $page) => $page->where('locale', 'es'));
    }

    public function test_unsupported_locale_cookie_is_ignored(): void
    {
        $this->withUnencryptedCookie('qopenpanel_locale', '../../private')->withHeader('Accept-Language', 'ja-JP')->get('/login')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('locale', 'ja'));
    }

    public function test_mcp_descriptions_and_instructions_follow_requested_language(): void
    {
        $user = User::factory()->create();
        $token = 'qpm_'.bin2hex(random_bytes(48));
        DB::table('mcp_grants')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'scopes' => json_encode(['panel:read']), 'expires_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now()]);
        $headers = ['Authorization' => 'Bearer '.$token, 'Accept-Language' => 'es', 'MCP-Protocol-Version' => '2025-11-25'];
        $this->postJson('/mcp/panel', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-11-25', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'test', 'version' => '1']]], $headers)->assertOk()->assertJsonPath('result.instructions', fn ($text) => str_starts_with($text, 'Gestiona solo el espacio autenticado.'));
        $this->postJson('/mcp/panel', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], $headers)->assertOk()->assertJsonPath('result.tools.0.description', fn ($text) => str_starts_with($text, 'Consulta cuotas, servicios, proyectos'));
    }
}
