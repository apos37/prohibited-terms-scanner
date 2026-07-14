jQuery( function ( $ ) {

    /**
     * Upload warning
     *
     * Warns (but never blocks) when a filename queued for upload matches a
     * monitored term. Covers both the media modal (Backbone/plupload-based
     * wp.Uploader) and plain <input type="file"> fields used on some
     * classic screens. No build tooling required.
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

            this.hookMediaUploader();
            this.hookPlainFileInputs();
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
         * Show a non-blocking confirm; result is informational only, upload proceeds regardless
         *
         * @param {string} filename
         */
        warnIfFlagged: function ( filename ) {
            const matches = this.getMatches( filename );

            if ( ! matches.length ) {
                return;
            }

            alert( ptscanner_upload_warning_data.message + matches.join( ', ' ) + ' (' + filename + ')' );
        }, // End warnIfFlagged()


        /**
         * Hook the media modal's uploader queue
         *
         * wp.Uploader wraps plupload; the 'wp-plupload' add-file event fires
         * per file as it's added to the queue, before upload actually starts.
         * We only warn here, never call up.abort() or similar, per spec.
         */
        hookMediaUploader: function () {
            if ( typeof wp === 'undefined' || typeof wp.Uploader === 'undefined' ) {
                return;
            }

            $( document ).on( 'wp-plupload-file-added wp-plupload-add-file', ( event, params ) => {
                if ( params && params.filename ) {
                    this.warnIfFlagged( params.filename );
                }
            } );

            // Fallback: some WP versions surface the file via plupload's own queue event.
            if ( wp.Uploader.prototype && wp.Uploader.prototype.init ) {
                const originalInit = wp.Uploader.prototype.init;
                const self = this;

                wp.Uploader.prototype.init = function () {
                    const result = originalInit.apply( this, arguments );

                    if ( this.uploader && this.uploader.bind ) {
                        this.uploader.bind( 'FilesAdded', function ( up, files ) {
                            files.forEach( function ( file ) {
                                self.warnIfFlagged( file.name );
                            } );
                        } );
                    }

                    return result;
                };
            }
        }, // End hookMediaUploader()


        /**
         * Hook plain <input type="file"> fields (classic screens without the modal)
         */
        hookPlainFileInputs: function () {
            $( document ).on( 'change', 'input[type="file"]', ( event ) => {
                const files = event.target.files;

                if ( ! files || ! files.length ) {
                    return;
                }

                for ( let i = 0; i < files.length; i++ ) {
                    this.warnIfFlagged( files[ i ].name );
                }
            } );
        }, // End hookPlainFileInputs()

    };

    ptscannerUploadWarning.init();

} );