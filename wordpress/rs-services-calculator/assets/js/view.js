( function () {
	'use strict';

	function formatMoney( cents, symbol, locale ) {
		var value = ( cents / 100 ).toLocaleString( locale || undefined, {
			minimumFractionDigits: 2,
			maximumFractionDigits: 2,
		} );
		return symbol + value;
	}

	function formatDays( days, root ) {
		var template = days === 1 ? root.dataset.day : root.dataset.days;
		return ( template || '%d days' ).replace( '%d', days );
	}

	function updateCard( card, root ) {
		var total = parseInt( card.dataset.base, 10 ) || 0;
		var days = parseInt( card.dataset.delivery, 10 ) || 1;

		card.querySelectorAll( '.rs-addon[aria-pressed="true"]' ).forEach( function ( btn ) {
			total += parseInt( btn.dataset.price, 10 ) || 0;
			days += parseInt( btn.dataset.days, 10 ) || 0;
		} );

		days = Math.max( 1, days );

		card.querySelector( '[data-rs-total]' ).textContent = formatMoney( total, root.dataset.currency, root.dataset.locale );
		card.querySelector( '[data-rs-days]' ).textContent = formatDays( days, root );
	}

	function init( root ) {
		if ( root.dataset.rsReady ) {
			return;
		}
		root.dataset.rsReady = '1';

		root.querySelectorAll( '[data-rs-addons]' ).forEach( function ( group ) {
			group.hidden = false;
		} );

		root.addEventListener( 'click', function ( event ) {
			var btn = event.target.closest( '.rs-addon' );
			if ( ! btn || ! root.contains( btn ) ) {
				return;
			}

			var pressed = btn.getAttribute( 'aria-pressed' ) === 'true';
			btn.setAttribute( 'aria-pressed', pressed ? 'false' : 'true' );

			var card = btn.closest( '[data-rs-service]' );
			if ( card ) {
				updateCard( card, root );
			}
		} );
	}

	document.querySelectorAll( '[data-rs-services]' ).forEach( init );
}() );
