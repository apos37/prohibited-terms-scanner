jQuery( function ( $ ) {

    /**
     * Upload warning
     *
     * Warns when a filename queued for upload matches a monitored term, and
     * lets the person cancel the upload if they choose. Covers both native
     * file input selection and drag-and-drop onto WordPress's media dropzone.
     */
    const ptscannerUploadWarning = {


        /**
         * Terms to check against
         */
        terms: [],


        /**
         * Init
         */
        init: function () {
            if ( typeof ptscanner_upload_warning_data === 'undefined' ) {
                return;
            }

            this.terms = ptscanner_upload_warning_data.terms || [];

            if ( ! this.terms.length ) {
                return;
            }

            this.hookFileInputs();
        }, // End init()


        /**
         * Check a filename against the term list
         *
         * @param {string} filename
         * @return {Array} matched term strings
         */
        getMatches: function ( filename ) {
            const matched = [];
            const loweredFilename = filename.toLowerCase();

            this.terms.forEach( ( termData ) => {
                const term = termData.case_sensitive ? termData.term : termData.term.toLowerCase();
                const haystack = termData.case_sensitive ? filename : loweredFilename;

                if ( haystack.indexOf( term ) !== -1 ) {
                    matched.push( termData.term );
                }
            } );

            return matched;
        }, // End getMatches()


        /**
         * Check a filename and return whether the upload should proceed
         *
         * @param {string} filename
         * @return {boolean} true to proceed, false to cancel
         */
        confirmIfFlagged: function ( filename ) {
            const matches = this.getMatches( filename );

            if ( ! matches.length ) {
                return true;
            }

            const message = ptscanner_upload_warning_data.message + matches.join( ', ' ) + ' (' + filename + '). ' + ptscanner_upload_warning_data.confirm;

            return confirm( message );
        }, // End confirmIfFlagged()


        /**
         * Hook both native file input selection and drag-and-drop
         */
        hookFileInputs: function () {
            document.addEventListener( 'change', ( event ) => {
                if ( ! event.target || event.target.type !== 'file' ) {
                    return;
                }

                const files = event.target.files;

                if ( ! files || ! files.length ) {
                    return;
                }

                let allowed = true;

                for ( let i = 0; i < files.length; i++ ) {
                    if ( ! this.confirmIfFlagged( files[ i ].name ) ) {
                        allowed = false;
                        break;
                    }
                }

                if ( ! allowed ) {
                    event.target.value = '';
                    event.stopImmediatePropagation();
                    event.preventDefault();
                }
            }, true );

            document.addEventListener( 'drop', ( event ) => {
                if ( ! event.dataTransfer || ! event.dataTransfer.files || ! event.dataTransfer.files.length ) {
                    return;
                }

                const files = event.dataTransfer.files;
                let allowed = true;

                for ( let i = 0; i < files.length; i++ ) {
                    if ( ! this.confirmIfFlagged( files[ i ].name ) ) {
                        allowed = false;
                        break;
                    }
                }

                if ( ! allowed ) {
                    event.stopImmediatePropagation();
                    event.preventDefault();
                }
            }, true );
        }, // End hookFileInputs()

    };

    ptscannerUploadWarning.init();

} );