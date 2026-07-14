jQuery( function ( $ ) {

    /**
     * Editor save/publish warning
     *
     * Intercepts the Classic Editor's publish/update click and, for
     * Gutenberg, subscribes to the editor's save-in-progress state to show
     * a confirm dialog when flagged terms are present. Non-blocking: if the
     * user confirms, the save proceeds; if they cancel, nothing further
     * happens (the click is simply not re-triggered).
     */
    const ptscannerEditorWarning = {


        /**
         * Terms to check against
         */
        terms: [],


        /**
         * Whether we've already warned for the current save attempt, to
         * avoid re-prompting endlessly on repeated saves of the same content
         */
        hasWarnedThisSession: false,


        /**
         * Init
         */
        init: function () {
            if ( typeof ptscanner_warning_data === 'undefined' ) {
                return;
            }

            this.terms = ptscanner_warning_data.terms || [];

            if ( ! this.terms.length ) {
                return;
            }

            if ( this.isGutenbergActive() ) {
                this.bindGutenberg();
            } else {
                this.bindClassicEditor();
            }
        }, // End init()


        /**
         * Detect Gutenberg
         *
         * @return {boolean}
         */
        isGutenbergActive: function () {
            return typeof wp !== 'undefined' && typeof wp.data !== 'undefined' && typeof wp.data.select( 'core/editor' ) !== 'undefined';
        }, // End isGutenbergActive()


        /**
         * Classic Editor: intercept the publish/update button click
         */
        bindClassicEditor: function () {
            $( '#publish' ).on( 'click', ( event ) => {
                const content = $( '#content' ).val() || '';
                const title = $( '#title' ).val() || '';

                if ( ! this.confirmIfFlagged( title + ' ' + content ) ) {
                    event.preventDefault();
                }
            } );
        }, // End bindClassicEditor()


        /**
         * Gutenberg: hook the publish/update button in the toolbar
         *
         * No build step available, so this binds directly to the DOM button
         * rather than using wp.data subscriptions/filters, which require a
         * compiled package. This is functional but may need selector updates
         * if a future WP core release changes the editor's markup.
         */
        bindGutenberg: function () {
            $( document ).on( 'click', '.editor-post-publish-button, .editor-post-publish-panel__toggle, .editor-post-save-draft', ( event ) => {
                if ( this.hasWarnedThisSession ) {
                    return;
                }

                const editorContent = wp.data.select( 'core/editor' ).getEditedPostContent() || '';
                const editorTitle = wp.data.select( 'core/editor' ).getEditedPostAttribute( 'title' ) || '';

                if ( ! this.confirmIfFlagged( editorTitle + ' ' + editorContent ) ) {
                    event.stopImmediatePropagation();
                    event.preventDefault();
                } else {
                    this.hasWarnedThisSession = true;
                }
            } );
        }, // End bindGutenberg()


        /**
         * Check content against terms; if flagged, show confirm dialog
         *
         * @param {string} content
         * @return {boolean} true to proceed with save, false to cancel
         */
        confirmIfFlagged: function ( content ) {
            const plainText = $( '<div></div>' ).html( content ).text().toLowerCase();
            const matchedTerms = [];

            this.terms.forEach( ( termData ) => {
                const term = termData.case_sensitive ? termData.term : termData.term.toLowerCase();
                const haystack = termData.case_sensitive ? $( '<div></div>' ).html( content ).text() : plainText;

                if ( haystack.indexOf( term ) !== -1 ) {
                    matchedTerms.push( termData.term );
                }
            } );

            if ( ! matchedTerms.length ) {
                return true;
            }

            const message = ptscanner_warning_data.message + matchedTerms.join( ', ' ) + '. ' + ptscanner_warning_data.confirm;

            return confirm( message );
        }, // End confirmIfFlagged()

    };

    ptscannerEditorWarning.init();

} );