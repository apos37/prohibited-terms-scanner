jQuery( function ( $ ) {

    $( document ).on( 'click', '.ptscanner-toggle-omit', function ( event ) {
        event.preventDefault();

        const link = $( this );
        const id = link.data( 'id' );
        const type = link.data( 'type' );
        const isOmitted = link.data( 'omitted' ) == 1;

        $.post( ptscanner_row_actions_data.ajaxUrl, {
            action: 'ptscanner_toggle_omit',
            nonce: ptscanner_row_actions_data.nonce,
            id: id,
            type: type,
            omit: isOmitted ? '0' : '1',
        } ).done( function ( response ) {
            if ( ! response.success ) {
                alert( response.data.message || 'Could not update.' );
                return;
            }

            const nowOmitted = ! isOmitted;
            link.data( 'omitted', nowOmitted ? '1' : '0' );
            link.text( nowOmitted ? ptscanner_row_actions_data.labels.unomit : ptscanner_row_actions_data.labels.omit );
        } ).fail( function () {
            alert( 'Request failed.' );
        } );
    } );

} );