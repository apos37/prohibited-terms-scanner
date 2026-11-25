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
         * Batch size used for this run
         */
        batchSize: 0,


        /**
         * Approximate running total of items checked so far this run
         */
        totalScanned: 0,


        /**
         * Running total of inserted rows this run
         */
        totalInserted: 0,


        /**
         * Whether the user has requested cancellation
         */
        cancelRequested: false,


        /**
         * Timestamp (ms) when the current scan run started
         */
        scanStartTime: 0,


        /**
         * Interval ID for the live elapsed-time display
         */
        elapsedInterval: null,


        /**
         * Flat map of location type slug → human label, built once at init
         */
        locationLabels: {},


        /**
         * Init
         */
        init: function () {
            this.renderLocationTypeCheckboxes();
            this.bindEvents();
            this.buildLocationLabels();
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
                const group = $( '<div class="ptscanner-checkbox-group"></div>' );
                const title = $( '<span class="ptscanner-checkbox-group-title"></span>' ).text( groupName.charAt( 0 ).toUpperCase() + groupName.slice( 1 ) );
                const list = $( '<div class="ptscanner-checkboxes"></div>' );

                $.each( types, ( slug, typeData ) => {
                    const label = $( '<label></label>' );
                    const checkbox = $( '<input type="checkbox" name="location_types[]" />' ).val( slug ).prop( 'checked', -1 !== enabled.indexOf( slug ) );

                    label.append( checkbox, document.createTextNode( ' ' + typeData.label ) );
                    list.append( label );
                } );

                container.append( group.append( title, list ) );
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
                alert( ptscanner_data.strings.noLocations );
                return;
            }

            window.ptscannerTermsUi.collapseToSummary();

            this.currentTypeIndex = 0;
            this.currentOffset = 0;
            this.isFirstBatchOfRun = true;
            this.batchSize = parseInt( ptscanner_data.batchSize, 10 ) || 20;
            this.totalScanned = 0;
            this.totalInserted = 0;
            this.cancelRequested = false;
            this.scanStartTime = Date.now();
            this.startElapsedTimer();

            $( '#ptscanner-start-scan' ).prop( 'disabled', true ).prop( 'hidden', true );
            $( '#ptscanner-cancel-scan' ).prop( 'hidden', false ).css( 'display', '' );
            $( '#ptscanner-progress' ).prop( 'hidden', false ).css( 'display', '' );
            $( '.ptscanner-progress-spinner' ).prop( 'hidden', false ).css( 'display', '' );
            $( '#ptscanner-progress-fill' ).removeClass( 'ptscanner-progress-stopped' );
            $( '#ptscanner-scan-errors' ).empty();
            this.updateProgressLabel( ptscanner_data.strings.scanning );

            window.ptscannerTermsUi.save( () => {
                this.runNextBatch();
            } );
        }, // End startScan()


        /**
         * Start a repeating timer that updates the elapsed-time display every second
         */
        startElapsedTimer: function () {
            this.stopElapsedTimer();

            $( '#ptscanner-elapsed-time' ).prop( 'hidden', false ).css( 'display', '' );

            this.elapsedInterval = setInterval( () => {
                const seconds = Math.floor( ( Date.now() - this.scanStartTime ) / 1000 );
                $( '#ptscanner-elapsed-time-text' ).text( this.formatElapsed( seconds ) );
            }, 1000 );
        }, // End startElapsedTimer()


        /**
         * Stop the elapsed-time timer, if running
         */
        stopElapsedTimer: function () {
            if ( this.elapsedInterval ) {
                clearInterval( this.elapsedInterval );
                this.elapsedInterval = null;
            }
        }, // End stopElapsedTimer()


        /**
         * Stop the timer and persist the final duration to the server
         */
        finishElapsedTimer: function () {
            this.stopElapsedTimer();

            const seconds = Math.floor( ( Date.now() - this.scanStartTime ) / 1000 );

            $.post( ptscanner_data.ajaxUrl, {
                action: 'ptscanner_save_scan_duration',
                nonce: ptscanner_data.nonce,
                seconds: seconds,
            } );
        }, // End finishElapsedTimer()


        /**
         * Format a duration in seconds as "Xm Ys" or "Xs"
         *
         * @param {number} totalSeconds
         * @return {string}
         */
        formatElapsed: function ( totalSeconds ) {
            const minutes = Math.floor( totalSeconds / 60 );
            const seconds = totalSeconds % 60;

            if ( minutes > 0 ) {
                return minutes + 'm ' + seconds + 's';
            }

            return seconds + 's';
        }, // End formatElapsed()


        /**
         * Request cancellation; the in-flight batch will finish, then the loop stops
         */
        cancelScan: function () {
            this.cancelRequested = true;
            $( '#ptscanner-cancel-scan' ).prop( 'disabled', true );
            this.updateProgressLabel( ptscanner_data.strings.cancelling );
        }, // End cancelScan()


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
            const failedOffset = this.currentOffset;

            $.post( ptscanner_data.ajaxUrl, {
                action: 'ptscanner_run_batch',
                nonce: ptscanner_data.nonce,
                location_type: currentType,
                offset: this.currentOffset,
                is_first_batch: this.isFirstBatchOfRun ? 'true' : 'false',
            } ).done( ( response ) => {
                this.isFirstBatchOfRun = false;

                if ( ! response.success ) {
                    const message = ( response.data && response.data.message ) ? response.data.message : ptscanner_data.strings.unknownError;
                    this.logError( currentType, failedOffset, message );
                    this.currentOffset += this.batchSize;
                    this.runNextBatch();
                    return;
                }

                this.totalInserted += response.data.inserted;
                this.totalScanned += this.batchSize;
                this.updateProgress( currentType );

                if ( response.data.done ) {
                    this.advanceToNextType();
                } else {
                    this.currentOffset = response.data.next_offset;
                }

                this.runNextBatch();
            } ).fail( () => {
                this.logError( currentType, failedOffset, ptscanner_data.strings.requestFailed );
                this.currentOffset += this.batchSize;
                this.runNextBatch();
            } );
        }, // End runNextBatch()


        /**
         * Flatten the grouped location type registry into a simple slug → label map
         */
        buildLocationLabels: function () {
            this.locationLabels = {};

            $.each( ptscanner_data.locationTypes, ( groupName, types ) => {
                $.each( types, ( slug, typeData ) => {
                    this.locationLabels[ slug ] = typeData.label;
                } );
            } );
        }, // End buildLocationLabels()


        /**
         * Log a visible, human-readable error for a given type/batch
         *
         * @param {string} typeSlug
         * @param {number} offset
         * @param {string} rawMessage
         */
        logError: function ( typeSlug, offset, rawMessage ) {
            const label = this.locationLabels[ typeSlug ] || typeSlug;
            const message = wp.i18n.sprintf( ptscanner_data.strings.scanError, label, offset, rawMessage );

            $( '#ptscanner-scan-errors' ).append( $( '<li class="ptscanner-scan-error"></li>' ).text( message ) );
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
            const label = this.locationLabels[ currentType ] || currentType;

            this.updateProgressLabel( ptscanner_data.strings.scanning + ': ' + wp.i18n.sprintf( ptscanner_data.strings.progressDetail, label, this.totalScanned, this.totalInserted ) );
        }, // End updateProgress()


        /**
         * Update just the progress label text
         *
         * @param {string} text
         */
        updateProgressLabel: function ( text ) {
            $( '#ptscanner-progress-label-text' ).text( text );
        }, // End updateProgressLabel()


        /**
         * Called when all types in the queue have finished
         */
        onRunComplete: function () {
            $( '#ptscanner-progress-fill' ).css( 'width', '100%' );
            $( '.ptscanner-progress-spinner' ).prop( 'hidden', true );
            $( '#ptscanner-progress-fill' ).addClass( 'ptscanner-progress-stopped' );
            this.updateProgressLabel( ptscanner_data.strings.done );
            this.resetControls();
            this.loadSummary();
            this.finishElapsedTimer();

            if ( $( '#ptscanner-front-results' ).length ) {
                window.ptscannerResults.frontPage = 1;
                window.ptscannerResults.loadFrontResults();
            }
        }, // End onRunComplete()


        /**
         * Called when the user cancels mid-run
         */
        onRunCancelled: function () {
            $( '.ptscanner-progress-spinner' ).prop( 'hidden', true );
            $( '#ptscanner-progress-fill' ).addClass( 'ptscanner-progress-stopped' );
            this.updateProgressLabel( ptscanner_data.strings.cancelled );
            this.resetControls();
            this.loadSummary();
            this.finishElapsedTimer();

            if ( $( '#ptscanner-front-results' ).length ) {
                window.ptscannerResults.frontPage = 1;
                window.ptscannerResults.loadFrontResults();
            }
        }, // End onRunCancelled()


        /**
         * Reset the start/cancel button states after a run ends (complete or cancelled)
         */
        resetControls: function () {
            $( '#ptscanner-start-scan' ).prop( 'disabled', false ).prop( 'hidden', false ).css( 'display', '' );
            $( '#ptscanner-cancel-scan' ).prop( 'disabled', false ).prop( 'hidden', true );
        }, // End resetControls()


        /**
         * Refresh the per-term summary table (shortcode only) and open it
         */
        loadSummary: function () {
            if ( ! $( '#ptscanner-persistent-summary-body' ).length ) {
                return;
            }

            window.ptscannerResults.loadSummary();
            $( '#ptscanner-summary-accordion' ).prop( 'open', true );
        }, // End loadSummary()

    };

    ptscannerScanner.init();

} );