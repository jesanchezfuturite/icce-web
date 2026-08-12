{{-- Propuesta comercial en PDF (REQ-09). Dompdf sólo entiende CSS 2.1,
     así que aquí no aplica nada del design system basado en Tailwind. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cotización {{ $order->folio }}</title>
    <style>
        @page { margin: 26mm 16mm 22mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #24261c; line-height: 1.5; }
        .marca { color: #578113; font-weight: bold; letter-spacing: .5px; }
        header { border-bottom: 3px solid #84c021; padding-bottom: 10px; margin-bottom: 18px; }
        h1 { font-size: 20px; margin: 0 0 2px; }
        .sub { color: #7e8273; font-size: 10px; }
        .bloques { width: 100%; margin-bottom: 18px; }
        .bloques td { vertical-align: top; width: 50%; padding-right: 14px; }
        .etiqueta { text-transform: uppercase; letter-spacing: 1px; font-size: 8px; color: #a6a99e; margin-bottom: 3px; }
        table.partidas { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.partidas th { background: #f3f4f0; text-align: left; padding: 7px 8px; font-size: 9px;
                            text-transform: uppercase; letter-spacing: .6px; color: #4d5143;
                            border-bottom: 1px solid #ced0c8; }
        table.partidas td { padding: 8px; border-bottom: 1px solid #e4e6e0; }
        .num { text-align: right; }
        .sku { color: #a6a99e; font-size: 9px; }
        .tachado { color: #a6a99e; text-decoration: line-through; font-size: 9px; }
        .totales { width: 46%; margin-left: 54%; margin-top: 14px; border-collapse: collapse; }
        .totales td { padding: 5px 8px; }
        .totales .final td { border-top: 2px solid #24261c; font-weight: bold; font-size: 13px; padding-top: 8px; }
        .aviso { margin-top: 22px; background: #f7fcee; border-left: 3px solid #84c021; padding: 10px 12px; font-size: 9.5px; }
        footer { position: fixed; bottom: -14mm; left: 0; right: 0; font-size: 8.5px; color: #a6a99e;
                 border-top: 1px solid #e4e6e0; padding-top: 6px; }
    </style>
</head>
<body>
    <header>
        <table style="width:100%">
            <tr>
                <td>
                    <div class="marca">ICCE RENTAS Y SERVICIOS</div>
                    <div class="sub">Pisos industriales y pisos superplanos · desde 1992</div>
                </td>
                <td style="text-align:right">
                    <h1>Cotización</h1>
                    <div class="sub">{{ $order->folio }}</div>
                </td>
            </tr>
        </table>
    </header>

    <table class="bloques">
        <tr>
            <td>
                <div class="etiqueta">Cliente</div>
                <strong>{{ $order->customer_name }}</strong><br>
                @if($order->customer_company){{ $order->customer_company }}<br>@endif
                {{ $order->customer_email }}<br>
                {{ $order->customer_phone }}
            </td>
            <td>
                <div class="etiqueta">Datos de la propuesta</div>
                Emitida: {{ $order->quoted_at?->translatedFormat('d/m/Y') ?? now()->format('d/m/Y') }}<br>
                @if($order->quote_valid_until)
                    Vigencia: <strong>{{ $order->quote_valid_until->translatedFormat('d/m/Y') }}</strong><br>
                @endif
                @if($order->agent)Atiende: {{ $order->agent->name }}@endif
            </td>
        </tr>
    </table>

    @if($order->shipping_address)
        <div class="etiqueta">Entrega</div>
        <div style="margin-bottom:14px">{{ implode(', ', $order->shipping_address) }}</div>
    @endif

    <table class="partidas">
        <thead>
            <tr>
                <th style="width:48%">Descripción</th>
                <th class="num">Cant.</th>
                <th class="num">Unitario</th>
                <th class="num">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td>
                        {{ $item->product_name }}<br>
                        <span class="sku">SKU {{ $item->product_sku }}</span>
                    </td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">
                        ${{ number_format((float) $item->effectiveUnitPrice(), 2) }}
                        @if($item->quoted_unit_price)
                            <br><span class="tachado">${{ number_format((float) $item->unit_price, 2) }}</span>
                        @endif
                    </td>
                    <td class="num">${{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totales">
        <tr>
            <td>Subtotal</td>
            <td class="num">${{ number_format((float) $order->subtotal, 2) }}</td>
        </tr>
        @if((float) $order->discount_amount > 0)
            <tr>
                <td>Descuento</td>
                <td class="num">−${{ number_format((float) $order->discount_amount, 2) }}</td>
            </tr>
        @endif
        <tr>
            <td>IVA {{ (int) (config('icce.tax_rate') * 100) }}%</td>
            <td class="num">${{ number_format((float) $order->tax_amount, 2) }}</td>
        </tr>
        <tr class="final">
            <td>Total</td>
            <td class="num">${{ number_format((float) $order->total_amount, 2) }} MXN</td>
        </tr>
    </table>

    <div class="aviso">
        Precios en pesos mexicanos. La vigencia aplica salvo cambio del fabricante o del tipo de cambio.
        Los tiempos de entrega de material bajo pedido se confirman al recibir la orden de compra.
    </div>

    @if($order->customer_notes)
        <div style="margin-top:14px">
            <div class="etiqueta">Notas del cliente</div>
            {{ $order->customer_notes }}
        </div>
    @endif

    <footer>
        ICCE Rentas y Servicios · Monterrey, Nuevo León ·
        {{ config('icce.sales_email') }} · Cotización {{ $order->folio }}
    </footer>
</body>
</html>
