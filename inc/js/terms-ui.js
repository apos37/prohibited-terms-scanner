jQuery( function ( $ ) {

    /**
     * Terms UI controller (Scanner tab and front-end shortcode)
     *
     * Owns: textarea parsing, card creation/dedup, pill rendering, accordion
     * collapse/expand, and saving the list via AJAX in the shape the Settings
     * class expects: [ { term, case_sensitive, strict } ]
     */
    const ptscannerTermsUi = {


        /**
         * In-memory term list, kept in sync with the DOM cards
         */
        terms: [],


        /**
         * Whether the list has changes that are not saved yet
         */
        isDirty: false,


        /**
         * Localized strings
         */
        strings: {},


        /**
         * Init
         */
        init: function () {
            this.strings = ptscanner_data.strings || {};

            if ( ptscanner_data.savedTerms ) {
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

            $( document ).on( 'click', '#ptscanner-save-terms', () => {
                this.save();
            } );

            $( document ).on( 'click', '.ptscanner-term-remove', ( event ) => {
                this.removeTerm( this.cardIndex( event ) );
            } );

            $( document ).on( 'change', '.ptscanner-term-case', ( event ) => {
                this.terms[ this.cardIndex( event ) ].case_sensitive = $( event.currentTarget ).is( ':checked' );
                this.markDirty();
            } );

            $( document ).on( 'change', '.ptscanner-term-strict', ( event ) => {
                this.terms[ this.cardIndex( event ) ].strict = $( event.currentTarget ).is( ':checked' );
                this.markDirty();
            } );

            $( document ).on( 'input', '.ptscanner-term-text', ( event ) => {
                this.terms[ this.cardIndex( event ) ].term = $( event.currentTarget ).val();
                this.markDirty();
            } );

            $( document ).on( 'click', '.ptscanner-pill-edit', () => {
                this.expandAccordion();
            } );

            $( document ).on( 'click', '#ptscanner-clear-all-terms', () => {
                if ( ! this.terms.length || ! window.confirm( this.strings.confirmClearAllTerms ) ) {
                    return;
                }

                this.terms = [];
                this.renderCards();
                this.markDirty();
            } );

            $( window ).on( 'beforeunload', () => {
                if ( this.isDirty ) {
                    return this.strings.unsavedTerms;
                }
            } );
        }, // End bindEvents()


        /**
         * Get the term index of the card that fired an event
         *
         * @param {Event} event
         * @return {number}
         */
        cardIndex: function ( event ) {
            return $( event.currentTarget ).closest( '.ptscanner-term-card' ).data( 'index' );
        }, // End cardIndex()


        /**
         * Flag unsaved changes
         */
        markDirty: function () {
            this.isDirty = true;
            this.setStatus( '', '' );
        }, // End markDirty()


        /**
         * Parse the textarea, add new term cards, skip duplicates
         */
        addFromTextarea: function () {
            const textarea = $( '#ptscanner-terms-textarea' );
            const raw = textarea.val();

            if ( ! raw || ! raw.trim() ) {
                return;
            }

            let skippedCount = 0;

            raw.split( /\r?\n/ ).forEach( ( line ) => {
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
                    case_sensitive: !! ptscanner_data.defaultCaseSensitive,
                    strict: !! ptscanner_data.defaultStrict,
                } );
            } );

            textarea.val( '' );
            this.renderCards();
            this.markDirty();

            if ( skippedCount > 0 ) {
                this.showNotice( this.strings.duplicateTerm + ' (' + skippedCount + ')' );
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
            this.markDirty();
        }, // End removeTerm()


        /**
         * Render the editable term cards
         */
        renderCards: function () {
            const container = $( '#ptscanner-terms-cards' ).empty();

            this.terms.forEach( ( termData, index ) => {
                const card = $( '<div class="ptscanner-term-card"></div>' ).attr( 'data-index', index );
                const textInput = $( '<input type="text" class="ptscanner-term-text" />' ).val( termData.term );
                const caseLabel = $( '<label class="ptscanner-term-flag"></label>' );
                const caseCheckbox = $( '<input type="checkbox" class="ptscanner-term-case" />' ).prop( 'checked', !! termData.case_sensitive );
                const strictLabel = $( '<label class="ptscanner-term-flag"></label>' );
                const strictCheckbox = $( '<input type="checkbox" class="ptscanner-term-strict" />' ).prop( 'checked', !! termData.strict );
                const removeButton = $( '<button type="button" class="ptscanner-term-remove"></button>' ).attr( 'aria-label', this.strings.removeTerm ).text( '×' );

                caseLabel.append( caseCheckbox, document.createTextNode( ' ' + this.strings.caseSensitiveLabel ) );
                strictLabel.append( strictCheckbox, document.createTextNode( ' ' + this.strings.strictLabel ) );
                card.append( textInput, caseLabel, strictLabel, removeButton );
                container.append( card );
            } );

            $( '#ptscanner-terms-count' ).text( this.terms.length );
            this.updatePillsHidden();
        }, // End renderCards()


        /**
         * Render collapsed pill view, one pill per term with active-flag badges
         */
        renderPills: function () {
            const container = $( '#ptscanner-terms-pills' ).empty();

            if ( this.terms.length > 75 ) {
                const summary = $( '<p class="ptscanner-pills-compact-summary"></p>' ).text( wp.i18n.sprintf( this.strings.termsLoaded, this.terms.length ) + ' ' );

                summary.append( $( '<button type="button" class="ptscanner-pill-edit"></button>' ).text( this.strings.editList ) );
                container.append( summary ).prop( 'hidden', false ).css( 'display', '' );
                return;
            }

            this.terms.forEach( ( termData ) => {
                const pill = $( '<span class="ptscanner-pill"></span>' ).text( termData.term );

                if ( termData.case_sensitive ) {
                    pill.append( $( '<span class="ptscanner-pill-badge"></span>' ).text( this.strings.caseBadge ) );
                }

                if ( termData.strict ) {
                    pill.append( $( '<span class="ptscanner-pill-badge"></span>' ).text( this.strings.strictLabel ) );
                }

                container.append( pill );
            } );

            container.append( $( '<button type="button" class="ptscanner-pill-edit"></button>' ).text( this.strings.edit ) ).prop( 'hidden', false ).css( 'display', '' );
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
            $( '#ptscanner-terms-pills' ).prop( 'hidden', true );
        }, // End expandAccordion()


        /**
         * Keep the pills container hidden while editing in the accordion
         */
        updatePillsHidden: function () {
            if ( $( '.ptscanner-terms-accordion' ).is( '[open]' ) ) {
                $( '#ptscanner-terms-pills' ).prop( 'hidden', true );
            }
        }, // End updatePillsHidden()


        /**
         * Get the current term list as a plain array
         *
         * @return {Array}
         */
        getTerms: function () {
            return this.terms;
        }, // End getTerms()


        /**
         * Save the term list via AJAX
         *
         * @param {Function} onComplete Optional. Called after the request finishes, success or not.
         */
        save: function ( onComplete ) {
            const button = $( '#ptscanner-save-terms' ).prop( 'disabled', true );

            $.post( ptscanner_data.ajaxUrl, {
                action: 'ptscanner_save_terms',
                nonce: ptscanner_data.nonce,
                terms: JSON.stringify( this.terms ),
            } ).done( ( response ) => {
                if ( response.success ) {
                    this.isDirty = false;
                    this.setStatus( this.strings.termsSaved, 'is-success' );
                } else {
                    this.setStatus( this.strings.termsSaveError, 'is-error' );
                }
            } ).fail( () => {
                this.setStatus( this.strings.termsSaveError, 'is-error' );
            } ).always( () => {
                button.prop( 'disabled', false );

                if ( 'function' === typeof onComplete ) {
                    onComplete();
                }
            } );
        }, // End save()


        /**
         * Show the save status next to the term buttons
         *
         * @param {string} message
         * @param {string} className
         */
        setStatus: function ( message, className ) {
            $( '#ptscanner-terms-status' ).removeClass( 'is-success is-error' ).addClass( className ).text( message );
        }, // End setStatus()


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

    // Exposed for scanner.js to read and save the term list and trigger collapse.
    window.ptscannerTermsUi = ptscannerTermsUi;

} );
