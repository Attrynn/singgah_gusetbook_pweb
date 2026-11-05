import Alpine from 'alpinejs';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import Chart from 'chart.js/auto';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
import { Html5Qrcode } from 'html5-qrcode';

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
window.Chart = Chart;
window.flatpickr = flatpickr;
window.Html5Qrcode = Html5Qrcode;

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
