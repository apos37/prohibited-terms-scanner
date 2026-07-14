jQuery( function ( $ ) {

    /**
     * Import/Export controller
     */
    const ptscannerImportExport = {


        /**
         * Init
         */
        init: function () {
            this.bindEvents();
        }, // End init()


        /**
         * Bind DOM events
         */
        bindEvents: function () {
            $( document ).on( 'click', '#ptscanner-export-btn', () => {
                this.handleExport();
            } );

            $( document ).on( 'click', '#ptscanner-import-btn', () => {
                this.handleImport();
            } );
        }, // End bindEvents()


        /**
         * Trigger export and download the resulting JSON as a file
         */
        handleExport: function () {
            const button = $( '#ptscanner-export-btn' );
            button.prop( 'disabled', true );

            $.post( ptscanner_data.ajaxUrl, {
                action: 'ptscanner_export',
                nonce: ptscanner_data.importExportNonce,
            } ).done( ( response ) => {
                button.prop( 'disabled', false );

                if ( ! response.success ) {
                    alert( response.data.message || 'Export failed.' );
                    return;
                }

                const blob = new Blob( [ JSON.stringify( response.data.data, null, 2 ) ], { type: 'application/json' } );
                const url = URL.createObjectURL( blob );
                const link = document.createElement( 'a' );

                link.href = url;
                link.download = response.data.filename;
                document.body.appendChild( link );
                link.click();
                document.body.removeChild( link );
                URL.revokeObjectURL( url );
            } ).fail( () => {
                button.prop( 'disabled', false );
                alert( ptscanner_data.strings.requestFailed || 'Request failed.' );
            } );
        }, // End handleExport()


        /**
         * Upload and import a JSON file
         */
        handleImport: function () {
            const fileInput = document.getElementById( 'ptscanner-import-file' );

            if ( ! fileInput.files.length ) {
                alert( ptscanner_data.strings.selectFile || 'Please select a file first.' );
                return;
            }

            if ( ! confirm( ptscanner_data.strings.confirmImport || 'This will overwrite your current terms and settings. Continue?' ) ) {
                return;
            }

            const formData = new FormData();
            formData.append( 'action', 'ptscanner_import' );
            formData.append( 'nonce', ptscanner_data.importExportNonce );
            formData.append( 'import_file', fileInput.files[ 0 ] );

            const button = $( '#ptscanner-import-btn' );
            button.prop( 'disabled', true );

            $.ajax( {
                url: ptscanner_data.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
            } ).done( ( response ) => {
                button.prop( 'disabled', false );

                if ( response.success ) {
                    alert( response.data.message );
                    window.location.reload();
                } else {
                    alert( response.data.message || 'Import failed.' );
                }
            } ).fail( () => {
                button.prop( 'disabled', false );
                alert( ptscanner_data.strings.requestFailed || 'Request failed.' );
            } );
        }, // End handleImport()

    };

    ptscannerImportExport.init();

} );