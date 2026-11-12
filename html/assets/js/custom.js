/**
 * Christmas Tree Farm - Custom JavaScript & Owl Carousel Initializations
 */

$(document).ready(function () {
  'use strict';

  // ==========================================================================
  // Initialize AOS (Animate On Scroll)
  // ==========================================================================
  if (typeof AOS !== 'undefined') {
    AOS.init({
      duration: 800,
      easing: 'ease-out-cubic',
      once: true,
      offset: 50,
      delay: 50
    });
  }

  // ==========================================================================
  // Sticky Navigation Header
  // ==========================================================================
  const $header = $('.site-header');
  const headerHeight = $header.outerHeight() || 80;

  $(window).on('scroll', function () {
    if ($(this).scrollTop() > headerHeight) {
      $header.addClass('is-sticky');
    } else {
      $header.removeClass('is-sticky');
    }
  });

  // ==========================================================================
  // Testimonials Carousel (Home Page)
  // ==========================================================================
  if ($('.testimonial-carousel').length > 0) {
    const $testimonialSlider = $('.testimonial-carousel');

    $testimonialSlider.owlCarousel({
      loop: true,
      margin: 24,
      nav: false,
      dots: true,
      autoplay: true,
      autoplayTimeout: 6000,
      autoplayHoverPause: true,
      smartSpeed: 700,
      responsive: {
        0: {
          items: 1
        },
        768: {
          items: 2
        },
        1200: {
          items: 3
        }
      }
    });

    // Custom Navigation Arrows
    $('.testimonial-prev').on('click', function () {
      $testimonialSlider.trigger('prev.owl.carousel');
    });

    $('.testimonial-next').on('click', function () {
      $testimonialSlider.trigger('next.owl.carousel');
    });
  }

  // ==========================================================================
  // Moments from the Farm Carousel (Inner Pages)
  // ==========================================================================
  if ($('.moments-carousel').length > 0) {
    const $momentsSlider = $('.moments-carousel');

    $momentsSlider.owlCarousel({
      loop: true,
      margin: 20,
      nav: false,
      dots: false,
      autoplay: true,
      autoplayTimeout: 6000,
      autoplayHoverPause: true,
      smartSpeed: 600,
      responsive: {
        0: {
          items: 1
        },
        576: {
          items: 2
        },
        992: {
          items: 3
        },
        1200: {
          items: 4
        }
      }
    });

    // Custom Arrows
    $('.moments-prev').on('click', function () {
      $momentsSlider.trigger('prev.owl.carousel');
    });

    $('.moments-next').on('click', function () {
      $momentsSlider.trigger('next.owl.carousel');
    });
  }

  // ==========================================================================
  // Events Filter Functionality (events.html)
  // ==========================================================================
  $('.filter-btn').on('click', function () {
    $('.filter-btn').removeClass('active');
    $(this).addClass('active');

    const filterVal = $(this).attr('data-filter');

    if (filterVal === 'all') {
      $('.event-card-item').fadeIn(300);
    } else {
      $('.event-card-item').hide();
      $('.event-card-item[data-category="' + filterVal + '"]').fadeIn(300);
    }
  });

  // Search input filter for events
  $('#eventSearchInput').on('keyup', function () {
    const query = $(this).val().toLowerCase();
    $('.event-card-item').each(function () {
      const title = $(this).find('.event-title').text().toLowerCase();
      const desc = $(this).find('.event-desc, .event-body p').text().toLowerCase();
      if (title.indexOf(query) > -1 || desc.indexOf(query) > -1) {
        $(this).fadeIn(200);
      } else {
        $(this).fadeOut(200);
      }
    });
  });

  // ==========================================================================
  // Blog Filter & Search Functionality (blog.html)
  // ==========================================================================
  $('.blog-filter-btn').on('click', function () {
    $('.blog-filter-btn').removeClass('active');
    $(this).addClass('active');

    const filterVal = $(this).attr('data-filter');

    if (filterVal === 'all') {
      $('.blog-card-item').fadeIn(300);
    } else {
      $('.blog-card-item').hide();
      $('.blog-card-item[data-category="' + filterVal + '"]').fadeIn(300);
    }
  });

  // Search input filter for blog
  $('#blogSearchInput, #sidebarBlogSearch').on('keyup', function () {
    const query = $(this).val().toLowerCase();
    $('.blog-card-item').each(function () {
      const title = $(this).find('.blog-card-title').text().toLowerCase();
      const desc = $(this).find('.blog-card-body p').text().toLowerCase();
      if (title.indexOf(query) > -1 || desc.indexOf(query) > -1) {
        $(this).fadeIn(200);
      } else {
        $(this).fadeOut(200);
      }
    });
  });

  // ==========================================================================
  // Smooth Scroll for Internal Anchors
  // ==========================================================================
  $('a[href^="#"]').on('click', function (e) {
    const target = $(this.getAttribute('href'));
    if (target.length) {
      e.preventDefault();
      $('html, body').stop().animate({
        scrollTop: target.offset().top - 90
      }, 600);
    }
  });

  // ==========================================================================
  // Form Submission Handlers with User Feedback
  // ==========================================================================
  $('#newsletterForm').on('submit', function (e) {
    e.preventDefault();
    const email = $(this).find('input[type="email"]').val();
    if (email) {
      const $btn = $(this).find('button');
      $btn.text('SUBSCRIBED!').css('background-color', '#2E7D32');
      setTimeout(function () {
        $btn.text('SUBSCRIBE').removeAttr('style');
        $('#newsletterForm')[0].reset();
      }, 3000);
    }
  });

  $('#contactForm').on('submit', function (e) {
    e.preventDefault();
    const $submitBtn = $(this).find('button[type="submit"]');
    $submitBtn.html('MESSAGE SENT! <i class="bi bi-check-circle-fill"></i>').css('background-color', '#2E7D32');
    setTimeout(function () {
      $submitBtn.html('SEND MESSAGE <i class="bi bi-arrow-right"></i>').removeAttr('style');
      $('#contactForm')[0].reset();
    }, 3500);
  });

  $('#commentForm').on('submit', function (e) {
    e.preventDefault();
    const $submitBtn = $(this).find('button[type="submit"]');
    $submitBtn.html('COMMENT POSTED! <i class="bi bi-check-circle-fill"></i>').css('background-color', '#2E7D32');
    setTimeout(function () {
      $submitBtn.html('POST COMMENT <i class="bi bi-arrow-right"></i>').removeAttr('style');
      $('#commentForm')[0].reset();
    }, 3500);
  });

  // ==========================================================================
  // Monterey Pine Background Video Autoplay Assurance
  // ==========================================================================
  const $bgVideo = $('.monterey-video-bg');
  if ($bgVideo.length > 0) {
    const videoEl = $bgVideo[0];
    videoEl.muted = true;
    const playPromise = videoEl.play();
    if (playPromise !== undefined) {
      playPromise.catch(function () {
        // Autoplay prevented by browser; resume on first user interaction
        $(document).one('click scroll touchstart', function () {
          videoEl.play();
        });
      });
    }
  }

  // Refresh AOS on window load to ensure accurate element offset calculations
  $(window).on('load', function () {
    if (typeof AOS !== 'undefined') {
      AOS.refresh();
    }
  });

});

