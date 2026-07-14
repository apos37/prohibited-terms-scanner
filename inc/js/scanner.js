jQuery( function ( $ ) {

    /**
     * Scanner controller
     *
     * Owns: saving the term list before a run, looping batches across all
     * enabled location types sequentially, updating the progress bar,
     * surfacing per-type errors, handling cancellation, and rendering the
     * final per-term summary table.
     */
    const ptscannerScanner = {


        /**
         * Location type slugs to scan this run, in order
         */
        typeQueue: [],


        /**
         * Index of the type currently being scanned
         */
        currentTypeIndex: 0,


        /**
         * Current offset within the current type's batch loop
         */
        currentOffset: 0,


        /**
         * Whether this is the very first batch of the entire run (controls wipe)
         */
        isFirstBatchOfRun: true,


        /**
         * Running total of inserted rows this run
         */
        totalInserted: 0,


        /**
         * Whether the user has requested cancellation
         */
        cancelRequested: false,


        /**
         * Init
         */
        init: function () {
            this.renderLocationTypeCheckboxes();
            this.bindEvents();
        }, // End init()


        /**
         * Bind DOM events
         */
        bindEvents: function () {
            $( document ).on( 'click', '#ptscanner-start-scan', () => {
                this.startScan();
            } );

            $( document ).on( 'click', '#ptscanner-cancel-scan', () => {
                this.cancelScan();
            } );
        }, // End bindEvents()


        /**
         * Render the location-type checkboxes from localized registry data
         */
        renderLocationTypeCheckboxes: function () {
            const container = $( '#ptscanner-location-type-checkboxes' );
            container.empty();

            const groups = ptscanner_data.locationTypes;
            const enabled = ptscanner_data.enabledTypes;

            $.each( groups, ( groupName, types ) => {
                const groupWrap = $( '<fieldset class="ptscanner-type-group"></fieldset>' );
                const label = groupName.charAt( 0 ).toUpperCase() + groupName.slice( 1 );
                const legend = $( '<legend></legend>' ).text( label );
                groupWrap.append( legend );

                $.each( types, ( slug, typeData ) => {
                    const isChecked = enabled.indexOf( slug ) !== -1;
                    const label = $( '<label class="ptscanner-type-checkbox"></label>' );
                    const checkbox = $( '<input type="checkbox" />' )
                        .val( slug )
                        .prop( 'checked', isChecked )
                        .attr( 'name', 'location_types[]' );

                    label.append( checkbox ).append( ' ' + typeData.label );
                    groupWrap.append( label );
                } );

                container.append( groupWrap );
            } );
        }, // End renderLocationTypeCheckboxes()


        /**
         * Get the currently checked location type slugs
         *
         * @return {Array}
         */
        getSelectedTypes: function () {
            const selected = [];

            $( '#ptscanner-location-type-checkboxes input:checked' ).each( function () {
                selected.push( $( this ).val() );
            } );

            return selected;
        }, // End getSelectedTypes()


        /**
         * Start a full scan run
         */
        startScan: function () {
            const terms = window.ptscannerTermsUi.getTerms();

            if ( ! terms || ! terms.length ) {
                alert( ptscanner_data.strings.noTerms );
                return;
            }

            this.typeQueue = this.getSelectedTypes();

            if ( ! this.typeQueue.length ) {
                return;
            }

            window.ptscannerTermsUi.collapseToSummary();

            this.currentTypeIndex = 0;
            this.currentOffset = 0;
            this.isFirstBatchOfRun = true;
            this.totalInserted = 0;
            this.cancelRequested = false;

            $( '#ptscanner-start-scan' ).prop( 'disabled', true ).hide();
            $( '#ptscanner-cancel-scan' ).show();
            $( '#ptscanner-progress' ).show();
            $( '#ptscanner-scan-errors' ).empty();
            $( '#ptscanner-summary' ).hide();
            this.updateProgressLabel( ptscanner_data.strings.scanning );

            this.saveTerms( terms, () => {
                this.runNextBatch();
            } );
        }, // End startScan()


        /**
         * Request cancellation; the in-flight batch will finish, then the loop stops
         */
        cancelScan: function () {
            this.cancelRequested = true;
            $( '#ptscanner-cancel-scan' ).prop( 'disabled', true );
            this.updateProgressLabel( ptscanner_data.strings.cancelling || 'Cancelling…' );
        }, // End cancelScan()


        /**
         * Save the current term list to the server before scanning
         *
         * @param {Array} terms
         * @param {Function} onComplete
         */
        saveTerms: function ( terms, onComplete ) {
            $.post( ptscanner_data.ajaxUrl, {
                action: 'ptscanner_save_terms',
                nonce: ptscanner_data.nonce,
                terms: JSON.stringify( terms ),
            } ).always( () => {
                onComplete();
            } );
        }, // End saveTerms()


        /**
         * Run the next batch in the queue, advancing types/offset as needed
         */
        runNextBatch: function () {
            if ( this.cancelRequested ) {
                this.onRunCancelled();
                return;
            }

            if ( this.currentTypeIndex >= this.typeQueue.length ) {
                this.onRunComplete();
                return;
            }

            const currentType = this.typeQueue[ this.currentTypeIndex ];

            $.post( ptscanner_data.ajaxUrl, {
                action: 'ptscanner_run_batch',
                nonce: ptscanner_data.nonce,
                location_type: currentType,
                offset: this.currentOffset,
                is_first_batch: this.isFirstBatchOfRun ? 'true' : 'false',
            } ).done( ( response ) => {
                this.isFirstBatchOfRun = false;

                if ( ! response.success ) {
                    const message = ( response.data && response.data.message ) ? response.data.message : 'Unknown error.';
                    this.logError( currentType, message );
                    this.advanceToNextType();
                    this.runNextBatch();
                    return;
                }

                this.totalInserted += response.data.inserted;
                this.updateProgress( currentType );

                if ( response.data.done ) {
                    this.advanceToNextType();
                } else {
                    this.currentOffset = response.data.next_offset;
                }

                this.runNextBatch();
            } ).fail( () => {
                this.logError( currentType, ptscanner_data.strings.requestFailed || 'Request failed (network or server error).' );
                this.advanceToNextType();
                this.runNextBatch();
            } );
        }, // End runNextBatch()


        /**
         * Log a visible error for a given type
         *
         * @param {string} typeSlug
         * @param {string} message
         */
        logError: function ( typeSlug, message ) {
            const item = $( '<li class="ptscanner-scan-error"></li>' );
            item.text( typeSlug + ': ' + message );
            $( '#ptscanner-scan-errors' ).append( item );
        }, // End logError()


        /**
         * Move to the next location type in the queue, resetting offset
         */
        advanceToNextType: function () {
            this.currentTypeIndex++;
            this.currentOffset = 0;
        }, // End advanceToNextType()


        /**
         * Update the progress bar and label
         *
         * @param {string} currentType
         */
        updateProgress: function ( currentType ) {
            const percent = Math.round( ( ( this.currentTypeIndex + 1 ) / this.typeQueue.length ) * 100 );

            $( '#ptscanner-progress-fill' ).css( 'width', percent + '%' );
            this.updateProgressLabel( ptscanner_data.strings.scanning + ': ' + currentType + ' (' + this.totalInserted + ' found so far)' );
        }, // End updateProgress()


        /**
         * Update just the progress label text
         *
         * @param {string} text
         */
        updateProgressLabel: function ( text ) {
            $( '#ptscanner-progress-label' ).text( text );
        }, // End updateProgressLabel()


        /**
         * Called when all types in the queue have finished
         */
        onRunComplete: function () {
            $( '#ptscanner-progress-fill' ).css( 'width', '100%' );
            this.updateProgressLabel( ptscanner_data.strings.done );
            this.resetControls();
            this.loadSummary();

            if ( $( '#ptscanner-front-results' ).length ) {
                window.ptscannerResults.frontPage = 1;
                window.ptscannerResults.loadFrontResults();
            }
        }, // End onRunComplete()


        /**
         * Called when the user cancels mid-run
         */
        onRunCancelled: function () {
            this.updateProgressLabel( ptscanner_data.strings.cancelled || 'Scan cancelled. Results so far are saved.' );
            this.resetControls();
            this.loadSummary();

            if ( $( '#ptscanner-front-results' ).length ) {
                window.ptscannerResults.frontPage = 1;
                window.ptscannerResults.loadFrontResults();
            }
        }, // End onRunCancelled()


        /**
         * Reset the start/cancel button states after a run ends (complete or cancelled)
         */
        resetControls: function () {
            $( '#ptscanner-start-scan' ).prop( 'disabled', false ).show();
            $( '#ptscanner-cancel-scan' ).prop( 'disabled', false ).hide();
        }, // End resetControls()


        /**
         * Fetch and render the per-term summary table
         */
        loadSummary: function () {
            $.post( ptscanner_data.ajaxUrl, {
                action: 'ptscanner_get_summary',
                nonce: ptscanner_data.nonce,
            } ).done( ( response ) => {
                if ( ! response.success ) {
                    return;
                }

                this.renderSummary( response.data.summary );
            } );
        }, // End loadSummary()


        /**
         * Render the summary table
         *
         * @param {Array} summary Array of [ term, count ]
         */
        renderSummary: function ( summary ) {
            const body = $( '#ptscanner-summary-body' );
            body.empty();

            if ( ! summary.length ) {
                const row = $( '<tr></tr>' );
                row.append( $( '<td colspan="2"></td>' ).text( 'No matches found.' ) );
                body.append( row );
                $( '#ptscanner-summary' ).show();
                return;
            }

            summary.forEach( ( entry ) => {
                const row = $( '<tr></tr>' );
                row.append( $( '<td></td>' ).text( entry.term ) );
                row.append( $( '<td></td>' ).text( entry.count ) );
                body.append( row );
            } );

            $( '#ptscanner-summary' ).show();
        }, // End renderSummary()

    };

    ptscannerScanner.init();

} );