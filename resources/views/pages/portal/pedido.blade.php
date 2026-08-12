<x-layouts.app :title="$order->folio" description="Detalle y seguimiento de tu pedido.">

    <x-ui.page-header
        :eyebrow="$order->order_type->label()"
        :title="$order->folio"
        :breadcrumbs="['Mi cuenta' => route('portal.index'), $order->folio => null]">

        <p class="mt-6 flex flex-wrap items-center gap-3 text-sm text-white/55">
            <span class="rounded-full bg-white/10 px-3 py-1 font-semibold text-white">{{ $order->status->label() }}</span>
            <span>Creado el {{ $order->created_at->translatedFormat('d \d\e F, Y') }}</span>
            @if($order->agent)
                <span class="text-white/25">&middot;</span>
                <span>Atiende {{ $order->agent->name }}</span>
            @endif
        </p>
    </x-ui.page-header>

    {{-- 7.2 Timeline de rastreo --}}
    <section class="border-b border-carbon-200 py-12">
        <x-ui.container>
            <h2 class="font-display text-sm font-bold uppercase tracking-[0.16em] text-carbon-950">Seguimiento</h2>
            <div class="mt-8">
                <x-ui.order-timeline :order="$order" />
            </div>
        </x-ui.container>
    </section>

    <section class="py-14 lg:py-20">
        <x-ui.container class="grid gap-12 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <h2 class="font-display text-xl font-extrabold text-carbon-950">Partidas del pedido</h2>

                <div class="mt-6 overflow-hidden rounded-2xl border border-carbon-200">
                    <table class="w-full text-sm">
                        <thead class="bg-carbon-50 text-left text-xs uppercase tracking-wider text-carbon-500">
                            <tr>
                                <th scope="col" class="px-5 py-3 font-semibold">Producto</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">Cant.</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">Unitario</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">Importe</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-carbon-200">
                            @foreach($order->items as $item)
                                <tr>
                                    <td class="px-5 py-4">
                                        <p class="font-medium text-carbon-900">{{ $item->product_name }}</p>
                                        <p class="mt-0.5 text-xs text-carbon-400">SKU {{ $item->product_sku }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-right tabular-nums text-carbon-700">{{ $item->quantity }}</td>
                                    <td class="px-5 py-4 text-right tabular-nums text-carbon-700">
                                        ${{ number_format((float) $item->effectiveUnitPrice(), 2) }}
                                        @if($item->quoted_unit_price)
                                            <span class="block text-xs text-brand-700">precio cotizado</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-right font-semibold tabular-nums text-carbon-950">
                                        ${{ number_format((float) $item->line_total, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($order->statusHistories->isNotEmpty())
                    <h2 class="mt-12 font-display text-xl font-extrabold text-carbon-950">Bitácora</h2>
                    <ol class="mt-6 space-y-4 border-l border-carbon-200 pl-6">
                        @foreach($order->statusHistories->sortByDesc('created_at') as $history)
                            <li class="relative">
                                <span class="absolute -left-[1.6875rem] top-1.5 size-2.5 rounded-full bg-brand-500 ring-4 ring-white"></span>
                                <p class="text-sm font-semibold text-carbon-900">{{ $history->to_status->label() }}</p>
                                <p class="mt-0.5 text-xs text-carbon-500">
                                    {{ $history->created_at->translatedFormat('d \d\e F, Y') }}
                                    @if($history->user) &middot; {{ $history->user->name }} @endif
                                    @if($history->notified_customer) &middot; cliente notificado @endif
                                </p>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>

            <aside class="lg:sticky lg:top-32 lg:self-start">
                <div class="rounded-2xl border border-carbon-200 p-6">
                    <h2 class="font-display text-sm font-bold uppercase tracking-[0.16em] text-carbon-950">Resumen</h2>

                    <dl class="mt-5 space-y-3 text-sm">
                        @foreach([
                            'Subtotal' => $order->subtotal,
                            'Descuento' => $order->discount_amount,
                            'IVA' => $order->tax_amount,
                            'Envío' => $order->shipping_amount,
                        ] as $label => $amount)
                            @if((float) $amount > 0 || $label === 'Subtotal')
                                <div class="flex justify-between">
                                    <dt class="text-carbon-500">{{ $label }}</dt>
                                    <dd class="tabular-nums text-carbon-900">${{ number_format((float) $amount, 2) }}</dd>
                                </div>
                            @endif
                        @endforeach

                        <div class="flex justify-between border-t border-carbon-200 pt-3">
                            <dt class="font-display font-bold text-carbon-950">Total</dt>
                            <dd class="font-display text-lg font-extrabold tabular-nums text-carbon-950">
                                ${{ number_format((float) $order->total_amount, 2) }}
                            </dd>
                        </div>
                    </dl>
                </div>

                @if($order->shipping_address)
                    <div class="mt-4 rounded-2xl border border-carbon-200 p-6">
                        <h2 class="font-display text-sm font-bold uppercase tracking-[0.16em] text-carbon-950">Dirección de entrega</h2>
                        <address class="mt-4 text-sm not-italic leading-relaxed text-carbon-600">
                            @foreach($order->shipping_address as $line)
                                {{ $line }}<br>
                            @endforeach
                        </address>
                    </div>
                @endif

                <div class="mt-4 rounded-2xl bg-carbon-950 p-6 text-white">
                    <p class="text-sm leading-relaxed text-white/60">
                        ¿Alguna duda con este pedido? Tu agente puede ajustarlo o reprogramar la entrega.
                    </p>
                    <x-ui.button href="{{ route('contacto') }}" class="mt-5 w-full">Contactar a mi agente</x-ui.button>
                </div>
            </aside>
        </x-ui.container>
    </section>
</x-layouts.app>
