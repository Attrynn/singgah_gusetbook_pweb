import Alpine from 'alpinejs';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';

// Heavy, page-specific libraries are loaded on demand so they never weigh down
// the shared bundle. Each loader memoises its in-flight promise so repeated
// calls (and multiple charts on one page) share a single download.
let chartPromise = null;
let qrScannerPromise = null;

window.loadChart = function loadChart() {
    if (!chartPromise) {
        chartPromise = import('chart.js/auto').then((module) => module.default);
    }

    return chartPromise;
};

window.loadQrScanner = function loadQrScanner() {
    if (!qrScannerPromise) {
        qrScannerPromise = import('html5-qrcode').then((module) => module.Html5Qrcode);
    }

    return qrScannerPromise;
};

// Global Theme Management (Midnight Slate & Champagne)
const Theme = {
    get() {
        return localStorage.getItem('vowspace_theme') || 'system';
    },
    set(theme) {
        localStorage.setItem('vowspace_theme', theme);
        this.apply(theme);
    },
    apply(theme) {
        const isDark =
            theme === 'dark' ||
            (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);

        if (isDark) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        window.dispatchEvent(new CustomEvent('theme-changed', { detail: { theme, isDark } }));
    },
    init() {
        this.apply(this.get());
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
            if (this.get() === 'system') {
                this.apply('system');
            }
        });
    },
};

Theme.init();

window.Theme = Theme;
window.Alpine = Alpine;
window.Swal = Swal;
window.flatpickr = flatpickr;

Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
    flatpickr('[data-datepicker]', {
        dateFormat: 'Y-m-d',
        allowInput: true,
        disableMobile: true,
    });

    flatpickr('[data-datetimepicker]', {
        enableTime: true,
        dateFormat: 'Y-m-d H:i',
        time_24hr: true,
        allowInput: true,
        disableMobile: true,
    });

    const flash = document.getElementById('flash-data');

    if (flash) {
        const type = flash.dataset.type || 'success';
        const message = flash.dataset.message;

        if (message) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: type === 'error' ? 'error' : type === 'warning' ? 'warning' : 'success',
                title: message,
                showConfirmButton: false,
                timer: 3600,
                timerProgressBar: true,
            });
        }
    }

    document.querySelectorAll('[data-copy]').forEach((button) => {
        button.addEventListener('click', () => {
            const value = button.dataset.copy;

            if (!value) {
                return;
            }

            navigator.clipboard.writeText(value).then(() => {
                if (window.Swal) {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Disalin ke clipboard',
                        showConfirmButton: false,
                        timer: 1600,
                    });
                }
            }).catch(() => {
                if (window.Swal) {
                    Swal.fire('Gagal menyalin', 'Salin manual: ' + value, 'error');
                }
            });
        });
    });

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed === 'true') {
                return;
            }

            event.preventDefault();

            Swal.fire({
                title: form.dataset.confirmTitle || 'Konfirmasi',
                text: form.dataset.confirmText || 'Apakah Anda yakin ingin melanjutkan tindakan ini?',
                icon: form.dataset.confirmIcon || 'warning',
                showCancelButton: true,
                confirmButtonText: form.dataset.confirmButton || 'Ya, Lanjutkan',
                cancelButtonText: 'Batal',
                reverseButtons: true,
            }).then((result) => {
                if (result.isConfirmed) {
                    form.dataset.confirmed = 'true';
                    form.submit();
                }
            });
        });
    });
});
