import Alpine from 'alpinejs';

/**
 * QR check-in scanner component.
 *
 * Registered from the Vite bundle before Alpine.start() runs. The previous
 * inline registration happened before the deferred module bundle defined
 * Alpine, so it never took effect and the scanner was inert.
 */
Alpine.data('scanner', () => ({
    isScanning: false,
    scanner: null,
    selectedEventId: null,
    manualToken: '',
    scans: [],
    lastResult: null,
    scanUrl: '',

    init() {
        this.selectedEventId = this.$el.dataset.selectedEventId
            ? Number(this.$el.dataset.selectedEventId)
            : null;
        this.scanUrl = this.$el.dataset.scanUrl || '';

        // The camera must be released when the user navigates away or the page
        // is put into the background, otherwise the device light stays on.
        this.onPageHide = () => this.releaseCamera();
        window.addEventListener('pagehide', this.onPageHide);
    },

    destroy() {
        window.removeEventListener('pagehide', this.onPageHide);
        this.releaseCamera();
    },

    now() {
        return new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    },

    async startCamera() {
        if (this.isScanning) {
            return;
        }

        try {
            const Html5Qrcode = await window.loadQrScanner();
            this.scanner = new Html5Qrcode('reader');
            await this.scanner.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: 250 },
                (decodedText) => this.handleScan(decodedText),
                () => { /* per-frame decode misses are expected */ }
            );
            this.isScanning = true;
        } catch (error) {
            this.releaseCamera();
            window.Swal?.fire(
                'Kamera tidak tersedia',
                'Pastikan kamera diizinkan dan tersedia. Anda masih dapat memasukkan token secara manual.',
                'error'
            );
        }
    },

    releaseCamera() {
        if (!this.scanner) {
            this.isScanning = false;
            return;
        }

        const scanner = this.scanner;
        this.scanner = null;
        this.isScanning = false;

        Promise.resolve()
            .then(() => scanner.stop())
            .then(() => scanner.clear())
            .catch(() => { /* already stopped */ });
    },

    stopCamera() {
        this.releaseCamera();
    },

    async handleScan(token) {
        this.releaseCamera();
        await this.submitToken(token);
    },

    async pasteToken() {
        const token = (this.manualToken || '').trim();

        if (!token) {
            return;
        }

        this.manualToken = '';
        await this.submitToken(token);
    },

    async submitToken(token) {
        try {
            const response = await fetch(this.scanUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ token, event_id: this.selectedEventId || undefined }),
            });

            if (!response.ok) {
                this.addResult({
                    status: 'error',
                    message: 'Server menolak permintaan (' + response.status + '). Check-in tidak tercatat.',
                });

                return;
            }

            this.addResult(await response.json());
        } catch (error) {
            this.addResult({
                status: 'error',
                message: 'Gagal menghubungi server. Check-in tidak tercatat.',
            });
        }
    },

    addResult(data) {
        const entry = {
            id: Date.now(),
            status: data.status || 'error',
            message: data.message || 'Tidak ada respons.',
            time: this.now(),
            warning: data.warning || null,
        };

        this.scans.unshift(entry);
        this.lastResult = { ...entry, guest: data.guest || null };
    },

    clearLog() {
        this.scans = [];
        this.lastResult = null;
    },
}));
