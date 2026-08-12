<?php

namespace App\Support\Cart;

use App\Models\Product;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * Carrito híbrido (3.4). Guarda en sesión sólo el par producto/cantidad; todo
 * lo demás —precio, disponibilidad, modo de compra— se resuelve contra la base
 * de datos en cada lectura, de forma que el carrito nunca sirve un precio
 * viejo ni promete existencia que ya no hay.
 */
class Cart
{
    private const KEY = 'icce.cart';

    /** @var Collection<int, CartLine>|null */
    private ?Collection $resolved = null;

    public function __construct(private readonly Session $session) {}

    // -----------------------------------------------------------------
    // Mutaciones
    // -----------------------------------------------------------------

    public function add(Product $product, int $quantity = 1): void
    {
        if ($quantity < 1) {
            return;
        }

        $items = $this->rawItems();
        $items[$product->id] = ($items[$product->id] ?? 0) + $quantity;

        $this->persist($items);
    }

    public function setQuantity(int $productId, int $quantity): void
    {
        $items = $this->rawItems();

        if ($quantity < 1) {
            unset($items[$productId]);
        } else {
            $items[$productId] = $quantity;
        }

        $this->persist($items);
    }

    public function remove(int $productId): void
    {
        $items = $this->rawItems();
        unset($items[$productId]);

        $this->persist($items);
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
        $this->resolved = null;
    }

    /** Vacía sólo las líneas ya convertidas en orden, conservando el resto. */
    public function removeMany(iterable $productIds): void
    {
        $items = $this->rawItems();

        foreach ($productIds as $id) {
            unset($items[$id]);
        }

        $this->persist($items);
    }

    // -----------------------------------------------------------------
    // Lectura
    // -----------------------------------------------------------------

    /** @return Collection<int, CartLine> */
    public function lines(): Collection
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $items = $this->rawItems();

        if ($items === []) {
            return $this->resolved = collect();
        }

        $products = Product::query()
            ->with(['brand', 'primaryImage'])
            ->whereIn('id', array_keys($items))
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        // Un producto dado de baja desaparece del carrito en silencio: no tiene
        // sentido pedirle al cliente que resuelva algo que ya no existe.
        $this->persist(array_intersect_key($items, $products->all()), reset: false);

        return $this->resolved = collect($items)
            ->filter(fn (int $qty, int $id) => $products->has($id))
            ->map(fn (int $qty, int $id) => CartLine::for($products->get($id), $qty))
            ->values();
    }

    /** Líneas que se pueden cobrar en línea (REQ-01). */
    public function purchasable(): Collection
    {
        return $this->lines()->filter(fn (CartLine $line) => $line->isPurchasable())->values();
    }

    /** Líneas que se convierten en solicitud de cotización (REQ-02). */
    public function quotable(): Collection
    {
        return $this->lines()->reject(fn (CartLine $line) => $line->isPurchasable())->values();
    }

    public function isEmpty(): bool
    {
        return $this->lines()->isEmpty();
    }

    /** Número de piezas, no de renglones: es lo que espera ver el usuario. */
    public function count(): int
    {
        return (int) $this->lines()->sum('quantity');
    }

    public function has(int $productId): bool
    {
        return array_key_exists($productId, $this->rawItems());
    }

    public function quantityOf(int $productId): int
    {
        return $this->rawItems()[$productId] ?? 0;
    }

    /** @param Collection<int, CartLine>|null $lines */
    public function subtotal(?Collection $lines = null): float
    {
        return round(($lines ?? $this->lines())->sum(fn (CartLine $line) => $line->total()), 2);
    }

    public function tax(?Collection $lines = null): float
    {
        return round($this->subtotal($lines) * (float) config('icce.tax_rate'), 2);
    }

    public function total(?Collection $lines = null): float
    {
        return round($this->subtotal($lines) + $this->tax($lines), 2);
    }

    /** El carrito mezcla los dos caminos y hay que resolver ambos. */
    public function isMixed(): bool
    {
        return $this->purchasable()->isNotEmpty() && $this->quotable()->isNotEmpty();
    }

    // -----------------------------------------------------------------
    // Sesión
    // -----------------------------------------------------------------

    /** @return array<int, int> producto => cantidad */
    private function rawItems(): array
    {
        return array_map('intval', $this->session->get(self::KEY, []));
    }

    private function persist(array $items, bool $reset = true): void
    {
        $this->session->put(self::KEY, $items);

        if ($reset) {
            $this->resolved = null;
        }
    }
}
