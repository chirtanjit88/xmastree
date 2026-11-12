( function () {
	'use strict';

	var controllers = typeof WeakMap === 'function' ? new WeakMap() : null;
	var timers = typeof WeakMap === 'function' ? new WeakMap() : null;

	function stripAos( container ) {
		if ( ! container ) {
			return;
		}
		var nodes = container.querySelectorAll( '[data-aos]' );
		for ( var i = 0; i < nodes.length; i++ ) {
			nodes[ i ].removeAttribute( 'data-aos' );
			nodes[ i ].removeAttribute( 'data-aos-duration' );
			nodes[ i ].removeAttribute( 'data-aos-delay' );
		}
	}

	function syncSearchInputs( root, value ) {
		var inputs = root.querySelectorAll( '[data-events-search-input]' );
		for ( var i = 0; i < inputs.length; i++ ) {
			if ( document.activeElement !== inputs[ i ] ) {
				inputs[ i ].value = value;
			}
		}

		var clearBtns = root.querySelectorAll( '[data-events-search-clear]' );
		for ( var j = 0; j < clearBtns.length; j++ ) {
			clearBtns[ j ].hidden = '' === value;
		}
	}

	function syncHiddenInputs( root, activeCat, activeDate ) {
		var forms = root.querySelectorAll( '[data-events-search-form]' );
		for ( var i = 0; i < forms.length; i++ ) {
			syncHiddenField( forms[ i ], 'event_cat', '[data-events-hidden-cat]', 'data-events-hidden-cat', activeCat );
			syncHiddenField( forms[ i ], 'event_date', '[data-events-hidden-date]', 'data-events-hidden-date', activeDate );
		}
	}

	function syncHiddenField( form, name, selector, attr, value ) {
		var existing = form.querySelector( selector );
		if ( value ) {
			if ( ! existing ) {
				existing = document.createElement( 'input' );
				existing.type = 'hidden';
				existing.name = name;
				existing.setAttribute( attr, '' );
				form.appendChild( existing );
			}
			existing.value = value;
		} else if ( existing ) {
			existing.parentNode.removeChild( existing );
		}
	}

	function buildEventsUrl( root, form, queryText, dateOverride ) {
		var rawBase = root.getAttribute( 'data-base-url' ) || ( form ? form.getAttribute( 'action' ) : '' ) || window.location.href;
		var url;
		try {
			url = new URL( rawBase, window.location.href );
		} catch ( err ) {
			return '';
		}

		if ( form ) {
			var hiddens = form.querySelectorAll( 'input[type="hidden"]' );
			for ( var i = 0; i < hiddens.length; i++ ) {
				var name = hiddens[ i ].name;
				if ( name && 'event_cat' !== name && 'event_s' !== name && 'event_date' !== name && 'event_page' !== name ) {
					url.searchParams.set( name, hiddens[ i ].value );
				}
			}
		}

		var activeCat = root.getAttribute( 'data-active-cat' ) || '';
		if ( activeCat ) {
			url.searchParams.set( 'event_cat', activeCat );
		} else {
			url.searchParams.delete( 'event_cat' );
		}

		var activeDate = typeof dateOverride === 'string' ? dateOverride : ( root.getAttribute( 'data-active-date' ) || '' );
		if ( activeDate ) {
			url.searchParams.set( 'event_date', activeDate );
		} else {
			url.searchParams.delete( 'event_date' );
		}

		url.searchParams.delete( 'event_page' );

		var trimmed = ( queryText || '' ).trim();
		if ( trimmed ) {
			url.searchParams.set( 'event_s', trimmed );
		} else {
			url.searchParams.delete( 'event_s' );
		}

		return url.toString();
	}

	function swapSlot( root, fetchedRoot, selector, cleanAos ) {
		var currentSlot = root.querySelector( selector );
		var nextSlot = fetchedRoot.querySelector( selector );

		if ( currentSlot && nextSlot ) {
			currentSlot.innerHTML = nextSlot.innerHTML;
			if ( cleanAos ) {
				stripAos( currentSlot );
			}
		} else if ( currentSlot && ! nextSlot ) {
			currentSlot.innerHTML = '';
		}
	}

	function scrollToResultsTop( root ) {
		var header = document.querySelector( '.site-header' );
		var headerOffset = header ? header.offsetHeight : 0;
		var top = root.getBoundingClientRect().top + window.pageYOffset - headerOffset - 20;

		window.scrollTo( {
			top: Math.max( 0, top ),
			behavior: 'smooth'
		} );
	}

	function loadEventsUrl( root, targetUrl, options ) {
		if ( ! root || ! targetUrl ) {
			return;
		}

		var opts = options || {};
		var resultsWrap = root.querySelector( '[data-events-results]' );

		if ( typeof window.fetch !== 'function' || typeof window.DOMParser !== 'function' ) {
			window.location.href = targetUrl;
			return;
		}

		if ( controllers && controllers.has( root ) ) {
			try {
				controllers.get( root ).abort();
			} catch ( err ) {}
		}

		var controller = typeof window.AbortController === 'function' ? new window.AbortController() : null;
		if ( controllers && controller ) {
			controllers.set( root, controller );
		}

		if ( resultsWrap ) {
			resultsWrap.classList.add( 'is-loading' );
			resultsWrap.setAttribute( 'aria-busy', 'true' );
		}

		window.fetch( targetUrl, {
			method: 'GET',
			credentials: 'same-origin',
			signal: controller ? controller.signal : undefined,
			headers: {
				'X-Requested-With': 'XMLHttpRequest'
			}
		} )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'HTTP ' + response.status );
				}
				return response.text();
			} )
			.then( function ( html ) {
				var parser = new window.DOMParser();
				var doc = parser.parseFromString( html, 'text/html' );
				var fetchedRoot = root.id ? doc.getElementById( root.id ) : null;
				if ( ! fetchedRoot ) {
					fetchedRoot = doc.querySelector( '[data-events-dynamic="1"]' );
				}

				if ( ! fetchedRoot ) {
					window.location.href = targetUrl;
					return;
				}

				var nextCat = fetchedRoot.getAttribute( 'data-active-cat' ) || '';
				var nextSearch = fetchedRoot.getAttribute( 'data-active-search' ) || '';
				var nextDate = fetchedRoot.getAttribute( 'data-active-date' ) || '';

				root.setAttribute( 'data-active-cat', nextCat );
				root.setAttribute( 'data-active-search', nextSearch );
				root.setAttribute( 'data-active-date', nextDate );

				swapSlot( root, fetchedRoot, '[data-events-filter-controls]', false );
				swapSlot( root, fetchedRoot, '[data-events-results]', true );

				syncHiddenInputs( root, nextCat, nextDate );
				syncSearchInputs( root, nextSearch );

				if ( opts.pushState && window.history && typeof window.history.pushState === 'function' ) {
					window.history.pushState( { xmastreeEventsUrl: targetUrl }, '', targetUrl );
				}

				if ( opts.scrollTo ) {
					scrollToResultsTop( root );
				}

				if ( window.AOS && typeof window.AOS.refresh === 'function' ) {
					window.AOS.refresh();
				}
			} )
			.catch( function ( err ) {
				if ( err && 'AbortError' === err.name ) {
					return;
				}
				window.location.href = targetUrl;
			} )
			.finally( function () {
				var currentResults = root.querySelector( '[data-events-results]' );
				if ( currentResults ) {
					currentResults.classList.remove( 'is-loading' );
					currentResults.removeAttribute( 'aria-busy' );
				}
			} );
	}

	document.addEventListener( 'click', function ( e ) {
		if ( e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || 0 !== e.button ) {
			return;
		}

		var target = e.target;
		if ( ! target || typeof target.closest !== 'function' ) {
			return;
		}

		var dateTrigger = target.closest( '[data-events-date-trigger]' );
		if ( dateTrigger ) {
			var dateWrap = dateTrigger.closest( '.event-date-picker-wrap' );
			var dateInput = dateWrap ? dateWrap.querySelector( '[data-events-date-input]' ) : null;
			if ( dateInput ) {
				if ( typeof dateInput.showPicker === 'function' ) {
					try {
						dateInput.showPicker();
						return;
					} catch ( err ) {}
				}
				dateInput.focus();
				dateInput.click();
			}
			return;
		}

		var clearBtn = target.closest( '[data-events-search-clear]' );
		if ( clearBtn ) {
			var clearRoot = clearBtn.closest( '[data-events-dynamic="1"]' );
			if ( clearRoot ) {
				e.preventDefault();
				var allInputs = clearRoot.querySelectorAll( '[data-events-search-input]' );
				for ( var i = 0; i < allInputs.length; i++ ) {
					allInputs[ i ].value = '';
				}
				syncSearchInputs( clearRoot, '' );
				var clearedUrl = buildEventsUrl( clearRoot, clearBtn.closest( 'form' ), '' );
				loadEventsUrl( clearRoot, clearedUrl, { pushState: true } );
			}
			return;
		}

		var navLink = target.closest(
			'[data-events-dynamic="1"] [data-events-filter-link],' +
			'[data-events-dynamic="1"] [data-events-page-link],' +
			'[data-events-dynamic="1"] [data-events-reset-link]'
		);
		if ( ! navLink ) {
			return;
		}

		var root = navLink.closest( '[data-events-dynamic="1"]' );
		var href = navLink.getAttribute( 'href' );
		if ( ! root || ! href || '#' === href ) {
			return;
		}

		e.preventDefault();

		var shouldScroll = navLink.hasAttribute( 'data-events-page-link' );
		loadEventsUrl( root, navLink.href, {
			pushState: true,
			scrollTo: shouldScroll
		} );
	} );

	document.addEventListener( 'change', function ( e ) {
		var dateInput = e.target;
		if ( ! dateInput || typeof dateInput.matches !== 'function' || ! dateInput.matches( '[data-events-date-input]' ) ) {
			return;
		}

		var root = dateInput.closest( '[data-events-dynamic="1"]' );
		if ( ! root ) {
			return;
		}

		var searchInput = root.querySelector( '[data-events-search-input]' );
		var form = root.querySelector( '[data-events-search-form]' );
		var queryText = searchInput ? searchInput.value : ( root.getAttribute( 'data-active-search' ) || '' );
		var nextUrl = buildEventsUrl( root, form, queryText, dateInput.value || '' );
		loadEventsUrl( root, nextUrl, { pushState: true } );
	} );

	document.addEventListener( 'submit', function ( e ) {
		var form = e.target;
		if ( ! form || typeof form.matches !== 'function' || ! form.matches( '[data-events-search-form]' ) ) {
			return;
		}

		var root = form.closest( '[data-events-dynamic="1"]' );
		if ( ! root ) {
			return;
		}

		e.preventDefault();

		if ( timers && timers.has( root ) ) {
			window.clearTimeout( timers.get( root ) );
		}

		var input = form.querySelector( '[data-events-search-input]' );
		var query = input ? input.value : '';
		var nextUrl = buildEventsUrl( root, form, query );
		loadEventsUrl( root, nextUrl, { pushState: true } );
	} );

	document.addEventListener( 'input', function ( e ) {
		var input = e.target;
		if ( ! input || typeof input.matches !== 'function' || ! input.matches( '[data-events-search-input]' ) ) {
			return;
		}

		var root = input.closest( '[data-events-dynamic="1"]' );
		if ( ! root ) {
			return;
		}

		var val = input.value;
		syncSearchInputs( root, val );

		if ( timers && timers.has( root ) ) {
			window.clearTimeout( timers.get( root ) );
		}

		var form = input.closest( '[data-events-search-form]' );
		var timerId = window.setTimeout( function () {
			var nextUrl = buildEventsUrl( root, form, input.value );
			loadEventsUrl( root, nextUrl, { pushState: true } );
		}, 350 );

		if ( timers ) {
			timers.set( root, timerId );
		}
	} );

	window.addEventListener( 'popstate', function () {
		var blocks = document.querySelectorAll( '[data-events-dynamic="1"]' );
		for ( var i = 0; i < blocks.length; i++ ) {
			loadEventsUrl( blocks[ i ], window.location.href, { pushState: false } );
		}
	} );
}() );

