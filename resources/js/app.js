import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('navigation', () => ({
    open: false,
    scrolled: false,
    init() {
        const update = () => { this.scrolled = window.scrollY > 12; };
        const closeAtDesktop = () => {
            if (window.innerWidth >= 1024) this.close();
        };

        update();
        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', closeAtDesktop, { passive: true });
        this.$watch('open', (isOpen) => {
            document.body.style.overflow = isOpen && window.innerWidth < 1024 ? 'hidden' : '';

            if (isOpen) {
                this.$nextTick(() => this.$refs.mobileMenu?.querySelector('a')?.focus());
            }
        });
    },
    toggle() {
        if (this.open) {
            this.close();
        } else {
            this.open = true;
        }
    },
    close(returnFocus = false) {
        this.open = false;
        document.body.style.overflow = '';
        if (returnFocus) this.$nextTick(() => this.$refs.toggleButton?.focus());
    },
}));

Alpine.data('faqItem', () => ({ open: false, toggle() { this.open = !this.open; } }));
Alpine.start();

const errorSummary = document.querySelector('[data-error-summary]');
if (errorSummary) requestAnimationFrame(() => errorSummary.focus());

const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
if (!reducedMotion && 'IntersectionObserver' in window) {
    document.documentElement.classList.add('reveal-ready');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });
    document.querySelectorAll('[data-reveal]').forEach((element) => observer.observe(element));
} else {
    document.querySelectorAll('[data-reveal]').forEach((element) => element.classList.add('is-visible'));
}
