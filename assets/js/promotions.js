/*
 * TS Promotions: closing the top bar and bottom bar; the pop-up and corner slide-in (when they
 * appear, how often, keyboard); ribbons on cards (1.1.0); "You are asking about" on enquiry
 * forms (1.1.0).
 */
( function () {
	'use strict';

	function store( kind ) {
		try {
			return window[ kind ];
		} catch ( e ) {
			return null;
		}
	}

	function get( kind, key ) {
		var s = store( kind );
		try {
			return s ? s.getItem( key ) : null;
		} catch ( e ) {
			return null;
		}
	}

	function set( kind, key, value ) {
		var s = store( kind );
		try {
			if ( s ) {
				s.setItem( key, value );
			}
		} catch ( e ) {}
	}

	function running( el ) {
		var now = Date.now();
		var start = +el.getAttribute( 'data-tsp-start' ) || 0;
		var end = +el.getAttribute( 'data-tsp-end' ) || 0;
		return now >= start && ( ! end || now <= end );
	}

	function remove( el ) {
		if ( el && el.parentNode ) {
			el.parentNode.removeChild( el );
		}
	}

	/* ---------- Top bar and bottom bar: closed for the rest of the visit ---------- */
	function closable( el, button ) {
		if ( ! el || ! button ) {
			return;
		}
		button.addEventListener( 'click', function () {
			set( 'sessionStorage', 'tsp-closed-' + el.getAttribute( 'data-tsp-id' ), '1' );
			remove( el );
			if ( el.classList.contains( 'tsp-bar' ) ) {
				barSize();
			}
		} );
	}

	var bar = document.querySelector( '.tsp-topbar' );
	if ( bar ) {
		closable( bar, bar.querySelector( '.tsp-topbar__close' ) );
	}

	/* The bottom bar's height is published as --tsp-bar-h (0 when it is hidden on this screen size),
	   so the page, the slide-in and the contact button stay clear of it. */
	var bottom = document.querySelector( '.tsp-bar' );
	function barSize() {
		var root = document.documentElement;
		var h = bottom && bottom.isConnected ? bottom.offsetHeight : 0;
		root.style.setProperty( '--tsp-bar-h', h + 'px' );
		root.classList.toggle( 'tsp-has-bar', h > 0 );
	}
	if ( bottom ) {
		closable( bottom, bottom.querySelector( '.tsp-bar__close' ) );
		barSize();
		window.addEventListener( 'resize', barSize );
	}

	/* ---------- Pop-up and slide-in: when they appear and how often ---------- */
	/* 1.2.1: when a pop-up and a slide-in are both on a page, the one that appears first stops the
	   other for the rest of the visit (remembered per offer and kind, like "seen"). */
	function stopKey( el ) {
		return 'tsp-stopped-' + el.getAttribute( 'data-tsp-id' ) + '-' + el.className.split( ' ' )[ 0 ];
	}

	function stop( el ) {
		if ( el ) {
			set( 'sessionStorage', stopKey( el ), '1' );
		}
	}

	function seen( el ) {
		if ( get( 'sessionStorage', stopKey( el ) ) ) {
			return true;
		}
		var often = el.getAttribute( 'data-often' ) || 'visit';
		var v = get( 'visit' === often ? 'sessionStorage' : 'localStorage', 'tsp-seen-' + el.getAttribute( 'data-tsp-id' ) + '-' + el.className.split( ' ' )[ 0 ] );
		if ( ! v ) {
			return false;
		}
		return 'day' === often ? Date.now() - ( +v || 0 ) < 864e5 : true;
	}

	function remember( el ) {
		var often = el.getAttribute( 'data-often' ) || 'visit';
		set( 'visit' === often ? 'sessionStorage' : 'localStorage', 'tsp-seen-' + el.getAttribute( 'data-tsp-id' ) + '-' + el.className.split( ' ' )[ 0 ], String( Date.now() ) );
	}

	/* Calls show() once: after the seconds, after scrolling a share of the page, or when the mouse
	   leaves through the top of the window (computers; elsewhere after the seconds). */
	function whenDue( el, show ) {
		var done = false;
		function go() {
			if ( ! done ) {
				done = true;
				show();
			}
		}
		var trigger = el.getAttribute( 'data-trigger' ) || 'time';
		var delay = ( +el.getAttribute( 'data-delay' ) || 8 ) * 1000;
		var hover = window.matchMedia && window.matchMedia( '(hover: hover) and (pointer: fine)' ).matches;

		if ( 'scroll' === trigger ) {
			var share = ( +el.getAttribute( 'data-scroll' ) || 50 ) / 100;
			var check = function () {
				var room = document.documentElement.scrollHeight - window.innerHeight;
				if ( room <= 0 || window.scrollY / room >= share ) {
					window.removeEventListener( 'scroll', check );
					go();
				}
			};
			window.addEventListener( 'scroll', check, { passive: true } );
			return;
		}
		if ( 'leave' === trigger && hover ) {
			var out = function ( e ) {
				if ( ! e.relatedTarget && e.clientY <= 0 ) {
					document.removeEventListener( 'mouseout', out );
					go();
				}
			};
			// Not in the first seconds, so moving to the address bar right away does not count.
			window.setTimeout( function () {
				document.addEventListener( 'mouseout', out );
			}, 3000 );
			return;
		}
		window.setTimeout( go, delay );
	}

	/* ---------- Pop-up ---------- */
	var pop = document.querySelector( '.tsp-popup' );
	if ( pop && running( pop ) && ! seen( pop ) ) {
		var box = pop.querySelector( '.tsp-popup__box' );
		var lastFocus = null;

		var focusables = function () {
			return Array.prototype.filter.call(
				box.querySelectorAll( 'a[href], button, [tabindex]:not([tabindex="-1"])' ),
				function ( el ) {
					return el.offsetParent !== null;
				}
			);
		};

		var onKey = function ( e ) {
			if ( 'Escape' === e.key ) {
				e.preventDefault();
				hidePop();
				return;
			}
			if ( 'Tab' !== e.key ) {
				return;
			}
			var items = focusables();
			if ( ! items.length ) {
				e.preventDefault();
				return;
			}
			var first = items[ 0 ];
			var last = items[ items.length - 1 ];
			if ( e.shiftKey && ( document.activeElement === first || document.activeElement === box ) ) {
				e.preventDefault();
				last.focus();
			} else if ( ! e.shiftKey && document.activeElement === last ) {
				e.preventDefault();
				first.focus();
			}
		};

		var hidePop = function () {
			pop.hidden = true;
			document.documentElement.classList.remove( 'tsp-lock' );
			document.removeEventListener( 'keydown', onKey );
			if ( lastFocus && lastFocus.focus ) {
				lastFocus.focus();
			}
		};

		pop.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '[data-tsp-close]' ) ) {
				hidePop();
			}
		} );

		whenDue( pop, function () {
			// Not when the offer ended while the page was open, or it was already seen meanwhile.
			if ( ! running( pop ) || seen( pop ) ) {
				return;
			}
			// The slide-in came first: no pop-up for the rest of the visit.
			if ( slide && ! slide.hidden ) {
				stop( pop );
				return;
			}
			lastFocus = document.activeElement;
			pop.hidden = false;
			document.documentElement.classList.add( 'tsp-lock' );
			document.addEventListener( 'keydown', onKey );
			box.focus();
			remember( pop );
			stop( slide );
		} );
	}

	/* ---------- Corner slide-in (does not take focus or block the page) ---------- */
	var slide = document.querySelector( '.tsp-slidein' );
	if ( slide && running( slide ) && ! seen( slide ) ) {
		slide.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '[data-tsp-close]' ) ) {
				slide.hidden = true;
			}
		} );
		slide.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key ) {
				slide.hidden = true;
			}
		} );
		whenDue( slide, function () {
			// seen() is also true when the pop-up appeared first in this visit (stop()).
			if ( ! running( slide ) || seen( slide ) ) {
				return;
			}
			// The pop-up is open right now: it came first, so no slide-in for the rest of the visit.
			if ( pop && ! pop.hidden ) {
				stop( slide );
				return;
			}
			slide.hidden = false;
			remember( slide );
			stop( pop );
		} );
	}

	/* ---------- Ribbons on cards ---------- */
	function plain( url ) {
		try {
			var u = new URL( url, window.location.href );
			var path = u.pathname.replace( /\/+$/, '' );
			var page = u.searchParams.get( 'page_id' );
			return u.origin + path + ( page ? '?page_id=' + page : '' );
		} catch ( e ) {
			return '';
		}
	}

	var ribbons = window.tspRibbons || [];
	if ( ribbons.length ) {
		var now = Date.now();
		var cards = document.querySelectorAll( '.ts-mcard, .ts-card' );
		ribbons.forEach( function ( r ) {
			if ( now < ( r.start || 0 ) || ( r.end && now > r.end ) ) {
				return;
			}
			var targets = ( r.urls || [] ).map( plain );
			var trips = r.trips ? plain( r.trips ) + '/' : '';
			Array.prototype.forEach.call( cards, function ( card ) {
				if ( card.querySelector( '.tsp-ribbon' ) ) {
					return;
				}
				// The card's own links, and the card itself when the whole card is a link.
				var links = Array.prototype.slice.call( card.querySelectorAll( 'a[href]' ) );
				if ( card.matches( 'a[href]' ) ) {
					links.push( card );
				}
				var hit = links.some( function ( a ) {
					var p = plain( a.getAttribute( 'href' ) );
					return p && ( targets.indexOf( p ) !== -1 || ( trips && ( p + '/' ).indexOf( trips ) === 0 && p + '/' !== trips ) );
				} );
				if ( ! hit ) {
					return;
				}
				// On the picture when the card has one; the other top corner when the card's own label is there.
				var host = card.querySelector( '.ts-mcard__media' ) || card;
				var corner = r.corner === 'top-right' ? 'top-right' : 'top-left';
				if ( host.querySelector( '.ts-mcard__badge--' + corner ) ) {
					corner = corner === 'top-left' ? 'top-right' : 'top-left';
				}
				var tag = document.createElement( 'span' );
				tag.className = 'tsp-ribbon tsp-ribbon--' + corner + ' tsp-c--' + r.colour;
				tag.textContent = r.text;
				if ( 'static' === window.getComputedStyle( host ).position ) {
					host.classList.add( 'tsp-ribbon-host' );
				}
				host.appendChild( tag );
			} );
		} );
	}

	/* ---------- "You are asking about": enquiry forms name the offer the visitor came from ---------- */
	var KEY = 'tsp-offer';
	var arrival = window.tspArrival;
	if ( arrival && arrival.id ) {
		set( 'sessionStorage', KEY, JSON.stringify( arrival ) );
	}
	var offer = null;
	try {
		offer = JSON.parse( get( 'sessionStorage', KEY ) || 'null' );
	} catch ( e ) {
		offer = null;
	}
	if ( offer && offer.id && ( ! offer.end || Date.now() <= offer.end ) ) {
		var forms = document.querySelectorAll( 'form.ts-enq__form, form#ts-flyout-form' );
		Array.prototype.forEach.call( forms, function ( form ) {
			var hidden = document.createElement( 'input' );
			hidden.type = 'hidden';
			hidden.name = 'tsp_offer';
			hidden.value = String( offer.id );
			form.appendChild( hidden );

			var line = document.createElement( 'div' );
			line.className = 'tsp-ask tsp-c--' + ( offer.colour || 'espresso' );
			var text = document.createElement( 'span' );
			text.className = 'tsp-ask__text';
			text.appendChild( document.createTextNode( ( ( window.tspText && tspText.asking ) || 'You are asking about:' ) + ' ' ) );
			var name = document.createElement( 'strong' );
			name.textContent = offer.name;
			text.appendChild( name );
			var drop = document.createElement( 'button' );
			drop.type = 'button';
			drop.className = 'tsp-ask__remove';
			drop.textContent = ( window.tspText && tspText.notThis ) || 'Not about this offer';
			drop.addEventListener( 'click', function () {
				set( 'sessionStorage', KEY, '' );
				Array.prototype.forEach.call( document.querySelectorAll( '.tsp-ask' ), remove );
				Array.prototype.forEach.call( document.querySelectorAll( 'input[name="tsp_offer"]' ), remove );
			} );
			line.appendChild( text );
			line.appendChild( drop );
			form.insertBefore( line, form.firstChild );
		} );
		// Arrived from the offer's button: bring the page's enquiry form into view.
		if ( arrival && arrival.id ) {
			var main = document.querySelector( 'form.ts-enq__form' );
			if ( main && ! window.location.hash ) {
				window.requestAnimationFrame( function () {
					window.scrollTo( { top: Math.max( 0, main.getBoundingClientRect().top + window.scrollY - 32 ) } );
				} );
			}
		}
	}
}() );
