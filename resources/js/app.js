import Alpine from 'alpinejs';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import Chart from 'chart.js/auto';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
import { Html5Qrcode } from 'html5-qrcode';

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
                text: form.dataset.confirm,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: form.dataset.confirmButton || 'Ya, lanjutkan',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#8EB4D3',
                cancelButtonColor: '#94A3B8',
                reverseButtons: true,
            }).then((result) => {
                if (result.isConfirmed) {
                    form.dataset.confirmed = 'true';
                    form.submit();
                }
            });
        });
    });

    document.querySelectorAll('[data-copy]').forEach((button) => {
        button.addEventListener('click', async () => {
            const value = button.dataset.copy;

            try {
                await navigator.clipboard.writeText(value);
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Disalin ke clipboard',
                    showConfirmButton: false,
                    timer: 1800,
                });
            } catch (error) {
                Swal.fire('Gagal menyalin', 'Salin manual: ' + value, 'info');
            }
        });
    });

    document.querySelectorAll('[data-auto-dismiss]').forEach((element) => {
        setTimeout(() => element.remove(), Number(element.dataset.autoDismiss) || 5000);
    });
});
