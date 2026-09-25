(() => {
  document.querySelectorAll('.hero[data-hero-carousel]').forEach((root) => {
    const textSlides = [...root.querySelectorAll('.hero-slides [data-slide]')];
    const imageSlides = [...root.querySelectorAll('.hero-image-slides [data-slide]')];
    const dots = [...root.querySelectorAll('[data-carousel-dot]')];
    const prev = root.querySelector('[data-carousel-prev]');
    const next = root.querySelector('[data-carousel-next]');
    const slideCount = Math.min(textSlides.length, imageSlides.length);
    let current = 0;
    let timer;

    if (!slideCount) return;

    const show = (index) => {
      current = (index + slideCount) % slideCount;
      [textSlides, imageSlides].forEach((group) => group.forEach((slide, i) => {
        const active = i === current;
        slide.classList.toggle('is-active', active);
        slide.setAttribute('aria-hidden', String(!active));
      }));
      dots.forEach((dot, i) => {
        const active = i === current;
        dot.classList.toggle('is-active', active);
        dot.setAttribute('aria-selected', String(active));
      });
    };

    const restart = () => {
      window.clearInterval(timer);
      timer = window.setInterval(() => show(current + 1), 6000);
    };

    prev?.addEventListener('click', () => { show(current - 1); restart(); });
    next?.addEventListener('click', () => { show(current + 1); restart(); });
    dots.forEach((dot) => dot.addEventListener('click', () => {
      show(Number(dot.dataset.carouselDot));
      restart();
    }));
    root.addEventListener('mouseenter', () => window.clearInterval(timer));
    root.addEventListener('mouseleave', restart);
    root.addEventListener('focusin', () => window.clearInterval(timer));
    root.addEventListener('focusout', restart);
    root.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowLeft') { show(current - 1); restart(); }
      if (event.key === 'ArrowRight') { show(current + 1); restart(); }
    });
    root.tabIndex = 0;
    show(0);
    restart();
  });
})();
