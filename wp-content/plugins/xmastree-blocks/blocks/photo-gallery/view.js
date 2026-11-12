( function () {
	'use strict';

	if ( window.xmastreeGalleryLightboxInit ) {
		return;
	}
	window.xmastreeGalleryLightboxInit = true;

	var modal = null;
	var stageImg = null;
	var captionEl = null;
	var counterEl = null;
	var prevBtn = null;
	var nextBtn = null;
	var closeBtn = null;
	var thumbsEl = null;

	var activeItems = [];
	var currentIndex = 0;
	var lastFocused = null;
	var touchStartX = 0;
	var touchEndX = 0;

	function buildModal() {
		if ( modal ) {
			return modal;
		}

		modal = document.createElement( 'div' );
		modal.className = 'xmastree-lightbox';
		modal.setAttribute( 'role', 'dialog' );
		modal.setAttribute( 'aria-modal', 'true' );
		modal.setAttribute( 'aria-label', 'Photo gallery lightbox viewer' );
		modal.setAttribute( 'hidden', '' );

		modal.innerHTML =
			'<div class="xmastree-lightbox-backdrop" data-lightbox-close="1"></div>' +
			'<div class="xmastree-lightbox-dialog">' +
				'<div class="xmastree-lightbox-topbar">' +
					'<span class="xmastree-lightbox-counter" aria-live="polite"></span>' +
					'<button type="button" class="xmastree-lightbox-close" data-lightbox-close="1" aria-label="Close lightbox">' +
						'<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/></svg>' +
					'</button>' +
				'</div>' +
				'<div class="xmastree-lightbox-stage">' +
					'<button type="button" class="xmastree-lightbox-nav xmastree-lightbox-prev" aria-label="Previous photo">' +
						'<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0"/></svg>' +
					'</button>' +
					'<div class="xmastree-lightbox-image-wrap">' +
						'<img class="xmastree-lightbox-img" src="" alt="">' +
					'</div>' +
					'<button type="button" class="xmastree-lightbox-nav xmastree-lightbox-next" aria-label="Next photo">' +
						'<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708"/></svg>' +
					'</button>' +
				'</div>' +
				'<div class="xmastree-lightbox-footer">' +
					'<div class="xmastree-lightbox-caption"></div>' +
					'<div class="xmastree-lightbox-thumbs"></div>' +
				'</div>' +
			'</div>';

		document.body.appendChild( modal );

		stageImg = modal.querySelector( '.xmastree-lightbox-img' );
		captionEl = modal.querySelector( '.xmastree-lightbox-caption' );
		counterEl = modal.querySelector( '.xmastree-lightbox-counter' );
		prevBtn = modal.querySelector( '.xmastree-lightbox-prev' );
		nextBtn = modal.querySelector( '.xmastree-lightbox-next' );
		closeBtn = modal.querySelector( '.xmastree-lightbox-close' );
		thumbsEl = modal.querySelector( '.xmastree-lightbox-thumbs' );

		modal.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '[data-lightbox-close="1"]' ) ) {
				closeLightbox();
			} else if ( e.target === modal || e.target.classList.contains( 'xmastree-lightbox-dialog' ) || e.target.classList.contains( 'xmastree-lightbox-stage' ) || e.target.classList.contains( 'xmastree-lightbox-image-wrap' ) ) {
				closeLightbox();
			}
		} );

		prevBtn.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			goToIndex( currentIndex - 1 );
		} );

		nextBtn.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			goToIndex( currentIndex + 1 );
		} );

		modal.addEventListener( 'touchstart', function ( e ) {
			if ( e.changedTouches && e.changedTouches.length ) {
				touchStartX = e.changedTouches[ 0 ].screenX;
			}
		}, { passive: true } );

		modal.addEventListener( 'touchend', function ( e ) {
			if ( e.changedTouches && e.changedTouches.length ) {
				touchEndX = e.changedTouches[ 0 ].screenX;
				var diff = touchStartX - touchEndX;
				if ( Math.abs( diff ) > 45 && activeItems.length > 1 ) {
					if ( diff > 0 ) {
						goToIndex( currentIndex + 1 );
					} else {
						goToIndex( currentIndex - 1 );
					}
				}
			}
		}, { passive: true } );

		return modal;
	}

	function renderThumbnails() {
		thumbsEl.innerHTML = '';
		if ( activeItems.length <= 1 ) {
			thumbsEl.style.display = 'none';
			return;
		}

		thumbsEl.style.display = 'flex';
		for ( var i = 0; i < activeItems.length; i++ ) {
			( function ( idx ) {
				var btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.className = 'xmastree-lightbox-thumb-btn';
				btn.setAttribute( 'aria-label', 'View photo ' + ( idx + 1 ) );
				var img = document.createElement( 'img' );
				img.src = activeItems[ idx ].thumb || activeItems[ idx ].src;
				img.alt = '';
				btn.appendChild( img );
				btn.addEventListener( 'click', function ( e ) {
					e.stopPropagation();
					goToIndex( idx );
				} );
				thumbsEl.appendChild( btn );
			} )( i );
		}
	}

	function updateSlide() {
		if ( ! activeItems.length ) {
			return;
		}
		var item = activeItems[ currentIndex ];
		stageImg.classList.add( 'is-loading' );
		stageImg.onload = function () {
			stageImg.classList.remove( 'is-loading' );
		};
		stageImg.src = item.src;
		stageImg.alt = item.alt || '';
		if ( stageImg.complete ) {
			stageImg.classList.remove( 'is-loading' );
		}

		counterEl.textContent = ( currentIndex + 1 ) + ' / ' + activeItems.length;

		if ( item.caption ) {
			captionEl.textContent = item.caption;
			captionEl.style.display = 'block';
		} else {
			captionEl.textContent = '';
			captionEl.style.display = 'none';
		}

		var hasMultiple = activeItems.length > 1;
		prevBtn.style.display = hasMultiple ? 'inline-flex' : 'none';
		nextBtn.style.display = hasMultiple ? 'inline-flex' : 'none';

		var thumbBtns = thumbsEl.querySelectorAll( '.xmastree-lightbox-thumb-btn' );
		for ( var i = 0; i < thumbBtns.length; i++ ) {
			if ( i === currentIndex ) {
				thumbBtns[ i ].classList.add( 'is-active' );
				thumbBtns[ i ].scrollIntoView( { behavior: 'smooth', block: 'nearest', inline: 'center' } );
			} else {
				thumbBtns[ i ].classList.remove( 'is-active' );
			}
		}
	}

	function goToIndex( idx ) {
		if ( ! activeItems.length ) {
			return;
		}
		var total = activeItems.length;
		currentIndex = ( ( idx % total ) + total ) % total;
		updateSlide();
	}

	function openLightbox( items, startIndex, triggerEl ) {
		if ( ! items.length ) {
			return;
		}
		buildModal();
		activeItems = items;
		currentIndex = Math.max( 0, Math.min( startIndex, items.length - 1 ) );
		lastFocused = triggerEl || document.activeElement;

		renderThumbnails();
		updateSlide();

		modal.removeAttribute( 'hidden' );
		document.documentElement.classList.add( 'xmastree-lightbox-open' );
		document.body.classList.add( 'xmastree-lightbox-open' );

		setTimeout( function () {
			modal.classList.add( 'is-open' );
			if ( closeBtn ) {
				closeBtn.focus();
			}
		}, 10 );
	}

	function closeLightbox() {
		if ( ! modal || modal.hasAttribute( 'hidden' ) ) {
			return;
		}
		modal.classList.remove( 'is-open' );
		document.documentElement.classList.remove( 'xmastree-lightbox-open' );
		document.body.classList.remove( 'xmastree-lightbox-open' );

		setTimeout( function () {
			if ( modal ) {
				modal.setAttribute( 'hidden', '' );
			}
			if ( stageImg ) {
				stageImg.src = '';
			}
			if ( lastFocused && typeof lastFocused.focus === 'function' ) {
				lastFocused.focus();
			}
		}, 180 );
	}

	document.addEventListener( 'click', function ( e ) {
		var trigger = e.target.closest( '[data-gallery-lightbox-trigger="1"]' );
		if ( ! trigger ) {
			return;
		}

		e.preventDefault();

		var root = trigger.closest( '[data-gallery-lightbox-root]' );
		var triggers = root
			? root.querySelectorAll( '[data-gallery-lightbox-trigger="1"]' )
			: [ trigger ];

		var items = [];
		var clickedIndex = 0;

		for ( var i = 0; i < triggers.length; i++ ) {
			var el = triggers[ i ];
			if ( el === trigger ) {
				clickedIndex = i;
			}
			items.push( {
				src: el.getAttribute( 'data-lightbox-src' ) || el.getAttribute( 'href' ) || '',
				thumb: el.getAttribute( 'data-lightbox-thumb' ) || '',
				alt: el.getAttribute( 'data-lightbox-alt' ) || '',
				caption: el.getAttribute( 'data-lightbox-caption' ) || '',
			} );
		}

		openLightbox( items, clickedIndex, trigger );
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( ! modal || modal.hasAttribute( 'hidden' ) ) {
			return;
		}
		if ( 'Escape' === e.key ) {
			e.preventDefault();
			closeLightbox();
		} else if ( 'ArrowLeft' === e.key && activeItems.length > 1 ) {
			e.preventDefault();
			goToIndex( currentIndex - 1 );
		} else if ( 'ArrowRight' === e.key && activeItems.length > 1 ) {
			e.preventDefault();
			goToIndex( currentIndex + 1 );
		}
	} );
} )();

