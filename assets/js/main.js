// mobile menu open/close (CSP-safe, no inline handlers)
const mobileMenu = document.getElementById('mobileMenu');
document.getElementById('burgerBtn').addEventListener('click', () => mobileMenu.classList.add('open'));
document.getElementById('mobileMenuClose').addEventListener('click', () => mobileMenu.classList.remove('open'));
document.querySelectorAll('.mobile-link').forEach(link => {
  link.addEventListener('click', () => mobileMenu.classList.remove('open'));
});

// contact form -> contact.php (PHP mail handler)
const contactForm = document.getElementById('contactForm');
if (contactForm) {
  const successMsg = contactForm.querySelector('.form-msg-success');
  const errorMsg = contactForm.querySelector('.form-msg-error');
  const submitBtn = contactForm.querySelector('button[type="submit"]');

  contactForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (successMsg) successMsg.style.display = 'none';
    if (errorMsg) errorMsg.style.display = 'none';

    const originalLabel = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Sending…';

    try {
      const res = await fetch('contact.php', {
        method: 'POST',
        body: new FormData(contactForm),
        headers: { Accept: 'application/json' }
      });
      const data = await res.json().catch(() => ({ ok: false }));
      if (res.ok && data.ok) {
        contactForm.reset();
        if (successMsg) successMsg.style.display = 'block';
      } else if (errorMsg) {
        errorMsg.style.display = 'block';
      }
    } catch (err) {
      if (errorMsg) errorMsg.style.display = 'block';
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = originalLabel;
    }
  });
}

// sticky nav shadow
const nav = document.getElementById('nav');
window.addEventListener('scroll', () => {
  if (window.scrollY > 40) nav.classList.add('scrolled');
  else nav.classList.remove('scrolled');
});

// respect reduced-motion preference for hero video
const heroVideo = document.getElementById('heroVideo');
if (heroVideo && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
  heroVideo.pause();
  heroVideo.removeAttribute('autoplay');
}

// count-up animation for project counters
const counters = document.querySelectorAll('#counters .cval');
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
function animateCount(el) {
  const target = parseInt(el.getAttribute('data-target'), 10);
  if (reduceMotion) { el.textContent = target.toLocaleString(); return; }
  const duration = 1600;
  const start = performance.now();
  function tick(now) {
    const progress = Math.min((now - start) / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3);
    el.textContent = Math.round(eased * target).toLocaleString();
    if (progress < 1) requestAnimationFrame(tick);
    else el.textContent = target.toLocaleString();
  }
  requestAnimationFrame(tick);
}
const counterSection = document.getElementById('counters');
if (counterSection) {
  const cio = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        counters.forEach(animateCount);
        cio.unobserve(e.target);
      }
    });
  }, { threshold: 0.3 });
  cio.observe(counterSection);
}

// scroll-reveal fade-in
const revealEls = document.querySelectorAll('section');
const io = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.style.opacity = 1;
      e.target.style.transform = 'translateY(0)';
      io.unobserve(e.target);
    }
  });
}, { threshold: 0.08 });
revealEls.forEach(el => {
  el.style.opacity = 0;
  el.style.transform = 'translateY(24px)';
  el.style.transition = 'opacity .7s ease, transform .7s ease';
  io.observe(el);
});
