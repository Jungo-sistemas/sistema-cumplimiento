{{-- Lógica de processes.partials.flow-change-evidences. Se incluye fuera de cualquier <template>. --}}
@once
<script>
function changeEvidencePicker(existing, max) {
    // Los File se guardan fuera del estado reactivo: DataTransfer no acepta los proxies de Alpine.
    let files = [];

    return {
        existing: existing || [],
        removed: [],
        names: [],
        error: '',

        get remaining() {
            return max - (this.existing.length - this.removed.length) - this.names.length;
        },

        reset(existing) {
            this.existing = existing || [];
            this.removed = [];
            this.error = '';
            files = [];
            this.sync();
        },

        pick(event) {
            this.error = '';
            for (const file of event.target.files) {
                if (this.remaining <= 0) {
                    this.error = `Solo se permiten ${max} evidencias de apoyo.`;
                    break;
                }
                if (!/\.(pdf|jpe?g|png)$/i.test(file.name)) {
                    this.error = 'Las evidencias deben ser imagen (JPG/PNG) o PDF.';
                    continue;
                }
                if (file.size > 20 * 1024 * 1024) {
                    this.error = `«${file.name}» pesa más de 20 MB.`;
                    continue;
                }
                files.push(file);
                this.names.push(file.name);
            }
            event.target.value = '';
            this.sync();
        },

        removeNew(i) {
            files.splice(i, 1);
            this.names.splice(i, 1);
            this.error = '';
            this.sync();
        },

        toggleExisting(id) {
            if (this.removed.includes(id)) {
                if (this.remaining <= 0) {
                    this.error = `Solo se permiten ${max} evidencias de apoyo.`;
                    return;
                }
                this.removed = this.removed.filter(r => r !== id);
            } else {
                this.removed.push(id);
            }
            this.error = '';
        },

        sync() {
            const dt = new DataTransfer();
            files.forEach(f => dt.items.add(f));
            this.$refs.files.files = dt.files;
            this.names = files.map(f => f.name);
        },
    };
}
</script>
@endonce
