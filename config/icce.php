<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reglas del carrito híbrido (REQ-01 / REQ-02)
    |--------------------------------------------------------------------------
    | Límite por omisión de unidades que se pueden cobrar en línea. Cada
    | producto puede sobrescribirlo con su columna `max_direct_purchase`.
    */
    'max_direct_purchase' => (int) env('ICCE_MAX_DIRECT_PURCHASE', 10),

    /*
    |--------------------------------------------------------------------------
    | Contacto
    |--------------------------------------------------------------------------
    */
    'whatsapp_number' => env('ICCE_WHATSAPP_NUMBER'),
    'sales_email' => env('ICCE_SALES_EMAIL', 'ventas@icce.com.mx'),

    /*
    |--------------------------------------------------------------------------
    | Impuestos
    |--------------------------------------------------------------------------
    */
    'tax_rate' => (float) env('ICCE_TAX_RATE', 0.16),

    /*
    |--------------------------------------------------------------------------
    | Pasarela de pagos (REQ-03)
    |--------------------------------------------------------------------------
    | `simulado` recorre el checkout completo sin credenciales y se niega a
    | operar en producción. `stripe` y `openpay` están esbozados y lanzan una
    | excepción hasta implementarse: es preferible fallar de forma ruidosa a
    | fingir un cobro.
    */
    'payment' => [
        'driver' => env('ICCE_PAYMENT_DRIVER', 'simulado'),
    ],

];
