@props(['data'])

{{-- Datos estructurados. JSON_UNESCAPED_SLASHES mantiene legibles las URLs;
     JSON_HEX_TAG evita que un </script> dentro de un texto rompa el bloque. --}}
<script type="application/ld+json">
{!! json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
</script>
