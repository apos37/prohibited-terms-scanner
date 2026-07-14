jQuery( function ( $ ) {

    /**
     * Results controller
     *
     * Handles the admin (server-rendered) results table row actions, and,
     * when present, the front-end shortcode's AJAX-rendered results table
     * (fetch, render, paginate, tab switch) plus shared row actions.
     */
    const ptscannerResults = {


        /**
         * Current status tab shown in the front-end table
         */
        frontStatus: 'flagged',


        /**
         * Current page shown in the front-end table
         */
        frontPage: 1,


        /**
         * Init
         */
        init: function () {
            this.bindEvents();

            if ( $( '#ptscanner-front-results' ).length ) {
                this.loadFrontResults();
            }
        }, // End init()


        /**
         * Bind DOM events
         */
        bindEvents: function () {
            $( document ).on( 'click', '.ptscanner-mark-ok, .ptscanner-front-mark-ok', ( event ) => {
                this.setStatus( event.currentTarget, 'ignored' );
            } );

            $( document ).on( 'click', '.ptscanner-mark-flagged, .ptscanner-front-mark-flagged', ( event ) => {
                this.setStatus( event.currentTarget, 'flagged' );
            } );

            $( document ).on( 'click', '.ptscanner-clear-result, .ptscanner-front-clear-result', ( event ) => {
                this.clearResult( event.currentTarget );
            } );

            $( document ).on( 'click', '.ptscanner-front-tab', ( event ) => {
                this.frontStatus = $( event.currentTarget ).data( 'status' );
                this.frontPage = 1;
                this.loadFrontResults();
            } );

            $( document ).on( 'click', '.ptscanner-front-page-link', ( event ) => {
                event.preventDefault();
                this.frontPage = $( event.currentTarget ).data( 'page' );
                this.loadFrontResults();
            } );

            $( document ).on( 'click', '#ptscanner-clear-errors', ( event ) => {
                if ( ! confirm( 'Clear all logged errors?' ) ) {
                    return;
                }

                const button = $( event.currentTarget );
                button.prop( 'disabled', true );

                $.post( ptscanner_data.ajaxUrl, {
                    action: 'ptscanner_clear_errors',
                    nonce: ptscanner_data.nonce,
                } ).done( ( response ) => {
                    if ( response.success ) {
                        window.location.reload();
                    } else {
                        button.prop( 'disabled', false );
                        alert( response.data.message || 'Could not clear errors.' );
                    }
                } );
            } );
        }, // End bindEvents()


        /**
         * Fetch and render a page of front-end results
         */
        loadFrontResults: function () {
            $( '#ptscanner-front-results' ).show();
            const body = $( '#ptscanner-front-results-body' );
            body.html( '<tr><td colspan="6">' + ( ptscanner_data.strings.loading || 'Loading…' ) + '</td></tr>' );

            $.post( ptscanner_data.ajaxUrl, {
                action: 'ptscanner_get_results',
                nonce: ptscanner_data.nonce,
                status: this.frontStatus,
                page: this.frontPage,
            } ).done( ( response ) => {
                if ( ! response.success ) {
                    body.html( '<tr><td colspan="6">' + ( response.data.message || 'Error loading results.' ) + '</td></tr>' );
                    return;
                }

                this.renderFrontResults( response.data );
            } ).fail( () => {
                body.html( '<tr><td colspan="6">' + ( ptscanner_data.strings.requestFailed || 'Request failed.' ) + '</td></tr>' );
            } );
        }, // End loadFrontResults()


        /**
         * Render the front-end results table body and pagination
         *
         * @param {Object} data
         */
        renderFrontResults: function ( data ) {
            const body = $( '#ptscanner-front-results-body' );
            body.empty();

            if ( ! data.rows.length ) {
                body.html( '<tr><td colspan="6">' + ( ptscanner_data.strings.noResults || 'No results found.' ) + '</td></tr>' );
            } else {
                data.rows.forEach( ( row ) => {
                    body.append( this.buildRow( row ) );
                } );
            }

            this.renderFrontPagination( data.total_pages );
        }, // End renderFrontResults()


        /**
         * Build a single result row for the front-end table
         *
         * @param {Object} row
         * @return {jQuery}
         */
        buildRow: function ( row ) {
            const tr = $( '<tr></tr>' ).attr( 'id', 'ptscanner-front-row-' + row.id ).attr( 'data-id', row.id );

            tr.append( $( '<td></td>' ).append( $( '<strong></strong>' ).text( row.term ) ) );

            const contextCell = $( '<td></td>' ).html( this.highlightTerm( row.context_snippet, row.term ) );
            if ( row.file_page ) {
                contextCell.append( $( '<br>' ) ).append( $( '<em></em>' ).text( 'Page ' + row.file_page ) );
            }
            tr.append( contextCell );

            tr.append( $( '<td></td>' ).text( row.location_label ) );

            const sourceCell = $( '<td></td>' );
            if ( row.highlight_link ) {
                sourceCell.append( $( '<a target="_blank"></a>' ).attr( 'href', row.highlight_link ).text( 'View' ) );
            } else {
                sourceCell.text( '—' );
            }
            tr.append( sourceCell );

            tr.append( $( '<td></td>' ).text( row.created_at ) );

            const actionsCell = $( '<td></td>' );
            if ( 'flagged' === row.status ) {
                actionsCell.append( $( '<button type="button" class="button-link ptscanner-front-mark-ok"></button>' ).attr( 'data-id', row.id ).text( 'Mark as OK' ) );
            } else {
                actionsCell.append( $( '<button type="button" class="button-link ptscanner-front-mark-flagged"></button>' ).attr( 'data-id', row.id ).text( 'Unignore' ) );
            }
            actionsCell.append( ' ' );
            actionsCell.append( $( '<button type="button" class="button-link ptscanner-front-clear-result"></button>' ).attr( 'data-id', row.id ).text( 'Clear' ) );
            tr.append( actionsCell );

            return tr;
        }, // End buildRow()


        /**
         * Wrap the first case-insensitive occurrence of a term in HTML-escaped snippet text
         *
         * @param {string} snippet
         * @param {string} term
         * @return {string}
         */
        highlightTerm: function ( snippet, term ) {
            const div = document.createElement( 'div' );
            div.textContent = snippet;
            const escapedSnippet = div.innerHTML;

            const termDiv = document.createElement( 'div' );
            termDiv.textContent = term;
            const escapedTerm = termDiv.innerHTML;

            if ( ! escapedTerm ) {
                return escapedSnippet;
            }

            const pattern = new RegExp( escapedTerm.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ), 'i' );

            return escapedSnippet.replace( pattern, ( match ) => '<strong class="ptscanner-highlighted-term">' + match + '</strong>' );
        }, // End highlightTerm()


        /**
         * Render simple prev/next + page number pagination for the front-end table
         *
         * @param {number} totalPages
         */
        renderFrontPagination: function ( totalPages ) {
            const container = $( '#ptscanner-front-pagination' );
            container.empty();

            if ( totalPages <= 1 ) {
                return;
            }

            for ( let i = 1; i <= totalPages; i++ ) {
                const link = $( '<a href="#" class="ptscanner-front-page-link"></a>' ).attr( 'data-page', i ).text( i );

                if ( i === this.frontPage ) {
                    link.addClass( 'current' );
                }

                container.append( link ).append( ' ' );
            }
        }, // End renderFrontPagination()


        /**
         * Update a row's status via AJAX, then remove it from the current view
         *
         * @param {HTMLElement} button
         * @param {string} status
         */
        setStatus: function ( button, status ) {
            const id = $( button ).data( 'id' );
            const row = $( button ).closest( 'tr' );

            $( button ).prop( 'disabled', true );

            $.post( ptscanner_data.ajaxUrl, {
                action: 'ptscanner_mark_status',
                nonce: ptscanner_data.nonce,
                id: id,
                status: status,
            } ).done( ( response ) => {
                if ( response.success ) {
                    row.fadeOut( 200, () => row.remove() );
                } else {
                    $( button ).prop( 'disabled', false );
                    alert( response.data.message || 'Could not update status.' );
                }
            } ).fail( () => {
                $( button ).prop( 'disabled', false );
                alert( ptscanner_data.strings.requestFailed || 'Request failed.' );
            } );
        }, // End setStatus()


        /**
         * Clear (delete) a result row after confirmation
         *
         * @param {HTMLElement} button
         */
        clearResult: function ( button ) {
            if ( ! confirm( ptscanner_data.strings.confirmClear ) ) {
                return;
            }

            const id = $( button ).data( 'id' );
            const row = $( button ).closest( 'tr' );

            $( button ).prop( 'disabled', true );

            $.post( ptscanner_data.ajaxUrl, {
                action: 'ptscanner_delete_result',
                nonce: ptscanner_data.nonce,
                id: id,
            } ).done( ( response ) => {
                if ( response.success ) {
                    row.fadeOut( 200, () => row.remove() );
                } else {
                    $( button ).prop( 'disabled', false );
                    alert( response.data.message || 'Could not clear result.' );
                }
            } ).fail( () => {
                $( button ).prop( 'disabled', false );
                alert( ptscanner_data.strings.requestFailed || 'Request failed.' );
            } );
        }, // End clearResult()

    };

    ptscannerResults.init();

    window.ptscannerResults = ptscannerResults;

} );