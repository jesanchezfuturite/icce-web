@php use App\Enums\OrderType; @endphp

<x-layouts.app title="Pedido confirmado" description="Tu pedido quedó registrado.">

    <section class="relative isolate overflow-hidden bg-carbon-950 py-20 lg:py-28">
        <div class="pointer-events-none absolute -right-40 top-1/2 size-[36rem] -translate-y-1/2 rounded-full bg-brand-500/10 blur-3xl"></div>

        <x-ui.container class="relative max-w-3xl text-center">
            <span class="mx-auto flex size-16 items-center justify-center rounded-full bg-brand-500 text-carbon-950">
                <svg class="size-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path d="m5 13 4 4 10-11" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>

            <h1 class="mt-8 font-display text-4xl font-extrabold leading-tight text-white sm:text-5xl">
                @if($orders->count() > 1)
                    Listo. Tu pedido se dividió en dos.
                @elseif($orders->first()->order_type === OrderType::Quote)
                    Recibimos tu solicitud
                @else
                    ¡Gracias por tu compra!
                @endif
            </h1>

            <p class="mx-auto mt-5 max-w-xl leading-relaxed text-white/60">
                @if($orders->count() > 1)
                    Lo que tenía existencia se procesó como compra; el resto entró como cotización
                    para que un agente le aplique precio de proyecto. Cada parte tiene su folio.
                @else
                    Te enviamos la confirmación por correo. Puedes seguir el avance desde tu cuenta.
                @endif
            </p>
        </x-ui.container>

        <div class="scale-rule absolute inset-x-0 bottom-0 h-1 opacity-70"></div>
    </section>

    <section class="py-14 lg:py-20">
        <x-ui.container class="max-w-3xl space-y-6">
            @foreach($orders as $order)
                <div class="rounded-2xl border border-carbon-200 p-7">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-carbon-400">
                                {{ $order->order_type->label() }}
                            </p>
                            <p class="mt-1 font-display text-2xl font-extrabold text-carbon-950">{{ $order->folio }}</p>
                        </div>
                        <span class="rounded-full bg-carbon-100 px-3 py-1.5 text-xs font-semibold text-carbon-700">
                            {{ $order->status->label() }}
                        </span>
                    </div>

                    <ul class="mt-6 space-y-2 border-t border-carbon-200 pt-5 text-sm">
                        @foreach($order->items as $item)
                            <li class="flex justify-between gap-4">
                                <span class="min-w-0 flex-1 text-carbon-600">{{ $item->quantity }} × {{ $item->product_name }}</span>
                                <span class="tabular-nums text-carbon-900">${{ number_format((float) $item->line_total, 2) }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-5 flex justify-between border-t border-carbon-200 pt-4">
                        <span class="font-display font-bold text-carbon-950">
                            {{ $order->order_type === OrderType::Quote ? 'Total estimado' : 'Total' }}
                        </span>
                        <span class="font-display text-lg font-extrabold tabular-nums text-carbon-950">
                            ${{ number_format((float) $order->total_amount, 2) }} MXN
                        </span>
                    </div>

                    <p class="mt-5 rounded-lg bg-carbon-50 p-4 text-sm leading-relaxed text-carbon-600">
                        @if($order->order_type === OrderType::Quote)
                            Un agente revisa las partidas, aplica el descuento por volumen que corresponda
                            y te envía la propuesta. Normalmente el mismo día hábil.
                        @elseif($order->payment_status === 'pending')
                            Te enviamos por correo la CLABE y la referencia SPEI.
                            El pedido avanza a almacén en cuanto se acredita el depósito.
                        @else
                            Pago confirmado. Almacén ya tiene la orden; te avisamos con la fecha de entrega.
                        @endif
                    </p>
                </div>
            @endforeach

            <div class="flex flex-wrap justify-center gap-3 pt-4">
                @auth
                    <x-ui.button href="{{ route('portal.index') }}" size="lg">Ver en mi cuenta</x-ui.button>
                @else
                    <x-ui.button href="{{ route('login') }}" size="lg">Entrar a mi cuenta</x-ui.button>
                @endauth
                <x-ui.button href="{{ route('catalogo.index') }}" variant="outline" size="lg" :icon="false">
                    Seguir comprando
                </x-ui.button>
            </div>

            <p class="pt-2 text-center text-sm text-carbon-500">
                Guarda {{ $orders->count() > 1 ? 'tus folios' : 'tu folio' }}: con
                {{ $orders->count() > 1 ? 'ellos' : 'él' }} podemos ubicar tu pedido al instante.
            </p>
        </x-ui.container>
    </section>
</x-layouts.app>
