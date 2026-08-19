/* ================================================
   INVESTHOOD IT - Programme & Scarce Skills Platform
   JavaScript
   ================================================ */

'use strict';

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function () {

  // ============================================
  // 1. MOBILE NAVIGATION TOGGLE
  // ============================================
  const hamburger = document.getElementById('hamburger');
  const nav = document.getElementById('nav');
  const navLinks = document.querySelectorAll('.nav__link');

  // Create overlay element
  const overlay = document.createElement('div');
  overlay.className = 'nav-overlay';
  document.body.appendChild(overlay);

  function toggleNav() {
    hamburger.classList.toggle('active');
    nav.classList.toggle('active');
    overlay.classList.toggle('active');
    document.body.style.overflow = nav.classList.contains('active') ? 'hidden' : '';
  }

  function closeNav() {
    hamburger.classList.remove('active');
    nav.classList.remove('active');
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  if (hamburger) {
    hamburger.addEventListener('click', toggleNav);
  }

  overlay.addEventListener('click', closeNav);

  // Close nav when a link is clicked
  navLinks.forEach(function (link) {
    link.addEventListener('click', closeNav);
  });

  // Close nav on Escape key
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && nav.classList.contains('active')) {
      closeNav();
    }
  });

  // ============================================
  // 2. HEADER SCROLL EFFECT
  // ============================================
  const header = document.getElementById('header');
  let lastScrollY = 0;

  function handleHeaderScroll() {
    const currentScrollY = window.scrollY;

    if (currentScrollY > 50) {
      header.classList.add('scrolled');
    } else {
      header.classList.remove('scrolled');
    }

    lastScrollY = currentScrollY;
  }

  window.addEventListener('scroll', handleHeaderScroll, { passive: true });

  // ============================================
  // 3. ACTIVE NAV LINK ON SCROLL
  // ============================================
  const sections = document.querySelectorAll('section[id]');

  function highlightActiveNav() {
    let current = '';
    const scrollPos = window.scrollY + 150;

    sections.forEach(function (section) {
      const sectionTop = section.offsetTop;
      const sectionHeight = section.offsetHeight;

      if (scrollPos >= sectionTop && scrollPos < sectionTop + sectionHeight) {
        current = section.getAttribute('id');
      }
    });

    navLinks.forEach(function (link) {
      link.classList.remove('active');
      if (link.getAttribute('href') === '#' + current) {
        link.classList.add('active');
      }
    });
  }

  window.addEventListener('scroll', highlightActiveNav, { passive: true });

  // ============================================
  // 4. COUNTER ANIMATION (Intersection Observer)
  // ============================================
  const counters = document.querySelectorAll('.counter');

  function animateCounter(counter) {
    const target = parseInt(counter.getAttribute('data-target'), 10);
    const duration = 2000; // ms
    const startTime = performance.now();

    function updateCounter(currentTime) {
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / duration, 1);
      // Ease-out cubic
      const eased = 1 - Math.pow(1 - progress, 3);
      const currentValue = Math.floor(eased * target);

      counter.textContent = currentValue.toLocaleString();

      if (progress < 1) {
        requestAnimationFrame(updateCounter);
      } else {
        counter.textContent = target.toLocaleString();
      }
    }

    requestAnimationFrame(updateCounter);
  }

  // Intersection Observer for counters
  const counterObserver = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        const counter = entry.target;
        animateCounter(counter);
        counterObserver.unobserve(counter); // Only animate once
      }
    });
  }, { threshold: 0.5 });

  counters.forEach(function (counter) {
    counterObserver.observe(counter);
  });

  // ============================================
  // 5. SCROLL ANIMATIONS (Fade-in on scroll)
  // ============================================
  const animatedElements = document.querySelectorAll('.feature-card, .programme-card, .opportunity-card, .skill-card, .features__card, .testimonial-card, .talent__feature');

  // Add fade-in classes
  animatedElements.forEach(function (el) {
    el.classList.add('fade-in');
  });

  const fadeObserver = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        fadeObserver.unobserve(entry.target);
      }
    });
  }, {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
  });

  animatedElements.forEach(function (el) {
    fadeObserver.observe(el);
  });

  // Stagger animation for grid containers
  const staggerContainers = document.querySelectorAll('.about__grid, .programmes__grid, .skills__grid, .features__grid, .testimonials__grid');

  staggerContainers.forEach(function (container) {
    container.classList.add('fade-in-stagger');
  });

  const staggerObserver = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        staggerObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });

  staggerContainers.forEach(function (container) {
    staggerObserver.observe(container);
  });

  // ============================================
  // 6. OPPORTUNITIES DATA & FILTERING
  // ============================================
  const opportunitiesData = [
    {
      id: 1,
      title: 'Junior Software Developer',
      type: 'graduate',
      typeLabel: 'Graduate Programme',
      location: 'Johannesburg, Gauteng',
      province: 'gauteng',
      closingDate: '30 June 2025',
      employmentType: 'Full-time',
      qualification: 'Bachelor\'s Degree in Computer Science or related',
      qualificationLevel: 'degree',
      skills: ['software']
    },
    {
      id: 2,
      title: 'IT Support Learnership',
      type: 'learnership',
      typeLabel: 'Learnership',
      location: 'Cape Town, Western Cape',
      province: 'western-cape',
      closingDate: '15 July 2025',
      employmentType: 'Fixed-term',
      qualification: 'Grade 12 + NQF Level 4 IT qualification',
      qualificationLevel: 'diploma',
      skills: ['software', 'networking']
    },
    {
      id: 3,
      title: 'Cloud Engineering Intern',
      type: 'internship',
      typeLabel: 'Internship',
      location: 'Durban, KwaZulu-Natal',
      province: 'kwazulu-natal',
      closingDate: '31 August 2025',
      employmentType: 'Internship',
      qualification: 'Bachelor\'s Degree in IT or Engineering',
      qualificationLevel: 'degree',
      skills: ['cloud']
    },
    {
      id: 4,
      title: 'Data Analytics Graduate',
      type: 'graduate',
      typeLabel: 'Graduate Programme',
      location: 'Johannesburg, Gauteng',
      province: 'gauteng',
      closingDate: '30 June 2025',
      employmentType: 'Full-time',
      qualification: 'Honours Degree in Data Science or Statistics',
      qualificationLevel: 'honours',
      skills: ['data']
    },
    {
      id: 5,
      title: 'Cyber Security Learnership',
      type: 'learnership',
      typeLabel: 'Learnership',
      location: 'Pretoria, Gauteng',
      province: 'gauteng',
      closingDate: '15 September 2025',
      employmentType: 'Fixed-term',
      qualification: 'NQF Level 5 in Cyber Security',
      qualificationLevel: 'diploma',
      skills: ['cyber']
    },
    {
      id: 6,
      title: 'Work Integrated Learning - IT',
      type: 'wil',
      typeLabel: 'Work Integrated Learning',
      location: 'Port Elizabeth, Eastern Cape',
      province: 'eastern-cape',
      closingDate: '30 July 2025',
      employmentType: 'Contract',
      qualification: 'Currently enrolled in IT degree (3rd year)',
      qualificationLevel: 'degree',
      skills: ['software', 'cloud']
    }
  ];

  const opportunitiesGrid = document.getElementById('opportunities-grid');

  function renderOpportunities(data) {
    if (!opportunitiesGrid) return;

    if (data.length === 0) {
      opportunitiesGrid.innerHTML = '<div class="opportunities__empty">No opportunities match your filters. Try adjusting your criteria.</div>';
      return;
    }

    opportunitiesGrid.innerHTML = data.map(function (opp) {
      return `
        <div class="opportunity-card">
          <div class="opportunity-card__header">
            <h3 class="opportunity-card__title">${opp.title}</h3>
            <span class="opportunity-card__type">${opp.typeLabel}</span>
          </div>
          <div class="opportunity-card__details">
            <div class="opportunity-card__detail">
              <i class="fas fa-map-marker-alt"></i> ${opp.location}
            </div>
            <div class="opportunity-card__detail">
              <i class="fas fa-calendar-alt"></i> Closes: ${opp.closingDate}
            </div>
            <div class="opportunity-card__detail">
              <i class="fas fa-briefcase"></i> ${opp.employmentType}
            </div>
            <div class="opportunity-card__detail">
              <i class="fas fa-check-circle"></i> ${opp.typeLabel}
            </div>
          </div>
          <div class="opportunity-card__qualification">
            <i class="fas fa-graduation-cap"></i> ${opp.qualification}
          </div>
          <a href="#" class="btn btn--primary btn--sm">Apply Now <i class="fas fa-arrow-right"></i></a>
        </div>
      `;
    }).join('');
  }

  // Initial render
  renderOpportunities(opportunitiesData);

  // Filter functionality
  const filterType = document.getElementById('filter-type');
  const filterProvince = document.getElementById('filter-province');
  const filterQualification = document.getElementById('filter-qualification');
  const filterSkill = document.getElementById('filter-skill');

  function filterOpportunities() {
    const typeVal = filterType ? filterType.value : 'all';
    const provinceVal = filterProvince ? filterProvince.value : 'all';
    const qualVal = filterQualification ? filterQualification.value : 'all';
    const skillVal = filterSkill ? filterSkill.value : 'all';

    const filtered = opportunitiesData.filter(function (opp) {
      const typeMatch = typeVal === 'all' || opp.type === typeVal;
      const provinceMatch = provinceVal === 'all' || opp.province === provinceVal;
      const qualMatch = qualVal === 'all' || opp.qualificationLevel === qualVal;
      const skillMatch = skillVal === 'all' || opp.skills.indexOf(skillVal) !== -1;

      return typeMatch && provinceMatch && qualMatch && skillMatch;
    });

    renderOpportunities(filtered);
  }

  // Add event listeners to filter selects
  [filterType, filterProvince, filterQualification, filterSkill].forEach(function (select) {
    if (select) {
      select.addEventListener('change', filterOpportunities);
    }
  });

  // ============================================
  // 7. SMOOTH SCROLL FOR ANCHOR LINKS
  // ============================================
  document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
    anchor.addEventListener('click', function (e) {
      const href = anchor.getAttribute('href');
      if (href === '#') return;

      const target = document.querySelector(href);
      if (target) {
        e.preventDefault();
        const headerHeight = header.offsetHeight;
        const targetPosition = target.getBoundingClientRect().top + window.scrollY - headerHeight;

        window.scrollTo({
          top: targetPosition,
          behavior: 'smooth'
        });
      }
    });
  });

  // ============================================
  // 8. NEWSLETTER FORM SUBMISSION
  // ============================================
  const newsletterForm = document.querySelector('.footer__form');
  if (newsletterForm) {
    newsletterForm.addEventListener('submit', function (e) {
      e.preventDefault();
      const input = this.querySelector('input[type="email"]');
      if (input && input.value.trim()) {
        // Show a simple success message
        const btn = this.querySelector('button');
        const originalText = btn.textContent;
        btn.textContent = 'Subscribed!';
        btn.style.background = '#10b981';
        input.value = '';

        setTimeout(function () {
          btn.textContent = originalText;
          btn.style.background = '';
        }, 3000);
      }
    });
  }

  // ============================================
  // 9. PASSWORD TOGGLE (Show/Hide)
  // ============================================
  document.querySelectorAll('.password-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const input = this.closest('.password-input-wrapper').querySelector('.form-input');
      const icon = this.querySelector('i');
      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
      } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
      }
    });
  });

  // ============================================
  // 10. PASSWORD STRENGTH INDICATOR
  // ============================================
  function evaluatePasswordStrength(password) {
    let score = 0;
    if (password.length >= 8) score++;
    if (password.length >= 12) score++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
    if (/\d/.test(password)) score++;
    if (/[^a-zA-Z0-9]/.test(password)) score++;

    if (score <= 1) return { level: 'weak', label: 'Weak' };
    if (score <= 3) return { level: 'medium', label: 'Medium' };
    return { level: 'strong', label: 'Strong' };
  }

  document.querySelectorAll('input[type="password"]').forEach(function (input) {
    input.addEventListener('input', function () {
      const wrapper = this.closest('.password-input-wrapper');
      const strengthEl = wrapper ? wrapper.nextElementSibling : null;
      if (!strengthEl || !strengthEl.classList.contains('password-strength')) return;

      const password = this.value;
      const segments = strengthEl.querySelectorAll('.password-strength__segment');
      const textEl = strengthEl.querySelector('.password-strength__text');

      if (password.length === 0) {
        segments.forEach(function (s) { s.className = 'password-strength__segment'; });
        if (textEl) textEl.textContent = '';
        return;
      }

      const result = evaluatePasswordStrength(password);
      const activeCount = result.level === 'weak' ? 1 : result.level === 'medium' ? 3 : 5;

      segments.forEach(function (s, i) {
        s.className = 'password-strength__segment';
        if (i < activeCount) {
          s.classList.add('active', result.level);
        }
      });

      if (textEl) {
        textEl.textContent = result.label + ' password';
        textEl.className = 'password-strength__text ' + result.level;
      }
    });
  });

  // ============================================
  // 11. NOTIFICATION SYSTEM
  // ============================================
  function createNotification(type, title, message, duration) {
    duration = duration || 5000;
    var container = document.querySelector('.notification-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'notification-container';
      document.body.appendChild(container);
    }

    var icons = {
      success: 'fa-check-circle',
      error: 'fa-exclamation-circle',
      info: 'fa-info-circle',
      warning: 'fa-exclamation-triangle'
    };

    var notif = document.createElement('div');
    notif.className = 'notification notification--' + type;
    notif.innerHTML =
      '<div class="notification__icon"><i class="fas ' + (icons[type] || icons.info) + '"></i></div>' +
      '<div class="notification__content">' +
        '<div class="notification__title">' + title + '</div>' +
        '<div class="notification__message">' + message + '</div>' +
      '</div>' +
      '<button class="notification__close" aria-label="Close"><i class="fas fa-times"></i></button>';

    container.appendChild(notif);

    notif.querySelector('.notification__close').addEventListener('click', function () {
      removeNotification(notif);
    });

    if (duration > 0) {
      setTimeout(function () {
        removeNotification(notif);
      }, duration);
    }
  }

  function removeNotification(notif) {
    if (notif.classList.contains('removing')) return;
    notif.classList.add('removing');
    setTimeout(function () {
      if (notif.parentNode) notif.parentNode.removeChild(notif);
    }, 300);
  }

  window.InvesthoodNotifications = { show: createNotification };

  // ============================================
  // 12. LOGIN FORM HANDLING
  // ============================================
  var loginForm = document.getElementById('loginForm');
  if (loginForm) {
    loginForm.addEventListener('submit', function (e) {
      // Basic validation
      var loginInput = document.getElementById('login');
      var password = document.getElementById('loginPassword');
      var loginError = document.getElementById('loginError');
      var passwordError = document.getElementById('loginPasswordError');
      var isValid = true;

      // Reset errors
      if (loginError) loginError.textContent = '';
      if (passwordError) passwordError.textContent = '';
      if (loginInput) loginInput.classList.remove('form-input--error');
      if (password) password.classList.remove('form-input--error');

      // Validate identifier (email or username)
      if (!loginInput || !loginInput.value.trim()) {
        if (loginError) loginError.textContent = 'Email or username is required';
        if (loginInput) loginInput.classList.add('form-input--error');
        isValid = false;
      }

      // Validate password
      if (!password || !password.value) {
        if (passwordError) passwordError.textContent = 'Password is required';
        if (password) password.classList.add('form-input--error');
        isValid = false;
      }

      if (!isValid) {
        e.preventDefault();
        return;
      }

      // Validation passed — allow the native form submission so the
      // server actually authenticates the user (no simulated login).
    });
  }

  // ============================================
  // 13. REGISTER FORM HANDLING
  // ============================================
  var registerForm = document.getElementById('registerForm');
  if (registerForm) {
    registerForm.addEventListener('submit', function (e) {
      // Validate all required fields
      var requiredFields = this.querySelectorAll('[required]');
      var isValid = true;
      var firstError = null;

      requiredFields.forEach(function (field) {
        var errorEl = document.getElementById(field.id + 'Error');
        if (errorEl) errorEl.textContent = '';
        field.classList.remove('form-input--error');

        if (!field.value.trim()) {
          var label = field.closest('.form-group').querySelector('.form-label');
          var fieldName = label ? label.textContent.replace('*', '').trim() : 'This field';
          if (errorEl) errorEl.textContent = fieldName + ' is required';
          field.classList.add('form-input--error');
          isValid = false;
          if (!firstError) firstError = field;
        }

        // Email validation
        if (field.type === 'email' && field.value.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value.trim())) {
          if (errorEl) errorEl.textContent = 'Please enter a valid email address';
          field.classList.add('form-input--error');
          isValid = false;
          if (!firstError) firstError = field;
        }
      });

      // Validate password match
      var password = document.getElementById('regPassword');
      var confirmPassword = document.getElementById('regConfirmPassword');
      var confirmError = document.getElementById('regConfirmPasswordError');

      if (password && confirmPassword && confirmPassword.value) {
        if (password.value !== confirmPassword.value) {
          if (confirmError) confirmError.textContent = 'Passwords do not match';
          confirmPassword.classList.add('form-input--error');
          isValid = false;
          if (!firstError) firstError = confirmPassword;
        }
      }

      // Validate terms checkbox
      var termsCheckbox = document.getElementById('termsCheckbox');
      var termsError = document.getElementById('termsError');
      if (termsCheckbox && !termsCheckbox.checked) {
        if (termsError) termsError.textContent = 'You must accept the Terms and Conditions';
        isValid = false;
      } else if (termsError) {
        termsError.textContent = '';
      }

      if (!isValid) {
        e.preventDefault();
        if (firstError) firstError.focus();
        return;
      }

      // Validation passed — do NOT prevent default. The form posts to
      // auth/register.php, which creates the account server-side.
    });
  }

  // ============================================
  // 14. MODAL HANDLING
  // ============================================
  var modalOverlays = document.querySelectorAll('.modal-overlay');
  modalOverlays.forEach(function (overlay) {
    overlay.addEventListener('click', function (e) {
      if (e.target === this) {
        this.classList.remove('active');
      }
    });
  });

// Only close the modal when an explicit close control is clicked.
  // Do NOT close on every .btn inside the modal — profile action buttons
  // (e.g. "Choose Image", "Upload", "Save") have their own handlers and
  // closing the modal on click breaks flows like the profile picture upload.
  var modalCloseBtns = document.querySelectorAll('.modal__close');
  modalCloseBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var modal = this.closest('.modal-overlay');
      if (modal) modal.classList.remove('active');
    });
  });

  // ============================================
  // 15. REAL-TIME FIELD VALIDATION
  // ============================================
  document.querySelectorAll('.form-input[required]').forEach(function (input) {
    input.addEventListener('blur', function () {
      var errorEl = document.getElementById(this.id + 'Error');
      if (!errorEl) return;

      if (!this.value.trim()) {
        var label = this.closest('.form-group').querySelector('.form-label');
        var fieldName = label ? label.textContent.replace('*', '').trim() : 'This field';
        errorEl.textContent = fieldName + ' is required';
        this.classList.add('form-input--error');
        this.classList.remove('form-input--success');
      } else {
        if (this.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value.trim())) {
          errorEl.textContent = 'Please enter a valid email address';
          this.classList.add('form-input--error');
          this.classList.remove('form-input--success');
        } else {
          errorEl.textContent = '';
          this.classList.remove('form-input--error');
          this.classList.add('form-input--success');
        }
      }
    });

    input.addEventListener('input', function () {
      var errorEl = document.getElementById(this.id + 'Error');
      if (errorEl && errorEl.textContent) {
        errorEl.textContent = '';
        this.classList.remove('form-input--error');
      }
    });
  });

  // ============================================
  // 16. FORGOT PASSWORD HANDLING (placeholder)
  // ============================================
  var forgotLinks = document.querySelectorAll('.forgot-link');
  forgotLinks.forEach(function (link) {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      createNotification('info', 'Password Reset', 'Password reset functionality will be available soon. Please contact support for assistance.');
    });
  });

  // ============================================
  // 17. LOG CONSOLE MESSAGE
  // ============================================
  console.log('%c Investhood IT Platform ', 'background: #1a56db; color: white; font-size: 16px; font-weight: bold; padding: 8px 12px; border-radius: 4px;');
  console.log('%c Empowering Tomorrow\'s Talent Today. ', 'font-size: 13px; color: #64748b;');

}); // End DOMContentLoaded
