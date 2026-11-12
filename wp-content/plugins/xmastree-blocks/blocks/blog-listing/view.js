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
		var inputs = root.querySelectorAll( '[data-blog-search-input]' );
		for ( var i = 0; i < inputs.length; i++ ) {
			if ( document.activeElement !== inputs[ i ] ) {
				inputs[ i ].value = value;
			}
		}

		var clearBtns = root.querySelectorAll( '[data-blog-search-clear]' );
		for ( var j = 0; j < clearBtns.length; j++ ) {
			clearBtns[ j ].hidden = '' === value;
		}
	}

	function syncHiddenCatInputs( root, activeCat ) {
		var forms = root.querySelectorAll( '[data-blog-search-form]' );
		for ( var i = 0; i < forms.length; i++ ) {
			var existing = forms[ i ].querySelector( '[data-blog-hidden-cat]' );
			if ( activeCat ) {
				if ( ! existing ) {
					existing = document.createElement( 'input' );
					existing.type = 'hidden';
					existing.name = 'blog_cat';
					existing.setAttribute( 'data-blog-hidden-cat', '' );
					forms[ i ].appendChild( existing );
				}
				existing.value = activeCat;
			} else if ( existing ) {
				existing.parentNode.removeChild( existing );
			}
		}
	}

	function buildSearchUrl( root, form, queryText ) {
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
				if ( name && 'blog_cat' !== name && 'blog_tag' !== name && 'blog_s' !== name && 'blog_page' !== name ) {
					url.searchParams.set( name, hiddens[ i ].value );
				}
			}
		}

		var activeCat = root.getAttribute( 'data-active-cat' ) || '';
		if ( activeCat ) {
			url.searchParams.set( 'blog_cat', activeCat );
		} else {
			url.searchParams.delete( 'blog_cat' );
		}

		url.searchParams.delete( 'blog_tag' );
		url.searchParams.delete( 'blog_page' );

		var trimmed = ( queryText || '' ).trim();
		if ( trimmed ) {
			url.searchParams.set( 'blog_s', trimmed );
		} else {
			url.searchParams.delete( 'blog_s' );
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
		var mainSection = root.querySelector( '.blog-main-section' ) || root;
		var header = document.querySelector( '.site-header' );
		var headerOffset = header ? header.offsetHeight : 0;
		var top = mainSection.getBoundingClientRect().top + window.pageYOffset - headerOffset - 20;

		window.scrollTo( {
			top: Math.max( 0, top ),
			behavior: 'smooth'
		} );
	}

	function loadBlogUrl( root, targetUrl, options ) {
		if ( ! root || ! targetUrl ) {
			return;
		}

		var opts = options || {};
		var resultsWrap = root.querySelector( '[data-blog-results]' );

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
					fetchedRoot = doc.querySelector( '[data-blog-dynamic="1"]' );
				}

				if ( ! fetchedRoot ) {
					window.location.href = targetUrl;
					return;
				}

				var nextCat = fetchedRoot.getAttribute( 'data-active-cat' ) || '';
				var nextTag = fetchedRoot.getAttribute( 'data-active-tag' ) || '';
				var nextSearch = fetchedRoot.getAttribute( 'data-active-search' ) || '';

				root.setAttribute( 'data-active-cat', nextCat );
				root.setAttribute( 'data-active-tag', nextTag );
				root.setAttribute( 'data-active-search', nextSearch );

				swapSlot( root, fetchedRoot, '[data-blog-featured]', true );
				swapSlot( root, fetchedRoot, '[data-blog-tabs]', false );
				swapSlot( root, fetchedRoot, '[data-blog-results]', true );
				swapSlot( root, fetchedRoot, '[data-blog-sidebar-cats]', false );
				swapSlot( root, fetchedRoot, '[data-blog-sidebar-tags]', false );

				syncHiddenCatInputs( root, nextCat );
				syncSearchInputs( root, nextSearch );

				if ( opts.pushState && window.history && typeof window.history.pushState === 'function' ) {
					window.history.pushState( { xmastreeBlogUrl: targetUrl }, '', targetUrl );
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
				var currentResults = root.querySelector( '[data-blog-results]' );
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

		var clearBtn = target.closest( '[data-blog-search-clear]' );
		if ( clearBtn ) {
			var clearRoot = clearBtn.closest( '[data-blog-dynamic="1"]' );
			if ( clearRoot ) {
				e.preventDefault();
				var allInputs = clearRoot.querySelectorAll( '[data-blog-search-input]' );
				for ( var i = 0; i < allInputs.length; i++ ) {
					allInputs[ i ].value = '';
				}
				syncSearchInputs( clearRoot, '' );
				var clearedUrl = buildSearchUrl( clearRoot, clearBtn.closest( 'form' ), '' );
				loadBlogUrl( clearRoot, clearedUrl, { pushState: true } );
			}
			return;
		}

		var navLink = target.closest(
			'[data-blog-dynamic="1"] [data-blog-filter-link],' +
			'[data-blog-dynamic="1"] [data-blog-tax-link],' +
			'[data-blog-dynamic="1"] [data-blog-page-link],' +
			'[data-blog-dynamic="1"] [data-blog-reset-link]'
		);
		if ( ! navLink ) {
			return;
		}

		var root = navLink.closest( '[data-blog-dynamic="1"]' );
		var href = navLink.getAttribute( 'href' );
		if ( ! root || ! href || '#' === href ) {
			return;
		}

		e.preventDefault();

		var shouldScroll = navLink.hasAttribute( 'data-blog-page-link' ) || navLink.hasAttribute( 'data-blog-tax-link' );
		loadBlogUrl( root, navLink.href, {
			pushState: true,
			scrollTo: shouldScroll
		} );
	} );

	document.addEventListener( 'submit', function ( e ) {
		var form = e.target;
		if ( ! form || typeof form.matches !== 'function' || ! form.matches( '[data-blog-search-form]' ) ) {
			return;
		}

		var root = form.closest( '[data-blog-dynamic="1"]' );
		if ( ! root ) {
			return;
		}

		e.preventDefault();

		if ( timers && timers.has( root ) ) {
			window.clearTimeout( timers.get( root ) );
		}

		var input = form.querySelector( '[data-blog-search-input]' );
		var query = input ? input.value : '';
		var nextUrl = buildSearchUrl( root, form, query );
		loadBlogUrl( root, nextUrl, { pushState: true } );
	} );

	document.addEventListener( 'input', function ( e ) {
		var input = e.target;
		if ( ! input || typeof input.matches !== 'function' || ! input.matches( '[data-blog-search-input]' ) ) {
			return;
		}

		var root = input.closest( '[data-blog-dynamic="1"]' );
		if ( ! root ) {
			return;
		}

		var val = input.value;
		syncSearchInputs( root, val );

		if ( timers && timers.has( root ) ) {
			window.clearTimeout( timers.get( root ) );
		}

		var form = input.closest( '[data-blog-search-form]' );
		var timerId = window.setTimeout( function () {
			var nextUrl = buildSearchUrl( root, form, input.value );
			loadBlogUrl( root, nextUrl, { pushState: true } );
		}, 350 );

		if ( timers ) {
			timers.set( root, timerId );
		}
	} );

	window.addEventListener( 'popstate', function () {
		var blocks = document.querySelectorAll( '[data-blog-dynamic="1"]' );
		for ( var i = 0; i < blocks.length; i++ ) {
			loadBlogUrl( blocks[ i ], window.location.href, { pushState: false } );
		}
	} );
}() );

