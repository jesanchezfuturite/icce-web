<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\UserRole;
use App\Exports\OrdersExport;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

/**
 * Descarga a Excel de «Pedidos y cotizaciones»: el libro y que respete lo que
 * se está viendo (pestaña, rango de fechas). Catálogo y pedidos mínimos.
 */
class ExcelPedidosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /** @var array<string, Order> */
    private array $ordenes = [];

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $categoria = Category::create(['name' => 'Llanas', 'slug' => 'llanas']);
        $producto = Product::create([
            'name' => 'Llana de acabado', 'slug' => 'llana', 'sku' => 'LL-001',
            'category_id' => $categoria->id, 'price' => 1000, 'stock_qty' => 50, 'is_active' => true,
        ]);

        // folio => [tipo, estatus, fecha del pedido]
        $datos = [
            'VD-2026-00001' => [OrderType::DirectSale, OrderStatus::Pending, '2026-09-05 10:00:00'],
            'COT-2026-00001' => [OrderType::Quote, OrderStatus::Pending, '2026-09-20 12:30:00'],
            'VD-2026-00002' => [OrderType::DirectSale, OrderStatus::Delivered, '2026-08-15 09:00:00'],
            'VD-2026-00003' => [OrderType::DirectSale, OrderStatus::Paid, '2026-09-28 18:45:00'],
            'COT-2026-00002' => [OrderType::Quote, OrderStatus::Quoted, '2026-10-02 08:15:00'],
        ];

        foreach ($datos as $folio => [$tipo, $estatus, $fecha]) {
            $orden = Order::create([
                'folio' => $folio, 'order_type' => $tipo, 'status' => $estatus,
                'customer_name' => 'Constructora '.$folio, 'customer_email' => 'compras@prueba.mx',
                'customer_phone' => '8112345678', 'subtotal' => 1000, 'tax_amount' => 160, 'total_amount' => 1160,
                'shipping_address' => ['calle' => 'Av. Prueba 123', 'colonia' => 'Centro', 'ciudad' => 'Monterrey', 'estado' => 'Nuevo León', 'cp' => '64000'],
                'customer_notes' => 'Entregar por la mañana',
            ]);
            $orden->items()->create([
                'product_id' => $producto->id, 'product_sku' => 'LL-001', 'product_name' => 'Llana de acabado',
                'quantity' => 2, 'unit_price' => 500, 'line_total' => 1000,
            ]);

            DB::table('orders')->where('id', $orden->id)->update(['created_at' => $fecha]);
            $this->ordenes[$folio] = $orden;
        }
    }

    /** @return array<string, array<int, array<int, mixed>>> hoja => filas */
    private function leer(string $ruta): array
    {
        $reader = new Reader;
        $reader->open($ruta);

        $hojas = [];

        foreach ($reader->getSheetIterator() as $hoja) {
            foreach ($hoja->getRowIterator() as $fila) {
                $hojas[$hoja->getName()][] = $fila->toArray();
            }
        }

        $reader->close();

        return $hojas;
    }

    /** @return array<int, string> */
    private function foliosDelListado(string $pestana, array $filtros = []): array
    {
        $pagina = Livewire::actingAs($this->admin)->test(ListOrders::class)->set('activeTab', $pestana);

        foreach ($filtros as $clave => $valor) {
            $pagina->set("tableFilters.fecha.{$clave}", $valor);
        }

        return $pagina->instance()->getFilteredSortedTableQuery()->pluck('folio')->sort()->values()->all();
    }

    public function test_el_libro_trae_una_hoja_de_pedidos_y_otra_de_partidas(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'xlsx');

        $total = (new OrdersExport)->write(Order::query(), $ruta);
        $hojas = $this->leer($ruta);
        unlink($ruta);

        $this->assertSame(5, $total);
        $this->assertSame(['Pedidos', 'Partidas'], array_keys($hojas));

        // Pedidos: encabezado + una fila por orden
        $this->assertCount(6, $hojas['Pedidos']);
        $encabezado = $hojas['Pedidos'][0];
        $this->assertSame('Folio', $encabezado[0]);

        $fila = collect($hojas['Pedidos'])->firstWhere(0, 'VD-2026-00001');
        $columna = array_flip($encabezado);

        $this->assertSame('Venta directa', $fila[$columna['Tipo']]);
        $this->assertSame('Constructora VD-2026-00001', $fila[$columna['Cliente']]);
        $this->assertEquals(1160.0, $fila[$columna['Total']]);
        $this->assertSame('Av. Prueba 123', $fila[$columna['Calle y número']]);
        $this->assertSame('Monterrey', $fila[$columna['Ciudad']]);
        $this->assertSame('2 × Llana de acabado', $fila[$columna['Productos']]);
        $this->assertSame('Entregar por la mañana', $fila[$columna['Notas del cliente']]);
        $this->assertInstanceOf(\DateTimeInterface::class, $fila[$columna['Fecha del pedido']]);
        $this->assertSame('2026-09-05', $fila[$columna['Fecha del pedido']]->format('Y-m-d'));

        // Partidas: encabezado + una fila por producto de cada orden
        $this->assertCount(6, $hojas['Partidas']);
        $partida = collect($hojas['Partidas'])->firstWhere(0, 'COT-2026-00001');
        $this->assertSame('LL-001', $partida[4]);
        $this->assertSame(2, $partida[6]);
    }

    public function test_cada_pestana_lista_solo_lo_suyo(): void
    {
        $this->assertSame(['COT-2026-00001', 'VD-2026-00001'], $this->foliosDelListado('por_atender'));
        $this->assertSame(['COT-2026-00001', 'COT-2026-00002'], $this->foliosDelListado('cotizaciones'));
        $this->assertSame(['VD-2026-00003'], $this->foliosDelListado('en_proceso'));
        $this->assertSame(['VD-2026-00002'], $this->foliosDelListado('entregados'));
        $this->assertCount(5, $this->foliosDelListado('todos'));
    }

    public function test_el_rango_de_fechas_limita_lo_que_se_lista_y_se_descarga(): void
    {
        $septiembre = ['desde' => '2026-09-01', 'hasta' => '2026-09-30'];

        // «Todos» pero sólo septiembre: quedan fuera agosto y octubre
        $this->assertSame(
            ['COT-2026-00001', 'VD-2026-00001', 'VD-2026-00003'],
            $this->foliosDelListado('todos', $septiembre),
        );

        // La pestaña y el rango se combinan
        $this->assertSame(
            ['COT-2026-00001', 'VD-2026-00001'],
            $this->foliosDelListado('por_atender', $septiembre),
        );

        // Los extremos del rango cuentan completos (el 30 a las 18:45 entra)
        $this->assertSame(['VD-2026-00003'], $this->foliosDelListado('todos', ['desde' => '2026-09-28', 'hasta' => '2026-09-28']));
    }

    public function test_la_descarga_se_nombra_con_la_pestana_y_el_rango(): void
    {
        Livewire::actingAs($this->admin)->test(ListOrders::class)
            ->set('activeTab', 'por_atender')
            ->set('tableFilters.fecha.desde', '2026-09-01')
            ->set('tableFilters.fecha.hasta', '2026-09-30')
            ->callAction('descargarExcel')
            ->assertFileDownloaded('pedidos-y-cotizaciones_por-atender_2026-09-01_a_2026-09-30.xlsx');
    }

    public function test_sin_resultados_avisa_y_no_descarga(): void
    {
        Livewire::actingAs($this->admin)->test(ListOrders::class)
            ->set('activeTab', 'todos')
            ->set('tableFilters.fecha.desde', '2020-01-01')
            ->set('tableFilters.fecha.hasta', '2020-01-31')
            ->callAction('descargarExcel')
            ->assertNotified('No hay pedidos para descargar')
            ->assertNoFileDownloaded();
    }
}
