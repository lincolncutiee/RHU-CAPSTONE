/**
 * RHU Management System — Global JavaScript (top navigation)
 */

document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('topnavToggle');
    const menu = document.getElementById('topnavMenu');
    const topnav = document.getElementById('appTopnav');

    if (!toggle || !menu) return;

    let overlay = document.querySelector('.topnav-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'topnav-overlay';
        document.body.appendChild(overlay);
    }

    function openMenu() {
        menu.classList.add('is-open');
        overlay.classList.add('show');
        toggle.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }

    function closeMenu() {
        menu.classList.remove('is-open');
        overlay.classList.remove('show');
        toggle.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    toggle.addEventListener('click', () => {
        menu.classList.contains('is-open') ? closeMenu() : openMenu();
    });

    overlay.addEventListener('click', closeMenu);

    window.addEventListener('resize', () => {
        if (window.innerWidth >= 992) closeMenu();
    });

    // Sticky shadow when scrolled
    if (topnav) {
        const onScroll = () => {
            topnav.classList.toggle('is-scrolled', window.scrollY > 4);
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }
});
