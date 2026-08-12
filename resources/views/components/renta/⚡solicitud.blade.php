<?php

use App\Enums\RentalCoverage;
use App\Mail\RentalRequestNotification;
use App\Mail\RentalRequestReceivedMail;
use App\Models\Product;
use App\Models\RentalRequest;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Formulario adaptativo de solicitud de renta (REQ-07).
 *
 * Los campos cambian según la cobertura porque las dos operaciones son
 * distintas: una regla láser que viaja a Guanajuato necesita saber si hay
 * operador y cómo se maniobra en sitio; una compactadora que sale del almacén
 * de Monterrey necesita saber si la recogen o se entrega.
 *
 * La cobertura se propone desde el equipo elegido y el cliente puede cambiarla:
 * quien pide desde Saltillo un equipo «local» sigue siendo un prospecto válido.
 */
new class extends Component
{
    #[Url(as: 'equipo', except: '')]
    public string $equipmentSlug = '';

    public ?int $productId = null;

    public string $equipmentName = '';

    public string $coverage = 'local';

    // Datos de contacto
    public string $clientName = '';
    public string $company = '';
    public string $email = '';
    public string $phone = '';

    // Datos de la obra
    public string $location = '';
    public string $startDate = '';
    public string $rentalDays = '';
    public string $projectDescription = '';

    // Campos propios de cada cobertura
    public bool $needsOperator = false;
    public bool $needsFreight = false;
    public string $siteAccess = '';
    public string $delivery = 'entrega';

    public bool $accepted = false;

    /** Trampa antispam. */
    public string $apellidoMaterno = '';

    public bool $sent = false;

    public ?string $folio = null;

    public function mount(?Product $product = null): void
    {
        $equipo = $product ?? ($this->equipmentSlug !== ''
            ? Product::rentals()->active()->where('slug', $this->equipmentSlug)->first()
            : null);

        if ($equipo) {
            $this->productId = $equipo->id;
            $this->equipmentName = $equipo->name;
            $this->equipmentSlug = $equipo->slug;
            $this->coverage = $equipo->rental_coverage?->value ?? 'local';
        }

        if ($usuario = auth()->user()) {
            $this->clientName = $usuario->name;
            $this->company = (string) $usuario->company;
            $this->email = $usuario->email;
            $this->phone = (string) $usuario->phone;
        }
    }

    #[Computed]
    public function isNational(): bool
    {
        return $this->coverage === RentalCoverage::National->value;
    }

    #[Computed]
    public function equipmentOptions()
    {
        return Product::rentals()->active()->orderBy('name')->get(['id', 'name', 'slug', 'rental_coverage']);
    }

    public function updatedProductId($value): void
    {
        $equipo = $this->equipmentOptions()->firstWhere('id', (int) $value);

        if ($equipo) {
            $this->equipmentName = $equipo->name;
            $this->coverage = $equipo->rental_coverage?->value ?? $this->coverage;
        }
    }

    protected function rules(): array
    {
        return [
            'productId' => ['nullable', 'integer', 'exists:products,id'],
            'equipmentName' => ['required', 'string', 'max:150'],
            'coverage' => ['required', 'in:national,local'],
            'clientName' => ['required', 'string', 'max:150'],
            'company' => ['nullable', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:191'],
            'phone' => ['required', 'string', 'max:50'],
            'location' => ['required', 'string', 'max:150'],
            'startDate' => ['nullable', 'date', 'after_or_equal:today'],
            'rentalDays' => ['nullable', 'integer', 'min:1', 'max:365'],
            'projectDescription' => ['nullable', 'string', 'max:1000'],
            'siteAccess' => ['nullable', 'string', 'max:255'],
            'delivery' => ['required', 'in:entrega,recoge'],
            'accepted' => ['accepted'],
            'apellidoMaterno' => ['prohibited'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'equipmentName' => 'equipo',
            'clientName' => 'nombre',
            'email' => 'correo electrónico',
            'phone' => 'teléfono',
            'location' => 'ubicación de la obra',
            'startDate' => 'fecha de inicio',
            'rentalDays' => 'días de renta',
            'accepted' => 'aviso de privacidad',
        ];
    }

    protected function messages(): array
    {
        return [
            'startDate.after_or_equal' => 'La fecha de inicio no puede ser anterior a hoy.',
            'accepted.accepted' => 'Necesitamos tu consentimiento para poder contactarte.',
            'apellidoMaterno.prohibited' => 'No pudimos procesar el envío.',
        ];
    }

    public function submit(): void
    {
        $this->validate();

        // Los campos propios de cada cobertura se guardan en la nota interna:
        // el agente los necesita, pero no justifican una columna cada uno.
        $detalles = array_filter([
            $this->isNational() && $this->needsFreight ? 'Requiere maniobra y flete a sitio.' : null,
            $this->needsOperator ? 'Requiere operador de ICCE.' : null,
            $this->siteAccess !== '' ? 'Acceso a sitio: '.$this->siteAccess : null,
            ! $this->isNational() ? ($this->delivery === 'recoge'
                ? 'El cliente recoge en almacén.'
                : 'Entrega en obra dentro del área metropolitana.') : null,
        ]);

        $solicitud = RentalRequest::create([
            'folio' => RentalRequest::nextFolio(),
            'product_id' => $this->productId,
            'equipment_name' => $this->equipmentName,
            'client_name' => $this->clientName,
            'company' => $this->company ?: null,
            'email' => $this->email,
            'phone' => $this->phone,
            'location' => $this->location,
            'coverage' => $this->coverage,
            'start_date' => $this->startDate ?: null,
            'rental_days' => $this->rentalDays !== '' ? (int) $this->rentalDays : null,
            'project_description' => $this->projectDescription ?: null,
            'notes' => $detalles ? implode(' ', $detalles) : null,
        ]);

        try {
            Mail::to(config('icce.sales_email'))->send(new RentalRequestNotification($solicitud));
            Mail::to($solicitud->email)->send(new RentalRequestReceivedMail($solicitud));
        } catch (\Throwable $e) {
            Log::error('No se pudo avisar la solicitud de renta '.$solicitud->folio, ['error' => $e->getMessage()]);
        }

        $this->folio = $solicitud->folio;
        $this->sent = true;
    }
};
?>

<div>
@if($sent)
    <div class="rounded-2xl border border-brand-500/40 bg-brand-50 p-8">
        <span class="flex size-12 items-center justify-center rounded-full bg-brand-500 text-carbon-950">
            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <path d="m5 13 4 4 10-11" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </span>
        <h2 class="mt-5 font-display text-2xl font-extrabold text-carbon-950">Solicitud recibida</h2>
        <p class="mt-3 leading-relaxed text-carbon-700">
            Tu folio es <b class="font-display">{{ $folio }}</b>. Un agente revisa disponibilidad
            para las fechas que indicaste y te contacta el mismo día hábil con la tarifa.
        </p>
        <div class="mt-6 flex flex-wrap gap-3">
            <x-ui.button href="{{ route('renta.index') }}">Ver más equipos</x-ui.button>
            <x-ui.button href="{{ route('renta.requisitos') }}" variant="outline" :icon="false">
                Revisar requisitos
            </x-ui.button>
        </div>
    </div>
@else
    <form wire:submit="submit" class="grid gap-6">
        {{-- Equipo y cobertura --}}
        <fieldset class="grid gap-5 sm:grid-cols-2">
            <legend class="mb-1 font-display text-lg font-extrabold text-carbon-950 sm:col-span-2">
                Qué equipo necesitas
            </legend>

            <div class="sm:col-span-2">
                <label for="equipo" class="block text-sm font-semibold text-carbon-900">
                    Equipo <span class="text-brand-700">*</span>
                </label>
                <select id="equipo" wire:model.live="productId"
                        class="mt-2 h-12 w-full rounded-lg border border-carbon-300 bg-white px-4 text-sm text-carbon-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25">
                    <option value="">Selecciona un equipo…</option>
                    @foreach($this->equipmentOptions() as $opcion)
                        <option value="{{ $opcion->id }}">{{ $opcion->name }}</option>
                    @endforeach
                </select>
                @error('equipmentName')<p class="mt-1.5 text-xs text-red-700">{{ $message }}</p>@enderror
            </div>

            <div class="sm:col-span-2">
                <span class="block text-sm font-semibold text-carbon-900">Cobertura</span>
                <p class="mt-1 text-xs text-carbon-500">
                    La proponemos según el equipo; cámbiala si tu obra está en otro lado.
                </p>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    @foreach([
                        ['local', 'Local — área de Monterrey', 'Entrega o recolección desde nuestro almacén.'],
                        ['national', 'Nacional — otro estado', 'El equipo viaja a tu obra en cualquier parte del país.'],
                    ] as [$valor, $titulo, $detalle])
                        <label @class([
                            'flex cursor-pointer gap-3 rounded-xl border p-4 transition',
                            'border-brand-500 bg-brand-50' => $coverage === $valor,
                            'border-carbon-300 hover:border-carbon-500' => $coverage !== $valor,
                        ])>
                            <input type="radio" wire:model.live="coverage" value="{{ $valor }}"
                                   class="mt-0.5 size-4 shrink-0 border-carbon-300 accent-brand-600">
                            <span>
                                <span class="block text-sm font-semibold text-carbon-950">{{ $titulo }}</span>
                                <span class="mt-0.5 block text-xs leading-relaxed text-carbon-600">{{ $detalle }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        </fieldset>

        {{-- Datos de la obra --}}
        <fieldset class="grid gap-5 border-t border-carbon-200 pt-6 sm:grid-cols-2">
            <legend class="mb-1 font-display text-lg font-extrabold text-carbon-950 sm:col-span-2">
                Datos de la obra
            </legend>

            <div class="sm:col-span-2">
                <label for="ubicacion" class="block text-sm font-semibold text-carbon-900">
                    {{ $this->isNational() ? 'Ciudad y estado de la obra' : 'Dirección o zona de la obra' }}
                    <span class="text-brand-700">*</span>
                </label>
                <input type="text" id="ubicacion" wire:model="location"
                       placeholder="{{ $this->isNational() ? 'Silao, Guanajuato' : 'Apodaca, Nuevo León' }}"
                       @class([
                           'mt-2 h-12 w-full rounded-lg border bg-white px-4 text-sm text-carbon-900 outline-none transition placeholder:text-carbon-400 focus:ring-2 focus:ring-brand-500/25',
                           'border-carbon-300 focus:border-brand-500' => ! $errors->has('location'),
                           'border-red-400' => $errors->has('location'),
                       ])>
                @error('location')<p class="mt-1.5 text-xs text-red-700">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="inicio" class="block text-sm font-semibold text-carbon-900">Fecha de inicio</label>
                <input type="date" id="inicio" wire:model="startDate" min="{{ now()->toDateString() }}"
                       class="mt-2 h-12 w-full rounded-lg border border-carbon-300 bg-white px-4 text-sm text-carbon-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25">
                @error('startDate')<p class="mt-1.5 text-xs text-red-700">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="dias" class="block text-sm font-semibold text-carbon-900">Días de renta estimados</label>
                <input type="number" id="dias" wire:model="rentalDays" min="1" max="365" placeholder="15"
                       class="mt-2 h-12 w-full rounded-lg border border-carbon-300 bg-white px-4 text-sm text-carbon-900 outline-none transition placeholder:text-carbon-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25">
                @error('rentalDays')<p class="mt-1.5 text-xs text-red-700">{{ $message }}</p>@enderror
            </div>

            {{-- REQ-07: aquí es donde el formulario se adapta --}}
            @if($this->isNational())
                <div class="sm:col-span-2 rounded-xl border border-carbon-200 bg-carbon-50 p-5">
                    <p class="font-display text-sm font-bold text-carbon-950">Para obra fuera de Monterrey</p>
                    <div class="mt-4 space-y-3">
                        <label class="flex items-start gap-2.5 text-sm text-carbon-700">
                            <input type="checkbox" wire:model="needsFreight"
                                   class="mt-0.5 size-4 shrink-0 rounded border-carbon-300 accent-brand-600">
                            Necesito que ICCE resuelva el flete y la maniobra en sitio
                        </label>
                        <label class="flex items-start gap-2.5 text-sm text-carbon-700">
                            <input type="checkbox" wire:model="needsOperator"
                                   class="mt-0.5 size-4 shrink-0 rounded border-carbon-300 accent-brand-600">
                            Necesito operador capacitado de ICCE
                        </label>
                    </div>

                    <div class="mt-5">
                        <label for="acceso" class="block text-sm font-semibold text-carbon-900">
                            Acceso a la obra
                        </label>
                        <input type="text" id="acceso" wire:model="siteAccess"
                               placeholder="Rampa, patio de maniobras, horario de ingreso…"
                               class="mt-2 h-12 w-full rounded-lg border border-carbon-300 bg-white px-4 text-sm text-carbon-900 outline-none transition placeholder:text-carbon-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25">
                        <p class="mt-1.5 text-xs text-carbon-500">
                            Nos ayuda a saber con qué unidad llegamos y cuánto tiempo toma descargar.
                        </p>
                    </div>
                </div>
            @else
                <div class="sm:col-span-2 rounded-xl border border-carbon-200 bg-carbon-50 p-5">
                    <p class="font-display text-sm font-bold text-carbon-950">Entrega en el área de Monterrey</p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        @foreach([
                            ['entrega', 'Que lo entreguen en obra'],
                            ['recoge', 'Lo recojo en el almacén'],
                        ] as [$valor, $titulo])
                            <label @class([
                                'flex cursor-pointer items-center gap-3 rounded-lg border p-3 text-sm transition',
                                'border-brand-500 bg-white' => $delivery === $valor,
                                'border-carbon-300 hover:border-carbon-500' => $delivery !== $valor,
                            ])>
                                <input type="radio" wire:model.live="delivery" value="{{ $valor }}"
                                       class="size-4 border-carbon-300 accent-brand-600">
                                <span class="font-medium text-carbon-900">{{ $titulo }}</span>
                            </label>
                        @endforeach
                    </div>

                    <label class="mt-4 flex items-start gap-2.5 text-sm text-carbon-700">
                        <input type="checkbox" wire:model="needsOperator"
                               class="mt-0.5 size-4 shrink-0 rounded border-carbon-300 accent-brand-600">
                        Necesito operador capacitado de ICCE
                    </label>
                </div>
            @endif

            <div class="sm:col-span-2">
                <label for="proyecto" class="block text-sm font-semibold text-carbon-900">
                    Cuéntanos del proyecto
                </label>
                <textarea id="proyecto" wire:model="projectDescription" rows="3"
                          placeholder="Tipo de losa, metros cuadrados, tolerancias requeridas…"
                          class="mt-2 w-full rounded-lg border border-carbon-300 bg-white p-4 text-sm text-carbon-900 outline-none transition placeholder:text-carbon-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25"></textarea>
            </div>
        </fieldset>

        {{-- Contacto --}}
        <fieldset class="grid gap-5 border-t border-carbon-200 pt-6 sm:grid-cols-2">
            <legend class="mb-1 font-display text-lg font-extrabold text-carbon-950 sm:col-span-2">
                Cómo te contactamos
            </legend>

            @foreach([
                ['clientName', 'nombre', 'Nombre completo', 'text', true],
                ['company', 'empresa', 'Empresa', 'text', false],
                ['email', 'correo', 'Correo electrónico', 'email', true],
                ['phone', 'telefono', 'Teléfono', 'tel', true],
            ] as [$prop, $id, $label, $type, $required])
                <div>
                    <label for="{{ $id }}" class="block text-sm font-semibold text-carbon-900">
                        {{ $label }} @if($required)<span class="text-brand-700">*</span>@endif
                    </label>
                    <input type="{{ $type }}" id="{{ $id }}" wire:model="{{ $prop }}"
                           @class([
                               'mt-2 h-12 w-full rounded-lg border bg-white px-4 text-sm text-carbon-900 outline-none transition focus:ring-2 focus:ring-brand-500/25',
                               'border-carbon-300 focus:border-brand-500' => ! $errors->has($prop),
                               'border-red-400' => $errors->has($prop),
                           ])>
                    @error($prop)<p class="mt-1.5 text-xs text-red-700">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </fieldset>

        <div class="hidden" aria-hidden="true">
            <label for="apellido_materno_renta">Apellido materno</label>
            <input type="text" id="apellido_materno_renta" wire:model="apellidoMaterno" tabindex="-1" autocomplete="off">
        </div>

        <div class="border-t border-carbon-200 pt-6">
            <label class="flex items-start gap-2.5 text-sm text-carbon-600">
                <input type="checkbox" wire:model="accepted"
                       class="mt-0.5 size-4 shrink-0 rounded border-carbon-300 accent-brand-600 focus:ring-2 focus:ring-brand-500/40">
                <span>
                    Acepto que ICCE use mis datos para contactarme, según el
                    <a href="{{ route('privacidad') }}" class="font-semibold text-brand-700 underline">aviso de privacidad</a>.
                </span>
            </label>
            @error('accepted')<p class="mt-1.5 text-xs text-red-700">{{ $message }}</p>@enderror

            <x-ui.button type="submit" size="lg" class="mt-6" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="submit">Enviar solicitud</span>
                <span wire:loading wire:target="submit">Enviando…</span>
            </x-ui.button>

            <p class="mt-4 text-xs leading-relaxed text-carbon-500">
                Esta solicitud no genera cobro. Un agente confirma disponibilidad y tarifa
                antes de comprometer nada.
            </p>
        </div>
    </form>
@endif
</div>
