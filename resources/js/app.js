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

/**
 * Guía interactiva de Procesos: resalta elementos reales de la interfaz (nav, botones, tablas)
 * en vez de solo describirlos en un modal. Como la app es multi-página (no SPA), el progreso se
 * guarda en localStorage para sobrevivir recargas completas al navegar de un paso a otro.
 *
 * @param {Array<{title:string, body:string, url:?string, target:?string}>} steps
 * @param {'mandatory'|'reminder'} mode
 * @param {string} completeUrl
 */
window.initProcesosGuide = function (steps, mode, completeUrl) {
    const STORAGE_KEY = 'procesosGuideState';
    let repositionHandler = null;

    function getState() {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_KEY));
        } catch {
            return null;
        }
    }
    function setState(state) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    }
    function clearState() {
        localStorage.removeItem(STORAGE_KEY);
    }
    function isCurrentUrl(url) {
        try {
            return new URL(url, window.location.href).pathname === window.location.pathname;
        } catch {
            return false;
        }
    }

    let state = getState();
    // Si no hay un recorrido en curso con el mismo modo, este render es un disparo nuevo del
    // servidor (obligatorio u opcional) — arranca desde el primer paso.
    if (!state || state.mode !== mode) {
        state = { step: 0, mode };
        setState(state);
    }

    function teardown() {
        if (repositionHandler) {
            window.removeEventListener('scroll', repositionHandler, true);
            window.removeEventListener('resize', repositionHandler);
            repositionHandler = null;
        }
        document.getElementById('procesos-guide-overlay')?.remove();
    }

    function finish() {
        teardown();
        clearState();
        if (mode === 'mandatory') {
            fetch(completeUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                    'Accept': 'application/json',
                },
            }).catch(() => {});
        }
    }

    function goToStep(index) {
        state.step = index;
        setState(state);
        render();
    }

    function render() {
        const step = steps[state.step];
        if (!step) {
            finish();
            return;
        }

        if (step.url && !isCurrentUrl(step.url)) {
            window.location.href = step.url;
            return;
        }

        const target = step.target ? document.querySelector(`[data-tour="${step.target}"]`) : null;

        // El elemento no existe en esta página (ej. un botón que ese rol no tiene, o una vista
        // distinta a la esperada) — se salta el paso en vez de dejar el recorrido roto.
        if (step.target && !target) {
            if (state.step < steps.length - 1) {
                goToStep(state.step + 1);
            } else {
                finish();
            }
            return;
        }

        draw(step, target);
    }

    function draw(step, target) {
        teardown();

        const overlay = document.createElement('div');
        overlay.id = 'procesos-guide-overlay';
        overlay.style.cssText = 'position:fixed;inset:0;z-index:100;';
        document.body.appendChild(overlay);

        const rect = target ? target.getBoundingClientRect() : null;
        const pad = 6;

        if (rect) {
            // Cuatro paneles oscuros alrededor del recuadro del elemento, en vez de uno con un
            // "agujero" recortado — más simple y confiable entre navegadores que clip-path.
            const panels = [
                { top: 0, left: 0, right: 0, height: Math.max(0, rect.top - pad) },
                { top: rect.bottom + pad, left: 0, right: 0, bottom: 0 },
                { top: rect.top - pad, left: 0, width: Math.max(0, rect.left - pad), height: rect.height + pad * 2 },
                { top: rect.top - pad, left: rect.right + pad, right: 0, height: rect.height + pad * 2 },
            ];
            panels.forEach((p) => {
                const div = document.createElement('div');
                div.style.position = 'absolute';
                div.style.background = 'rgba(0,0,0,0.5)';
                Object.entries(p).forEach(([k, v]) => { div.style[k] = typeof v === 'number' ? v + 'px' : v; });
                overlay.appendChild(div);
            });

            const ring = document.createElement('div');
            ring.style.cssText = `position:absolute;top:${rect.top - pad}px;left:${rect.left - pad}px;width:${rect.width + pad * 2}px;height:${rect.height + pad * 2}px;border:2px solid #1A428A;border-radius:8px;box-shadow:0 0 0 4px rgba(26,66,138,0.25);pointer-events:none;`;
            overlay.appendChild(ring);
        } else {
            const dim = document.createElement('div');
            dim.style.cssText = 'position:absolute;inset:0;background:rgba(0,0,0,0.5);';
            overlay.appendChild(dim);
        }

        const isLast = state.step === steps.length - 1;
        const card = document.createElement('div');
        card.style.cssText = 'position:absolute;width:320px;max-width:90vw;background:#fff;border-radius:12px;box-shadow:0 20px 40px rgba(0,0,0,0.25);padding:20px;';

        const dots = steps.map((_, i) => `<span style="height:6px;flex:1;border-radius:9999px;background:${i <= state.step ? '#1A428A' : '#e5e7eb'}"></span>`).join('');

        card.innerHTML = `
            <div style="display:flex;gap:6px;">${dots}</div>
            <p style="margin:8px 0 0;font-size:12px;font-weight:600;color:#9ca3af;">Paso ${state.step + 1} de ${steps.length}</p>
            <h2 style="margin:8px 0 0;font-size:16px;font-weight:600;color:#1A428A;">${step.title}</h2>
            <p style="margin:8px 0 0;font-size:14px;color:#4b5563;">${step.body}</p>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-top:16px;">
                <button type="button" data-action="prev" style="padding:8px 16px;border-radius:6px;border:1px solid #d1d5db;color:#4b5563;font-size:14px;font-weight:500;background:#fff;${state.step === 0 ? 'visibility:hidden' : ''}">Atrás</button>
                <button type="button" data-action="${isLast ? 'finish' : 'next'}" style="padding:8px 16px;border-radius:6px;border:none;background:#1A428A;color:#fff;font-size:14px;font-weight:600;">
                    ${isLast ? (mode === 'mandatory' ? 'Entendido, comenzar' : 'Cerrar') : 'Siguiente'}
                </button>
            </div>
        `;
        overlay.appendChild(card);

        if (mode === 'reminder') {
            const closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.setAttribute('aria-label', 'Cerrar');
            closeBtn.style.cssText = 'position:absolute;top:10px;right:10px;background:none;border:none;color:#9ca3af;font-size:18px;line-height:1;cursor:pointer;';
            closeBtn.textContent = '×';
            closeBtn.addEventListener('click', finish);
            card.style.position = 'absolute';
            card.appendChild(closeBtn);
        }

        card.querySelector('[data-action="prev"]')?.addEventListener('click', () => goToStep(state.step - 1));
        card.querySelector('[data-action="next"]')?.addEventListener('click', () => goToStep(state.step + 1));
        card.querySelector('[data-action="finish"]')?.addEventListener('click', finish);

        positionCard(card, rect);
        repositionHandler = () => positionCard(card, target ? target.getBoundingClientRect() : null);
        window.addEventListener('scroll', repositionHandler, true);
        window.addEventListener('resize', repositionHandler);
    }

    function positionCard(card, rect) {
        const vw = window.innerWidth;
        const vh = window.innerHeight;
        const cardW = 320;
        const cardH = card.offsetHeight || 220;

        if (!rect) {
            card.style.top = (vh / 2 - cardH / 2) + 'px';
            card.style.left = (vw / 2 - cardW / 2) + 'px';
            return;
        }

        let top = rect.bottom + 14;
        if (top + cardH > vh) {
            top = Math.max(14, rect.top - cardH - 14);
        }
        let left = Math.min(Math.max(14, rect.left), vw - cardW - 14);

        card.style.top = top + 'px';
        card.style.left = left + 'px';
    }

    render();
};
