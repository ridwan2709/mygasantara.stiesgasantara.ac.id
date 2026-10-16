import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

const digitalClock = document.querySelector('[data-digital-clock]');

if (digitalClock) {
    const formatter = new Intl.DateTimeFormat('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hourCycle: 'h23',
    });

    const updateDigitalClock = () => {
        digitalClock.textContent = formatter.format(new Date());
    };

    updateDigitalClock();
    window.setInterval(updateDigitalClock, 1000);
}
