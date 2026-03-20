document.querySelectorAll('[data-vv-scrollto]').forEach(btn => {
  btn.addEventListener('click', () => {
    const target = document.querySelector(btn.dataset.vvScrollto);

    if (target) {
      const offset = 80; // hauteur header
      const y = target.getBoundingClientRect().top + window.scrollY - offset;

      window.scrollTo({
        top: y,
        behavior: 'smooth'
      });
    }
  });
});

document.addEventListener("DOMContentLoaded", () => {
  // ====== NAVBAR SCROLL ======
  const header = document.querySelector("[data-vv-header]");
  const setScrolled = () => {
    if (!header) return;
    header.classList.toggle("is-scrolled", window.scrollY > 20);
  };
  window.addEventListener("scroll", setScrolled, { passive: true });
  setScrolled();

  // ====== SCROLL REVEAL ======
  const els = document.querySelectorAll(".vv-scroll-reveal");
  if ("IntersectionObserver" in window) {
    const io = new IntersectionObserver(
      (entries) => entries.forEach((e) => e.isIntersecting && e.target.classList.add("vv-visible")),
      { threshold: 0.1 }
    );
    els.forEach((el) => io.observe(el));
  } else {
    els.forEach((el) => el.classList.add("vv-visible"));
  }

  // ====== EVENTS BG PARALLAX ======
  const section = document.querySelector(".vv-eventsSection");
  const bg = document.querySelector(".vv-eventsSection__bg");
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

    window.addEventListener("scroll", updateTarget, { passive: true });
    window.addEventListener("resize", updateTarget);
    updateTarget();
    animate();
  }

  // ====== GLIGHTBOX (GALERIE) ======
  if (window.GLightbox) {
    GLightbox({
      selector: 'a.glightbox[data-gallery="epicafe-gallery"]',
      loop: true,
      touchNavigation: true,
      keyboardNavigation: true,
      closeOnOutsideClick: true,
    });
  }

  
});