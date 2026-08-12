<?php

namespace Tests\Feature;

use App\Enums\ContactStatus;
use App\Enums\RentalCoverage;
use App\Enums\RentalRequestStatus;
use App\Mail\ContactMessageNotification;
use App\Mail\ContactReceivedMail;
use App\Mail\RentalRequestNotification;
use App\Mail\RentalRequestReceivedMail;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Product;
use App\Models\RentalRequest;
use App\Support\PersonName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Formulario general (6.1) y solicitud de renta adaptativa (REQ-07).
 */
class FormulariosTest extends TestCase
{
    use RefreshDatabase;

    private Product $equipoNacional;

    private Product $equipoLocal;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $category = Category::create(['name' => 'Renta', 'slug' => 'renta']);

        $this->equipoNacional = Product::create([
            'name' => 'Regla Láser S-940', 'slug' => 'regla-laser-s940', 'sku' => 'RL-940',
            'category_id' => $category->id, 'price' => 0, 'stock_qty' => 2,
            'is_active' => true, 'is_for_sale' => false, 'is_rental' => true,
            'rental_coverage' => RentalCoverage::National,
        ]);

        $this->equipoLocal = Product::create([
            'name' => 'Compactadora Bailarina', 'slug' => 'compactadora-bailarina', 'sku' => 'CB-001',
            'category_id' => $category->id, 'price' => 0, 'stock_qty' => 4,
            'is_active' => true, 'is_for_sale' => false, 'is_rental' => true,
            'rental_coverage' => RentalCoverage::Local,
        ]);
    }

    private function datosContacto(array $extra = []): array
    {
        return array_merge([
            'nombre' => 'Ing. Mariana Treviño',
            'empresa' => 'Desarrollos Pantera',
            'email' => 'mariana@pantera.mx',
            'telefono' => '8118887766',
            'obra' => 'Guadalupe, Nuevo León',
            'asunto' => 'Asesoría técnica',
            'mensaje' => 'Necesito recomendación de sellador para juntas de contracción.',
            'acepto' => '1',
        ], $extra);
    }

    // ---------------- Formulario general ----------------

    public function test_el_mensaje_se_guarda_y_se_notifica(): void
    {
        $this->post('/contacto', $this->datosContacto())
            ->assertRedirect()
            ->assertSessionHas('contacto.enviado');

        $mensaje = ContactMessage::sole();
        $this->assertSame('mariana@pantera.mx', $mensaje->email);
        $this->assertSame(ContactStatus::New, $mensaje->status);

        Mail::assertSent(ContactMessageNotification::class, fn ($m) => $m->hasTo(config('icce.sales_email')));
        Mail::assertSent(ContactReceivedMail::class, fn ($m) => $m->hasTo('mariana@pantera.mx'));
    }

    public function test_el_mensaje_se_conserva_aunque_falle_el_correo(): void
    {
        // Un problema del servidor de correo no debe costar un prospecto
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));

        $this->post('/contacto', $this->datosContacto())->assertRedirect();

        $this->assertSame(1, ContactMessage::count());
    }

    public function test_el_formulario_exige_los_campos_minimos(): void
    {
        $this->post('/contacto', ['nombre' => 'Solo el nombre'])
            ->assertSessionHasErrors(['email', 'telefono', 'asunto', 'mensaje', 'acepto']);

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_sin_consentimiento_no_se_envia(): void
    {
        $this->post('/contacto', $this->datosContacto(['acepto' => null]))
            ->assertSessionHasErrors('acepto');

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_la_trampa_antispam_bloquea_al_robot(): void
    {
        // Un campo que sólo un robot llena
        $this->post('/contacto', $this->datosContacto(['apellido_materno' => 'Relleno']))
            ->assertSessionHasErrors('apellido_materno');

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_un_mensaje_plagado_de_enlaces_se_rechaza(): void
    {
        $this->post('/contacto', $this->datosContacto([
            'mensaje' => 'http://a.com http://b.com http://c.com http://d.com',
        ]))->assertSessionHasErrors('mensaje');

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_el_formulario_tiene_techo_de_envios(): void
    {
        // RNF-02: seis por minuto es de sobra para una persona
        for ($i = 0; $i < 6; $i++) {
            $this->post('/contacto', $this->datosContacto(['email' => "p{$i}@obra.mx"]));
        }

        $this->post('/contacto', $this->datosContacto(['email' => 'septimo@obra.mx']))
            ->assertStatus(429);

        $this->assertSame(6, ContactMessage::count());
    }

    // ---------------- Solicitud de renta (REQ-07) ----------------

    public function test_la_pagina_de_solicitud_responde(): void
    {
        $this->get('/renta/solicitar')->assertOk()->assertSee('Solicitar renta');
    }

    public function test_el_equipo_se_precarga_desde_su_ficha(): void
    {
        Livewire::withQueryParams(['equipo' => $this->equipoNacional->slug])
            ->test('renta.solicitud')
            ->assertSet('productId', $this->equipoNacional->id)
            ->assertSet('equipmentName', 'Regla Láser S-940')
            // La cobertura se propone desde el equipo elegido
            ->assertSet('coverage', 'national');
    }

    public function test_elegir_otro_equipo_reajusta_la_cobertura(): void
    {
        Livewire::test('renta.solicitud')
            ->set('productId', $this->equipoNacional->id)
            ->assertSet('coverage', 'national')
            ->set('productId', $this->equipoLocal->id)
            ->assertSet('coverage', 'local');
    }

    public function test_el_formulario_cambia_de_campos_segun_la_cobertura(): void
    {
        $componente = Livewire::test('renta.solicitud')->set('coverage', 'local');

        $componente->assertSee('Lo recojo en el almacén')
            ->assertDontSee('resuelva el flete');

        $componente->set('coverage', 'national')
            ->assertSee('resuelva el flete')
            ->assertSee('Acceso a la obra')
            ->assertDontSee('Lo recojo en el almacén');
    }

    public function test_la_etiqueta_de_ubicacion_se_adapta(): void
    {
        Livewire::test('renta.solicitud')
            ->set('coverage', 'national')
            ->assertSee('Ciudad y estado de la obra')
            ->set('coverage', 'local')
            ->assertSee('Dirección o zona de la obra');
    }

    public function test_la_solicitud_genera_folio_y_avisa(): void
    {
        Livewire::test('renta.solicitud')
            ->set('productId', $this->equipoNacional->id)
            ->set('coverage', 'national')
            ->set('location', 'Silao, Guanajuato')
            ->set('startDate', now()->addWeek()->toDateString())
            ->set('rentalDays', '20')
            ->set('needsFreight', true)
            ->set('needsOperator', true)
            ->set('clientName', 'Ing. Rodrigo Cantú')
            ->set('email', 'rodrigo@vertice.mx')
            ->set('phone', '8112223344')
            ->set('accepted', true)
            ->call('submit')
            ->assertSet('sent', true);

        $solicitud = RentalRequest::sole();
        $this->assertStringStartsWith('RNT-', $solicitud->folio);
        $this->assertSame(RentalCoverage::National, $solicitud->coverage);
        $this->assertSame(RentalRequestStatus::New, $solicitud->status);
        $this->assertSame($this->equipoNacional->id, $solicitud->product_id);

        // Los campos propios de la cobertura quedan en la nota del agente
        $this->assertStringContainsString('flete', $solicitud->notes);
        $this->assertStringContainsString('operador', $solicitud->notes);

        Mail::assertSent(RentalRequestNotification::class);
        Mail::assertSent(RentalRequestReceivedMail::class, fn ($m) => $m->hasTo('rodrigo@vertice.mx'));
    }

    public function test_la_cobertura_local_registra_si_entrega_o_recoge(): void
    {
        Livewire::test('renta.solicitud')
            ->set('productId', $this->equipoLocal->id)
            ->set('location', 'Apodaca, Nuevo León')
            ->set('delivery', 'recoge')
            ->set('clientName', 'Sr. Luis Gaytán')
            ->set('email', 'luis@regia.mx')
            ->set('phone', '8110001111')
            ->set('accepted', true)
            ->call('submit')
            ->assertSet('sent', true);

        $this->assertStringContainsString('recoge en almacén', RentalRequest::sole()->notes);
    }

    public function test_la_solicitud_valida_lo_indispensable(): void
    {
        Livewire::test('renta.solicitud')
            ->call('submit')
            ->assertHasErrors(['equipmentName', 'clientName', 'email', 'phone', 'location', 'accepted']);

        $this->assertSame(0, RentalRequest::count());
    }

    public function test_no_se_acepta_una_fecha_de_inicio_pasada(): void
    {
        Livewire::test('renta.solicitud')
            ->set('startDate', now()->subWeek()->toDateString())
            ->call('submit')
            ->assertHasErrors('startDate');
    }

    // ---------------- Saludo con nombre de pila ----------------

    public function test_el_saludo_salta_el_titulo_profesional(): void
    {
        // «Hola, Ing.» es el error clásico de tomar la primera palabra
        $this->assertSame('Mariana', PersonName::first('Ing. Mariana Treviño'));
        $this->assertSame('Rodrigo', PersonName::first('Arq. Rodrigo Cantú'));
        $this->assertSame('Luis', PersonName::first('Sr. Luis Gaytán'));
        $this->assertSame('Ana', PersonName::first('Ana López'));
        $this->assertSame('Ana', PersonName::first('  Ana  '));
        $this->assertSame('de nuevo', PersonName::first(''));
        $this->assertSame('Ing.', PersonName::first('Ing.'));
    }
}
