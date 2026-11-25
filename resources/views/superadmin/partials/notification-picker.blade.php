{{--
    Notificaciones de administración en pastillas compactas (en línea, sin lista desplegable que se
    salga del modal): "Todas", una por cada módulo que tiene el usuario y Auditorías.
    $areas:   variable de Alpine con el arreglo de módulos marcados (claves de PortalModules)
    $audit:   variable booleana de Auditorías (receives_audit_alerts)
    $modules: expresión JS con los módulos que tiene el usuario (los de "Módulos visibles"); se
              evalúa dentro del objeto, así que sus variables van con this.
    Se guarda notify_modules[] y, para los avisos que ya existen, notification_modules por área.
--}}
<div x-data="{
        labels: { fiscal: 'Fiscal', regulatorio: 'Regulatorio', objetivos: 'Objetivos', procesos: 'Procesos', flujos: 'Flujos', cursos: 'Cursos' },
        get options() { return Object.keys(this.labels).filter(k => ({{ $modules }}).includes(k)); },
        get chosen() { return this.{{ $areas }}.filter(k => this.options.includes(k)); },
        isOn(k) { return this.{{ $areas }}.includes(k); },
        toggle(k) { this.{{ $areas }} = this.isOn(k) ? this.{{ $areas }}.filter(a => a !== k) : [...this.{{ $areas }}, k]; },
        get allOn() { return this.options.every(k => this.isOn(k)) && this.{{ $audit }}; },
        toggleAll() {
            const on = ! this.allOn;
            this.{{ $areas }} = on ? [...this.options] : [];
            this.{{ $audit }} = on;
        },
        get areaValue() {
            const c = this.chosen.some(k => k !== 'procesos'), p = this.chosen.includes('procesos');
            return c && p ? 'all' : (c ? 'cumplimiento' : (p ? 'procesos' : 'none'));
        },
     }">
    <input type="hidden" name="notification_modules" :value="areaValue">
    <input type="hidden" name="receives_audit_alerts" :value="{{ $audit }} ? 1 : 0">
    <template x-for="k in chosen" :key="k">
        <input type="hidden" name="notify_modules[]" :value="k">
    </template>

    <div class="flex flex-wrap gap-2">
        <button type="button" @click="toggleAll()" :aria-pressed="allOn"
                :class="allOn ? 'border-brand bg-brand text-white' : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'"
                class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm font-medium transition-colors">
            <x-icon name="check" class="h-3.5 w-3.5" x-show="allOn" />
            Todas
        </button>
        <template x-for="k in options" :key="k">
            <button type="button" @click="toggle(k)" :aria-pressed="isOn(k)"
                    :class="isOn(k) ? 'border-brand bg-brand/10 text-brand' : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'"
                    class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm font-medium transition-colors">
                <x-icon name="check" class="h-3.5 w-3.5" x-show="isOn(k)" />
                <span x-text="labels[k]"></span>
            </button>
        </template>
        <button type="button" @click="{{ $audit }} = ! {{ $audit }}" :aria-pressed="{{ $audit }}"
                :class="{{ $audit }} ? 'border-brand bg-brand/10 text-brand' : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'"
                class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm font-medium transition-colors">
            <x-icon name="check" class="h-3.5 w-3.5" x-show="{{ $audit }}" />
            Auditorías
        </button>
    </div>
</div>
<p class="mt-1.5 text-xs text-gray-400">
    Módulos: solicitudes de acceso, escalamientos y avisos a administradores de ese módulo. Auditorías: hallazgos críticos y plazos vencidos de los hallazgos del grupo. Sus avisos personales le llegan siempre.
</p>
