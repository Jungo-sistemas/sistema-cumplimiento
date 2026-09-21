<x-layouts.vigia :title="'Editar · ' . $regulation->name">

<div class="flex flex-col" style="height: calc(100vh - 120px)">

    {{-- Topbar --}}
    <div class="flex items-center justify-between gap-4 mb-4 shrink-0">
        <div class="min-w-0">
            <div class="text-xs text-gray-500 mb-0.5">
                <a href="{{ route('processes.show', $regulation) }}" class="hover:underline text-[#1A428A]">
                    {{ $regulation->name }}
                </a> &rsaquo; Editar
            </div>
            <h1 class="text-base font-semibold text-gray-900 truncate">
                {{ $version->original_name }}
                <span class="ml-1 text-xs font-normal text-gray-500">(v{{ $version->version_number }})</span>
            </h1>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            {{-- Auto-save status --}}
            <span id="saveStatus" class="text-xs text-gray-400"></span>

            {{-- Lock expiry indicator --}}
            <span id="lockBadge"
                  class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 border border-green-300">
                <span class="h-2 w-2 rounded-full bg-green-500"></span>
                <span id="lockLabel">Bloqueo activo</span>
            </span>

            <button type="button" id="cancelBtn"
                    class="px-3 py-2 rounded-md border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                Cancelar
            </button>
            <button type="button" id="saveBtn"
                    class="px-4 py-2 rounded-md bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
                Guardar nueva versión
            </button>
        </div>
    </div>

    @if($reconstructedFromDocx)
        <div class="mb-4 shrink-0 rounded-lg border border-orange-300 bg-orange-50 px-4 py-3 text-sm text-orange-800">
            <strong>Aviso:</strong> este documento no tenía una copia editable guardada, así que su
            contenido se reconstruyó automáticamente a partir del archivo .docx (se verificó que las
            tablas e imágenes originales se conservaron). Aun así, revisa el formato antes de
            guardar por si algo se ve distinto a lo esperado.
        </div>
    @endif

    {{-- Editor area --}}
    <div class="flex flex-1 gap-4 min-h-0">

        {{-- TipTap --}}
        <div class="flex-1 flex flex-col min-w-0 bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div id="toolbar" class="flex flex-wrap items-center gap-1 px-3 py-2 border-b border-gray-200 bg-gray-50 shrink-0">
                {{-- Fuente y tamaño --}}
                <select data-cmd="fontFamily" title="Fuente" class="tb-select w-32">
                    <option value="">Fuente</option>
                    <option value="Calibri, sans-serif">Calibri</option>
                    <option value="Arial, sans-serif">Arial</option>
                    <option value="Times New Roman, serif">Times New Roman</option>
                    <option value="Georgia, serif">Georgia</option>
                    <option value="Verdana, sans-serif">Verdana</option>
                    <option value="Tahoma, sans-serif">Tahoma</option>
                    <option value="Courier New, monospace">Courier New</option>
                </select>
                <select data-cmd="fontSize" title="Tamaño de fuente" class="tb-select w-16">
                    <option value="">Tamaño</option>
                    @foreach([8,9,10,11,12,14,16,18,20,24,28,32,36,40,44,48,54,60,66,72,80,88,96] as $size)
                        <option value="{{ $size }}pt">{{ $size }}</option>
                    @endforeach
                </select>
                <div class="w-px h-5 bg-gray-300 mx-1"></div>

                {{-- Énfasis --}}
                <button type="button" data-cmd="bold"         title="Negrita"                class="tb-btn font-bold">B</button>
                <button type="button" data-cmd="italic"       title="Cursiva"                class="tb-btn italic">I</button>
                <button type="button" data-cmd="underline"    title="Subrayado"              class="tb-btn underline">U</button>
                <button type="button" data-cmd="strike"       title="Tachado"                class="tb-btn line-through">S</button>
                <button type="button" data-cmd="subscript"    title="Subíndice"              class="tb-btn text-xs">X₂</button>
                <button type="button" data-cmd="superscript"  title="Superíndice"            class="tb-btn text-xs">X²</button>
                <div class="w-px h-5 bg-gray-300 mx-1"></div>

                {{-- Color de texto y resaltado --}}
                <label class="tb-btn flex items-center gap-1 cursor-pointer" title="Color de texto">
                    A<input type="color" data-cmd="textColor" value="#000000" class="tb-color">
                </label>
                <label class="tb-btn flex items-center gap-1 cursor-pointer" title="Color de resaltado">
                    🖊<input type="color" data-cmd="highlightColor" value="#FFF176" class="tb-color">
                </label>
                <button type="button" data-cmd="clearHighlight" title="Quitar resaltado" class="tb-btn text-xs">✕🖊</button>
                <button type="button" data-cmd="clearFormat"    title="Limpiar formato"  class="tb-btn text-xs">Tx✕</button>
                <div class="w-px h-5 bg-gray-300 mx-1"></div>

                {{-- Estilos de párrafo --}}
                <button type="button" data-cmd="normal"      title="Texto normal" class="tb-btn text-xs">¶</button>
                <button type="button" data-cmd="h1"          title="Título 1"   class="tb-btn text-xs">H1</button>
                <button type="button" data-cmd="h2"          title="Título 2"   class="tb-btn text-xs">H2</button>
                <button type="button" data-cmd="h3"          title="Título 3"   class="tb-btn text-xs">H3</button>
                <div class="w-px h-5 bg-gray-300 mx-1"></div>

                {{-- Alineación --}}
                <button type="button" data-cmd="alignLeft"    title="Alinear a la izquierda" class="tb-btn text-xs">⇤</button>
                <button type="button" data-cmd="alignCenter"  title="Centrar"                class="tb-btn text-xs">↔</button>
                <button type="button" data-cmd="alignRight"   title="Alinear a la derecha"   class="tb-btn text-xs">⇥</button>
                <button type="button" data-cmd="alignJustify" title="Justificar"             class="tb-btn text-xs">≡J</button>
                <div class="w-px h-5 bg-gray-300 mx-1"></div>

                {{-- Listas y sangría --}}
                <button type="button" data-cmd="bulletList"  title="Lista con viñetas" class="tb-btn">≡</button>
                <button type="button" data-cmd="orderedList" title="Lista numerada"    class="tb-btn">#</button>
                <button type="button" data-cmd="outdent"     title="Disminuir sangría" class="tb-btn text-xs">⇤¶</button>
                <button type="button" data-cmd="indent"      title="Aumentar sangría"  class="tb-btn text-xs">⇥¶</button>
                <div class="w-px h-5 bg-gray-300 mx-1"></div>

                {{-- Tabla --}}
                <button type="button" data-cmd="insertTable"     title="Insertar tabla"              class="tb-btn text-xs">⊞ Tabla</button>
                <button type="button" data-cmd="addRowAfter"     title="Agregar fila abajo"          class="tb-btn text-xs">+Fila</button>
                <button type="button" data-cmd="deleteRow"       title="Eliminar fila"               class="tb-btn text-xs">−Fila</button>
                <button type="button" data-cmd="addColumnAfter"  title="Agregar columna a la derecha" class="tb-btn text-xs">+Col</button>
                <button type="button" data-cmd="deleteColumn"    title="Eliminar columna"            class="tb-btn text-xs">−Col</button>
                <button type="button" data-cmd="mergeOrSplit"    title="Combinar / dividir celdas"   class="tb-btn text-xs">⊟ Celdas</button>
                <button type="button" data-cmd="toggleHeaderRow" title="Encabezado de tabla"         class="tb-btn text-xs">Enc.</button>
                <button type="button" data-cmd="deleteTable"     title="Eliminar tabla"              class="tb-btn text-xs">✕Tabla</button>
                <div class="w-px h-5 bg-gray-300 mx-1"></div>

                {{-- Enlace --}}
                <button type="button" data-cmd="link"   title="Insertar / editar enlace" class="tb-btn text-xs">🔗</button>
                <button type="button" data-cmd="unlink" title="Quitar enlace"            class="tb-btn text-xs">🔗✕</button>
                <div class="w-px h-5 bg-gray-300 mx-1"></div>

                <button type="button" data-cmd="undo"        title="Deshacer"   class="tb-btn">↩</button>
                <button type="button" data-cmd="redo"        title="Rehacer"    class="tb-btn">↪</button>
                <div class="w-px h-5 bg-gray-300 mx-1"></div>
                <span class="text-xs text-gray-400" title="Escribe @ para etiquetar a alguien del grupo, o # para referenciar otro documento">
                    @ persona &nbsp;·&nbsp; # documento
                </span>
            </div>
            <div id="editor" class="flex-1 overflow-y-auto px-8 py-6 text-sm text-gray-900"></div>
        </div>

        {{-- Side panel --}}
        <div class="w-72 shrink-0 flex flex-col gap-3">

            @if($rejectionComment)
            <div class="bg-red-50 border border-red-300 rounded-xl p-4 text-xs text-red-800">
                <div class="font-semibold mb-1">✕ Motivo del rechazo</div>
                <p class="whitespace-pre-line">{{ $rejectionComment }}</p>
            </div>
            @endif

            @if($hasDraft)
            <div class="bg-yellow-50 border border-yellow-300 rounded-xl p-4 text-xs text-yellow-800">
                <div class="font-semibold mb-1">📝 Borrador recuperado</div>
                <p>Estás retomando un borrador guardado anteriormente. Los cambios se resaltan en amarillo.</p>
            </div>
            @endif

            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <div class="text-sm font-semibold text-gray-800 mb-3">Guardar versión</div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Descripción del cambio</label>
                <textarea id="changeDesc" rows="4"
                          placeholder="¿Qué se modificó?"
                          class="block w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A]"></textarea>
                <p class="text-xs text-gray-400 mt-2 mb-3">Los cambios en amarillo quedan visibles al abrir el .docx en Word.</p>

                <label class="block text-xs font-medium text-gray-600 mb-1">
                    Justificación del cambio <span class="text-red-500">*</span>
                </label>
                <textarea id="changeJustification" rows="3"
                          placeholder="¿Por qué se hizo este cambio?"
                          class="block w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A]"></textarea>
                <p id="justificationError" class="text-xs text-red-600 mt-1 hidden">Explica por qué se hizo este cambio antes de guardar.</p>
            </div>

            <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 text-xs text-gray-500">
                <div class="font-semibold text-gray-700 mb-1">Información del bloqueo</div>
                <div>El documento está bloqueado para ti durante 30 minutos desde la última actividad.</div>
                <div class="mt-2">Si cierras el navegador sin guardar, el borrador se conserva y puedes retomarlo.</div>
            </div>
        </div>
    </div>
</div>

{{-- Hidden save form --}}
<form id="saveForm" method="POST" action="{{ route('regulation-versions.saveEdit', $version) }}" class="hidden">
    @csrf
    <input type="hidden" id="contentInput"        name="content" value="">
    <input type="hidden" id="descInput"           name="change_description" value="">
    <input type="hidden" id="justificationInput"  name="change_justification" value="">
</form>

{{-- Cancel modal --}}
<div id="cancelModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4">
    <div class="w-full max-w-sm rounded-xl bg-white shadow-2xl p-6">
        <h3 class="font-bold text-gray-900 mb-2">¿Qué quieres hacer con el borrador?</h3>
        <p class="text-sm text-gray-600 mb-5">Tienes cambios no guardados como nueva versión.</p>
        <div class="flex flex-col gap-2">
            <button type="button" id="keepDraftBtn"
                    class="w-full px-4 py-2 rounded-md bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
                Conservar borrador y salir
            </button>
            <button type="button" id="discardDraftBtn"
                    class="w-full px-4 py-2 rounded-md border border-red-300 text-red-600 text-sm font-semibold hover:bg-red-50">
                Descartar borrador y salir
            </button>
            <button type="button" id="stayBtn"
                    class="w-full px-4 py-2 rounded-md border border-gray-300 text-gray-700 text-sm font-semibold hover:bg-gray-50">
                Seguir editando
            </button>
        </div>
    </div>
</div>

{{-- Save confirmation modal --}}
<div id="saveModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4">
    <div class="w-full max-w-sm rounded-xl bg-white shadow-2xl p-6">
        <h3 class="font-bold text-gray-900 mb-2">¿Guardar nueva versión?</h3>
        <p class="text-sm text-gray-600 mb-5">
            Se creará una versión nueva del documento con los cambios realizados.
            La versión anterior quedará en el historial.
        </p>
        <div class="flex flex-col gap-2">
            <button type="button" id="confirmSaveBtn"
                    class="w-full px-4 py-2 rounded-md bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
                Guardar nueva versión
            </button>
            <button type="button" id="cancelSaveBtn"
                    class="w-full px-4 py-2 rounded-md border border-gray-300 text-gray-700 text-sm font-semibold hover:bg-gray-50">
                Seguir editando
            </button>
        </div>
    </div>
</div>

<style>
    #editor { min-height: 400px; }
    #editor h1 { font-size: 1.4em; font-weight: 700; margin: .8em 0 .4em; }
    #editor h2 { font-size: 1.2em; font-weight: 700; margin: .7em 0 .35em; }
    #editor h3 { font-size: 1.05em; font-weight: 600; margin: .6em 0 .3em; }
    #editor p  { margin: .4em 0; line-height: 1.7; }
    {{-- Tailwind Preflight pone "list-style: none" en todo ul/ol del sitio — sin esto, las
         viñetas/números de las listas del editor no se ven aunque el <ul>/<ol>/<li> esté ahí. --}}
    #editor ul, #editor ol { padding-left: 1.5em; margin: .4em 0; }
    #editor ul { list-style-type: disc; }
    #editor ol { list-style-type: decimal; }
    #editor ul ul { list-style-type: circle; }
    #editor ul ul ul { list-style-type: square; }
    #editor ol ol { list-style-type: lower-alpha; }
    #editor ol ol ol { list-style-type: lower-roman; }
    #editor li { margin: .2em 0; }
    #editor li::marker { color: inherit; }
    #editor strong { font-weight: 700; }
    #editor em     { font-style: italic; }
    #editor u      { text-decoration: underline; }
    #editor mark   { background-color: #FFF176; border-radius: 2px; padding: 0 1px; }
    #editor .ProseMirror:focus { outline: none; }
    #editor .ProseMirror { min-height: 100%; }

    /* Tablas del documento (Objetivo/Alcance/Indicadores/Definiciones/pasos) — el color/borde real
       de cada celda viene de su propio atributo style/bgcolor (ver PreserveInlineStyle); esto solo
       cubre el layout básico y el resaltado de selección de celdas de @tiptap/extension-table. */
    #editor table { border-collapse: collapse; margin: .5em 0; overflow: hidden; width: 100%; }
    #editor td, #editor th { position: relative; vertical-align: top; box-sizing: border-box; }
    #editor .selectedCell:after {
        content: ""; position: absolute; inset: 0; z-index: 2;
        background: rgba(37, 99, 235, .12); pointer-events: none;
    }

    /* Imágenes (diagramas de flujo, etc.) — el HTML trae width/height fijos pensados para la
       exportación a Word (ver imageDimensionAttrs en AiProcedureGenerationService); en el panel
       del editor, más angosto, eso desborda y se ve "partido". Esto solo reescala la vista, no
       toca esos atributos. */
    #editor img { max-width: 100%; height: auto; display: block; margin: .5em auto; }
    .tb-btn {
        padding: 3px 8px; border-radius: 4px; font-size: 13px; color: #374151;
        background: transparent; border: 1px solid transparent; cursor: pointer;
    }
    .tb-btn:hover    { background: #e5e7eb; border-color: #d1d5db; }
    .tb-btn.is-active { background: #dbeafe; border-color: #93c5fd; color: #1d4ed8; }
    .tb-btn:disabled { opacity: .35; cursor: not-allowed; }
    .tb-btn:disabled:hover { background: transparent; border-color: transparent; }
    .tb-select {
        padding: 3px 4px; border-radius: 4px; font-size: 12px; color: #374151;
        background: #fff; border: 1px solid #d1d5db; cursor: pointer;
    }
    .tb-color {
        width: 16px; height: 16px; padding: 0; border: none; background: none; cursor: pointer; vertical-align: middle;
    }

    /* Etiquetas @persona y #documento */
    .mention-tag {
        display: inline-block; border-radius: 4px; padding: 0 4px; font-weight: 600;
        text-decoration: none; white-space: nowrap;
    }
    .mention-person { background: #DBEAFE; color: #1D4ED8; }
    .mention-doc     { background: #E0E7FF; color: #4338CA; cursor: pointer; }
    .mention-doc:hover { text-decoration: underline; }
    /* Links genéricos (ej. una referencia que perdió su etiqueta especial tras pasar por Word) */
    #editor a:not(.mention-doc) { color: #2563eb; text-decoration: underline; }

    /* Menú desplegable de sugerencias */
    .mention-suggestion-list {
        position: absolute; z-index: 1000; min-width: 220px; max-width: 320px; max-height: 260px;
        overflow-y: auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;
        box-shadow: 0 4px 16px rgba(0,0,0,.12); padding: 4px; font-size: 13px;
    }
    .mention-suggestion-item {
        padding: 6px 10px; border-radius: 6px; cursor: pointer; color: #374151;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .mention-suggestion-item.is-selected { background: #EFF6FF; color: #1D4ED8; }
    .mention-suggestion-empty { padding: 6px 10px; color: #9ca3af; font-style: italic; }
</style>

<script type="module">
// Todas las importaciones fijan la MISMA versión de @tiptap/core y @tiptap/pm vía ?deps=
// — sin esto, esm.sh resuelve la dependencia interna de Mention/Link a otra versión distinta
// (ej. 2.27.2) y quedan dos copias incompatibles de @tiptap/core cargadas a la vez, rompiendo
// silenciosamente cualquier chequeo de identidad de clase (Suggestion, PluginKey, etc.).
import { Editor, mergeAttributes, Extension } from 'https://esm.sh/@tiptap/core@2.27.2';
import StarterKit    from 'https://esm.sh/@tiptap/starter-kit@2.27.2?deps=@tiptap/core@2.27.2,@tiptap/pm@2.27.2';
import Underline     from 'https://esm.sh/@tiptap/extension-underline@2.27.2?deps=@tiptap/core@2.27.2,@tiptap/pm@2.27.2';
import Highlight     from 'https://esm.sh/@tiptap/extension-highlight@2.27.2?deps=@tiptap/core@2.27.2,@tiptap/pm@2.27.2';
import Mention       from 'https://esm.sh/@tiptap/extension-mention@2.27.2?deps=@tiptap/core@2.27.2,@tiptap/pm@2.27.2';
import Link          from 'https://esm.sh/@tiptap/extension-link@2.27.2?deps=@tiptap/core@2.27.2,@tiptap/pm@2.27.2';
import Image         from 'https://esm.sh/@tiptap/extension-image@2.27.2?deps=@tiptap/core@2.27.2,@tiptap/pm@2.27.2';
import TextStyle     from 'https://esm.sh/@tiptap/extension-text-style@2.27.2?deps=@tiptap/core@2.27.2,@tiptap/pm@2.27.2';
import Table         from 'https://esm.sh/@tiptap/extension-table@2.27.2?deps=@tiptap/core@2.27.2,@tiptap/pm@2.27.2';
import TableRow      from 'https://esm.sh/@tiptap/extension-table-row@2.27.2?deps=@tiptap/core@2.27.2,@tiptap/pm@2.27.2';
import TableHeader   from 'https://esm.sh/@tiptap/extension-table-header@2.27.2?deps=@tiptap/core@2.27.2,@tiptap/pm@2.27.2';
import TableCell     from 'https://esm.sh/@tiptap/extension-table-cell@2.27.2?deps=@tiptap/core@2.27.2,@tiptap/pm@2.27.2';
import Subscript     from 'https://esm.sh/@tiptap/extension-subscript@2.27.2?deps=@tiptap/core@2.27.2,@tiptap/pm@2.27.2';
import Superscript   from 'https://esm.sh/@tiptap/extension-superscript@2.27.2?deps=@tiptap/core@2.27.2,@tiptap/pm@2.27.2';
import { PluginKey } from 'https://esm.sh/@tiptap/pm@2.27.2/state';
// ── URLs ────────────────────────────────────────────────────────────────────
const DRAFT_URL         = "{{ route('regulation-versions.saveDraft', $version) }}";
const LOCK_URL          = "{{ route('regulation-versions.releaseLock', $version) }}";
const MENTION_USERS_URL = "{{ route('regulation-versions.mentions.users', $version) }}";
const MENTION_DOCS_URL  = "{{ route('regulation-versions.mentions.documents', $version) }}";
const CSRF            = document.querySelector('meta[name=csrf-token]')?.content ?? '';
const AUTOSAVE_MS     = 30_000;
const LOCK_WARN_SECS  = 300;
let lockExpiresAt     = Date.now() + 30 * 60 * 1000;

// ── @persona y #documento: búsqueda ──────────────────────────────────────────
// Sin debounce manual a propósito: el plugin de sugerencias de TipTap espera que
// CADA llamada a items() resuelva (aunque sea con datos obsoletos) para llevar su
// propio ciclo de vida onStart/onUpdate/onExit — un debounce que descarta timers
// anteriores deja esas promesas colgadas para siempre y rompe ese ciclo.
async function fetchJson(url, query) {
    try {
        const res = await fetch(`${url}?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } });
        return res.ok ? await res.json() : [];
    } catch {
        return [];
    }
}
const fetchUsers = (query) => fetchJson(MENTION_USERS_URL, query);
const fetchDocs  = (query) => fetchJson(MENTION_DOCS_URL, query);

// ── Menú desplegable genérico de sugerencias ─────────────────────────────────
function makeSuggestionRenderer({ renderLabel, emptyText }) {
    return () => {
        // onKeyDown recibe un `props` distinto y más limitado que onStart/onUpdate
        // ({view, event, range}, SIN command ni items — ver @tiptap/suggestion) — por eso
        // `command` e `items` se guardan aparte en vez de leerse de props en cada callback.
        let el, items = [], selected = 0, command = () => {};

        function draw() {
            el.innerHTML = '';
            if (items.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'mention-suggestion-empty';
                empty.textContent = emptyText;
                el.appendChild(empty);
                return;
            }
            items.forEach((item, i) => {
                const row = document.createElement('div');
                row.className = 'mention-suggestion-item' + (i === selected ? ' is-selected' : '');
                row.textContent = renderLabel(item);
                row.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    command(item.attrs);
                });
                el.appendChild(row);
            });
        }

        function place(props) {
            const rect = props.clientRect?.();
            if (!rect) return;
            el.style.left = (rect.left + window.scrollX) + 'px';
            el.style.top  = (rect.bottom + window.scrollY + 4) + 'px';
        }

        return {
            onStart(props) {
                items = props.items; selected = 0; command = props.command;
                el = document.createElement('div');
                el.className = 'mention-suggestion-list';
                document.body.appendChild(el);
                draw(); place(props);
            },
            onUpdate(props) {
                items = props.items; selected = 0; command = props.command;
                draw(); place(props);
            },
            onKeyDown(props) {
                if (props.event.key === 'Escape') { el.remove(); return true; }
                if (!items.length) return false;
                if (props.event.key === 'ArrowDown') { selected = (selected + 1) % items.length; draw(); return true; }
                if (props.event.key === 'ArrowUp')   { selected = (selected - 1 + items.length) % items.length; draw(); return true; }
                if (props.event.key === 'Enter')     { command(items[selected].attrs); return true; }
                return false;
            },
            onExit() { el?.remove(); },
        };
    };
}

// ── Extensión @persona ────────────────────────────────────────────────────────
const PersonMention = Mention.extend({
    name: 'personMention',
    addAttributes() {
        return {
            id:    { default: null, parseHTML: el => el.getAttribute('data-id'),    renderHTML: a => ({ 'data-id': a.id }) },
            label: { default: null, parseHTML: el => el.getAttribute('data-label'), renderHTML: a => ({ 'data-label': a.label }) },
        };
    },
    parseHTML()  { return [{ tag: 'span[data-type="person-mention"]' }]; },
    renderHTML({ node, HTMLAttributes }) {
        return ['span', mergeAttributes({
            'data-type': 'person-mention',
            class: 'mention-tag mention-person',
            style: 'background-color:#DBEAFE;color:#1D4ED8;border-radius:4px;padding:0 4px;font-weight:600;',
        }, HTMLAttributes), `@${node.attrs.label ?? ''}`];
    },
    renderText({ node }) { return `@${node.attrs.label ?? ''}`; },
}).configure({
    suggestion: {
        char: '@',
        pluginKey: new PluginKey('personMention'),
        items: async ({ query }) => (await fetchUsers(query)).map(u => ({ attrs: { id: u.id, label: u.name } })),
        render: makeSuggestionRenderer({ renderLabel: item => item.attrs.label, emptyText: 'Sin personas encontradas' }),
        command: ({ editor, range, props }) => {
            editor.chain().focus().insertContentAt(range, [
                { type: 'personMention', attrs: props },
                { type: 'text', text: ' ' },
            ]).run();
        },
    },
});

// ── Extensión #documento ──────────────────────────────────────────────────────
const DocReference = Mention.extend({
    name: 'docReference',
    addAttributes() {
        return {
            id:    { default: null, parseHTML: el => el.getAttribute('data-id'),    renderHTML: a => ({ 'data-id': a.id }) },
            label: { default: null, parseHTML: el => el.getAttribute('data-label'), renderHTML: a => ({ 'data-label': a.label }) },
            url:   { default: null, parseHTML: el => el.getAttribute('href'),       renderHTML: a => ({ href: a.url }) },
        };
    },
    parseHTML()  { return [{ tag: 'a[data-type="doc-reference"]', priority: 100 }]; },
    renderHTML({ node, HTMLAttributes }) {
        return ['a', mergeAttributes({
            'data-type': 'doc-reference',
            class: 'mention-tag mention-doc',
            target: '_blank',
            rel: 'noopener',
            style: 'background-color:#E0E7FF;color:#4338CA;border-radius:4px;padding:0 4px;font-weight:600;text-decoration:none;',
        }, HTMLAttributes), `#${node.attrs.label ?? ''}`];
    },
    renderText({ node }) { return `#${node.attrs.label ?? ''}`; },
}).configure({
    suggestion: {
        char: '#',
        pluginKey: new PluginKey('docReference'),
        items: async ({ query }) => (await fetchDocs(query)).map(d => ({ attrs: { id: d.id, label: d.code, url: d.url }, code: d.code, name: d.name })),
        render: makeSuggestionRenderer({ renderLabel: item => `${item.code} — ${item.name}`, emptyText: 'Sin documentos encontrados' }),
        command: ({ editor, range, props }) => {
            editor.chain().focus().insertContentAt(range, [
                { type: 'docReference', attrs: props },
                { type: 'text', text: ' ' },
            ]).run();
        },
    },
});

// ── Preservar el formato fijo del documento (colores/tablas del generador de IA) ─────────────
// Sin esto, el esquema de TipTap descarta cualquier atributo/estilo que no modele explícitamente:
// las tablas de Objetivo/Alcance/Indicadores/Definiciones y los colores de RegulationBodyHtmlBuilder
// (título #1A5276, encabezados de tabla #002060, etc.) se aplanarían al abrir el documento en este
// editor y se perderían para siempre al guardar. En vez de modelar cada color/tabla como un atributo
// semántico propio, se conserva el atributo "style"/"bgcolor" tal cual venga en el HTML de entrada
// y se reescribe idéntico al guardar — funciona para cualquier estilo que el builder use hoy o en
// el futuro, sin tener que tocar este editor cada vez que cambie el formato del documento.
const PreserveInlineStyle = Extension.create({
    name: 'preserveInlineStyle',
    addGlobalAttributes() {
        const rawStyle = {
            style: {
                default: null,
                parseHTML: el => el.getAttribute('style'),
                renderHTML: attrs => attrs.style ? { style: attrs.style } : {},
            },
        };
        const rawBgcolor = {
            bgcolor: {
                default: null,
                parseHTML: el => el.getAttribute('bgcolor'),
                renderHTML: attrs => attrs.bgcolor ? { bgcolor: attrs.bgcolor } : {},
            },
        };
        // El Image de TipTap solo modela src/alt/title — sin esto, el diagrama de flujo pierde
        // su width/height (los que lo encogen a 6.5in) en cuanto se abre/guarda una vez en este
        // editor, y el siguiente .docx lo incrusta a su resolución nativa (mucho más ancho que la página).
        const rawDimensions = {
            width: {
                default: null,
                parseHTML: el => el.getAttribute('width'),
                renderHTML: attrs => attrs.width ? { width: attrs.width } : {},
            },
            height: {
                default: null,
                parseHTML: el => el.getAttribute('height'),
                renderHTML: attrs => attrs.height ? { height: attrs.height } : {},
            },
        };
        return [
            { types: ['paragraph', 'heading', 'table', 'tableRow'], attributes: rawStyle },
            { types: ['image'], attributes: { ...rawStyle, ...rawDimensions } },
            { types: ['tableCell', 'tableHeader'], attributes: { ...rawStyle, ...rawBgcolor } },
            { types: ['textStyle'], attributes: rawStyle },
        ];
    },
});

// ── Editor ──────────────────────────────────────────────────────────────────
const editor = new Editor({
    element: document.getElementById('editor'),
    extensions: [
        // strike: false — el <s> que genera el mark Strike de StarterKit no lo reconoce el
        // importador HTML de PHPWord (Html::$nodes no lo mapea) y el tachado se pierde en
        // silencio al exportar a .docx. En vez de eso, "Tachado" en el toolbar aplica
        // "text-decoration: line-through" sobre el mismo textStyle/span que ya sí se preserva.
        StarterKit.configure({ strike: false }), Underline, Highlight.configure({ multicolor: true }),
        Link.configure({ openOnClick: false, autolink: false, HTMLAttributes: { target: '_blank', rel: 'noopener' } }),
        // inline: true — el diagrama de flujo (y cualquier otra imagen) viene envuelto en un
        // <p> (RegulationBodyHtmlBuilder); Image es un nodo de bloque por defecto, y ProseMirror
        // descarta un nodo de bloque anidado dentro de un párrafo al analizar el HTML — la imagen
        // desaparecía en silencio al abrir el editor. allowBase64: true — por defecto Image
        // directamente EXCLUYE del parseHTML cualquier <img src="data:..."> (para no inflar el
        // documento con imágenes pesadas), pero el diagrama siempre se inserta como data URI
        // (insertFlowDiagram()) — sin esto también desaparecía en silencio, sin ningún error.
        Image.configure({ inline: true, allowBase64: true }), TextStyle,
        Table.configure({ resizable: false }), TableRow, TableHeader, TableCell,
        Subscript, Superscript,
        PreserveInlineStyle,
        PersonMention, DocReference,
    ],
    content: {!! json_encode($bodyHtml) !!},
    editorProps: { attributes: { class: 'ProseMirror focus:outline-none min-h-full' } },
    onUpdate({ editor }) {
        updateToolbar(editor);
        markDirty();
    },
    onSelectionUpdate({ editor }) { updateToolbar(editor); },
});

// ── Toolbar ──────────────────────────────────────────────────────────────────
// Fuente/tamaño/color de texto y alineación/sangría NO se modelan como atributos propios
// (a diferencia de bold/italic, que TipTap ya trae) — se leen/escriben directo sobre el
// atributo "style" genérico que PreserveInlineStyle ya preserva en textStyle/paragraph/heading,
// para no duplicar el manejo de "style" con extensiones oficiales (Color, FontFamily, TextAlign)
// que competirían por ese mismo atributo y se pisarían entre sí al renderizar.
function parseStyle(str) {
    const obj = {};
    (str || '').split(';').forEach(rule => {
        const idx = rule.indexOf(':');
        if (idx === -1) return;
        const k = rule.slice(0, idx).trim().toLowerCase();
        const v = rule.slice(idx + 1).trim();
        if (k && v) obj[k] = v;
    });
    return obj;
}
function stringifyStyle(obj) {
    return Object.entries(obj).map(([k, v]) => `${k}: ${v}`).join('; ');
}
function currentBlockType() {
    return editor.isActive('heading') ? 'heading' : 'paragraph';
}
function applyTextStyleProp(prop, value) {
    const style = parseStyle(editor.getAttributes('textStyle').style || '');
    if (!value) delete style[prop]; else style[prop] = value;
    const next = stringifyStyle(style);
    if (next) editor.chain().focus().setMark('textStyle', { style: next }).run();
    else editor.chain().focus().unsetMark('textStyle').run();
}
function applyBlockStyleProp(prop, value) {
    const type = currentBlockType();
    const style = parseStyle(editor.getAttributes(type).style || '');
    if (!value) delete style[prop]; else style[prop] = value;
    editor.chain().focus().updateAttributes(type, { style: stringifyStyle(style) || null }).run();
}
function adjustIndent(delta) {
    if (delta > 0 && editor.can().sinkListItem('listItem')) { editor.chain().focus().sinkListItem('listItem').run(); return; }
    if (delta < 0 && editor.can().liftListItem('listItem')) { editor.chain().focus().liftListItem('listItem').run(); return; }
    const type = currentBlockType();
    const style = parseStyle(editor.getAttributes(type).style || '');
    const next = Math.max(0, Math.min(240, (parseInt(style['margin-left']) || 0) + delta));
    if (next > 0) style['margin-left'] = next + 'px'; else delete style['margin-left'];
    editor.chain().focus().updateAttributes(type, { style: stringifyStyle(style) || null }).run();
}

const TABLE_CMDS = ['addRowAfter', 'deleteRow', 'addColumnAfter', 'deleteColumn', 'deleteTable', 'toggleHeaderRow', 'mergeOrSplit'];

function updateToolbar(ed) {
    const textStyle  = parseStyle(ed.getAttributes('textStyle').style || '');
    const blockStyle = parseStyle(ed.getAttributes(currentBlockType()).style || '');
    const align      = blockStyle['text-align'] || 'left';
    const hexColor   = v => /^#[0-9a-f]{6}$/i.test(v || '') ? v : null;

    document.querySelectorAll('[data-cmd]').forEach(el => {
        const c = el.dataset.cmd;

        if (el.tagName === 'SELECT') {
            if (c === 'fontFamily') el.value = textStyle['font-family'] || '';
            if (c === 'fontSize')   el.value = textStyle['font-size'] || '';
            return;
        }
        if (el.tagName === 'INPUT') {
            if (c === 'textColor')      el.value = hexColor(textStyle.color) || '#000000';
            if (c === 'highlightColor') el.value = hexColor(ed.getAttributes('highlight').color) || '#FFF176';
            return;
        }
        if (TABLE_CMDS.includes(c)) el.disabled = !ed.can()[c]();

        el.classList.toggle('is-active',
            c === 'bold'          ? ed.isActive('bold') :
            c === 'italic'        ? ed.isActive('italic') :
            c === 'underline'     ? ed.isActive('underline') :
            c === 'strike'        ? textStyle['text-decoration'] === 'line-through' :
            c === 'subscript'     ? ed.isActive('subscript') :
            c === 'superscript'   ? ed.isActive('superscript') :
            c === 'h1'            ? ed.isActive('heading', { level: 1 }) :
            c === 'h2'            ? ed.isActive('heading', { level: 2 }) :
            c === 'h3'            ? ed.isActive('heading', { level: 3 }) :
            c === 'normal'        ? ed.isActive('paragraph') :
            c === 'bulletList'    ? ed.isActive('bulletList') :
            c === 'orderedList'   ? ed.isActive('orderedList') :
            c === 'alignLeft'     ? align === 'left' :
            c === 'alignCenter'   ? align === 'center' :
            c === 'alignRight'    ? align === 'right' :
            c === 'alignJustify'  ? align === 'justify' :
            c === 'link'          ? ed.isActive('link') : false
        );
    });
}

document.getElementById('toolbar').addEventListener('click', e => {
    const btn = e.target.closest('[data-cmd]');
    if (!btn || btn.tagName === 'SELECT' || btn.tagName === 'INPUT') return;
    const c = btn.dataset.cmd, ch = editor.chain().focus();
    if      (c === 'bold')           ch.toggleBold().run();
    else if (c === 'italic')         ch.toggleItalic().run();
    else if (c === 'underline')      ch.toggleUnderline().run();
    else if (c === 'strike') {
        const isStrike = parseStyle(editor.getAttributes('textStyle').style || '')['text-decoration'] === 'line-through';
        applyTextStyleProp('text-decoration', isStrike ? null : 'line-through');
    }
    else if (c === 'subscript')      ch.toggleSubscript().run();
    else if (c === 'superscript')    ch.toggleSuperscript().run();
    else if (c === 'clearHighlight') ch.unsetHighlight().run();
    else if (c === 'clearFormat')    ch.unsetAllMarks().updateAttributes(currentBlockType(), { style: null }).run();
    else if (c === 'normal')         ch.setParagraph().run();
    else if (c === 'h1')             ch.toggleHeading({ level: 1 }).run();
    else if (c === 'h2')             ch.toggleHeading({ level: 2 }).run();
    else if (c === 'h3')             ch.toggleHeading({ level: 3 }).run();
    else if (c === 'alignLeft')      applyBlockStyleProp('text-align', null);
    else if (c === 'alignCenter')    applyBlockStyleProp('text-align', 'center');
    else if (c === 'alignRight')     applyBlockStyleProp('text-align', 'right');
    else if (c === 'alignJustify')   applyBlockStyleProp('text-align', 'justify');
    else if (c === 'bulletList')     ch.toggleBulletList().run();
    else if (c === 'orderedList')    ch.toggleOrderedList().run();
    else if (c === 'indent')         adjustIndent(24);
    else if (c === 'outdent')        adjustIndent(-24);
    else if (c === 'insertTable')    ch.insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
    else if (c === 'addRowAfter')    ch.addRowAfter().run();
    else if (c === 'deleteRow')      ch.deleteRow().run();
    else if (c === 'addColumnAfter') ch.addColumnAfter().run();
    else if (c === 'deleteColumn')   ch.deleteColumn().run();
    else if (c === 'deleteTable')    ch.deleteTable().run();
    else if (c === 'toggleHeaderRow') ch.toggleHeaderRow().run();
    else if (c === 'mergeOrSplit')   ch.mergeOrSplit().run();
    else if (c === 'link') {
        const prev = editor.getAttributes('link').href || 'https://';
        const url = window.prompt('URL del enlace', prev);
        if (url === null) return;
        if (url === '') { ch.unsetLink().run(); return; }
        ch.extendMarkRange('link').setLink({ href: url }).run();
    }
    else if (c === 'unlink')         ch.unsetLink().run();
    else if (c === 'undo')           ch.undo().run();
    else if (c === 'redo')           ch.redo().run();
});
document.getElementById('toolbar').addEventListener('change', e => {
    const el = e.target.closest('[data-cmd]');
    if (!el) return;
    const c = el.dataset.cmd;
    if      (c === 'fontFamily')     applyTextStyleProp('font-family', el.value || null);
    else if (c === 'fontSize')       applyTextStyleProp('font-size', el.value || null);
    else if (c === 'textColor')      applyTextStyleProp('color', el.value);
    else if (c === 'highlightColor') editor.chain().focus().setHighlight({ color: el.value }).run();
});

// El navegador normaliza cualquier "color:#RRGGBB" que llega en el HTML a "color: rgb(r, g, b)"
// al serializarlo de vuelta (editor.getHTML()) — PhpWord\Shared\Html::addHtml() (usado al armar
// el .docx en el servidor) solo entiende colores en hex y descarta silenciosamente cualquier
// rgb(), dejando el texto en negro por defecto. Se revierte a hex aquí, antes de guardar.
function rgbToHex(html) {
    return html.replace(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*(?:,\s*[\d.]+\s*)?\)/g, (_, r, g, b) => {
        const toHex = n => Number(n).toString(16).padStart(2, '0');
        return '#' + toHex(r) + toHex(g) + toHex(b);
    });
}
function getContentForSave() {
    return rgbToHex(editor.getHTML());
}

// ── Auto-save + lock renewal ─────────────────────────────────────────────────
let dirty = false;
let autoSaveTimer = null;

function markDirty() {
    dirty = true;
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(doAutoSave, AUTOSAVE_MS);
}

async function doAutoSave() {
    if (!dirty) return;
    setStatus('Guardando borrador…');
    try {
        const res = await fetch(DRAFT_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ content: getContentForSave() }),
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        dirty = false;
        lockExpiresAt = Date.now() + 30 * 60 * 1000;  // renewed
        setStatus('Borrador guardado a las ' + data.saved_at);
        updateLockBadge();
    } catch (err) {
        setStatus('Error al guardar borrador (' + err.message + ')', true);
    }
}

function setStatus(msg, isError = false) {
    const el = document.getElementById('saveStatus');
    el.textContent = msg;
    el.className = 'text-xs ' + (isError ? 'text-red-500' : 'text-gray-400');
}

// ── Lock badge countdown ──────────────────────────────────────────────────────
let warned5min = false;

function showLockWarningToast() {
    const toast = document.createElement('div');
    toast.className = 'fixed top-16 right-4 z-50 max-w-sm bg-orange-50 border border-orange-300 text-orange-800 text-sm rounded-lg shadow-lg px-4 py-3 flex items-start gap-2';
    toast.innerHTML = '<span>⏰</span><div><strong>El bloqueo expira en menos de 5 minutos.</strong><br>Guarda tus cambios pronto para no perder el acceso de edición.</div>';
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 8000);
}

function updateLockBadge() {
    const remaining = Math.max(0, Math.round((lockExpiresAt - Date.now()) / 1000));
    const min = Math.floor(remaining / 60);
    const sec = String(remaining % 60).padStart(2, '0');
    const badge = document.getElementById('lockBadge');
    const label = document.getElementById('lockLabel');

    if (remaining === 0) {
        badge.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700 border border-red-300';
        label.textContent = 'Bloqueo expirado — guarda ahora';
    } else if (remaining < LOCK_WARN_SECS) {
        badge.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-orange-100 text-orange-700 border border-orange-300';
        label.textContent = `Expira en ${min}:${sec}`;
        if (!warned5min) {
            warned5min = true;
            showLockWarningToast();
        }
    } else {
        badge.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 border border-green-300';
        label.textContent = `Bloqueado — ${min}:${sec}`;
        warned5min = false;
    }
}
setInterval(updateLockBadge, 1000);
updateLockBadge();

// ── Save final ────────────────────────────────────────────────────────────────
function showSaveModal() {
    const m = document.getElementById('saveModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function hideSaveModal() {
    const m = document.getElementById('saveModal');
    m.classList.add('hidden');
    m.classList.remove('flex');
}

document.getElementById('saveBtn').addEventListener('click', () => {
    const justification = document.getElementById('changeJustification').value.trim();
    if (!justification) {
        document.getElementById('justificationError').classList.remove('hidden');
        document.getElementById('changeJustification').focus();
        return;
    }
    document.getElementById('justificationError').classList.add('hidden');
    showSaveModal();
});
document.getElementById('cancelSaveBtn').addEventListener('click', hideSaveModal);

document.getElementById('confirmSaveBtn').addEventListener('click', () => {
    clearTimeout(autoSaveTimer);
    dirty = false;   // evita el diálogo nativo "¿Deseas abandonar el sitio?"
    document.getElementById('contentInput').value       = getContentForSave();
    document.getElementById('descInput').value          = document.getElementById('changeDesc').value.trim();
    document.getElementById('justificationInput').value = document.getElementById('changeJustification').value.trim();
    document.getElementById('saveForm').submit();
});

// ── Cancel modal ──────────────────────────────────────────────────────────────
function showCancelModal() {
    const m = document.getElementById('cancelModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function hideCancelModal() {
    const m = document.getElementById('cancelModal');
    m.classList.add('hidden');
    m.classList.remove('flex');
}

document.getElementById('cancelBtn').addEventListener('click', showCancelModal);
document.getElementById('stayBtn').addEventListener('click', hideCancelModal);

async function releaseLock(keepDraft) {
    clearTimeout(autoSaveTimer);
    // If keeping draft and there are unsaved changes, auto-save first
    if (keepDraft && dirty) await doAutoSave();

    // El usuario ya confirmó qué hacer con el borrador en el modal de arriba — sin esto, el
    // envío del formulario de abajo dispara TAMBIÉN el diálogo nativo "beforeunload" del navegador
    // (el de "localhost dice...") justo encima del modal que ya se acaba de confirmar.
    dirty = false;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = LOCK_URL;
    form.innerHTML = `
        <input type="hidden" name="_token"      value="${CSRF}">
        <input type="hidden" name="_method"     value="DELETE">
        <input type="hidden" name="keep_draft"  value="${keepDraft ? '1' : '0'}">
    `;
    document.body.appendChild(form);
    form.submit();
}

document.getElementById('keepDraftBtn').addEventListener('click',    () => releaseLock(true));
document.getElementById('discardDraftBtn').addEventListener('click', () => releaseLock(false));

// ── Warn on browser close if dirty ────────────────────────────────────────────
window.addEventListener('beforeunload', e => {
    if (dirty) {
        // Trigger a best-effort auto-save (may not complete before close)
        navigator.sendBeacon(DRAFT_URL,
            new Blob([JSON.stringify({ content: getContentForSave(), _token: CSRF })],
                     { type: 'application/json' })
        );
        e.preventDefault();
        e.returnValue = '';
    }
});
</script>

</x-layouts.vigia>
