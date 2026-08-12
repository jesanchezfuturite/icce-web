<?php

namespace App\Enums;

/**
 * Estatus de una orden. Los cinco pasos que el cliente ve en el timeline
 * (REQ-04) son: Cotizado -> Pagado -> En Almacén -> En Tránsito -> Entregado.
 * `Pending` y `Cancelled` existen en el modelo pero viven fuera de esa barra.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Quoted = 'quoted';
    case Paid = 'paid';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Quoted => 'Cotizado',
            self::Paid => 'Pagado',
            self::Processing => 'En almacén',
            self::Shipped => 'En tránsito',
            self::Delivered => 'Entregado',
            self::Cancelled => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Quoted => 'info',
            self::Paid => 'success',
            self::Processing => 'warning',
            self::Shipped => 'primary',
            self::Delivered => 'success',
            self::Cancelled => 'danger',
        };
    }

    /** Pasos que se dibujan en la barra de progreso del portal de cliente. */
    public static function trackingSteps(): array
    {
        return [self::Quoted, self::Paid, self::Processing, self::Shipped, self::Delivered];
    }

    /** Posición en el timeline; -1 si el estatus no forma parte de la barra. */
    public function trackingPosition(): int
    {
        $index = array_search($this, self::trackingSteps(), strict: true);

        return $index === false ? -1 : $index;
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Delivered, self::Cancelled], strict: true);
    }
}
