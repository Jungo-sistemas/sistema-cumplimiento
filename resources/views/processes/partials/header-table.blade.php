{{--
    Espejo en HTML de App\Services\RegulationDocxHeaderBuilder — mismo contenido y colores que
    la tabla fija que Word agrega como encabezado nativo en cada página. Se muestra en la vista
    previa (antes de confirmar) para que lo que se ve ahí sea igual a lo que va a salir en el
    .docx final; si no, quien revisa la vista previa no ve el encabezado y piensa que el
    documento quedó mal, aunque en realidad sí se genera correctamente al descargarlo.
--}}
<table class="doc-header-table">
    <tr>
        <td class="doc-header-cell doc-header-logo">
            <div class="doc-header-label">LOGO EMPRESA</div>
            <div class="doc-header-sublabel">(insertar logotipo)</div>
        </td>
        <td class="doc-header-cell doc-header-accent">
            <div class="doc-header-label">PROCEDIMIENTO</div>
            <div class="doc-header-value doc-header-value-italic">{{ $meta['nombre'] ?? '' }}</div>
        </td>
        <td class="doc-header-cell doc-header-accent">
            <div class="doc-header-label">CÓDIGO</div>
            <div class="doc-header-value">{{ $meta['codigo'] ?? '—' }}</div>
        </td>
        <td class="doc-header-cell doc-header-accent">
            <div class="doc-header-label">VERSIÓN</div>
            <div class="doc-header-value">{{ $meta['version'] ?? '01' }}</div>
        </td>
    </tr>
    <tr>
        <td class="doc-header-cell doc-header-gray">
            <div class="doc-header-label">ELABORADO POR:</div>
            <div class="doc-header-value">{{ $meta['quien_elabora'] ?? '—' }}</div>
        </td>
        <td class="doc-header-cell doc-header-gray">
            <div class="doc-header-label">APROBADO POR:</div>
            <div class="doc-header-value">{{ $meta['quien_aprueba'] ?? '—' }}</div>
        </td>
        <td class="doc-header-cell doc-header-gray">
            <div class="doc-header-label">Fecha de elaboración:</div>
            <div class="doc-header-value">{{ $meta['fecha_vigencia_formatted'] ?? '—' }}</div>
        </td>
        <td class="doc-header-cell doc-header-gray">
            <div class="doc-header-label">Página:</div>
            <div class="doc-header-value doc-page-indicator">1 de 1</div>
        </td>
    </tr>
</table>
