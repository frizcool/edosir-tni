/**
 * TRISULA TNI AD - MODERN ERROR HANDLING INTERACTION SCRIPT
 * Radar sweep canvas animation, 3D card tilt, incident code copier,
 * and ripple interactions.
 */

(function () {
  'use strict';

  // =====================================================================
  // 1. TACTICAL RADAR CANVAS ANIMATION
  // =====================================================================
  const canvas = document.getElementById('radarCanvas');
  if (canvas) {
    const ctx = canvas.getContext('2d');
    let width = 0;
    let height = 0;
    let sweepAngle = 0;
    let animationId = null;

    function resize() {
      width = canvas.width = window.innerWidth;
      height = canvas.height = window.innerHeight;
    }
    window.addEventListener('resize', resize);
    resize();

    // Floating tactical nodes
    const nodeCount = window.innerWidth < 768 ? 20 : 45;
    const nodes = [];
    for (let i = 0; i < nodeCount; i++) {
      nodes.push({
        x: Math.random() * width,
        y: Math.random() * height,
        size: Math.random() * 2 + 1,
        speedY: (Math.random() - 0.5) * 0.4,
        speedX: (Math.random() - 0.5) * 0.4,
        alpha: Math.random() * 0.5 + 0.2
      });
    }

    function render() {
      ctx.clearRect(0, 0, width, height);

      const centerX = width / 2;
      const centerY = height / 2;
      const maxRadius = Math.min(width, height) * 0.55;

      // Draw faint radar circles in background center
      ctx.strokeStyle = 'rgba(74, 222, 128, 0.05)';
      ctx.lineWidth = 1;

      for (let r = maxRadius * 0.25; r <= maxRadius; r += maxRadius * 0.25) {
        ctx.beginPath();
        ctx.arc(centerX, centerY, r, 0, Math.PI * 2);
        ctx.stroke();
      }

      // Draw crosshair axes
      ctx.beginPath();
      ctx.moveTo(centerX - maxRadius, centerY);
      ctx.lineTo(centerX + maxRadius, centerY);
      ctx.moveTo(centerX, centerY - maxRadius);
      ctx.lineTo(centerX, centerY + maxRadius);
      ctx.stroke();

      // Radar sweep cone
      sweepAngle += 0.015;
      if (sweepAngle > Math.PI * 2) sweepAngle = 0;

      const gradient = ctx.createRadialGradient(centerX, centerY, 0, centerX, centerY, maxRadius);
      gradient.addColorStop(0, 'rgba(74, 222, 128, 0.08)');
      gradient.addColorStop(1, 'transparent');

      ctx.save();
      ctx.beginPath();
      ctx.moveTo(centerX, centerY);
      ctx.arc(centerX, centerY, maxRadius, sweepAngle - 0.35, sweepAngle);
      ctx.closePath();
      ctx.fillStyle = gradient;
      ctx.fill();
      ctx.restore();

      // Floating nodes
      for (let i = 0; i < nodes.length; i++) {
        const n = nodes[i];
        n.x += n.speedX;
        n.y += n.speedY;

        if (n.x < 0) n.x = width;
        if (n.x > width) n.x = 0;
        if (n.y < 0) n.y = height;
        if (n.y > height) n.y = 0;

        ctx.beginPath();
        ctx.arc(n.x, n.y, n.size, 0, Math.PI * 2);
        ctx.fillStyle = `rgba(212, 175, 55, ${n.alpha})`;
        ctx.fill();
      }

      animationId = requestAnimationFrame(render);
    }

    animationId = requestAnimationFrame(render);

    document.addEventListener('visibilitychange', () => {
      if (document.hidden) {
        cancelAnimationFrame(animationId);
      } else {
        animationId = requestAnimationFrame(render);
      }
    });
  }

  // =====================================================================
  // 2. 3D CARD TILT ON MOUSEMOVE
  // =====================================================================
  const cardContainer = document.getElementById('errorCardContainer');
  if (cardContainer && window.innerWidth >= 900) {
    document.addEventListener('mousemove', function (e) {
      const rect = cardContainer.getBoundingClientRect();
      const centerX = rect.left + rect.width / 2;
      const centerY = rect.top + rect.height / 2;

      const deltaX = (e.clientX - centerX) / (window.innerWidth / 2);
      const deltaY = (e.clientY - centerY) / (window.innerHeight / 2);

      const rotateY = deltaX * 6;
      const rotateX = -deltaY * 6;

      cardContainer.style.transform = `perspective(1000px) rotateX(${rotateX.toFixed(2)}deg) rotateY(${rotateY.toFixed(2)}deg)`;
    });

    document.addEventListener('mouseleave', function () {
      cardContainer.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg)';
    });
  }

  // =====================================================================
  // 3. COPY INCIDENT REFERENCE ID
  // =====================================================================
  const incidentCodeEl = document.getElementById('incidentCodeVal');
  if (incidentCodeEl) {
    incidentCodeEl.style.cursor = 'pointer';
    incidentCodeEl.title = 'Klik untuk menyalin kode insiden';
    incidentCodeEl.addEventListener('click', function () {
      const textToCopy = incidentCodeEl.textContent.trim();
      navigator.clipboard.writeText(textToCopy).then(() => {
        const originalText = incidentCodeEl.textContent;
        incidentCodeEl.textContent = 'COPIED TO CLIPBOARD ✓';
        incidentCodeEl.style.color = '#4ade80';
        setTimeout(() => {
          incidentCodeEl.textContent = originalText;
          incidentCodeEl.style.color = '';
        }, 1800);
      });
    });
  }

  // =====================================================================
  // 4. BUTTON RIPPLE EFFECT
  // =====================================================================
  document.querySelectorAll('.error-btn').forEach(button => {
    button.addEventListener('click', function (e) {
      const circle = document.createElement('span');
      const diameter = Math.max(this.clientWidth, this.clientHeight);
      const radius = diameter / 2;
      const rect = this.getBoundingClientRect();

      circle.style.width = circle.style.height = `${diameter}px`;
      circle.style.left = `${e.clientX - rect.left - radius}px`;
      circle.style.top = `${e.clientY - rect.top - radius}px`;
      circle.classList.add('btn-ripple');

      const existing = this.querySelector('.btn-ripple');
      if (existing) existing.remove();

      this.appendChild(circle);
    });
  });

})();
