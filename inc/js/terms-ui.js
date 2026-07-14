jQuery( function ( $ ) {

    /**
     * Terms UI controller
     *
     * Owns: textarea parsing, card creation/dedup, pill rendering,
     * accordion collapse/expand, and serializing to the JSON shape
     * the Settings class expects: [ { term, case_sensitive, strict } ]
     */
    const ptscannerTermsUi = {


        /**
         * In-memory term list, kept in sync with the DOM cards
         */
        terms: [],


        /**
         * Init
         */
        init: function () {
            if ( typeof ptscanner_data !== 'undefined' && ptscanner_data.savedTerms ) {
                this.terms = ptscanner_data.savedTerms;
            }

            this.renderCards();
            this.bindEvents();
        }, // End init()


        /**
         * Bind DOM events
         */
        bindEvents: function () {
            $( document ).on( 'click', '#ptscanner-add-terms', () => {
                this.addFromTextarea();
            } );

            $( document ).on( 'click', '.ptscanner-term-remove', ( event ) => {
                const index = $( event.currentTarget ).closest( '.ptscanner-term-card' ).data( 'index' );
                this.removeTerm( index );
            } );

            $( document ).on( 'change', '.ptscanner-term-case', ( event ) => {
                const index = $( event.currentTarget ).closest( '.ptscanner-term-card' ).data( 'index' );
                this.terms[ index ].case_sensitive = $( event.currentTarget ).is( ':checked' );
            } );

            $( document ).on( 'change', '.ptscanner-term-strict', ( event ) => {
                const index = $( event.currentTarget ).closest( '.ptscanner-term-card' ).data( 'index' );
                this.terms[ index ].strict = $( event.currentTarget ).is( ':checked' );
            } );

            $( document ).on( 'input', '.ptscanner-term-text', ( event ) => {
                const index = $( event.currentTarget ).closest( '.ptscanner-term-card' ).data( 'index' );
                this.terms[ index ].term = $( event.currentTarget ).val();
            } );

            $( document ).on( 'click', '.ptscanner-pill-edit', () => {
                this.expandAccordion();
            } );
        }, // End bindEvents()


        /**
         * Parse the textarea, add new term cards, skip duplicates
         */
        addFromTextarea: function () {
            const textarea = $( '#ptscanner-terms-textarea' );
            const raw = textarea.val();

            if ( ! raw || ! raw.trim() ) {
                return;
            }

            const lines = raw.split( /\r?\n/ );
            const defaultCase = ptscanner_data.defaultCaseSensitive || false;
            const defaultStrict = ptscanner_data.defaultStrict || false;
            let skippedCount = 0;

            lines.forEach( ( line ) => {
                const cleaned = line.trim().replace( /\s+/g, ' ' );

                if ( '' === cleaned ) {
                    return;
                }

                if ( this.isDuplicate( cleaned ) ) {
                    skippedCount++;
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

            if ( skippedCount > 0 ) {
                this.showNotice( ptscanner_data.strings.duplicateTerm + ' (' + skippedCount + ')' );
            }
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
        }, // End removeTerm()


        /**
         * Render the editable term cards
         */
        renderCards: function () {
            const container = $( '#ptscanner-terms-cards' );
            container.empty();

            this.terms.forEach( ( termData, index ) => {
                const card = $( '<div class="ptscanner-term-card" data-index="' + index + '"></div>' );

                const textInput = $( '<input type="text" class="ptscanner-term-text regular-text" />' ).val( termData.term );
                const caseLabel = $( '<label class="ptscanner-term-flag"></label>' );
                const caseCheckbox = $( '<input type="checkbox" class="ptscanner-term-case" />' ).prop( 'checked', !! termData.case_sensitive );
                const strictLabel = $( '<label class="ptscanner-term-flag"></label>' );
                const strictCheckbox = $( '<input type="checkbox" class="ptscanner-term-strict" />' ).prop( 'checked', !! termData.strict );
                const removeButton = $( '<button type="button" class="button-link ptscanner-term-remove" aria-label="Remove"></button>' ).text( '×' );

                caseLabel.append( caseCheckbox ).append( ' ' + ( ptscanner_data.strings.caseSensitiveLabel || 'Case Sensitive' ) );
                strictLabel.append( strictCheckbox ).append( ' ' + ( ptscanner_data.strings.strictLabel || 'Strict' ) );

                card.append( textInput, caseLabel, strictLabel, removeButton );
                container.append( card );
            } );

            this.updatePillsHidden();
        }, // End renderCards()


        /**
         * Render collapsed pill view, one pill per term with active-flag badges
         */
        renderPills: function () {
            const container = $( '#ptscanner-terms-pills' );
            container.empty();

            const useCompactMode = this.terms.length > 75;

            if ( useCompactMode ) {
                const summary = $( '<p class="ptscanner-pills-compact-summary"></p>' );
                summary.text( this.terms.length + ' terms loaded. ' );

                const editLink = $( '<button type="button" class="button-link ptscanner-pill-edit"></button>' ).text( 'Edit list' );
                summary.append( editLink );
                container.append( summary );
                container.show();
                return;
            }

            this.terms.forEach( ( termData ) => {
                const pill = $( '<span class="ptscanner-pill"></span>' );
                pill.text( termData.term );

                if ( termData.case_sensitive ) {
                    pill.append( $( '<span class="ptscanner-pill-badge">CS</span>' ) );
                }

                if ( termData.strict ) {
                    pill.append( $( '<span class="ptscanner-pill-badge">Strict</span>' ) );
                }

                container.append( pill );
            } );

            const editButton = $( '<button type="button" class="button-link ptscanner-pill-edit"></button>' ).text( 'Edit' );
            container.append( editButton );
            container.show();
        }, // End renderPills()


        /**
         * Collapse the accordion and show pills (called on scan start)
         */
        collapseToSummary: function () {
            $( '.ptscanner-terms-accordion' ).removeAttr( 'open' );
            this.renderPills();
        }, // End collapseToSummary()


        /**
         * Re-expand the accordion for editing, hide pills
         */
        expandAccordion: function () {
            $( '.ptscanner-terms-accordion' ).attr( 'open', 'open' );
            $( '#ptscanner-terms-pills' ).hide();
        }, // End expandAccordion()


        /**
         * Keep the pills container hidden while editing in the accordion
         */
        updatePillsHidden: function () {
            if ( $( '.ptscanner-terms-accordion' ).is( '[open]' ) ) {
                $( '#ptscanner-terms-pills' ).hide();
            }
        }, // End updatePillsHidden()


        /**
         * Get the current term list as a plain array (for saving/serializing)
         *
         * @return {Array}
         */
        getTerms: function () {
            return this.terms;
        }, // End getTerms()


        /**
         * Show a transient inline notice near the terms input
         *
         * @param {string} message
         */
        showNotice: function ( message ) {
            const notice = $( '<p class="ptscanner-inline-notice"></p>' ).text( message );
            $( '.ptscanner-terms-input' ).append( notice );

            setTimeout( () => {
                notice.fadeOut( 300, () => notice.remove() );
            }, 3000 );
        }, // End showNotice()

    };

    ptscannerTermsUi.init();

    // Expose for scanner.js to read the current term list and trigger collapse.
    window.ptscannerTermsUi = ptscannerTermsUi;

} );