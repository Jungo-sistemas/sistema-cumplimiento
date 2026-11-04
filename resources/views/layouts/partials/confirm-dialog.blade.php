{{--
    Pop up de confirmación del sistema (reemplaza confirm() del navegador).

    Uso en formularios: data-confirm="¿Mensaje?" en el <form> o en el botón que lo envía.
    Opcionales: data-confirm-title, data-confirm-button (texto del botón) y data-confirm-danger
    (botón rojo, para acciones que borran).
    Uso desde JS/Alpine: vigiaConfirm('¿Mensaje?', { title, confirmText, danger }).then(ok => ...)
--}}
<div x-data="{
        show: false,
        title: '',
        message: '',
        confirmText: '',
        danger: false,
        resolve: null,
        open(detail) {
            this.title       = detail.title || 'Confirmar';
            this.message     = detail.message || '';
            this.confirmText = detail.confirmText || 'Sí, continuar';
            this.danger      = !! detail.danger;
            this.resolve     = detail.resolve;
            this.show        = true;
            this.$nextTick(() => this.$refs.confirmButton.focus());
        },
        close(result) {
            this.show = false;
            if (this.resolve) this.resolve(result);
            this.resolve = null;
        },
     }"
     @vigia-confirm.window="open($event.detail)"
     @keydown.escape.window="show && close(false)"
     x-show="show"
     x-transition.opacity
     class="fixed inset-0 z-[80] flex items-start justify-center bg-black/50 px-4 pt-[15vh]"
     style="display:none;">
    <div @click.outside="close(false)" class="w-full max-w-md rounded-xl bg-white shadow-2xl">
        <div class="p-6 border-b">
            <h3 class="text-lg font-bold text-gray-900" x-text="title"></h3>
            <p class="mt-2 text-sm text-gray-600 whitespace-pre-line" x-text="message"></p>
        </div>
        <div class="p-6 pt-4 flex items-center justify-end gap-3">
            <button type="button" @click="close(false)"
                    class="px-4 py-2 rounded-md border border-gray-300 bg-white text-gray-700 font-semibold text-sm hover:bg-gray-50">
                Cancelar
            </button>
            <button type="button" x-ref="confirmButton" @click="close(true)"
                    :class="danger ? 'bg-red-600 hover:bg-red-700' : 'bg-brand hover:bg-brand-dark'"
                    class="px-4 py-2 rounded-md text-white font-semibold text-sm"
                    x-text="confirmText"></button>
        </div>
    </div>
</div>

<script>
    window.vigiaConfirm = function (message, options = {}) {
        return new Promise((resolve) => {
            window.dispatchEvent(new CustomEvent('vigia-confirm', {
                detail: { ...options, message, resolve },
            }));
        });
    };

    // Formularios con data-confirm (en el form o en el botón): se detiene el envío, se pregunta
    // con el pop up y, si se acepta, se vuelve a enviar con el mismo botón.
    document.addEventListener('submit', function (event) {
        const form = event.target;
        const submitter = event.submitter;
        const source = (submitter && submitter.dataset.confirm !== undefined) ? submitter : form;
        const message = source.dataset.confirm;

        if (message === undefined) return;
        if (form.dataset.confirmed === '1') {
            delete form.dataset.confirmed;
            return;
        }

        // Fase de captura: que no lleguen al form otros @submit hasta que se confirme.
        event.preventDefault();
        event.stopImmediatePropagation();

        window.vigiaConfirm(message, {
            title:       source.dataset.confirmTitle,
            confirmText: source.dataset.confirmButton,
            danger:      source.dataset.confirmDanger !== undefined,
        }).then((ok) => {
            if (!ok) return;
            form.dataset.confirmed = '1';
            form.requestSubmit(submitter || undefined);
        });
    }, true);
</script>
