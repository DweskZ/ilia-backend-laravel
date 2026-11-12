<?php

namespace Tests\Feature\LuisFigueroa;

use App\Models\Activity;
use App\Models\AppUser;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SeguridadEntregasTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'clave-de-prueba-123';

    private Activity $actividad;

    private AppUser $docente;

    private AppUser $otro;

    private AppUser $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actividad = Activity::factory()->create();
        $this->docente = AppUser::factory()->create(['password_hash' => bcrypt(self::CLAVE)]);
        $this->otro = AppUser::factory()->create();
        $this->admin = AppUser::factory()->admin()->create(['password_hash' => bcrypt(self::CLAVE)]);
    }

    private function entrega(AppUser $dueno, int $intento = 1): Submission
    {
        return Submission::create([
            'activity_id' => $this->actividad->id, 'user_id' => $dueno->id,
            'prompt' => 'Actua como tutor y explica las fracciones a estudiantes de quinto grado.',
            'attempt' => $intento, 'status' => 'recibida', 'sent_at' => now(),
        ])->refresh();
    }

    private function payload(AppUser $docente): array
    {
        return [
            'actividad_id' => $this->actividad->id,
            'docente_id' => $docente->id,
            'pedido' => 'Actua como tutor y explica las fracciones a estudiantes de quinto grado.',
            'intento' => 1,
            'enviado_en' => '2026-11-10T14:30:00-05:00',
            'observacion' => null,
        ];
    }

    // --- Tokens -----------------------------------------------------------

    public function test_login_emite_token_bearer_funcional(): void
    {
        $r = $this->postJson('/api/auth/token', ['email' => $this->docente->email, 'password' => self::CLAVE])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('usuario.rol', 'docente')
            ->assertJsonStructure(['access_token', 'expira_en']);

        $this->withHeader('Authorization', 'Bearer '.$r->json('access_token'))
            ->getJson('/api/entregas')
            ->assertOk();
    }

    public function test_login_con_clave_incorrecta_o_usuario_inexistente_da_401_generico(): void
    {
        $this->postJson('/api/auth/token', ['email' => $this->docente->email, 'password' => 'mala'])
            ->assertStatus(401)->assertExactJson(['mensaje' => 'Credenciales inválidas.']);
        $this->postJson('/api/auth/token', ['email' => 'nadie@ilia.test', 'password' => 'mala'])
            ->assertStatus(401)->assertExactJson(['mensaje' => 'Credenciales inválidas.']);
    }

    public function test_login_sin_campos_da_422_con_la_forma_del_modulo(): void
    {
        $this->postJson('/api/auth/token', [])
            ->assertStatus(422)->assertJsonStructure(['mensaje', 'errores' => ['email', 'password']]);
    }

    public function test_logout_revoca_el_token(): void
    {
        $token = $this->postJson('/api/auth/token', ['email' => $this->docente->email, 'password' => self::CLAVE])
            ->json('access_token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout')->assertOk()->assertJsonPath('mensaje', 'Sesión cerrada.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/entregas')->assertStatus(401);
    }

    // --- P1: authenticated with permission --------------------------------

    public function test_p1_docente_registra_su_propia_entrega(): void
    {
        Sanctum::actingAs($this->docente);

        $this->postJson('/api/entregas', $this->payload($this->docente))->assertStatus(201);
    }

    public function test_p1_docente_lista_solo_las_suyas_y_admin_todas(): void
    {
        $this->entrega($this->docente);
        $this->entrega($this->otro);

        Sanctum::actingAs($this->docente);
        $this->getJson('/api/entregas')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.docente_id', $this->docente->id);

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/entregas')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_p1_dueno_y_admin_ven_el_detalle(): void
    {
        $e = $this->entrega($this->docente);

        Sanctum::actingAs($this->docente);
        $this->getJson("/api/entregas/{$e->id}")->assertOk();

        Sanctum::actingAs($this->admin);
        $this->getJson("/api/entregas/{$e->id}")->assertOk();
    }

    public function test_p1_admin_elimina(): void
    {
        $e = $this->entrega($this->docente);
        Sanctum::actingAs($this->admin);

        $this->deleteJson("/api/entregas/{$e->id}")->assertOk()->assertJsonPath('mensaje', 'Entrega eliminada');
        $this->assertSame(0, Submission::count());
    }

    // --- P2: no authentication --------------------------------------------

    public function test_p2_sin_token_todas_las_rutas_protegidas_dan_401(): void
    {
        $e = $this->entrega($this->docente);

        foreach ([
            ['getJson', '/api/entregas'],
            ['getJson', "/api/entregas/{$e->id}"],
            ['postJson', '/api/entregas'],
            ['deleteJson', "/api/entregas/{$e->id}"],
            ['postJson', '/api/auth/logout'],
        ] as [$metodo, $ruta]) {
            $this->$metodo($ruta)->assertStatus(401)->assertExactJson(['mensaje' => 'No autenticado.']);
        }
        $this->assertSame(1, Submission::count());
    }

    public function test_p2_token_invalido_da_401_sin_repetir_el_token(): void
    {
        $r = $this->withHeader('Authorization', 'Bearer 123|token-falso-que-no-debe-aparecer')
            ->getJson('/api/entregas')->assertStatus(401);

        $this->assertStringNotContainsString('token-falso', $r->getContent());
    }

    // --- P3: authenticated without permission -----------------------------

    public function test_p3_docente_no_ve_la_entrega_de_otro(): void
    {
        $ajena = $this->entrega($this->otro);
        Sanctum::actingAs($this->docente);

        $this->getJson("/api/entregas/{$ajena->id}")
            ->assertStatus(403)->assertExactJson(['mensaje' => 'No tiene permiso para esta acción.']);
    }

    public function test_p3_docente_no_puede_eliminar(): void
    {
        $propia = $this->entrega($this->docente);
        Sanctum::actingAs($this->docente);

        $this->deleteJson("/api/entregas/{$propia->id}")
            ->assertStatus(403)->assertExactJson(['mensaje' => 'No tiene permiso para esta acción.']);
        $this->assertSame(1, Submission::count());
    }

    public function test_p3_docente_no_puede_entregar_a_nombre_de_otro(): void
    {
        Sanctum::actingAs($this->docente);

        $this->postJson('/api/entregas', $this->payload($this->otro))
            ->assertStatus(403)->assertExactJson(['mensaje' => 'No tiene permiso para esta acción.']);
        $this->assertSame(0, Submission::count());
    }

    public function test_admin_puede_entregar_a_nombre_de_un_docente(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/entregas', $this->payload($this->docente))->assertStatus(201);
    }

    public function test_respuestas_de_seguridad_incluyen_cabeceras_de_endurecimiento(): void
    {
        $this->getJson('/api/entregas')->assertStatus(401)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'no-store, private');
    }
}
