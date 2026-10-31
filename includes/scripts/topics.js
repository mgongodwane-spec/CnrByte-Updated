/* topic.js
   Reusable slider logic for all dashboard topic cards.
   Each .topic-card contains a .slides container and optional .prev/.next buttons.
   Images are expected in ../uploads/images/dashboard/
*/

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.topic-card').forEach(card => {
    const slidesEl = card.querySelector('.slides');
    if (!slidesEl) return;

    const slides = slidesEl.querySelectorAll('.slide');
    const prevBtn = card.querySelector('.prev');
    const nextBtn = card.querySelector('.next');
    let index = 0;

    function update() {
      slidesEl.style.transform = `translateX(-${index * 100}%)`;
    }

    function prev() {
      index = (index - 1 + slides.length) % slides.length;
      update();
    }

    function next() {
      index = (index + 1) % slides.length;
      update();
    }

    if (prevBtn) prevBtn.addEventListener('click', prev);
    if (nextBtn) nextBtn.addEventListener('click', next);

    // Auto-advance every 5s
    let auto = setInterval(next, 5000);

    // Pause auto-advance on hover
    card.addEventListener('mouseenter', () => clearInterval(auto));
    card.addEventListener('mouseleave', () => { auto = setInterval(next, 5000); });

    // Touch swipe support for mobile
    let startX = 0;
    slidesEl.addEventListener('touchstart', e => {
      startX = e.touches[0].clientX;
    });
    slidesEl.addEventListener('touchend', e => {
      const endX = e.changedTouches[0].clientX;
      if (endX - startX > 50) prev();
      if (startX - endX > 50) next();
    });

    update();
  });
});