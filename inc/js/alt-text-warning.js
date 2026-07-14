jQuery( function ( $ ) {

    /**
     * Alt text warning
     *
     * Warns (never blocks) when an alt text field's value matches a
     * monitored term. Covers the classic attachment edit screen field and
     * the media modal's attachment details panel, both of which use the
     * same input name/class pattern in core.
     */
    const ptscannerAltTextWarning = {


        /**
         * Terms to check against
         */
        terms: [],


        /**
         * Debounce timer handle
         */
        debounceTimer: null,


        /**
         * Init
         */
        init: function () {
            if ( typeof ptscanner_alt_text_warning_data === 'undefined' ) {
                return;
            }

            this.terms = ptscanner_alt_text_warning_data.terms || [];

            if ( ! this.terms.length ) {
                return;
            }

            this.bindEvents();
        }, // End init()


        /**
         * Bind change/blur on known alt text field selectors
         *
         * '#attachment_alt' is the classic attachment edit screen field.
         * '.setting[data-setting="alt"] input' covers the media modal's
         * attachment details panel (Backbone-rendered, no build step needed
         * since we're just delegating a DOM event).
         */
        bindEvents: function () {
            $( document ).on( 'blur', '#attachment_alt, .setting[data-setting="alt"] input', ( event ) => {
                this.checkField( event.currentTarget );
            } );
        }, // End bindEvents()


        /**
         * Check a field's current value, debounced slightly to avoid firing
         * mid-keystroke if a future selector ever binds to 'input' instead of 'blur'
         *
         * @param {HTMLElement} field
         */
        checkField: function ( field ) {
            clearTimeout( this.debounceTimer );

            this.debounceTimer = setTimeout( () => {
                const value = $( field ).val() || '';
                const matches = this.getMatches( value );

                if ( matches.length ) {
                    alert( ptscanner_alt_text_warning_data.message + matches.join( ', ' ) );
                }
            }, 150 );
        }, // End checkField()


        /**
         * Check a value against the term list
         *
         * @param {string} value
         * @return {Array} matched term strings
         */
        getMatches: function ( value ) {
            const matched = [];
            const loweredValue = value.toLowerCase();

            this.terms.forEach( ( termData ) => {
                const term = termData.case_sensitive ? termData.term : termData.term.toLowerCase();
                const haystack = termData.case_sensitive ? value : loweredValue;

                if ( '' !== term && haystack.indexOf( term ) !== -1 ) {
                    matched.push( termData.term );
                }
            } );

            return matched;
        }, // End getMatches()

    };

    ptscannerAltTextWarning.init();

} );