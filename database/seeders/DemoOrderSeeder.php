<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\RentalCoverage;
use App\Enums\RentalRequestStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\RentalRequest;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Datos de demostración para poder ver el CRM y el timeline del portal de
 * cliente con contenido desde el primer arranque. No se ejecuta en producción.
 */
class DemoOrderSeeder extends Seeder
{
    public function run(): void
    {
        $client = User::where('role', UserRole::Client)->firstOrFail();
        $agent = User::where('role', UserRole::Sales)->firstOrFail();

        // Se acotan los precios para que los importes de demostración sean
        // creíbles en una presentación: un pedido de consumibles, no una orden
        // de tres millones de pesos en equipo pesado.
        $inStock = Product::forSale()->where('is_on_demand', false)
            ->where('stock_qty', '>', 20)
            ->whereBetween('price', [300, 5000])
            ->take(6)->get();

        $onDemand = Product::where('is_on_demand', true)
            ->whereBetween('price', [300, 6000])
            ->take(3)->get();

        if ($inStock->isEmpty()) {
            $this->command?->warn('Sin productos en existencia: se omite DemoOrderSeeder.');

            return;
        }

        // Una orden por cada estatus del timeline, para ver la barra completa.
        $scenarios = [
            [OrderType::DirectSale, OrderStatus::Paid, 0],
            [OrderType::DirectSale, OrderStatus::Processing, 3],
            [OrderType::DirectSale, OrderStatus::Shipped, 7],
            [OrderType::DirectSale, OrderStatus::Delivered, 14],
            [OrderType::Quote, OrderStatus::Pending, 1],
            [OrderType::Quote, OrderStatus::Quoted, 5],
        ];

        foreach ($scenarios as [$type, $status, $daysAgo]) {
            $products = $type === OrderType::Quote && $onDemand->isNotEmpty()
                ? $onDemand
                : $inStock->random(min(3, $inStock->count()));

            $order = Order::create([
                'folio' => Order::nextFolio($type),
                'user_id' => $client->id,
                'assigned_to' => $agent->id,
                'order_type' => $type,
                'status' => $status,
                'customer_name' => $client->name,
                'customer_email' => $client->email,
                'customer_phone' => $client->phone,
                'customer_company' => $client->company,
                'currency' => 'MXN',
                'shipping_address' => [
                    'calle' => 'Av. Manuel L. Barragán 1234',
                    'colonia' => 'Residencial Anáhuac',
                    'ciudad' => 'San Nicolás de los Garza',
                    'estado' => 'Nuevo León',
                    'cp' => '66450',
                ],
                'estimated_delivery_date' => $status->trackingPosition() >= 2
                    ? now()->subDays($daysAgo)->addDays(7)
                    : null,
                'tracking_number' => $status === OrderStatus::Shipped || $status === OrderStatus::Delivered
                    ? 'ICCE'.now()->format('y').str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT)
                    : null,
                'created_at' => now()->subDays($daysAgo),
                'updated_at' => now()->subDays($daysAgo),
            ]);

            $subtotal = 0;

            foreach ($products as $product) {
                // Cantidad alta a propósito en cotizaciones: dispara REQ-02.
                $quantity = $type === OrderType::Quote
                    ? random_int(15, 60)
                    : random_int(1, min(5, max(1, $product->max_direct_purchase)));

                $lineTotal = round((float) $product->price * $quantity, 2);
                $subtotal += $lineTotal;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_sku' => $product->sku,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                    'line_total' => $lineTotal,
                ]);
            }

            $tax = round($subtotal * (float) config('icce.tax_rate'), 2);

            $order->update([
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'total_amount' => round($subtotal + $tax, 2),
            ]);

            $this->seedHistory($order, $agent);
        }

        $this->seedRentalRequests($agent);

        $this->command?->info('Órdenes de demostración: '.Order::count().' | Solicitudes de renta: '.RentalRequest::count());
    }

    /** Reconstruye la bitácora hasta el estatus actual para alimentar el timeline. */
    private function seedHistory(Order $order, User $agent): void
    {
        $target = $order->status->trackingPosition();

        if ($target < 0) {
            return;
        }

        $previous = null;
        $steps = array_slice(OrderStatus::trackingSteps(), 0, $target + 1);

        foreach ($steps as $index => $step) {
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'user_id' => $agent->id,
                'from_status' => $previous,
                'to_status' => $step,
                'note' => 'Cambio de estatus a '.$step->label().'.',
                'notified_customer' => true,
                'created_at' => $order->created_at->copy()->addDays($index),
                'updated_at' => $order->created_at->copy()->addDays($index),
            ]);

            $previous = $step;
        }
    }

    private function seedRentalRequests(User $agent): void
    {
        $requests = [
            ['Regla Láser Somero S-940', 'Ing. Rodrigo Cantú', 'Grupo Constructor Vertice',
                'Apodaca, Nuevo León', RentalCoverage::National, RentalRequestStatus::New, 12],
            ['Allanadora Doble 46"', 'Arq. Mariana Treviño', 'Desarrollos Pantera',
                'Guadalajara, Jalisco', RentalCoverage::National, RentalRequestStatus::Contacted, 5],
            ['Compactadora Bailarina', 'Sr. Luis Gaytán', 'Urbanizadora Regia',
                'Monterrey, Nuevo León', RentalCoverage::Local, RentalRequestStatus::Quoted, 20],
        ];

        foreach ($requests as [$equipment, $name, $company, $location, $coverage, $status, $days]) {
            RentalRequest::create([
                'folio' => RentalRequest::nextFolio(),
                'equipment_name' => $equipment,
                'client_name' => $name,
                'company' => $company,
                'email' => 'contacto@'.str($company)->slug().'.mx',
                'phone' => '81 '.random_int(1000, 9999).' '.random_int(1000, 9999),
                'location' => $location,
                'coverage' => $coverage,
                'start_date' => now()->addDays(random_int(3, 30)),
                'rental_days' => $days,
                'project_description' => 'Colado de losa industrial con acabado superplano.',
                'status' => $status,
                'assigned_to' => $status === RentalRequestStatus::New ? null : $agent->id,
                'contacted_at' => $status === RentalRequestStatus::New ? null : now()->subDays(2),
            ]);
        }
    }
}
