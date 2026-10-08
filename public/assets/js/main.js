/**
* Template Name: Visible
* Template URL: https://bootstrapmade.com/visible-bootstrap-agency-template/
* Updated: May 22 2025 with Bootstrap v5.3.6
* Author: BootstrapMade.com
* License: https://bootstrapmade.com/license/
*/

(function() {
  "use strict";

  /**
   * Apply .scrolled class to the body as the page is scrolled down
   */
  function toggleScrolled() {
    const selectBody = document.querySelector('body');
    const selectHeader = document.querySelector('#header');
    if (!selectHeader.classList.contains('scroll-up-sticky') && !selectHeader.classList.contains('sticky-top') && !selectHeader.classList.contains('fixed-top')) return;
    window.scrollY > 100 ? selectBody.classList.add('scrolled') : selectBody.classList.remove('scrolled');
  }

  document.addEventListener('scroll', toggleScrolled);
  window.addEventListener('load', toggleScrolled);

  /**
   * Mobile nav toggle
   */
  const mobileNavToggleBtn = document.querySelector('.mobile-nav-toggle');

  function mobileNavToogle() {
    document.querySelector('body').classList.toggle('mobile-nav-active');
    mobileNavToggleBtn.classList.toggle('bi-list');
    mobileNavToggleBtn.classList.toggle('bi-x');
  }
  if (mobileNavToggleBtn) {
    mobileNavToggleBtn.addEventListener('click', mobileNavToogle);
  }

  /**
   * Hide mobile nav on real navigation links (excluding dropdown triggers)
   */
  document.querySelectorAll('#navmenu a').forEach(navmenu => {
    navmenu.addEventListener('click', (e) => {
      const href = navmenu.getAttribute('href');
      // If it's a dropdown toggle link (href="#" or parent is .dropdown), don't close sidebar
      if (navmenu.parentElement.classList.contains('dropdown') && (href === '#' || !href)) {
        return;
      }
      if (document.querySelector('.mobile-nav-active')) {
        mobileNavToogle();
      }
    });
  });

  /**
   * Toggle mobile nav dropdowns on entire link click (Text or Icon)
   */
  document.querySelectorAll('.navmenu .dropdown > a').forEach(dropdownToggle => {
    dropdownToggle.addEventListener('click', function(e) {
      e.preventDefault();
      this.classList.toggle('active');
      const dropdownUl = this.nextElementSibling;
      if (dropdownUl) {
        dropdownUl.classList.toggle('dropdown-active');
      }
      e.stopImmediatePropagation();
    });
  });

  /**
   * Preloader
   */
  const preloader = document.querySelector('#preloader');
  if (preloader) {
    window.addEventListener('load', () => {
      preloader.remove();
    });
  }

  /**
   * Scroll top button
   */
  let scrollTop = document.querySelector('.scroll-top');

  function toggleScrollTop() {
    if (scrollTop) {
      window.scrollY > 100 ? scrollTop.classList.add('active') : scrollTop.classList.remove('active');
    }
  }
  scrollTop.addEventListener('click', (e) => {
    e.preventDefault();
    window.scrollTo({
      top: 0,
      behavior: 'smooth'
    });
  });

  window.addEventListener('load', toggleScrollTop);
  document.addEventListener('scroll', toggleScrollTop);

  /**
   * Animation on scroll function and init
   */
  function aosInit() {
    AOS.init({
      duration: 600,
      easing: 'ease-in-out',
      once: true,
      mirror: false
    });
  }
  window.addEventListener('load', aosInit);

  /**
   * Initiate Pure Counter
   */
  new PureCounter();

  /**
   * Initiate glightbox
   */
  const glightbox = GLightbox({
    selector: '.glightbox'
  });

  /**
   * Init isotope layout and filters
   */
  document.querySelectorAll('.isotope-layout').forEach(function(isotopeItem) {
    let layout = isotopeItem.getAttribute('data-layout') ?? 'masonry';
    let filter = isotopeItem.getAttribute('data-default-filter') ?? '*';
    let sort = isotopeItem.getAttribute('data-sort') ?? 'original-order';

    let initIsotope;
    imagesLoaded(isotopeItem.querySelector('.isotope-container'), function() {
      initIsotope = new Isotope(isotopeItem.querySelector('.isotope-container'), {
        itemSelector: '.isotope-item',
        layoutMode: layout,
        filter: filter,
        sortBy: sort
      });
    });

    isotopeItem.querySelectorAll('.isotope-filters li').forEach(function(filters) {
      filters.addEventListener('click', function() {
        isotopeItem.querySelector('.isotope-filters .filter-active').classList.remove('filter-active');
        this.classList.add('filter-active');
        initIsotope.arrange({
          filter: this.getAttribute('data-filter')
        });
        if (typeof aosInit === 'function') {
          aosInit();
        }
      }, false);
    });

  });

  /**
   * Frequently Asked Questions Toggle
   */
  document.querySelectorAll('.faq-item h3, .faq-item .faq-toggle, .faq-item .faq-header').forEach((faqItem) => {
    faqItem.addEventListener('click', () => {
      faqItem.parentNode.classList.toggle('faq-active');
    });
  });

  /**
   * Init swiper sliders with guaranteed autoplay
   */
  function initSwiper() {
    // 1. Generic init-swiper with swiper-config
    document.querySelectorAll(".init-swiper").forEach(function(swiperElement) {
      if (swiperElement.swiper) return; // already initialized
      try {
        const configScript = swiperElement.querySelector(".swiper-config");
        let config = {};
        if (configScript) {
          config = JSON.parse(configScript.innerHTML.trim());
        }

        if (swiperElement.classList.contains("swiper-tab")) {
          initSwiperWithCustomPagination(swiperElement, config);
        } else {
          new Swiper(swiperElement, config);
        }
      } catch (err) {
        console.error("Swiper init error:", err);
      }
    });

    // 2. Specific landscape showcase swiper fallback / direct init
    const landscapeEl = document.querySelector('.landscapeSwiper');
    if (landscapeEl && !landscapeEl.swiper && typeof Swiper !== 'undefined') {
      new Swiper(landscapeEl, {
        loop: true,
        speed: 800,
        autoplay: {
          delay: 2800,
          disableOnInteraction: false,
          pauseOnMouseEnter: true
        },
        centeredSlides: true,
        spaceBetween: 20,
        pagination: {
          el: '.swiper-pagination',
          clickable: true
        },
        navigation: {
          nextEl: '.swiper-button-next',
          prevEl: '.swiper-button-prev'
        },
        breakpoints: {
          320: {
            slidesPerView: 1.15,
            spaceBetween: 12
          },
          768: {
            slidesPerView: 1.4,
            spaceBetween: 18
          },
          1200: {
            slidesPerView: 1.8,
            spaceBetween: 24
          }
        }
      });
    }
  }

  // Run on both DOMContentLoaded and load to prevent delays
  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    setTimeout(initSwiper, 50);
  } else {
    document.addEventListener("DOMContentLoaded", initSwiper);
  }
  window.addEventListener("load", initSwiper);

  /**
   * Correct scrolling position upon page load for URLs containing hash links.
   */
  window.addEventListener('load', function(e) {
    if (window.location.hash) {
      if (document.querySelector(window.location.hash)) {
        setTimeout(() => {
          let section = document.querySelector(window.location.hash);
          let scrollMarginTop = getComputedStyle(section).scrollMarginTop;
          window.scrollTo({
            top: section.offsetTop - parseInt(scrollMarginTop),
            behavior: 'smooth'
          });
        }, 100);
      }
    }
  });

  /**
   * Navmenu Scrollspy
   */
  let navmenulinks = document.querySelectorAll('.navmenu a');

  function navmenuScrollspy() {
    navmenulinks.forEach(navmenulink => {
      if (!navmenulink.hash) return;
      let section = document.querySelector(navmenulink.hash);
      if (!section) return;
      let position = window.scrollY + 200;
      if (position >= section.offsetTop && position <= (section.offsetTop + section.offsetHeight)) {
        document.querySelectorAll('.navmenu a.active').forEach(link => link.classList.remove('active'));
        navmenulink.classList.add('active');
      } else {
        navmenulink.classList.remove('active');
      }
    })
  }
  window.addEventListener('load', navmenuScrollspy);
  document.addEventListener('scroll', navmenuScrollspy);

})();

  /**
   * Universal Interactive Number Counting Animation
   */
  function initNumberCounters() {
    const counterElements = document.querySelectorAll('.purecounter, [data-counter]');

    const animateCounter = (el) => {
      if (el.dataset.animated === 'true') return;
      el.dataset.animated = 'true';

      const start = parseFloat(el.getAttribute('data-start') || el.getAttribute('data-purecounter-start') || '0');
      const end = parseFloat(el.getAttribute('data-end') || el.getAttribute('data-purecounter-end') || el.textContent.replace(/[^0-9.]/g, ''));
      const duration = parseFloat(el.getAttribute('data-duration') || el.getAttribute('data-purecounter-duration') || '1.6') * 1000;
      const prefix = el.getAttribute('data-prefix') || '';
      const suffix = el.getAttribute('data-suffix') || (el.textContent.includes('%') ? '%' : (el.textContent.includes('s') ? 's' : (el.textContent.includes('+') ? '+' : '')));
      const decimals = el.getAttribute('data-decimals') ? parseInt(el.getAttribute('data-decimals')) : (end % 1 !== 0 ? 1 : 0);

      const startTime = performance.now();

      const updateCount = (currentTime) => {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        // Ease-out cubic
        const easeOut = 1 - Math.pow(1 - progress, 3);
        const currentVal = start + (end - start) * easeOut;

        el.textContent = `${prefix}${decimals > 0 ? currentVal.toFixed(decimals) : Math.floor(currentVal)}${suffix}`;

        if (progress < 1) {
          requestAnimationFrame(updateCount);
        } else {
          el.textContent = `${prefix}${decimals > 0 ? end.toFixed(decimals) : end}${suffix}`;
        }
      };

      requestAnimationFrame(updateCount);
    };

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          animateCounter(entry.target);
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.15 });

    counterElements.forEach(el => observer.observe(el));
  }

  window.addEventListener('DOMContentLoaded', initNumberCounters);
  window.addEventListener('load', initNumberCounters);
