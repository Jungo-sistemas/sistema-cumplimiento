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

    {{-- Editor area --}}
    <div class="flex flex-1 gap-4 min-h-0">

        {{-- TipTap --}}
        <div class="flex-1 flex flex-col min-w-0 bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div id="toolbar" class="flex flex-wrap items-center gap-1 px-3 py-2 border-b border-gray-200 bg-gray-50 shrink-0">
                <button type="button" data-cmd="bold"        title="Negrita"    class="tb-btn font-bold">B</button>
                <button type="button" data-cmd="italic"      title="Cursiva"    class="tb-btn italic">I</button>
                <button type="button" data-cmd="underline"   title="Subrayado"  class="tb-btn underline">U</button>
                <div class="w-px h-5 bg-gray-300 mx-1"></div>
                <button type="button" data-cmd="h1"          title="Título 1"   class="tb-btn text-xs">H1</button>
                <button type="button" data-cmd="h2"          title="Título 2"   class="tb-btn text-xs">H2</button>
                <button type="button" data-cmd="h3"          title="Título 3"   class="tb-btn text-xs">H3</button>
                <div class="w-px h-5 bg-gray-300 mx-1"></div>
                <button type="button" data-cmd="bulletList"  title="Lista"      class="tb-btn">≡</button>
                <button type="button" data-cmd="orderedList" title="Numerada"   class="tb-btn">#</button>
                <div class="w-px h-5 bg-gray-300 mx-1"></div>
                <button type="button" data-cmd="undo"        title="Deshacer"   class="tb-btn">↩</button>
                <button type="button" data-cmd="redo"        title="Rehacer"    class="tb-btn">↪</button>
                <div class="w-px h-5 bg-gray-300 mx-1"></div>
                <button type="button" data-cmd="highlight"      title="Resaltar selección en amarillo" class="tb-btn text-xs" style="background:#FFF176;">🖊 Resaltar</button>
                <button type="button" data-cmd="clearHighlight" title="Quitar resaltado de selección"  class="tb-btn text-xs">✕ Resaltado</button>
                <div class="w-px h-5 bg-gray-300 mx-1"></div>
                <span class="text-xs text-gray-400" title="Escribe @ para etiquetar a alguien del grupo, o # para referenciar otro documento">
                    @ persona &nbsp;·&nbsp; # documento
                </span>
            </div>
            <div id="editor" class="flex-1 overflow-y-auto px-8 py-6 text-sm text-gray-900"></div>
        </div>

        {{-- Side panel --}}
        <div class="w-72 shrink-0 flex flex-col gap-3">

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
                <p class="text-xs text-gray-400 mt-2">Los cambios en amarillo quedan visibles al abrir el .docx en Word.</p>
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
    <input type="hidden" id="contentInput"  name="content" value="">
    <input type="hidden" id="descInput"     name="change_description" value="">
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
    #editor ul, #editor ol { padding-left: 1.5em; margin: .4em 0; }
    #editor li { margin: .2em 0; }
    #editor strong { font-weight: 700; }
    #editor em     { font-style: italic; }
    #editor u      { text-decoration: underline; }
    #editor mark   { background-color: #FFF176; border-radius: 2px; padding: 0 1px; }
    #editor .ProseMirror:focus { outline: none; }
    #editor .ProseMirror { min-height: 100%; }
    .tb-btn {
        padding: 3px 8px; border-radius: 4px; font-size: 13px; color: #374151;
        background: transparent; border: 1px solid transparent; cursor: pointer;
    }
    .tb-btn:hover    { background: #e5e7eb; border-color: #d1d5db; }
    .tb-btn.is-active { background: #dbeafe; border-color: #93c5fd; color: #1d4ed8; }

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
import { Editor, mergeAttributes } from 'https://esm.sh/@tiptap/core@2.27.2';
import StarterKit    from 'https://esm.sh/@tiptap/starter-kit@2.27.2?deps=@tiptap/core@2.27.2,@tiptap/pm@2.27.2';
import Underline     from 'https://esm.sh/@tiptap/extension-underline@2.27.2?deps=@tiptap/core@2.27.2,@tiptap/pm@2.27.2';
import Highlight     from 'https://esm.sh/@tiptap/extension-highlight@2.27.2?deps=@tiptap/core@2.27.2,@tiptap/pm@2.27.2';
import Mention       from 'https://esm.sh/@tiptap/extension-mention@2.27.2?deps=@tiptap/core@2.27.2,@tiptap/pm@2.27.2';
import Link          from 'https://esm.sh/@tiptap/extension-link@2.27.2?deps=@tiptap/core@2.27.2,@tiptap/pm@2.27.2';
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

// ── Editor ──────────────────────────────────────────────────────────────────
const editor = new Editor({
    element: document.getElementById('editor'),
    extensions: [
        StarterKit, Underline, Highlight.configure({ multicolor: true }),
        Link.configure({ openOnClick: false, autolink: false, HTMLAttributes: { target: '_blank', rel: 'noopener' } }),
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
function updateToolbar(ed) {
    document.querySelectorAll('[data-cmd]').forEach(btn => {
        const c = btn.dataset.cmd;
        btn.classList.toggle('is-active',
            c === 'bold'         ? ed.isActive('bold') :
            c === 'italic'       ? ed.isActive('italic') :
            c === 'underline'    ? ed.isActive('underline') :
            c === 'highlight'    ? ed.isActive('highlight', { color: '#FFF176' }) :
            c === 'h1'           ? ed.isActive('heading', { level: 1 }) :
            c === 'h2'           ? ed.isActive('heading', { level: 2 }) :
            c === 'h3'           ? ed.isActive('heading', { level: 3 }) :
            c === 'bulletList'   ? ed.isActive('bulletList') :
            c === 'orderedList'  ? ed.isActive('orderedList') : false
        );
    });
}
document.getElementById('toolbar').addEventListener('click', e => {
    const btn = e.target.closest('[data-cmd]');
    if (!btn) return;
    const c = btn.dataset.cmd, ch = editor.chain().focus();
    if      (c === 'bold')           ch.toggleBold().run();
    else if (c === 'italic')         ch.toggleItalic().run();
    else if (c === 'underline')      ch.toggleUnderline().run();
    else if (c === 'highlight')      ch.toggleHighlight({ color: '#FFF176' }).run();
    else if (c === 'h1')             ch.toggleHeading({ level: 1 }).run();
    else if (c === 'h2')             ch.toggleHeading({ level: 2 }).run();
    else if (c === 'h3')             ch.toggleHeading({ level: 3 }).run();
    else if (c === 'bulletList')     ch.toggleBulletList().run();
    else if (c === 'orderedList')    ch.toggleOrderedList().run();
    else if (c === 'undo')           ch.undo().run();
    else if (c === 'redo')           ch.redo().run();
    else if (c === 'clearHighlight') ch.unsetHighlight().run();
});

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
            body: JSON.stringify({ content: editor.getHTML() }),
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
    } else {
        badge.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 border border-green-300';
        label.textContent = `Bloqueado — ${min}:${sec}`;
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

document.getElementById('saveBtn').addEventListener('click', showSaveModal);
document.getElementById('cancelSaveBtn').addEventListener('click', hideSaveModal);

document.getElementById('confirmSaveBtn').addEventListener('click', () => {
    clearTimeout(autoSaveTimer);
    dirty = false;   // evita el diálogo nativo "¿Deseas abandonar el sitio?"
    document.getElementById('contentInput').value = editor.getHTML();
    document.getElementById('descInput').value    = document.getElementById('changeDesc').value.trim();
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
            new Blob([JSON.stringify({ content: editor.getHTML(), _token: CSRF })],
                     { type: 'application/json' })
        );
        e.preventDefault();
        e.returnValue = '';
    }
});
</script>

</x-layouts.vigia>
