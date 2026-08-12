@php use App\Enums\OrderType; @endphp

<x-layouts.app title="Mi cuenta" description="Historial de pedidos, cotizaciones y seguimiento de entregas.">

    <x-ui.page-header
        eyebrow="Portal de cliente"
        :title="'Hola, '.\App\Support\PersonName::first(auth()->user()->name)"
        :lead="auth()->user()->company ?: 'Consulta tus pedidos, cotizaciones y fechas de entrega.'"
        :breadcrumbs="['Mi cuenta' => null]" />

    <section class="border-b border-carbon-200 bg-carbon-50">
        <x-ui.container class="grid grid-cols-2 divide-carbon-200 py-8 sm:divide-x lg:grid-cols-4">
            @foreach([
                [$orders->count(), 'Pedidos totales'],
                [$sales->count(), 'Compras directas'],
                [$quotes->count(), 'Cotizaciones'],
                [$inTransit->count(), 'En proceso de entrega'],
            ] as [$value, $label])
                <div class="px-2 py-4 text-center sm:px-6">
                    <p class="font-display text-2xl font-extrabold text-carbon-950 sm:text-3xl">{{ $value }}</p>
                    <p class="mt-1 text-xs uppercase tracking-wider text-carbon-500">{{ $label }}</p>
                </div>
            @endforeach
        </x-ui.container>
    </section>

    <section class="py-14 lg:py-20">
        <x-ui.container>
            <x-ui.section-heading eyebrow="7.1 Historial" title="Pedidos y cotizaciones" />

            <div class="mt-10 space-y-4">
                @forelse($orders as $order)
                    <a href="{{ route('portal.pedido', $order) }}"
                       class="group block rounded-2xl border border-carbon-200 p-6 transition hover:-translate-y-0.5 hover:border-carbon-950 hover:shadow-xl hover:shadow-carbon-950/8">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <span class="font-display text-lg font-extrabold text-carbon-950">{{ $order->folio }}</span>

                                    <span @class([
                                        'rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset',
                                        'bg-brand-50 text-brand-800 ring-brand-600/20' => $order->order_type === OrderType::DirectSale,
                                        'bg-sky-50 text-sky-700 ring-sky-600/20' => $order->order_type === OrderType::Quote,
                                    ])>{{ $order->order_type->label() }}</span>

                                    <span class="rounded-full bg-carbon-100 px-2.5 py-1 text-xs font-semibold text-carbon-700">
                                        {{ $order->status->label() }}
                                    </span>
                                </div>

                                <p class="mt-2 text-sm text-carbon-500">
                                    {{ $order->created_at->translatedFormat('d \d\e F, Y') }}
                                    &middot; {{ $order->items->count() }} {{ Str::plural('partida', $order->items->count()) }}
                                    @if($order->estimated_delivery_date)
                                        &middot; entrega estimada {{ $order->estimated_delivery_date->translatedFormat('d M Y') }}
                                    @endif
                                </p>
                            </div>

                            <div class="text-right">
                                <p class="font-display text-xl font-extrabold text-carbon-950">
                                    ${{ number_format((float) $order->total_amount, 2) }}
                                </p>
                                <p class="text-xs text-carbon-400">{{ $order->currency }} con IVA</p>
                            </div>
                        </div>

                        <div class="mt-6 border-t border-carbon-200 pt-5">
                            <x-ui.order-timeline :order="$order" />
                        </div>
                    </a>
                @empty
                    <div class="rounded-2xl border border-carbon-200 bg-carbon-50 p-10 text-center">
                        <p class="font-display text-lg font-bold text-carbon-950">Aún no tienes pedidos</p>
                        <p class="mt-2 text-sm text-carbon-600">Cuando hagas tu primera compra o cotización, aparecerá aquí.</p>
                        <x-ui.button href="{{ route('catalogo.index') }}" class="mt-6">Ir al catálogo</x-ui.button>
                    </div>
                @endforelse
            </div>

            <p class="mt-12 rounded-xl border border-amber-300/60 bg-amber-50 p-5 text-sm leading-relaxed text-amber-900">
                <strong class="font-semibold">Adelanto de la fase 5.</strong>
                Ya funcionan el historial (7.1) y el timeline de rastreo (7.2). Los datos de facturación
                y direcciones (7.3), y la aprobación y pago de cotizaciones en línea, llegan con esa fase.
            </p>
        </x-ui.container>
    </section>
</x-layouts.app>
