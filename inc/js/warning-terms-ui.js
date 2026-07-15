jQuery( function ( $ ) {

    /**
     * Warning terms controller (Settings page)
     *
     * Simpler than the scanner's terms-ui: no per-term case/strict override,
     * no pills/accordion collapse — just an editable card list serialized to
     * the same JSON shape on form submit.
     */
    const ptscannerWarningTermsUi = {


        /**
         * In-memory term list
         */
        terms: [],


        /**
         * Init
         */
        init: function () {
            if ( typeof ptscanner_data !== 'undefined' && ptscanner_data.savedWarningTerms ) {
                this.terms = ptscanner_data.savedWarningTerms;
            }

            this.renderCards();
            this.bindEvents();
            this.syncHiddenField();
        }, // End init()


        /**
         * Bind DOM events
         */
        bindEvents: function () {
            $( document ).on( 'click', '#ptscanner-add-warning-terms', () => {
                this.addFromTextarea();
            } );

            $( document ).on( 'click', '.ptscanner-warning-term-remove', ( event ) => {
                const index = $( event.currentTarget ).closest( '.ptscanner-term-card' ).data( 'index' );
                this.removeTerm( index );
            } );

            $( document ).on( 'input', '.ptscanner-warning-term-text', ( event ) => {
                const index = $( event.currentTarget ).closest( '.ptscanner-term-card' ).data( 'index' );
                this.terms[ index ].term = $( event.currentTarget ).val();
                this.syncHiddenField();
            } );

            $( document.getElementById( 'ptscanner-warning-terms-json' ) ).closest( 'form' ).on( 'submit', () => {
                this.syncHiddenField();
            } );

            $( document ).on( 'click', '#ptscanner-clear-all-warning-terms', () => {
                if ( ! this.terms.length ) {
                    return;
                }

                if ( ! confirm( 'Clear all terms from the list? This cannot be undone.' ) ) {
                    return;
                }

                this.terms = [];
                this.renderCards();
                this.syncHiddenField();
            } );
        }, // End bindEvents()


        /**
         * Parse the textarea, add new term cards, skip duplicates
         */
        addFromTextarea: function () {
            const textarea = $( '#ptscanner-warning-terms-textarea' );
            const raw = textarea.val();

            if ( ! raw || ! raw.trim() ) {
                return;
            }

            const lines = raw.split( /\r?\n/ );
            const defaultCase = ptscanner_data.defaultCaseSensitive || false;
            const defaultStrict = ptscanner_data.defaultStrict || false;

            lines.forEach( ( line ) => {
                const cleaned = line.trim().replace( /\s+/g, ' ' );

                if ( '' === cleaned || this.isDuplicate( cleaned ) ) {
                    return;
                }

                this.terms.push( {
                    term: cleaned,
                    case_sensitive: defaultCase,
                    strict: defaultStrict,
                } );
            } );

            textarea.val( '' );
            this.renderCards();
            this.syncHiddenField();
        }, // End addFromTextarea()


        /**
         * Check whether a term already exists (case-insensitive compare)
         *
         * @param {string} term
         * @return {boolean}
         */
        isDuplicate: function ( term ) {
            const lowered = term.toLowerCase();

            return this.terms.some( ( existing ) => existing.term.toLowerCase() === lowered );
        }, // End isDuplicate()


        /**
         * Remove a term by index
         *
         * @param {number} index
         */
        removeTerm: function ( index ) {
            this.terms.splice( index, 1 );
            this.renderCards();
            this.syncHiddenField();
        }, // End removeTerm()


        /**
         * Render the editable term cards (text + remove only, no case/strict toggles)
         */
        renderCards: function () {
            const container = $( '#ptscanner-warning-terms-cards' );
            container.empty();

            this.terms.forEach( ( termData, index ) => {
                const card = $( '<div class="ptscanner-term-card" data-index="' + index + '"></div>' );
                const textInput = $( '<input type="text" class="ptscanner-warning-term-text regular-text" />' ).val( termData.term );
                const removeButton = $( '<button type="button" class="button-link ptscanner-warning-term-remove" aria-label="Remove"></button>' ).text( '×' );

                card.append( textInput, removeButton );
                container.append( card );
            } );
        }, // End renderCards()


        /**
         * Serialize the term list into the hidden field before submit
         */
        syncHiddenField: function () {
            $( '#ptscanner-warning-terms-json' ).val( JSON.stringify( this.terms ) );
        }, // End syncHiddenField()

    };

    ptscannerWarningTermsUi.init();

} );