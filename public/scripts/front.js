console.log('front.js chargé');

function initFront() {
  console.log('initFront lancé');

  // ===== BURGER =====
  const burger = document.querySelector('[data-vv-burger]');
  const mobileMenu = document.querySelector('[data-vv-mobile]');
  const closeLinks = document.querySelectorAll('[data-vv-close-mobile]');

  console.log('burger:', burger);
  console.log('mobileMenu:', mobileMenu);
  console.log('closeLinks:', closeLinks.length);

  if (burger && mobileMenu) {
    const closeMobileMenu = () => {
      mobileMenu.classList.remove('is-open');
      burger.setAttribute('aria-expanded', 'false');
    };

    burger.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      console.log('click burger');

      const isOpen = mobileMenu.classList.toggle('is-open');
      burger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    closeLinks.forEach(link => {
      link.addEventListener('click', () => {
        closeMobileMenu();
      });
    });

    document.addEventListener('click', (event) => {
      const clickedBurger = burger.contains(event.target);
      const clickedMenu = mobileMenu.contains(event.target);

      if (!clickedBurger && !clickedMenu) {
        closeMobileMenu();
      }
    });

    window.addEventListener('resize', () => {
      if (window.innerWidth >= 980) {
        closeMobileMenu();
      }
    });
  } else {
    console.warn('Burger ou menu mobile introuvable');
  }

  // ===== SCROLL TO =====
  document.querySelectorAll('[data-vv-scrollto]').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = document.querySelector(btn.dataset.vvScrollto);

      if (target) {
        const offset = 80;
        const y = target.getBoundingClientRect().top + window.scrollY - offset;

        window.scrollTo({
          top: y,
          behavior: 'smooth'
        });
      }
    });
  });

  // ===== NAVBAR SCROLL =====
  const header = document.querySelector('[data-vv-header]');
  const setScrolled = () => {
    if (!header) return;
    header.classList.toggle('is-scrolled', window.scrollY > 20);
  };
  window.addEventListener('scroll', setScrolled, { passive: true });
  setScrolled();

  // ===== SCROLL REVEAL =====
  const els = document.querySelectorAll('.vv-scroll-reveal');
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver(
      (entries) => entries.forEach((e) => e.isIntersecting && e.target.classList.add('vv-visible')),
      { threshold: 0.1 }
    );
    els.forEach((el) => io.observe(el));
  } else {
    els.forEach((el) => el.classList.add('vv-visible'));
  }

  // ===== PARALLAX =====
  const section = document.querySelector('.vv-eventsSection');
  const bg = document.querySelector('.vv-eventsSection__bg');
  if (section && bg) {
    const speed = 0.35;
    const ease = 0.08;
    let current = 0;
    let target = 0;

    const updateTarget = () => {
      const rect = section.getBoundingClientRect();
      const windowH = window.innerHeight || 1;
      if (rect.bottom < 0 || rect.top > windowH) return;
      target = -rect.top * speed;
    };

    const animate = () => {
      current += (target - current) * ease;
      bg.style.transform = `translate3d(0, ${current}px, 0)`;
      requestAnimationFrame(animate);
    };

    window.addEventListener('scroll', updateTarget, { passive: true });
    window.addEventListener('resize', updateTarget);
    updateTarget();
    animate();
  }

  // ===== GLIGHTBOX =====
  if (window.GLightbox) {
    GLightbox({
      selector: 'a.glightbox[data-gallery="epicafe-gallery"]',
      loop: true,
      touchNavigation: true,
      keyboardNavigation: true,
      closeOnOutsideClick: true,
    });
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initFront);
} else {
  initFront();
}