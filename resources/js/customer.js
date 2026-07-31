import './bootstrap';
import { Alpine, Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';

window.Alpine = Alpine;

Alpine.data('portalNavigation', () => ({
    open: false,
    init() {
        this.$watch('open', (isOpen) => {
            document.body.style.overflow = isOpen ? 'hidden' : '';
            if (isOpen) this.$nextTick(() => this.$refs.mobileMenu?.querySelector('a')?.focus());
        });
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 1024) this.closeMenu();
        }, { passive: true });
    },
    openMenu() {
        this.open = true;
    },
    closeMenu(returnFocus = false) {
        this.open = false;
        document.body.style.overflow = '';
        if (returnFocus) this.$nextTick(() => this.$refs.toggleButton?.focus());
    },
}));

Livewire.start();

const errorSummary = document.querySelector('[data-error-summary]');
if (errorSummary) requestAnimationFrame(() => errorSummary.focus());
