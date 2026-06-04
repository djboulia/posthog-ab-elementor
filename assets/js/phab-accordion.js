/**
 * phab-accordion.js
 *
 * Frontend handler for the PostHog Accordion widget.
 * Adapted from Elementor's NestedAccordion handler (modules/nested-accordion).
 *
 * Uses the Web Animations API to animate <details> open/close.
 * Registers with elementorFrontend.hooks so it works in the editor preview
 * and re-initialises correctly after Elementor re-renders a widget.
 */
( function () {
	'use strict';

	/**
	 * Map of ongoing animations keyed by <details> element.
	 * @type {Map<HTMLElement, Animation>}
	 */
	var animations = new Map();

	// ── Public initialiser ──────────────────────────────────────────────────

	/**
	 * Initialise all accordions inside a given root element.
	 * @param {HTMLElement} root
	 */
	function init( root ) {
		root.querySelectorAll( '.phab-n-accordion' ).forEach( function ( accordion ) {
			initAccordion( accordion );
		} );
	}

	// ── Per-accordion setup ─────────────────────────────────────────────────

	function initAccordion( accordion ) {
		var summaries = accordion.querySelectorAll(
			':scope > .phab-n-accordion-item > .phab-n-accordion-item-title'
		);

		summaries.forEach( function ( summary ) {
			summary.addEventListener( 'click', onSummaryClick );
		} );

		// Keyboard handler (mirrors Elementor's NestedAccordionTitleKeyboardHandler).
		accordion.addEventListener( 'keydown', onKeyDown );
	}

	// ── Click handler ───────────────────────────────────────────────────────

	function onSummaryClick( event ) {
		event.preventDefault();

		var summary      = event.currentTarget;
		var accordionItem = summary.closest( '.phab-n-accordion-item' );
		var accordion     = summary.closest( '.phab-n-accordion' );
		var content       = accordionItem.querySelector( ':scope > .e-con' );
		var maxExpanded   = getElementSetting( accordion, 'max_items_expended' ) || 'one';

		if ( 'one' === maxExpanded ) {
			closeAllItems( accordion, accordionItem );
		}

		if ( accordionItem.open ) {
			closeItem( accordionItem, summary );
		} else {
			openItem( accordionItem, summary, content );
		}
	}

	// ── Open / Close ────────────────────────────────────────────────────────

	function openItem( accordionItem, summary, content ) {
		// Snapshot current (collapsed) height before opening.
		accordionItem.style.overflow = 'hidden';
		accordionItem.style.height   = accordionItem.offsetHeight + 'px';
		accordionItem.open = true;

		// After the browser has laid out the open state, animate to full height.
		window.requestAnimationFrame( function () {
			var targetHeight = summary.offsetHeight + ( content ? content.offsetHeight : 0 );
			animateItem( accordionItem, accordionItem.offsetHeight + 'px', targetHeight + 'px', true );
		} );
	}

	function closeItem( accordionItem, summary ) {
		var startHeight = accordionItem.offsetHeight + 'px';
		var endHeight   = summary.offsetHeight + 'px';
		animateItem( accordionItem, startHeight, endHeight, false );
	}

	function closeAllItems( accordion, except ) {
		accordion.querySelectorAll( ':scope > .phab-n-accordion-item[open]' ).forEach( function ( item ) {
			if ( item === except ) return;
			var summary = item.querySelector( '.phab-n-accordion-item-title' );
			closeItem( item, summary );
		} );
	}

	// ── Animation ───────────────────────────────────────────────────────────

	function animateItem( accordionItem, startHeight, endHeight, isOpen ) {
		var existing = animations.get( accordionItem );
		if ( existing ) {
			existing.cancel();
		}

		var duration = getAnimationDuration( accordionItem );
		var animation = accordionItem.animate(
			{ height: [ startHeight, endHeight ] },
			{ duration: duration, easing: 'ease' }
		);

		animations.set( accordionItem, animation );

		animation.onfinish = function () {
			onAnimationFinish( accordionItem, isOpen );
		};

		// Update aria-expanded on the summary.
		var summary = accordionItem.querySelector( '.phab-n-accordion-item-title' );
		if ( summary ) {
			summary.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
		}
	}

	function onAnimationFinish( accordionItem, isOpen ) {
		accordionItem.open            = isOpen;
		accordionItem.style.height    = '';
		accordionItem.style.overflow  = '';
		animations.set( accordionItem, null );
	}

	// ── Keyboard handler ────────────────────────────────────────────────────

	function onKeyDown( event ) {
		var accordion = event.currentTarget;
		var target    = event.target;

		if ( ! target.classList.contains( 'phab-n-accordion-item-title' ) ) {
			return;
		}

		var items = Array.from(
			accordion.querySelectorAll( ':scope > .phab-n-accordion-item > .phab-n-accordion-item-title' )
		);
		var currentIndex = items.indexOf( target );

		switch ( event.key ) {
			case 'ArrowDown':
				event.preventDefault();
				if ( currentIndex < items.length - 1 ) {
					items[ currentIndex + 1 ].focus();
				}
				break;
			case 'ArrowUp':
				event.preventDefault();
				if ( currentIndex > 0 ) {
					items[ currentIndex - 1 ].focus();
				}
				break;
			case 'Home':
				event.preventDefault();
				items[ 0 ].focus();
				break;
			case 'End':
				event.preventDefault();
				items[ items.length - 1 ].focus();
				break;
			case 'Enter':
			case ' ':
				event.preventDefault();
				target.click();
				break;
			case 'Escape':
				event.preventDefault();
				var openItem = accordion.querySelector( ':scope > .phab-n-accordion-item[open] > .phab-n-accordion-item-title' );
				if ( openItem ) {
					openItem.click();
				}
				break;
		}
	}

	// ── Settings helpers ────────────────────────────────────────────────────

	/**
	 * Read a frontend_available setting from the widget's data attribute.
	 * Elementor serialises these as data-settings on the .elementor-widget element.
	 */
	function getElementSetting( accordion, key ) {
		var widget = accordion.closest( '.elementor-widget' );
		if ( ! widget ) return null;
		try {
			var settings = JSON.parse( widget.dataset.settings || '{}' );
			return settings[ key ] || null;
		} catch ( e ) {
			return null;
		}
	}

	function getAnimationDuration( accordionItem ) {
		var accordion = accordionItem.closest( '.phab-n-accordion' );
		var setting   = getElementSetting( accordion, 'n_accordion_animation_duration' );
		if ( ! setting ) return 400;
		var size = setting.size || 400;
		var unit = setting.unit || 'ms';
		return 'ms' === unit ? size : size * 1000;
	}

	// ── Bootstrap ──────────────────────────────────────────────────────────

	if ( window.elementorFrontend ) {
		window.elementorFrontend.hooks.addAction(
			'frontend/element_ready/phab_accordion.default',
			function ( $element ) {
				init( $element[ 0 ] );
			}
		);
	} else {
		document.addEventListener( 'DOMContentLoaded', function () {
			init( document.body );
		} );
	}

} )();
