/**
 * TRISULA TNI AD - MODERN ANIMATED LOGIN SCRIPT
 * Handles Canvas Particle System, 3D Tilt Effect, Modal Management,
 * Password Visibility Toggle, and LocalStorage Remember Me.
 */

(function () {
  'use strict';

  // =====================================================================
  // 1. CANVAS PARTICLE SYSTEM (Tactical Military Embers & Cyber Nodes)
  // =====================================================================
  const canvas = document.getElementById('particlesCanvas');
  let ctx = null;
  let particles = [];
  let animationFrameId = null;
  let width = 0;
  let height = 0;

  if (canvas) {
    ctx = canvas.getContext('2d');

    function resizeCanvas() {
      width = canvas.width = window.innerWidth;
      height = canvas.height = window.innerHeight;
    }

    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();

    const PARTICLE_COUNT = window.innerWidth < 768 ? 35 : 75;
    const colors = [
      'rgba(212, 175, 55, ',   // Gold
      'rgba(255, 215, 0, ',    // Bright Gold
      'rgba(74, 222, 128, ',   // Tactical Emerald
      'rgba(143, 174, 83, '    // Army Accent Green
    ];

    class Particle {
      constructor() {
        this.reset(true);
      }

      reset(initial = false) {
        this.x = Math.random() * width;
        this.y = initial ? Math.random() * height : height + 10;
        this.size = Math.random() * 2.2 + 0.8;
        this.speedY = Math.random() * 0.7 + 0.3;
        this.speedX = (Math.random() - 0.5) * 0.4;
        this.baseAlpha = Math.random() * 0.6 + 0.2;
        this.alpha = this.baseAlpha;
        this.colorPrefix = colors[Math.floor(Math.random() * colors.length)];
        this.twinkleSpeed = Math.random() * 0.02 + 0.01;
        this.twinkleDir = 1;
      }

      update() {
        this.y -= this.speedY;
        this.x += this.speedX + Math.sin(this.y * 0.005) * 0.2;

        // Twinkle effect
        this.alpha += this.twinkleSpeed * this.twinkleDir;
        if (this.alpha > this.baseAlpha + 0.25 || this.alpha < 0.15) {
          this.twinkleDir *= -1;
        }

        // Reset if moved off top or sides
        if (this.y < -10 || this.x < -20 || this.x > width + 20) {
          this.reset(false);
        }
      }

      draw() {
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
        ctx.fillStyle = this.colorPrefix + Math.max(0, Math.min(1, this.alpha)) + ')';
        ctx.shadowBlur = this.size * 3;
        ctx.shadowColor = this.colorPrefix + '0.8)';
        ctx.fill();
        ctx.shadowBlur = 0;
      }
    }

    // Initialize particles
    for (let i = 0; i < PARTICLE_COUNT; i++) {
      particles.push(new Particle());
    }

    function renderParticles() {
      ctx.clearRect(0, 0, width, height);

      // Connect nearby particles with subtle tactical cyber lines
      for (let i = 0; i < particles.length; i++) {
        for (let j = i + 1; j < particles.length; j++) {
          const dx = particles[i].x - particles[j].x;
          const dy = particles[i].y - particles[j].y;
          const dist = Math.sqrt(dx * dx + dy * dy);

          if (dist < 90) {
            const lineAlpha = (1 - dist / 90) * 0.12;
            ctx.beginPath();
            ctx.moveTo(particles[i].x, particles[i].y);
            ctx.lineTo(particles[j].x, particles[j].y);
            ctx.strokeStyle = `rgba(212, 175, 55, ${lineAlpha})`;
            ctx.lineWidth = 0.6;
            ctx.stroke();
          }
        }
      }

      for (let i = 0; i < particles.length; i++) {
        particles[i].update();
        particles[i].draw();
      }

      animationFrameId = requestAnimationFrame(renderParticles);
    }

    // Start rendering
    animationFrameId = requestAnimationFrame(renderParticles);

    // Pause on hidden tab to conserve performance
    document.addEventListener('visibilitychange', () => {
      if (document.hidden) {
        cancelAnimationFrame(animationFrameId);
      } else {
        animationFrameId = requestAnimationFrame(renderParticles);
      }
    });
  }

  // =====================================================================
  // 2. CURSOR AMBIENT GLOW & 3D CARD TILT
  // =====================================================================
  const cursorGlow = document.getElementById('cursorAmbientGlow');
  const cardContainer = document.getElementById('loginCardContainer');
  const glassCard = document.getElementById('glassCard');

  let mouseX = window.innerWidth / 2;
  let mouseY = window.innerHeight / 2;
  let cardRect = cardContainer ? cardContainer.getBoundingClientRect() : null;

  function updateCardRect() {
    if (cardContainer) {
      cardRect = cardContainer.getBoundingClientRect();
    }
  }
  window.addEventListener('resize', updateCardRect);
  window.addEventListener('scroll', updateCardRect);

  document.addEventListener('mousemove', function (e) {
    mouseX = e.clientX;
    mouseY = e.clientY;

    // Move subtle cursor glow
    if (cursorGlow) {
      cursorGlow.style.left = mouseX + 'px';
      cursorGlow.style.top = mouseY + 'px';
    }

    // 3D Tilt calculation (desktop only)
    if (cardContainer && glassCard && window.innerWidth >= 900 && !cardContainer.classList.contains('hidden-card') && cardContainer.style.display !== 'none') {
      if (!cardRect) updateCardRect();

      const cardCenterX = cardRect.left + cardRect.width / 2;
      const cardCenterY = cardRect.top + cardRect.height / 2;

      const deltaX = (mouseX - cardCenterX) / (window.innerWidth / 2);
      const deltaY = (mouseY - cardCenterY) / (window.innerHeight / 2);

      const rotateY = deltaX * 8; // Max 8 deg rotation
      const rotateX = -deltaY * 8;

      cardContainer.style.transform = `perspective(1000px) rotateX(${rotateX.toFixed(2)}deg) rotateY(${rotateY.toFixed(2)}deg)`;

      // Update specular glare position inside card
      const rect = glassCard.getBoundingClientRect();
      const localX = ((mouseX - rect.left) / rect.width) * 100;
      const localY = ((mouseY - rect.top) / rect.height) * 100;
      glassCard.style.setProperty('--mouse-x', `${localX.toFixed(1)}%`);
      glassCard.style.setProperty('--mouse-y', `${localY.toFixed(1)}%`);
    }
  });

  // Reset card tilt when mouse leaves window
  document.addEventListener('mouseleave', function () {
    if (cardContainer) {
      cardContainer.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg)';
    }
  });

  // =====================================================================
  // 3. WORKFLOW VIEW & LOGIN CARD TOGGLE
  // =====================================================================
  const workflowContainer = document.getElementById('workflowContainer');
  const loginCardContainer = document.getElementById('loginCardContainer');
  const cardCloseBtn = document.getElementById('cardCloseBtn');
  const cardRestoreTrigger = document.getElementById('cardRestoreTrigger');
  const navLoginBtn = document.getElementById('navLoginBtn');
  const navHomeLink = document.getElementById('navHomeLink');
  const navWorkflowBtn = document.getElementById('navWorkflowBtn');
  const workflowLoginBtn = document.getElementById('workflowLoginBtn');
  const backToWorkflowBtn = document.getElementById('backToWorkflowBtn');
  const usernameInput = document.getElementById('usernameInput');

  function showLoginForm() {
    if (workflowContainer) {
      workflowContainer.style.display = 'none';
    }
    if (loginCardContainer) {
      loginCardContainer.style.display = 'block';
      loginCardContainer.classList.remove('hidden-card');
      loginCardContainer.style.animation = 'none';
      void loginCardContainer.offsetWidth; // trigger reflow
      loginCardContainer.style.animation = 'cardEntrance 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards';
      updateCardRect();
    }
    if (cardRestoreTrigger) {
      cardRestoreTrigger.classList.remove('visible');
    }
    if (navLoginBtn) {
      navLoginBtn.classList.remove('active-glow');
    }
    if (usernameInput) {
      setTimeout(() => usernameInput.focus(), 250);
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
    try {
      if (window.location.hash !== '#login') {
        history.replaceState(null, null, '#login');
      }
    } catch (e) {}
  }

  function showWorkflow() {
    if (loginCardContainer) {
      loginCardContainer.style.display = 'none';
      loginCardContainer.classList.add('hidden-card');
    }
    if (workflowContainer) {
      workflowContainer.style.display = 'block';
      workflowContainer.style.animation = 'none';
      void workflowContainer.offsetWidth;
      workflowContainer.style.animation = 'workflowEntrance 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards';
    }
    if (cardRestoreTrigger) {
      cardRestoreTrigger.classList.remove('visible');
    }
    if (navLoginBtn) {
      navLoginBtn.classList.add('active-glow');
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
    try {
      if (window.location.hash === '#login') {
        history.replaceState(null, null, window.location.pathname);
      }
    } catch (e) {}
  }

  if (workflowLoginBtn) {
    workflowLoginBtn.addEventListener('click', function(e) {
      e.preventDefault();
      showLoginForm();
    });
  }

  document.querySelectorAll('[data-action="open-login"]').forEach(el => {
    el.addEventListener('click', function(e) {
      e.preventDefault();
      showLoginForm();
    });
  });

  if (cardCloseBtn) {
    cardCloseBtn.addEventListener('click', function (e) {
      e.preventDefault();
      showWorkflow();
    });
  }

  if (backToWorkflowBtn) {
    backToWorkflowBtn.addEventListener('click', function (e) {
      e.preventDefault();
      showWorkflow();
    });
  }

  if (cardRestoreTrigger) {
    cardRestoreTrigger.addEventListener('click', function () {
      showLoginForm();
    });
  }

  if (navLoginBtn) {
    navLoginBtn.addEventListener('click', function (e) {
      e.preventDefault();
      showLoginForm();
    });
  }

  if (navHomeLink) {
    navHomeLink.addEventListener('click', function (e) {
      e.preventDefault();
      showWorkflow();
    });
  }

  if (navWorkflowBtn) {
    navWorkflowBtn.addEventListener('click', function (e) {
      e.preventDefault();
      showWorkflow();
    });
  }

  // Keyboard Escape: return to workflow if login card is open
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && loginCardContainer && loginCardContainer.style.display !== 'none' && !loginCardContainer.classList.contains('hidden-card')) {
      showWorkflow();
    }
  });

  // Check URL hash on load
  if (window.location.hash === '#login') {
    showLoginForm();
  }

  // =====================================================================
  // 4. PASSWORD VISIBILITY TOGGLE
  // =====================================================================
  const passwordInput = document.getElementById('passwordInput');
  const togglePasswordBtn = document.getElementById('togglePasswordBtn');

  if (togglePasswordBtn && passwordInput) {
    togglePasswordBtn.addEventListener('click', function () {
      const isPassword = passwordInput.getAttribute('type') === 'password';
      passwordInput.setAttribute('type', isPassword ? 'text' : 'password');

      // Update SVG icon
      togglePasswordBtn.innerHTML = isPassword
        ? `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
             <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
             <line x1="1" y1="1" x2="23" y2="23"></line>
           </svg>`
        : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
             <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
             <circle cx="12" cy="12" r="3"></circle>
           </svg>`;
    });
  }

  // =====================================================================
  // 5. REMEMBER ME (LocalStorage Persistence)
  // =====================================================================
  const rememberMeCheckbox = document.getElementById('rememberMeCheckbox');
  const loginForm = document.getElementById('loginForm');
  const STORAGE_KEY = 'trisula_remember_nrp';

  // Load remembered NRP on startup
  if (rememberMeCheckbox && usernameInput) {
    const savedNrp = localStorage.getItem(STORAGE_KEY);
    if (savedNrp) {
      usernameInput.value = savedNrp;
      rememberMeCheckbox.checked = true;
      if (passwordInput) passwordInput.focus();
    }
  }

  // Save or clear on form submit
  if (loginForm) {
    loginForm.addEventListener('submit', function () {
      const submitBtn = document.getElementById('submitLoginBtn');
      const submitText = document.getElementById('submitBtnText');

      if (rememberMeCheckbox && usernameInput) {
        if (rememberMeCheckbox.checked) {
          localStorage.setItem(STORAGE_KEY, usernameInput.value.trim());
        } else {
          localStorage.removeItem(STORAGE_KEY);
        }
      }

      // Add loading state
      if (submitBtn) {
        submitBtn.classList.add('is-loading');
        if (submitText) {
          submitText.textContent = 'Memverifikasi Otentikasi...';
        }
      }
    });
  }

  // =====================================================================
  // 6. BUTTON RIPPLE EFFECT
  // =====================================================================
  document.querySelectorAll('.btn-submit-login, .nav-btn-login, .modal-btn').forEach(button => {
    button.addEventListener('click', function (e) {
      const circle = document.createElement('span');
      const diameter = Math.max(this.clientWidth, this.clientHeight);
      const radius = diameter / 2;
      const rect = this.getBoundingClientRect();

      circle.style.width = circle.style.height = `${diameter}px`;
      circle.style.left = `${e.clientX - rect.left - radius}px`;
      circle.style.top = `${e.clientY - rect.top - radius}px`;
      circle.classList.add('ripple-wave');

      const existingRipple = this.querySelector('.ripple-wave');
      if (existingRipple) {
        existingRipple.remove();
      }

      this.appendChild(circle);
    });
  });

  // =====================================================================
  // 7. MODALS LOGIC (About, Services, Contact, Forgot Password)
  // =====================================================================
  const modalOverlays = document.querySelectorAll('.modal-overlay');

  function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.add('active');
      document.body.style.overflow = 'hidden';
    }
  }

  function closeAllModals() {
    modalOverlays.forEach(modal => modal.classList.remove('active'));
    document.body.style.overflow = '';
  }

  // Close buttons inside modals
  document.querySelectorAll('.modal-close-btn, [data-close-modal]').forEach(btn => {
    btn.addEventListener('click', closeAllModals);
  });

  // Close when clicking overlay backdrop
  modalOverlays.forEach(overlay => {
    overlay.addEventListener('click', function (e) {
      if (e.target === this) {
        closeAllModals();
      }
    });
  });

  // Navbar link triggers
  const navAboutBtn = document.getElementById('navAboutBtn');
  const navServicesBtn = document.getElementById('navServicesBtn');
  const navContactBtn = document.getElementById('navContactBtn');
  const forgotPasswordBtn = document.getElementById('forgotPasswordBtn');

  if (navAboutBtn) {
    navAboutBtn.addEventListener('click', (e) => {
      e.preventDefault();
      openModal('aboutModal');
    });
  }

  if (navServicesBtn) {
    navServicesBtn.addEventListener('click', (e) => {
      e.preventDefault();
      openModal('servicesModal');
    });
  }

  if (navContactBtn) {
    navContactBtn.addEventListener('click', (e) => {
      e.preventDefault();
      openModal('contactModal');
    });
  }

  if (forgotPasswordBtn) {
    forgotPasswordBtn.addEventListener('click', (e) => {
      e.preventDefault();
      openModal('forgotPasswordModal');
    });
  }

  // Escape key handler
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      const activeModal = document.querySelector('.modal-overlay.active');
      if (activeModal) {
        closeAllModals();
      } else if (cardContainer && !cardContainer.classList.contains('hidden-card')) {
        dismissCard();
      }
    }
  });

  // =====================================================================
  // 8. MOBILE NAVIGATION DRAWER
  // =====================================================================
  const mobileMenuBtn = document.getElementById('mobileMenuBtn');
  const navLinksContainer = document.getElementById('navLinksContainer');

  if (mobileMenuBtn && navLinksContainer) {
    mobileMenuBtn.addEventListener('click', function () {
      navLinksContainer.classList.toggle('mobile-open');
    });

    // Close when clicking any nav item
    navLinksContainer.querySelectorAll('.nav-item a, .nav-item button').forEach(item => {
      item.addEventListener('click', () => {
        navLinksContainer.classList.remove('mobile-open');
      });
    });
  }

  // Navbar scroll background enhancement
  const topNavbar = document.getElementById('topNavbar');
  window.addEventListener('scroll', () => {
    if (window.scrollY > 30) {
      topNavbar && topNavbar.classList.add('scrolled');
    } else {
      topNavbar && topNavbar.classList.remove('scrolled');
    }
  });

})();
