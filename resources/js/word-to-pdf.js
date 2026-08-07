export function wordToPdfUpload(maximumMegabytes, timeoutSeconds) {
    return {
        file: null,
        dragging: false,
        submitting: false,
        clientError: '',
        chooseFile() {
            if (!this.submitting) this.$refs.document.click();
        },
        onFileChange(event) {
            this.selectFile(event.target.files?.[0] ?? null);
        },
        onDrop(event) {
            this.dragging = false;
            const file = event.dataTransfer?.files?.[0] ?? null;
            if (!file) return;

            const transfer = new DataTransfer();
            transfer.items.add(file);
            this.$refs.document.files = transfer.files;
            this.selectFile(file);
        },
        selectFile(file) {
            this.clientError = '';
            this.file = null;
            if (!file) return;

            const extension = file.name.split('.').pop()?.toLowerCase();
            if (!['doc', 'docx'].includes(extension)) {
                this.clientError = 'Pilih file dengan format DOC atau DOCX.';
                this.$refs.document.value = '';
                return;
            }

            if (file.size < 1 || file.size > maximumMegabytes * 1024 * 1024) {
                this.clientError = `Ukuran dokumen harus lebih dari 0 byte dan maksimal ${maximumMegabytes} MB.`;
                this.$refs.document.value = '';
                return;
            }

            this.file = file;
        },
        submit(event) {
            if (!this.file || this.clientError) {
                event.preventDefault();
                this.clientError ||= 'Pilih dokumen Word terlebih dahulu.';
                return;
            }

            this.submitting = true;
            window.setTimeout(() => { this.submitting = false; }, (timeoutSeconds + 5) * 1000);
        },
        fileSize() {
            if (!this.file) return '';
            const size = this.file.size < 1024 * 1024
                ? `${(this.file.size / 1024).toFixed(1)} KB`
                : `${(this.file.size / (1024 * 1024)).toFixed(2)} MB`;
            return size;
        },
    };
}
