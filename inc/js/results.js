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
                const isAdmin = $( event.currentTarget ).hasClass( 'ptscanner-mark-ok' );
                this.setStatus( event.currentTarget, 'ignored', isAdmin );
            } );

            $( document ).on( 'click', '.ptscanner-mark-flagged, .ptscanner-front-mark-flagged', ( event ) => {
                const isAdmin = $( event.currentTarget ).hasClass( 'ptscanner-mark-flagged' );
                this.setStatus( event.currentTarget, 'flagged', isAdmin );
            } );

            $( document ).on( 'click', '.ptscanner-clear-result, .ptscanner-front-clear-result', ( event ) => {
                const isAdmin = $( event.currentTarget ).hasClass( 'ptscanner-clear-result' );
                this.clearResult( event.currentTarget, isAdmin );
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

            $( document ).on( 'click', '#ptscanner-clear-all', ( event ) => {
                const button = $( event.currentTarget );
                const status = button.data( 'status' );
                const label = 'flagged' === status ? 'flagged results' : 'ignored results';

                if ( ! confirm( 'Clear all ' + label + '? This cannot be undone.' ) ) {
                    return;
                }

                button.prop( 'disabled', true );

                $.post( ptscanner_data.ajaxUrl, {
                    action: 'ptscanner_clear_all',
                    nonce: ptscanner_data.nonce,
                    status: status,
                } ).done( ( response ) => {
                    if ( response.success ) {
                        window.location.reload();
                    } else {
                        button.prop( 'disabled', false );
                        alert( response.data.message || 'Could not clear results.' );
                    }
                } ).fail( () => {
                    button.prop( 'disabled', false );
                    alert( ptscanner_data.strings.requestFailed || 'Request failed.' );
                } );
            } );

            $( document ).on( 'click', '#ptscanner-front-clear-all', ( event ) => {
                const status = this.frontStatus;
                const label = 'flagged' === status ? 'flagged results' : 'ignored results';

                if ( ! confirm( 'Clear all ' + label + '? This cannot be undone.' ) ) {
                    return;
                }

                const button = $( event.currentTarget );
                button.prop( 'disabled', true );

                $.post( ptscanner_data.ajaxUrl, {
                    action: 'ptscanner_clear_all',
                    nonce: ptscanner_data.nonce,
                    status: status,
                } ).done( ( response ) => {
                    button.prop( 'disabled', false );

                    if ( response.success ) {
                        this.loadFrontResults();
                    } else {
                        alert( response.data.message || 'Could not clear results.' );
                    }
                } ).fail( () => {
                    button.prop( 'disabled', false );
                    alert( ptscanner_data.strings.requestFailed || 'Request failed.' );
                } );
            } );

            $( document ).on( 'click', '.ptscanner-remove-omit', ( event ) => {
                const button = $( event.currentTarget );
                const row = button.closest( 'tr' );

                $.post( ptscanner_data.ajaxUrl, {
                    action: 'ptscanner_toggle_omit',
                    nonce: ptscanner_data.nonce,
                    id: button.data( 'id' ),
                    type: button.data( 'type' ),
                    omit: '0',
                } ).done( ( response ) => {
                    if ( response.success ) {
                        row.fadeOut( 200, () => row.remove() );
                    } else {
                        alert( response.data.message || 'Could not remove.' );
                    }
                } );
            } );

            $( document ).on( 'click', '.ptscanner-toggle-omit', ( event ) => {
                event.preventDefault();

                const link = $( event.currentTarget );
                const isOmitted = '1' === link.data( 'omitted' );

                $.post( ptscanner_data.ajaxUrl, {
                    action: 'ptscanner_toggle_omit',
                    nonce: ptscanner_data.nonce,
                    id: link.data( 'id' ),
                    type: link.data( 'type' ),
                    omit: isOmitted ? '0' : '1',
                } ).done( ( response ) => {
                    if ( response.success ) {
                        link.data( 'omitted', isOmitted ? '0' : '1' );
                        link.text( isOmitted ? 'Omit' : 'Unomit' );
                    } else {
                        alert( response.data.message || 'Could not update.' );
                    }
                } );
            } );
        }, // End bindEvents()


        /**
         * Decrement the admin menu's red count bubble by 1, purely visual —
         * the actual server-side count is already accurate via cache
         * invalidation; this just avoids needing a full page reload to see
         * the number change. Only targets the wp-admin menu, never the
         * front-end shortcode (which has no such badge).
         */
        decrementMenuBadge: function () {
            const badges = document.querySelectorAll( '#adminmenu .update-plugins .update-count' );

            badges.forEach( function ( badge ) {
                const current = parseInt( badge.textContent, 10 );

                if ( isNaN( current ) ) {
                    return;
                }

                const next = Math.max( 0, current - 1 );
                badge.textContent = next;

                if ( 0 === next ) {
                    badge.closest( '.update-plugins' ).remove();
                }
            } );
        }, // End decrementMenuBadge()


        /**
         * Increment the admin menu's red count bubble by 1 — used when an
         * action on the "Marked as OK" tab returns an item to flagged status
         * (Unignore) or removes an ignored item (Clear), both of which
         * should visually restore/adjust the flagged count.
         */
        incrementMenuBadge: function () {
            const badges = document.querySelectorAll( '#adminmenu .update-plugins .update-count' );

            if ( badges.length ) {
                badges.forEach( function ( badge ) {
                    const current = parseInt( badge.textContent, 10 );
                    badge.textContent = isNaN( current ) ? 1 : current + 1;
                } );

                return;
            }

            // No badge currently exists (count was at 0) — create one on both menu items.
            const menuItems = document.querySelectorAll( '#adminmenu .toplevel_page_prohibited-terms-scanner > a, #adminmenu a[href*="page=prohibited-terms-scanner_results"]' );

            menuItems.forEach( function ( link ) {
                const bubble = document.createElement( 'span' );
                bubble.className = 'update-plugins count-1';
                bubble.innerHTML = '<span class="update-count">1</span>';
                link.appendChild( bubble );
            } );
        }, // End incrementMenuBadge()


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

            if ( row.source_title ) {
                sourceCell.append( $( '<strong></strong>' ).text( row.source_title ) ).append( $( '<br>' ) );
            }

            if ( row.highlight_link ) {
                sourceCell.append( $( '<a target="_blank"></a>' ).attr( 'href', row.highlight_link ).text( 'View' ) );
            } else {
                sourceCell.text( sourceCell.text() + '—' );
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
         * @param {boolean} isAdminBadgeTarget Whether to decrement the wp-admin menu badge (only for "Mark as OK", not "Unignore")
         */
        setStatus: function ( button, status, isAdminBadgeTarget ) {
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
                    if ( isAdminBadgeTarget && 'ignored' === status ) {
                        this.decrementMenuBadge();
                    } else if ( isAdminBadgeTarget && 'flagged' === status ) {
                        this.incrementMenuBadge();
                    }

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
         * @param {boolean} isAdminBadgeTarget Whether to adjust the wp-admin menu badge
         */
        clearResult: function ( button, isAdminBadgeTarget ) {
            if ( ! confirm( ptscanner_data.strings.confirmClear ) ) {
                return;
            }

            const id = $( button ).data( 'id' );
            const row = $( button ).closest( 'tr' );
            const isIgnoredTab = $( '.subsubsub a.current' ).text().indexOf( 'Marked as OK' ) !== -1;

            $( button ).prop( 'disabled', true );

            $.post( ptscanner_data.ajaxUrl, {
                action: 'ptscanner_delete_result',
                nonce: ptscanner_data.nonce,
                id: id,
            } ).done( ( response ) => {
                if ( response.success ) {
                    if ( isAdminBadgeTarget && ! isIgnoredTab ) {
                        this.decrementMenuBadge();
                    }

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