/**
 * phab-frontend.js
 * Lightweight helper that applies PostHog feature-flag variants
 * to Elementor A/B sections/widgets on the page.
 *
 * Strategy:
 *  1. On DOMContentLoaded, mark all [data-phab-flag] elements as hidden.
 *  2. Wait for PostHog feature flags to load.
 *  3. Reveal only the element whose data-phab-variant matches the flag value.
 *  4. If PostHog never loads (timeout), reveal the control variant.
 */
(function () {
  'use strict';

  var TIMEOUT_MS = 3000; // fallback reveal after 3 s
  var revealed   = false;

  /**
   * Hide all A/B containers until we know which variant to show.
   * Uses inline style so it takes effect before any CSS loads.
   */
  function hideContainers() {
    var els = document.querySelectorAll('[data-phab-flag]');
    els.forEach(function (el) {
      el.style.visibility = 'hidden';
      el.style.height     = '0';
      el.style.overflow   = 'hidden';
    });
  }

  /**
   * Show only the matching variant element(s) for each flag group.
   * Elements with no matching variant show the control ('control' or 'false').
   */
  function applyVariants() {
    if (revealed) return;
    revealed = true;

    // Group elements by flag key.
    var groups = {};
    var els = document.querySelectorAll('[data-phab-flag]');
    els.forEach(function (el) {
      var flag = el.getAttribute('data-phab-flag');
      if (!groups[flag]) groups[flag] = [];
      groups[flag].push(el);
    });

    Object.keys(groups).forEach(function (flag) {
      var flagValue = (window.posthog && window.posthog.getFeatureFlag)
        ? window.posthog.getFeatureFlag(flag)
        : null;

      // Normalise: false / undefined / null → 'control'
      if (!flagValue || flagValue === false) flagValue = 'control';

      groups[flag].forEach(function (el) {
        var variant = el.getAttribute('data-phab-variant') || 'control';
        if (variant === flagValue) {
          el.style.visibility = '';
          el.style.height     = '';
          el.style.overflow   = '';
        } else {
          el.style.display = 'none';
        }
      });
    });
  }

  // Safety timeout: reveal control if PostHog stalls.
  var safetyTimer = setTimeout(function () {
    applyVariants();
  }, TIMEOUT_MS);

  document.addEventListener('DOMContentLoaded', function () {
    hideContainers();

    if (window.posthog && window.posthog.onFeatureFlags) {
      window.posthog.onFeatureFlags(function () {
        clearTimeout(safetyTimer);
        applyVariants();
      });
    } else {
      // PostHog stub or not loaded — reveal immediately.
      clearTimeout(safetyTimer);
      applyVariants();
    }
  });

})();
