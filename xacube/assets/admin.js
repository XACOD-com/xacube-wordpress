/*
 * XACUBE settings page: asks before anything destructive, and shows that a button is working
 * while its request is on its way (connecting can take a moment). The page works without it.
 */
( function () {
	document.querySelectorAll( '.xacube form' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function ( event ) {
			var question = form.getAttribute( 'data-xc-confirm' );
			if ( question && ! window.confirm( question ) ) {
				event.preventDefault();
				return;
			}

			var pending = form.getAttribute( 'data-xc-pending' );
			var button = event.submitter || form.querySelector( '[type="submit"]' );
			if ( ! button ) {
				return;
			}
			if ( button.getAttribute( 'aria-busy' ) === 'true' ) {
				event.preventDefault();
				return;
			}
			button.setAttribute( 'aria-busy', 'true' );
			if ( pending ) {
				var label = button.querySelector( 'span' ) || button;
				label.textContent = pending;
			}
			// Disabled after this tick, so the browser still sends the form it is submitting.
			setTimeout( function () {
				button.disabled = true;
			} );
		} );
	} );
} )();
