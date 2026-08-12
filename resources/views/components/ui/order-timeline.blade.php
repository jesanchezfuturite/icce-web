@props(['order'])

@php
    use App\Enums\OrderStatus;

    $steps = OrderStatus::trackingSteps();
    $current = $order->status->trackingPosition();
    $cancelled = $order->status === OrderStatus::Cancelled;

    // Fecha real de cada paso, tomada de la bitácora en vez de inferirla
    $dates = $order->relationLoaded('statusHistories')
        ? $order->statusHistories->keyBy(fn ($h) => $h->to_status->value)
        : collect();
@endphp

<div {{ $attributes->class(['w-full']) }}>
    @if($cancelled)
        <div class="rounded-xl border border-red-300 bg-red-50 p-5">
            <p class="font-display font-bold text-red-900">Pedido cancelado</p>
            <p class="mt-1 text-sm text-red-800">
                Cancelado el {{ $order->cancelled_at?->translatedFormat('d \d\e F, Y') ?? '—' }}.
                Contacta a tu agente si necesitas reactivarlo.
            </p>
        </div>
    @else
        <ol class="grid gap-6 sm:grid-cols-5 sm:gap-2">
            @foreach($steps as $index => $step)
                @php
                    $done = $current >= $index;
                    $isCurrent = $current === $index;
                    $date = $dates->get($step->value)?->created_at;
                @endphp

                <li class="relative flex gap-4 sm:flex-col sm:gap-3">
                    {{-- Riel de progreso --}}
                    <div class="flex flex-col items-center sm:w-full sm:flex-row">
                        @if(! $loop->first)
                            <span @class([
                                'absolute -top-6 left-[0.9375rem] h-6 w-0.5 sm:static sm:h-0.5 sm:w-full sm:flex-1',
                                'bg-brand-500' => $done,
                                'bg-carbon-200' => ! $done,
                            ])></span>
                        @endif

                        <span @class([
                            'relative z-10 flex size-8 shrink-0 items-center justify-center rounded-full border-2 transition',
                            'border-brand-500 bg-brand-500 text-carbon-950' => $done,
                            'border-carbon-300 bg-white text-carbon-400' => ! $done,
                            'ring-4 ring-brand-500/25' => $isCurrent,
                        ])>
                            @if($done)
                                <svg class="size-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                    <path d="m3.5 8.5 3 3 6-7" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            @else
                                <span class="size-1.5 rounded-full bg-current"></span>
                            @endif
                        </span>

                        @if(! $loop->last)
                            <span @class([
                                'hidden h-0.5 flex-1 sm:block',
                                'bg-brand-500' => $current > $index,
                                'bg-carbon-200' => $current <= $index,
                            ])></span>
                        @endif
                    </div>

                    <div class="pb-2 sm:pb-0">
                        <p @class([
                            'font-display text-sm font-bold',
                            'text-carbon-950' => $done,
                            'text-carbon-400' => ! $done,
                        ])>{{ $step->label() }}</p>

                        <p class="mt-0.5 text-xs text-carbon-500">
                            @if($date)
                                {{ $date->translatedFormat('d M Y') }}
                            @elseif($isCurrent)
                                En curso
                            @else
                                Pendiente
                            @endif
                        </p>
                    </div>
                </li>
            @endforeach
        </ol>

        @if($order->estimated_delivery_date)
            <div class="mt-8 flex flex-wrap items-center gap-x-8 gap-y-3 rounded-xl border border-carbon-200 bg-carbon-50 px-5 py-4">
                <div>
                    <p class="text-xs uppercase tracking-wider text-carbon-400">Fecha estimada de entrega</p>
                    <p class="mt-0.5 font-display text-base font-bold text-carbon-950">
                        {{ $order->estimated_delivery_date->translatedFormat('d \d\e F, Y') }}
                    </p>
                </div>

                @if($order->tracking_number)
                    <div>
                        <p class="text-xs uppercase tracking-wider text-carbon-400">Guía de rastreo</p>
                        <p class="mt-0.5 font-display text-base font-bold text-carbon-950">{{ $order->tracking_number }}</p>
                    </div>
                @endif
            </div>
        @endif
    @endif
</div>
