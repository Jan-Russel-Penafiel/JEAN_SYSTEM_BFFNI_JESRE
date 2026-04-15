document.addEventListener('click', function (event) {
    const openTrigger = event.target.closest('[data-modal-open]');
    if (openTrigger) {
        const modalId = openTrigger.getAttribute('data-modal-open');
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
    }

    const closeTrigger = event.target.closest('[data-modal-close]');
    if (closeTrigger) {
        const modal = closeTrigger.closest('[data-modal]');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }

    if (event.target.matches('[data-modal]')) {
        event.target.classList.add('hidden');
        event.target.classList.remove('flex');
    }
});
