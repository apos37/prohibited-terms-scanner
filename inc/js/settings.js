/**
 * PROHIBITED TERMS SCANNER: Settings
 *
 * AJAX save with Ctrl+S support, unsaved-changes reminder, download/upload and reset.
 */

( function ( $ ) {
    'use strict';

    var config = ( window.ptscanner_data && window.ptscanner_data.settings ) || {};
    var saveButton;
    var originalText;
    var savingInterval;
    var isDirty = false;

    $( document ).ready( function () {
        saveButton = $( '#ptscanner-save' );

        if ( ! saveButton.length ) {
            return;
        }

        originalText = saveButton.text();

        $( document ).on( 'input change', '#ptscanner-settings-form [name]', markDirty );
        $( document ).on( 'input change', '#ptscanner-warning-terms-cards :input', markDirty );
        $( document ).on( 'click', '#ptscanner-download-settings', downloadSettings );
        $( document ).on( 'change', '#ptscanner-upload-settings', importSettings );
        $( document ).on( 'click', '#ptscanner-reset-settings', resetSettings );

        $( document ).on( 'keydown', 'label[for="ptscanner-upload-settings"]', function ( event ) {
            if ( 'Enter' === event.key || ' ' === event.key ) {
                event.preventDefault();
                $( '#ptscanner-upload-settings' ).trigger( 'click' );
            }
        } );

        saveButton.on( 'click', function ( event ) {
            event.preventDefault();
            saveSettings();
        } );

        $( document ).on( 'keydown', function ( event ) {
            if ( ( event.ctrlKey || event.metaKey ) && 's' === event.key.toLowerCase() ) {
                event.preventDefault();
                saveSettings();
            }
        } );

        $( window ).on( 'beforeunload', function () {
            if ( isDirty ) {
                return config.unsavedText;
            }
        } );
    } );


    /**
     * Show the save reminder.
     */
    function markDirty() {
        if ( isDirty ) {
            return;
        }

        isDirty = true;
        $( '#ptscanner-save-reminder' ).fadeIn( 200 );
    } // End markDirty()


    /**
     * Collect every named field within the settings grid.
     *
     * @return {Object}
     */
    function gatherSettings() {
        var data = {};

        $( '#ptscanner-settings-form [name]' ).each( function () {
            var field = $( this );
            var name = field.attr( 'name' );

            if ( 'checkbox' === field.attr( 'type' ) ) {
                if ( '[]' === name.slice( -2 ) ) {
                    var key = name.slice( 0, -2 );

                    data[ key ] = data[ key ] || [];

                    if ( field.is( ':checked' ) ) {
                        data[ key ].push( field.val() );
                    }
                } else {
                    data[ name ] = field.is( ':checked' ) ? 1 : 0;
                }

                return;
            }

            data[ name ] = field.val();
        } );

        return data;
    } // End gatherSettings()


    /**
     * Save all settings via AJAX.
     */
    function saveSettings() {
        if ( saveButton.prop( 'disabled' ) ) {
            return;
        }

        var originalTitle = startSavingTitle();

        $( '#ptscanner-save-reminder' ).hide();
        isDirty = false;
        showSaving();

        $.ajax( {
            url: window.ptscanner_data.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: { action: 'ptscanner_save_settings', nonce: config.nonce, settings: gatherSettings() }
        } ).done( function ( response ) {
            stopSavingTitle( originalTitle );

            if ( response.success ) {
                if ( response.data && response.data.cronStatus ) {
                    $( '#ptscanner-cron-status' ).html( response.data.cronStatus );
                }

                showResult( config.savedText, true );
            } else {
                showResult( response.data || config.errorText, false );
            }
        } ).fail( function () {
            stopSavingTitle( originalTitle );
            showResult( config.errorText, false );
        } );
    } // End saveSettings()


    /**
     * Request the export payload and download it as a JSON file.
     */
    function downloadSettings() {
        var button = $( this ).prop( 'disabled', true );

        $.ajax( {
            url: window.ptscanner_data.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: { action: 'ptscanner_export', nonce: config.nonce }
        } ).done( function ( response ) {
            if ( ! response.success ) {
                showResult( response.data || config.errorText, false );
                return;
            }

            var blob = new Blob( [ JSON.stringify( response.data.data, null, 2 ) ], { type: 'application/json' } );
            var url = URL.createObjectURL( blob );
            var link = document.createElement( 'a' );

            link.href = url;
            link.download = response.data.filename;
            document.body.appendChild( link );
            link.click();
            document.body.removeChild( link );
            URL.revokeObjectURL( url );
        } ).fail( function () {
            showResult( config.errorText, false );
        } ).always( function () {
            button.prop( 'disabled', false );
        } );
    } // End downloadSettings()


    /**
     * Read an exported file and send it to the server.
     *
     * @param {Event} event
     */
    function importSettings( event ) {
        var file = event.target.files[ 0 ];

        if ( ! file ) {
            return;
        }

        if ( ! window.confirm( config.confirmImport ) ) {
            $( event.target ).val( '' );
            return;
        }

        var reader = new FileReader();

        reader.onload = function ( loaded ) {
            $( '#ptscanner-upload-filename' ).text( file.name ).show();

            $.ajax( {
                url: window.ptscanner_data.ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: { action: 'ptscanner_import', nonce: config.nonce, payload: loaded.target.result }
            } ).done( function ( response ) {
                if ( response.success ) {
                    isDirty = false;
                    showResult( response.data, true );
                    setTimeout( function () {
                        window.location.reload();
                    }, 900 );
                } else {
                    showResult( response.data || config.importError, false );
                }
            } ).fail( function ( xhr ) {
                showResult( config.importError + ' (HTTP ' + xhr.status + ')', false );
            } ).always( function () {
                $( event.target ).val( '' );
            } );
        };

        reader.readAsText( file );
    } // End importSettings()


    /**
     * Reset every setting to its default.
     */
    function resetSettings() {
        if ( ! window.confirm( config.confirmReset ) ) {
            return;
        }

        $.ajax( {
            url: window.ptscanner_data.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: { action: 'ptscanner_reset_settings', nonce: config.nonce }
        } ).done( function ( response ) {
            if ( response.success ) {
                isDirty = false;
                showResult( config.resetOk, true );
                setTimeout( function () {
                    window.location.reload();
                }, 900 );
            } else {
                showResult( response.data || config.errorText, false );
            }
        } ).fail( function () {
            showResult( config.errorText, false );
        } );
    } // End resetSettings()


    /**
     * Animate the browser tab title while saving.
     *
     * @return {string}
     */
    function startSavingTitle() {
        var originalTitle = document.title;
        var dots = 0;

        document.title = config.savingText;

        savingInterval = setInterval( function () {
            dots = ( dots + 1 ) % 4;
            document.title = config.savingText + '.'.repeat( dots );
        }, 500 );

        return originalTitle;
    } // End startSavingTitle()


    /**
     * Restore the browser tab title.
     *
     * @param {string} originalTitle
     */
    function stopSavingTitle( originalTitle ) {
        clearInterval( savingInterval );
        document.title = originalTitle;
    } // End stopSavingTitle()


    /**
     * Show the spinning save state.
     */
    function showSaving() {
        var icon = $( '<span class="dashicons dashicons-update spin"></span>' );

        saveButton.addClass( 'ptscanner-button-saving' ).prop( 'disabled', true ).empty().append( icon, document.createTextNode( ' ' + config.savingText + '...' ) );
        $( '#ptscanner-save-note' ).remove();
    } // End showSaving()


    /**
     * Show the result message next to the save button.
     *
     * @param {string} message
     * @param {boolean} success
     */
    function showResult( message, success ) {
        saveButton.removeClass( 'ptscanner-button-saving' ).prop( 'disabled', false ).text( originalText );
        $( '#ptscanner-save-note' ).remove();

        var note = $( '<span id="ptscanner-save-note"></span>' ).text( message ).addClass( success ? 'is-success' : 'is-error' );

        $( '#ptscanner-save-reminder' ).before( note );

        setTimeout( function () {
            note.fadeOut( 400, function () {
                $( this ).remove();
            } );
        }, 4000 );
    } // End showResult()

} )( jQuery );
