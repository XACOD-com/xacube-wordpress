/* Gives the XACUBE inbox frame its tickets. The frame asks; this page's server decides. */
( function () {
	'use strict';

	var settings = window.xacubeInbox;
	var frame = document.getElementById( 'xc-inbox-frame' );
	if ( ! settings || ! frame ) {
		return;
	}

	function answer( id, data ) {
		frame.contentWindow.postMessage( Object.assign( { type: 'xc:inbox-ticket', id: id }, data ), settings.appOrigin );
	}

	window.addEventListener( 'message', function ( event ) {
		var data = event.data;
		if ( event.origin !== settings.appOrigin || event.source !== frame.contentWindow || ! data || data.type !== 'xc:inbox-ticket-request' ) {
			return;
		}

		var body = new URLSearchParams();
		body.set( 'action', 'xacube_inbox_ticket' );
		body.set( '_ajax_nonce', settings.nonce );

		fetch( settings.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				// WordPress's nonce expired in a tab left open for a day: a reload issues a new one.
				if ( 403 === response.status ) {
					window.location.reload();
					return null;
				}
				return response.json();
			} )
			.then( function ( result ) {
				if ( ! result ) {
					return;
				}
				if ( result.success ) {
					answer( data.id, result.data );
				} else if ( result.data && ( 'revoked' === result.data.error || 'not_connected' === result.data.error ) ) {
					window.location.reload();
				} else {
					answer( data.id, { error: 'unavailable' } );
				}
			} )
			.catch( function () {
				answer( data.id, { error: 'unavailable' } );
			} );
	} );
}() );
