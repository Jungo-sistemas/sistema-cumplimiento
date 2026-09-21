import './bootstrap';

import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();

import TomSelect from 'tom-select';
window.TomSelect = TomSelect;

/**
 * Buscador con autocompletado para elegir persona(s). Toma las opciones
 * directamente del <select> ya renderizado (incluye cuáles vienen
 * preseleccionadas). Pasa `multiple: false` para permitir una sola persona
 * (ej. Responsable) en vez de varias (ej. Accesos Autorizados).
 */
window.initPersonPicker = function (selectEl, { multiple = true } = {}) {
    if (!selectEl || !window.TomSelect) return null;

    const ts = new TomSelect(selectEl, {
        plugins: ['remove_button'],
        maxItems: multiple ? null : 1,
        create: false,
        dropdownParent: 'body',
        placeholder: multiple ? 'Escribe para buscar personas...' : 'Escribe para buscar una persona...',
    });

    // El cálculo de posición que trae Tom Select para dropdownParent:'body'
    // asume una página que hace scroll normal. Este formulario vive dentro
    // de un modal con position:fixed (con el scroll del body bloqueado
    // mientras está abierto), así que se recalcula con coordenadas de
    // viewport (position:fixed) en vez de sumar window.scrollY.
    ts.positionDropdown = function () {
        const rect = this.control.getBoundingClientRect();
        Object.assign(this.dropdown.style, {
            position: 'fixed',
            width: rect.width + 'px',
            top: (rect.top + rect.height) + 'px',
            left: rect.left + 'px',
            // El modal (x-modal) usa z-50; esto asegura que el listado de
            // sugerencias quede siempre por encima del modal, no detrás.
            zIndex: 9999,
        });
    };

    // Dentro de un modal con el scroll del body bloqueado, esas coordenadas de
    // viewport no cambian mientras está abierto. Pero este mismo picker también
    // se usa en formularios de página normal (sin modal), donde sí se puede
    // seguir haciendo scroll con el dropdown abierto — sin recalcular aquí, se
    // queda pegado a donde estaba el campo cuando se abrió y se ve "flotando"
    // en otro lugar de la pantalla.
    const reposition = () => ts.positionDropdown();
    ts.on('dropdown_open', () => {
        window.addEventListener('scroll', reposition, true);
        window.addEventListener('resize', reposition);
    });
    ts.on('dropdown_close', () => {
        window.removeEventListener('scroll', reposition, true);
        window.removeEventListener('resize', reposition);
    });

    return ts;
};
