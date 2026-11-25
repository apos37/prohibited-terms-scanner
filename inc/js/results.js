jQuery( function ( $ ) {

    /**
     * Results controller
     *
     * Handles the admin (server-rendered) results table row actions, and,
     * when present, the front-end shortcode's AJAX-rendered results table
     * (fetch, render, paginate, tab switch) plus shared row actions.
     */
    const ptscannerResults = {


        frontStatus: 'flagged',
        frontPage: 1,
        frontPagesPage: 1,
        frontExtLinksPage: 1,
        frontExtLinksFilter: 'all',
        frontTermFilter: '',
        frontLocationFilter: '',


        /**
         * Init
         */
        init: function () {
            this.bindEvents();

            if ( $( '#ptscanner-persistent-summary-body' ).length ) {
                this.loadSummary();
            }

            if ( $( '#ptscanner-front-results' ).length ) {
                $( '.ptscanner-front-tab[data-status="flagged"]' ).addClass( 'active' );
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
                const tab = $( event.currentTarget );

                $( '.ptscanner-front-tab' ).removeClass( 'active' );
                tab.addClass( 'active' );

                this.frontStatus = tab.data( 'status' );
                this.frontPage = 1;
                this.loadFrontResults();
            } );

            $( document ).on( 'change', '#ptscanner-front-term-filter', ( event ) => {
                this.frontTermFilter = $( event.currentTarget ).val();
                this.frontPage = 1;
                this.loadFrontResults();
            } );

            $( document ).on( 'change', '#ptscanner-front-location-filter', ( event ) => {
                this.frontLocationFilter = $( event.currentTarget ).val();
                this.frontPage = 1;
                this.loadFrontResults();
            } );

            $( document ).on( 'click', '.ptscanner-front-page-link', ( event ) => {
                event.preventDefault();

                const link = $( event.currentTarget );

                if ( 'pages' === link.data( 'context' ) ) {
                    this.frontPagesPage = link.data( 'page' );
                    this.loadFrontPages();
                    return;
                }

                this.frontPage = link.data( 'page' );
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
                const id = link.data( 'id' );
                const type = link.data( 'type' );
                const self = this;

                if ( ! isOmitted ) {
                    if ( ! confirm( 'Omit this source from all future scans? Existing flagged results for this source will also be cleared.' ) ) {
                        return;
                    }
                }

                $.post( ptscanner_data.ajaxUrl, {
                    action: 'ptscanner_toggle_omit',
                    nonce: ptscanner_data.nonce,
                    id: id,
                    type: type,
                    omit: isOmitted ? '0' : '1',
                } ).done( ( response ) => {
                    if ( ! response.success ) {
                        alert( response.data.message || 'Could not update.' );
                        return;
                    }

                    if ( ! isOmitted ) {
                        $( 'tr[data-id]' ).each( function () {
                            const row = $( this );
                            const rowOmitLink = row.find( '.ptscanner-toggle-omit' );

                            if ( rowOmitLink.length && rowOmitLink.data( 'id' ) === id && rowOmitLink.data( 'type' ) === type ) {
                                self.decrementMenuBadge();
                                row.fadeOut( 200 );
                                setTimeout( () => row.remove(), 200 );
                            }
                        } );
                    } else {
                        link.data( 'omitted', '0' );
                        link.text( 'Omit' );
                    }
                } );
            } );

            $( document ).on( 'click', '.ptscanner-front-main-tab', ( event ) => {
                const tab = $( event.currentTarget );
                const panel = tab.data( 'panel' );

                $( '.ptscanner-front-main-tab' ).removeClass( 'active' );
                tab.addClass( 'active' );

                $( '#ptscanner-front-results' ).toggle( 'results' === panel );
                $( '#ptscanner-front-pages' ).toggle( 'pages' === panel );
                $( '#ptscanner-front-external-links' ).toggle( 'external-links' === panel );

                if ( 'pages' === panel ) {
                    this.loadFrontPages();
                } else if ( 'external-links' === panel ) {
                    this.loadFrontExtLinks();
                }
            } );

            $( document ).on( 'click', '.ptscanner-ext-links-front-filter-tab', ( event ) => {
                const tab = $( event.currentTarget );

                $( '.ptscanner-ext-links-front-filter-tab' ).removeClass( 'active' );
                tab.addClass( 'active' );

                this.frontExtLinksFilter = tab.data( 'filter' );
                this.frontExtLinksPage = 1;
                this.loadFrontExtLinks();
            } );

            $( document ).on( 'click', '.ptscanner-front-ext-links-page-link', ( event ) => {
                event.preventDefault();
                this.frontExtLinksPage = $( event.currentTarget ).data( 'page' );
                this.loadFrontExtLinks();
            } );

            $( document ).on( 'click', '.ptscanner-ext-link-save-note', ( event ) => {
                const button = $( event.currentTarget );
                const row = button.closest( 'tr' );
                const field = row.find( '.ptscanner-ext-link-note' );

                this.saveExtLinkNote( field.data( 'id' ), field.val(), button );
            } );

            $( document ).on( 'blur', '.ptscanner-ext-link-note', ( event ) => {
                const field = $( event.currentTarget );

                this.saveExtLinkNote( field.data( 'id' ), field.val() );
            } );

            $( document ).on( 'click', '.ptscanner-pages-toggle-omit', ( event ) => {
                event.preventDefault();

                const link = $( event.currentTarget );
                const row = link.closest( 'tr' );
                const spinner = row.find( '.ptscanner-inline-spinner' );
                const isOmitted = '1' === link.data( 'omitted' );
                const id = link.data( 'id' );
                const type = link.data( 'type' );

                link.addClass( 'ptscanner-pending' );
                spinner.addClass( 'ptscanner-spinner-active' );

                $.post( ptscanner_data.ajaxUrl, {
                    action: 'ptscanner_toggle_omit',
                    nonce: ptscanner_data.nonce,
                    id: id,
                    type: type,
                    omit: isOmitted ? '0' : '1',
                } ).done( ( response ) => {
                    link.removeClass( 'ptscanner-pending' );
                    spinner.removeClass( 'ptscanner-spinner-active' );

                    if ( ! response.success ) {
                        alert( response.data.message || 'Could not update.' );
                        return;
                    }

                    const nowOmitted = ! isOmitted;
                    link.data( 'omitted', nowOmitted ? '1' : '0' );
                    link.text( nowOmitted ? 'Un-Omit' : 'Omit Page' );
                    row.toggleClass( 'ptscanner-row-omitted', nowOmitted );
                } ).fail( () => {
                    link.removeClass( 'ptscanner-pending' );
                    spinner.removeClass( 'ptscanner-spinner-active' );
                    alert( ptscanner_data.strings.requestFailed || 'Request failed.' );
                } );
            } );

            $( document ).on( 'click', '.ptscanner-save-note', ( event ) => {
                const button = $( event.currentTarget );
                const row = button.closest( 'tr' );
                const field = row.find( '.ptscanner-note-field' );

                this.saveNote( field.data( 'id' ), field.val(), button );
            } );

            $( document ).on( 'blur', '.ptscanner-note-field', ( event ) => {
                const field = $( event.currentTarget );

                this.saveNote( field.data( 'id' ), field.val() );
            } );

            $( document ).on( 'click', '.ptscanner-check-media-usage', ( event ) => {
                const button = $( event.currentTarget );
                const row = button.closest( 'tr' );
                const resultSpan = row.find( '.ptscanner-media-usage-result' );

                button.prop( 'disabled', true );
                const spinner = $( '<span class="ptscanner-inline-spinner ptscanner-spinner-active"></span>' );
                button.after( spinner );

                $.post( ptscanner_data.ajaxUrl, {
                    action: 'ptscanner_check_media_usage',
                    nonce: ptscanner_data.nonce,
                    attachment_id: button.data( 'id' ),
                } ).done( ( response ) => {
                    button.prop( 'disabled', false );
                    spinner.remove();

                    if ( ! response.success ) {
                        resultSpan.text( response.data.message || 'Error checking usage.' );
                        return;
                    }

                    if ( ! response.data.used ) {
                        resultSpan.html( '<strong style="color:#d63638;">Not used anywhere — safe to omit.</strong>' );
                    } else {
                        const items = response.data.locations.map( ( loc ) => {
                            const statusLabel = 'publish' === loc.status ? '' : ' <em>(' + loc.status + ')</em>';
                            return '<li><a href="' + loc.link + '" target="_blank">' + loc.title + '</a>' + statusLabel + '</li>';
                        } );

                        resultSpan.html( '<strong>Used on:</strong><ul class="ptscanner-media-usage-list">' + items.join( '' ) + '</ul>' );
                    }

                    resultSpan.addClass( 'ptscanner-media-usage-highlight' );
                    setTimeout( () => resultSpan.removeClass( 'ptscanner-media-usage-highlight' ), 2100 );
                } ).fail( () => {
                    button.prop( 'disabled', false );
                    spinner.remove();
                    resultSpan.text( ptscanner_data.strings.requestFailed || 'Request failed.' );
                } );
            } );
        }, // End bindEvents()


        /**
         * Load the summary table in the admin results page
         */
        loadSummary: function () {
            $.post( ptscanner_data.ajaxUrl, {
                action: 'ptscanner_get_summary',
                nonce: ptscanner_data.nonce,
            } ).done( ( response ) => {
                const body = $( '#ptscanner-persistent-summary-body' );
                body.empty();

                if ( ! response.success || ! response.data.summary.length ) {
                    body.html( '<tr><td colspan="2">No flagged terms.</td></tr>' );
                    return;
                }

                response.data.summary.forEach( ( row ) => {
                    body.append(
                        $( '<tr></tr>' )
                            .append( $( '<td></td>' ).text( row.term ) )
                            .append( $( '<td></td>' ).text( row.count ) )
                    );
                } );
            } );
        }, // End loadSummary()


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
            body.html( '<tr><td colspan="7">' + ( ptscanner_data.strings.loading || 'Loading…' ) + '</td></tr>' );

            $.post( ptscanner_data.ajaxUrl, {
                action: 'ptscanner_get_results',
                nonce: ptscanner_data.nonce,
                status: this.frontStatus,
                page: this.frontPage,
                term_filter: this.frontTermFilter,
                location_filter: this.frontLocationFilter,
            } ).done( ( response ) => {
                if ( ! response.success ) {
                    body.html( '<tr><td colspan="7">' + ( response.data.message || 'Error loading results.' ) + '</td></tr>' );
                    return;
                }

                this.renderFrontResults( response.data );
            } ).fail( () => {
                body.html( '<tr><td colspan="7">' + ( ptscanner_data.strings.requestFailed || 'Request failed.' ) + '</td></tr>' );
            } );
        }, // End loadFrontResults()


        /**
         * Render the front-end results table body and pagination
         *
         * @param {Object} data
         */
        renderFrontResults: function ( data ) {
            $( '.ptscanner-front-tab[data-status="flagged"]' ).text( 'Flagged (' + data.total_flagged + ')' );
            $( '.ptscanner-front-tab[data-status="ignored"]' ).text( 'Marked as OK (' + data.total_ignored + ')' );

            const termSelect = $( '#ptscanner-front-term-filter' );
            const currentTermVal = termSelect.val();
            termSelect.empty().append( '<option value="">All Terms</option>' );
            data.distinct_terms.forEach( ( term ) => {
                termSelect.append( $( '<option></option>' ).val( term ).text( term ) );
            } );
            termSelect.val( currentTermVal );

            const locationSelect = $( '#ptscanner-front-location-filter' );
            const currentLocationVal = locationSelect.val();
            locationSelect.empty().append( '<option value="">All Locations</option>' );
            $.each( data.distinct_locations, ( slug, label ) => {
                locationSelect.append( $( '<option></option>' ).val( slug ).text( label ) );
            } );
            locationSelect.val( currentLocationVal );

            const body = $( '#ptscanner-front-results-body' );
            body.empty();

            if ( ! data.rows.length ) {
                body.html( '<tr><td colspan="7">' + ( ptscanner_data.strings.noResults || 'No results found.' ) + '</td></tr>' );
            } else {
                data.rows.forEach( ( row ) => {
                    body.append( this.buildRow( row ) );
                } );
            }

            this.renderFrontPagination( data.total_pages );
        }, // End renderFrontResults()


        loadFrontPages: function () {
            const body = $( '#ptscanner-front-pages-body' );
            body.html( '<tr><td colspan="3">' + ( ptscanner_data.strings.loading || 'Loading…' ) + '</td></tr>' );

            $.post( ptscanner_data.ajaxUrl, {
                action: 'ptscanner_get_pages_list',
                nonce: ptscanner_data.nonce,
                page: this.frontPagesPage,
            } ).done( ( response ) => {
                if ( ! response.success ) {
                    body.html( '<tr><td colspan="3">' + ( response.data.message || 'Error loading pages.' ) + '</td></tr>' );
                    return;
                }

                this.renderFrontPages( response.data );
            } ).fail( () => {
                body.html( '<tr><td colspan="3">' + ( ptscanner_data.strings.requestFailed || 'Request failed.' ) + '</td></tr>' );
            } );
        }, // End loadFrontPages()


        /**
         * Render the front-end pages table body and pagination
         *
         * @param {Object} data
         */
        renderFrontPages: function ( data ) {
            const body = $( '#ptscanner-front-pages-body' );
            body.empty();

            if ( ! data.rows.length ) {
                body.html( '<tr><td colspan="3">' + ( ptscanner_data.strings.noResults || 'No pages found.' ) + '</td></tr>' );
            } else {
                data.rows.forEach( ( row ) => {
                    body.append( this.buildPageRow( row ) );
                } );
            }

            this.renderFrontPagesPagination( data.total_pages );
        }, // End renderFrontPages()


        /**
         * Build a single page row for the front-end table
         * 
         * @param {Object} row
         * @return {jQuery}
         */
        buildPageRow: function ( row ) {
            const tr = $( '<tr></tr>' ).attr( 'id', 'ptscanner-front-page-row-' + row.id ).toggleClass( 'ptscanner-row-omitted', row.is_omitted );

            tr.append( $( '<td></td>' ).append( $( '<a target="_blank"></a>' ).attr( 'href', row.permalink ).text( row.title ) ) );
            tr.append( $( '<td></td>' ).text( row.post_type ) );

            const statusCell = $( '<td></td>' );
            statusCell.append(
                $( '<a href="#" class="ptscanner-pages-toggle-omit"></a>' )
                    .attr( 'data-id', row.id )
                    .attr( 'data-type', 'post' )
                    .attr( 'data-omitted', row.is_omitted ? '1' : '0' )
                    .text( row.is_omitted ? 'Un-Omit' : 'Omit Page' )
            );
            statusCell.append( $( '<span class="ptscanner-inline-spinner"></span>' ) );
            tr.append( statusCell );

            return tr;
        }, // End buildPageRow()


        /**
         * Render simple prev/next + page number pagination for the front-end pages table
         *
         * @param {number} totalPages
         */
        renderFrontPagesPagination: function ( totalPages ) {
            console.log( 'renderFrontPagesPagination called with', totalPages, typeof totalPages );

            const container = $( '#ptscanner-front-pages-pagination' );
            console.log( 'container found:', container.length );

            container.empty();

            if ( totalPages <= 1 ) {
                console.log( 'bailing early, totalPages <= 1' );
                return;
            }

            for ( let i = 1; i <= totalPages; i++ ) {
                const link = $( '<a href="#"></a>' )
                    .addClass( 'ptscanner-front-page-link' )
                    .attr( 'data-page', i )
                    .attr( 'data-context', 'pages' )
                    .text( i );

                if ( i === this.frontPagesPage ) {
                    link.addClass( 'current' );
                }

                container.append( link ).append( ' ' );
            }

            console.log( 'final container html:', container.html() );
        }, // End renderFrontPagesPagination()


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
                sourceCell.append( $( '<a target="_blank"></a>' ).attr( 'href', row.highlight_link ).text( 'View Source' ) ).append( ' | ' );
            } else {
                sourceCell.append( '— ' );
            }

            sourceCell.append(
                $( '<a href="#" class="ptscanner-toggle-omit"></a>' )
                    .attr( 'data-id', row.source_id )
                    .attr( 'data-type', row.source_type )
                    .attr( 'data-omitted', '0' )
                    .text( 'Omit Source' )
            );

            tr.append( sourceCell );

            const noteCell = $( '<td></td>' );
            noteCell.append(
                $( '<textarea class="ptscanner-note-field" rows="2"></textarea>' )
                    .attr( 'data-id', row.id )
                    .attr( 'placeholder', 'Add a note…' )
                    .val( row.note || '' )
            );
            noteCell.append( $( '<br>' ) );
            noteCell.append(
                $( '<button type="button" class="button-link ptscanner-save-note"></button>' )
                    .attr( 'data-id', row.id )
                    .text( 'Save Note' )
            );
            tr.append( noteCell );

            tr.append( $( '<td></td>' ).text( this.formatDate( row.created_at ) ) );

            const actionsCell = $( '<td></td>' );
            if ( 'flagged' === row.status ) {
                actionsCell.append( $( '<button type="button" class="button-link ptscanner-front-mark-ok"></button>' ).attr( 'data-id', row.id ).text( 'Mark as OK' ) );
            } else {
                actionsCell.append( $( '<button type="button" class="button-link ptscanner-front-mark-flagged"></button>' ).attr( 'data-id', row.id ).text( 'Unignore' ) );
            }
            actionsCell.append( ' ' );
            actionsCell.append( $( '<button type="button" class="button-link ptscanner-front-clear-result"></button>' ).attr( 'data-id', row.id ).text( 'Clear' ) );

            if ( 'filename' === row.location_type || 'alt_text' === row.location_type ) {
                actionsCell.append( $( '<br>' ) );
                actionsCell.append(
                    $( '<button type="button" class="button-link ptscanner-check-media-usage"></button>' )
                        .attr( 'data-id', row.source_id )
                        .text( 'Check Usage' )
                );
                sourceCell.append( $( '<div class="ptscanner-media-usage-result"></div>' ).attr( 'id', 'ptscanner-media-usage-' + row.id ) );
            }

            tr.append( actionsCell );

            return tr;
        }, // End buildRow()


        loadFrontExtLinks: function () {
            const body = $( '#ptscanner-front-ext-links-body' );
            body.html( '<tr><td colspan="5">' + ( ptscanner_data.strings.loading || 'Loading…' ) + '</td></tr>' );

            $.post( ptscanner_data.ajaxUrl, {
                action: 'ptscanner_get_external_links',
                nonce: ptscanner_data.nonce,
                page: this.frontExtLinksPage,
                filter: this.frontExtLinksFilter,
            } ).done( ( response ) => {
                if ( ! response.success ) {
                    body.html( '<tr><td colspan="5">' + ( response.data.message || 'Error loading links.' ) + '</td></tr>' );
                    return;
                }

                $( '.ptscanner-ext-links-front-filter-tab[data-filter="all"]' ).text( 'All Links (' + response.data.total_all + ')' );
                $( '.ptscanner-ext-links-front-filter-tab[data-filter="flagged"]' ).text( 'Flagged Only (' + response.data.total_flagged + ')' );

                this.renderFrontExtLinks( response.data );
            } ).fail( () => {
                body.html( '<tr><td colspan="5">' + ( ptscanner_data.strings.requestFailed || 'Request failed.' ) + '</td></tr>' );
            } );
        }, // End loadFrontExtLinks()


        renderFrontExtLinks: function ( data ) {
            const body = $( '#ptscanner-front-ext-links-body' );
            body.empty();

            if ( ! data.rows.length ) {
                body.html( '<tr><td colspan="5">' + ( ptscanner_data.strings.noResults || 'No links found.' ) + '</td></tr>' );
            } else {
                data.rows.forEach( ( row ) => {
                    body.append( this.buildExtLinkRow( row ) );
                } );
            }

            const container = $( '#ptscanner-front-ext-links-pagination' );
            container.empty();

            if ( data.total_pages <= 1 ) {
                return;
            }

            for ( let i = 1; i <= data.total_pages; i++ ) {
                const link = $( '<a href="#"></a>' )
                    .addClass( 'ptscanner-front-page-link ptscanner-front-ext-links-page-link' )
                    .attr( 'data-page', i )
                    .text( i );

                if ( i === this.frontExtLinksPage ) {
                    link.addClass( 'current' );
                }

                container.append( link ).append( ' ' );
            }
        }, // End renderFrontExtLinks()


        buildExtLinkRow: function ( row ) {
            const tr = $( '<tr></tr>' );

            if ( '1' === String( row.is_flagged ) ) {
                tr.addClass( 'ptscanner-row-flagged' );
            }

            const linkCell = $( '<td></td>' );
            const linkAnchor = $( '<a target="_blank"></a>' ).attr( 'href', row.link );

            if ( row.matched_term ) {
                linkAnchor.html( this.highlightTerm( row.link, row.matched_term ) );
            } else {
                linkAnchor.text( row.link );
            }

            linkCell.append( linkAnchor );
            tr.append( linkCell );

            const anchorCell = $( '<td></td>' );

            if ( row.matched_term && row.anchor_text ) {
                anchorCell.html( this.highlightTerm( row.anchor_text, row.matched_term ) );
            } else {
                anchorCell.text( row.anchor_text || '' );
            }

            tr.append( anchorCell );

            tr.append( $( '<td></td>' ).append( $( '<a target="_blank"></a>' ).attr( 'href', row.source_link ).text( row.source_title ) ) );
            tr.append( $( '<td></td>' ).text( row.matched_term || '' ) );

            const noteCell = $( '<td></td>' );
            noteCell.append(
                $( '<textarea class="ptscanner-ext-link-note" rows="2"></textarea>' )
                    .attr( 'data-id', row.id )
                    .attr( 'placeholder', 'Add a note…' )
                    .val( row.note || '' )
            );
            noteCell.append( $( '<br>' ) );
            noteCell.append(
                $( '<button type="button" class="button-link ptscanner-ext-link-save-note"></button>' )
                    .attr( 'data-id', row.id )
                    .text( 'Save Note' )
            );
            tr.append( noteCell );

            return tr;
        }, // End buildExtLinkRow()


        /**
         * Format a MySQL datetime string (e.g. "2026-07-16 14:30:00") into
         * a readable 12-hour date/time string, matching the admin table's format
         *
         * @param {string} mysqlDatetime
         * @return {string}
         */
        formatDate: function ( mysqlDatetime ) {
            if ( ! mysqlDatetime ) {
                return '';
            }

            const parts = mysqlDatetime.split( /[- :]/ );

            if ( parts.length < 5 ) {
                return mysqlDatetime;
            }

            const date = new Date( parts[ 0 ], parts[ 1 ] - 1, parts[ 2 ], parts[ 3 ], parts[ 4 ] );

            const year = date.getFullYear();
            const month = String( date.getMonth() + 1 ).padStart( 2, '0' );
            const day = String( date.getDate() ).padStart( 2, '0' );

            let hours = date.getHours();
            const minutes = String( date.getMinutes() ).padStart( 2, '0' );
            const ampm = hours >= 12 ? 'PM' : 'AM';

            hours = hours % 12;
            hours = hours ? hours : 12;

            return year + '-' + month + '-' + day + ' ' + hours + ':' + minutes + ' ' + ampm;
        }, // End formatDate()


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
            const row = $( button ).closest( 'tr' );
            const noteField = row.find( '.ptscanner-note-field' );
            const noteDisplay = row.find( '.ptscanner-note-display' );
            const hasNote = ( noteField.length && '' !== noteField.val().trim() ) || ( noteDisplay.length && '' !== noteDisplay.text().trim() );

            const confirmMessage = hasNote
                ? 'This result has a note on it. Clear it anyway? This cannot be undone.'
                : ptscanner_data.strings.confirmClear;

            if ( ! confirm( confirmMessage ) ) {
                return;
            }

            const id = $( button ).data( 'id' );
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


        /**
         * Save a note on a result row, optionally giving button feedback
         *
         * @param {number} id
         * @param {string} note
         * @param {jQuery} [button]
         */
        saveNote: function ( id, note, button ) {
            if ( button ) {
                button.prop( 'disabled', true ).text( 'Saving…' );
            }

            $.post( ptscanner_data.ajaxUrl, {
                action: 'ptscanner_save_note',
                nonce: ptscanner_data.nonce,
                id: id,
                note: note,
            } ).done( ( response ) => {
                if ( ! response.success ) {
                    alert( response.data.message || 'Could not save note.' );

                    if ( button ) {
                        button.prop( 'disabled', false ).text( 'Save Note' );
                    }

                    return;
                }

                if ( button ) {
                    button.prop( 'disabled', false ).text( 'Saved' );
                    setTimeout( () => button.text( 'Save Note' ), 1500 );
                }
            } ).fail( () => {
                alert( ptscanner_data.strings.requestFailed || 'Request failed.' );

                if ( button ) {
                    button.prop( 'disabled', false ).text( 'Save Note' );
                }
            } );
        }, // End saveNote()


        saveExtLinkNote: function ( id, note, button ) {
            if ( button ) {
                button.prop( 'disabled', true ).text( 'Saving…' );
            }

            $.post( ptscanner_data.ajaxUrl, {
                action: 'ptscanner_save_external_link_note',
                nonce: ptscanner_data.nonce,
                id: id,
                note: note,
            } ).done( ( response ) => {
                if ( ! response.success ) {
                    alert( response.data.message || 'Could not save note.' );

                    if ( button ) {
                        button.prop( 'disabled', false ).text( 'Save Note' );
                    }

                    return;
                }

                if ( button ) {
                    button.prop( 'disabled', false ).text( 'Saved' );
                    setTimeout( () => button.text( 'Save Note' ), 1500 );
                }
            } ).fail( () => {
                alert( ptscanner_data.strings.requestFailed || 'Request failed.' );

                if ( button ) {
                    button.prop( 'disabled', false ).text( 'Save Note' );
                }
            } );
        }, // End saveExtLinkNote()

    };

    ptscannerResults.init();

    window.ptscannerResults = ptscannerResults;

} );