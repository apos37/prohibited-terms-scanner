jQuery( function ( $ ) {

    $( '#ptscanner-settings-form' ).on( 'submit', function ( event ) {
        event.preventDefault();

        const form = $( this );
        const submitButton = form.find( '#submit' );
        const formData = form.serialize() + '&action=ptscanner_save_settings';

        submitButton.prop( 'disabled', true );

        $.post( ptscanner_data.ajaxUrl, formData ).done( ( response ) => {
            submitButton.prop( 'disabled', false );

            if ( response.success ) {
                const notice = $( '<div class="notice notice-success is-dismissible"><p></p></div>' );
                notice.find( 'p' ).text( response.data.message );
                form.before( notice );
                window.scrollTo( 0, 0 );
            } else {
                alert( response.data.message || 'Could not save settings.' );
            }
        } ).fail( () => {
            submitButton.prop( 'disabled', false );
            alert( ptscanner_data.strings.requestFailed || 'Request failed.' );
        } );
    } );

} );