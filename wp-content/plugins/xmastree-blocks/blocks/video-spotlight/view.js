( function () {
	'use strict';

	var modalEl = null;
	var stageEl = null;
	var closeBtn = null;
	var previousFocus = null;
	var previousOverflow = '';

	function ensureModal() {
		if ( modalEl ) {
			return modalEl;
		}

		modalEl = document.createElement( 'div' );
		modalEl.className = 'xmastree-video-modal';
		modalEl.setAttribute( 'role', 'dialog' );
		modalEl.setAttribute( 'aria-modal', 'true' );
		modalEl.setAttribute( 'aria-label', 'Video Player' );
		modalEl.hidden = true;

		var backdrop = document.createElement( 'div' );
		backdrop.className = 'xmastree-video-modal__backdrop';

		var dialog = document.createElement( 'div' );
		dialog.className = 'xmastree-video-modal__dialog';

		closeBtn = document.createElement( 'button' );
		closeBtn.type = 'button';
		closeBtn.className = 'xmastree-video-modal__close';
		closeBtn.setAttribute( 'aria-label', 'Close video popup' );
		closeBtn.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';

		stageEl = document.createElement( 'div' );
		stageEl.className = 'xmastree-video-modal__stage';

		dialog.appendChild( closeBtn );
		dialog.appendChild( stageEl );
		modalEl.appendChild( backdrop );
		modalEl.appendChild( dialog );
		document.body.appendChild( modalEl );

		backdrop.addEventListener( 'click', closeModal );
		closeBtn.addEventListener( 'click', closeModal );

		document.addEventListener( 'keydown', function ( e ) {
			if ( modalEl && ! modalEl.hidden && 'Escape' === e.key ) {
				closeModal();
			}
		} );

		return modalEl;
	}

	function clearStage() {
		if ( ! stageEl ) {
			return;
		}

		var activeVideo = stageEl.querySelector( 'video' );
		if ( activeVideo ) {
			try {
				activeVideo.pause();
				activeVideo.removeAttribute( 'src' );
				activeVideo.innerHTML = '';
				activeVideo.load();
			} catch ( err ) {
				// Ignore cleanup error on detached media element.
			}
		}

		stageEl.innerHTML = '';
	}

	function closeModal() {
		if ( ! modalEl || modalEl.hidden ) {
			return;
		}

		clearStage();
		modalEl.hidden = true;
		document.body.style.overflow = previousOverflow;

		if ( previousFocus && typeof previousFocus.focus === 'function' ) {
			try {
				previousFocus.focus();
			} catch ( err ) {
				// Ignore focus restore error if element is no longer focusable.
			}
		}
		previousFocus = null;
	}

	function openVideoPopup( trigger ) {
		var type = trigger.getAttribute( 'data-video-type' ) || '';
		var src = trigger.getAttribute( 'data-video-src' ) || '';
		var mime = trigger.getAttribute( 'data-video-mime' ) || 'video/mp4';
		var title = trigger.getAttribute( 'data-video-title' ) || 'Video Player';

		if ( ! type || ! src ) {
			return;
		}

		ensureModal();
		clearStage();

		modalEl.setAttribute( 'aria-label', title );

		if ( 'upload' === type ) {
			var video = document.createElement( 'video' );
			video.controls = true;
			video.autoplay = true;
			video.playsInline = true;
			video.setAttribute( 'playsinline', '' );
			video.setAttribute( 'preload', 'auto' );

			var source = document.createElement( 'source' );
			source.src = src;
			if ( mime ) {
				source.type = mime;
			}
			video.appendChild( source );
			video.src = src;

			stageEl.appendChild( video );

			var playPromise = video.play();
			if ( playPromise && typeof playPromise.catch === 'function' ) {
				playPromise.catch( function () {
					// Fallback to muted playback if strict autoplay blocks audible media.
					video.muted = true;
					video.play().catch( function () {} );
				} );
			}
		} else if ( 'youtube' === type || 'vimeo' === type ) {
			var iframe = document.createElement( 'iframe' );
			iframe.src = src;
			iframe.title = title;
			iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share; fullscreen';
			iframe.setAttribute( 'allowfullscreen', '' );
			iframe.setAttribute( 'referrerpolicy', 'strict-origin-when-cross-origin' );
			stageEl.appendChild( iframe );
		}

		previousFocus = document.activeElement;
		previousOverflow = document.body.style.overflow || '';
		document.body.style.overflow = 'hidden';
		modalEl.hidden = false;

		if ( closeBtn ) {
			closeBtn.focus();
		}
	}

	document.addEventListener( 'click', function ( e ) {
		var target = e.target;
		if ( ! target || typeof target.closest !== 'function' ) {
			return;
		}

		var trigger = target.closest( '[data-mas-video-trigger]' );
		if ( ! trigger ) {
			var wrapper = target.closest( '.mas-video-wrapper.mas-video-clickable' );
			if ( wrapper ) {
				trigger = wrapper.querySelector( '[data-mas-video-trigger]' );
			}
		}

		if ( ! trigger ) {
			return;
		}

		e.preventDefault();
		openVideoPopup( trigger );
	} );
}() );
