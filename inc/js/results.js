jQuery( function ( $ ) {

    const strings = ptscanner_data.strings;
    const sprintf = wp.i18n.sprintf;

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

            $( '.ptscanner-filter-submit' ).prop( 'hidden', true );

            if ( $( '#ptscanner-front-results' ).length ) {
                this.loadFrontResults();
            }
        }, // End init()

        /**
         * Bind DOM events
         */
        bindEvents: function () {
            $( document ).on( 'click', '.ptscanner-mark-ok', ( event ) => {
                this.setStatus( event.currentTarget, 'ignored' );
            } );

            $( document ).on( 'click', '.ptscanner-mark-flagged', ( event ) => {
                this.setStatus( event.currentTarget, 'flagged' );
            } );

            $( document ).on( 'click', '.ptscanner-clear-result', ( event ) => {
                this.clearResult( event.currentTarget );
            } );

            $( document ).on( 'click', '.ptscanner-front-tab', ( event ) => {
                const tab = this.setCurrentTab( event.currentTarget );

                this.frontStatus = tab.data( 'status' );
                this.frontPage = 1;
                $( '#ptscanner-front-results' ).attr( 'data-status', this.frontStatus ).data( 'status', this.frontStatus );
                this.loadFrontResults();
            } );

            $( document ).on( 'change', '.ptscanner-results-filters select', ( event ) => {
                event.currentTarget.form.submit();
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

            $( document ).on( 'click', '#ptscanner-front-pagination .page-numbers', ( event ) => {
                event.preventDefault();
                this.frontPage = $( event.currentTarget ).data( 'page' );
                this.loadFrontResults();
            } );

            $( document ).on( 'click', '#ptscanner-front-pages-pagination .page-numbers', ( event ) => {
                event.preventDefault();
                this.frontPagesPage = $( event.currentTarget ).data( 'page' );
                this.loadFrontPages();
            } );

            $( document ).on( 'click', '#ptscanner-front-ext-links-pagination .page-numbers', ( event ) => {
                event.preventDefault();
                this.frontExtLinksPage = $( event.currentTarget ).data( 'page' );
                this.loadFrontExtLinks();
            } );

            $( document ).on( 'click', '#ptscanner-clear-errors', ( event ) => {
                if ( ! confirm( strings.confirmClearErrors ) ) {
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
                        alert( this.errorMessage( response ) );
                    }
                } );
            } );

            $( document ).on( 'click', '#ptscanner-clear-all', ( event ) => {
                const button = $( event.currentTarget );
                const status = button.data( 'status' );

                if ( ! confirm( 'flagged' === status ? strings.confirmClearAllFlagged : strings.confirmClearAllIgnored ) ) {
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
                        alert( this.errorMessage( response ) );
                    }
                } ).fail( () => {
                    button.prop( 'disabled', false );
                    alert( strings.requestFailed );
                } );
            } );

            $( document ).on( 'click', '#ptscanner-front-clear-all', ( event ) => {
                const status = this.frontStatus;

                if ( ! confirm( 'flagged' === status ? strings.confirmClearAllFlagged : strings.confirmClearAllIgnored ) ) {
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
                        alert( this.errorMessage( response ) );
                    }
                } ).fail( () => {
                    button.prop( 'disabled', false );
                    alert( strings.requestFailed );
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
                        alert( this.errorMessage( response ) );
                    }
                } );
            } );

            $( document ).on( 'click', '.ptscanner-toggle-omit', ( event ) => {
                event.preventDefault();

                const link = $( event.currentTarget );
                const isOmitted = '1' === String( link.data( 'omitted' ) );
                const id = link.data( 'id' );
                const type = link.data( 'type' );
                const self = this;

                if ( ! isOmitted ) {
                    if ( ! confirm( strings.confirmOmit ) ) {
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
                        alert( this.errorMessage( response ) );
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
                        link.text( strings.omitSource );
                    }
                } );
            } );

            $( document ).on( 'click', '.ptscanner-front-main-tab', ( event ) => {
                const panel = this.setCurrentTab( event.currentTarget ).data( 'panel' );

                $( '#ptscanner-front-results' ).prop( 'hidden', 'results' !== panel );
                $( '#ptscanner-front-pages' ).prop( 'hidden', 'pages' !== panel );
                $( '#ptscanner-front-external-links' ).prop( 'hidden', 'external-links' !== panel );

                if ( 'pages' === panel ) {
                    this.loadFrontPages();
                } else if ( 'external-links' === panel ) {
                    this.loadFrontExtLinks();
                }
            } );

            $( document ).on( 'click', '.ptscanner-ext-links-front-filter-tab', ( event ) => {
                this.frontExtLinksFilter = this.setCurrentTab( event.currentTarget ).data( 'filter' );
                this.frontExtLinksPage = 1;
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
                const link = $( event.currentTarget );
                const row = link.closest( 'tr' );
                const isOmitted = '1' === String( link.data( 'omitted' ) );
                const id = link.data( 'id' );
                const type = link.data( 'type' );

                link.prop( 'disabled', true );

                $.post( ptscanner_data.ajaxUrl, {
                    action: 'ptscanner_toggle_omit',
                    nonce: ptscanner_data.nonce,
                    id: id,
                    type: type,
                    omit: isOmitted ? '0' : '1',
                } ).done( ( response ) => {
                    link.prop( 'disabled', false );

                    if ( ! response.success ) {
                        alert( this.errorMessage( response ) );
                        return;
                    }

                    const nowOmitted = ! isOmitted;
                    link.data( 'omitted', nowOmitted ? '1' : '0' );
                    link.text( nowOmitted ? strings.unomit : strings.omitPage );
                    row.toggleClass( 'ptscanner-row-omitted', nowOmitted );
                } ).fail( () => {
                    link.prop( 'disabled', false );
                    alert( strings.requestFailed );
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
                        resultSpan.text( this.errorMessage( response ) );
                        return;
                    }

                    resultSpan.empty();

                    if ( ! response.data.used ) {
                        resultSpan.append( $( '<strong class="ptscanner-media-unused"></strong>' ).text( strings.mediaNotUsed ) );
                    } else {
                        const list = $( '<ul class="ptscanner-media-usage-list"></ul>' );

                        response.data.locations.forEach( ( loc ) => {
                            const item = $( '<li></li>' ).append( $( '<a target="_blank" rel="noopener noreferrer"></a>' ).attr( 'href', loc.link ).text( loc.title ) );

                            if ( 'publish' !== loc.status ) {
                                item.append( ' ', $( '<em></em>' ).text( '(' + loc.status + ')' ) );
                            }

                            list.append( item );
                        } );

                        resultSpan.append( $( '<strong></strong>' ).text( strings.mediaUsedOn ), list );
                    }

                    resultSpan.addClass( 'ptscanner-media-usage-highlight' );
                    setTimeout( () => resultSpan.removeClass( 'ptscanner-media-usage-highlight' ), 2100 );
                } ).fail( () => {
                    button.prop( 'disabled', false );
                    spinner.remove();
                    resultSpan.text( strings.requestFailed );
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
                    this.messageRow( body, 2, strings.noFlaggedTerms );
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
            const badges = document.querySelectorAll( '#adminmenu .update-plugins .update-count, #ptscanner-header .ptscanner-tab-count' );

            badges.forEach( function ( badge ) {
                const current = parseInt( badge.textContent, 10 );

                if ( isNaN( current ) ) {
                    return;
                }

                const next = Math.max( 0, current - 1 );
                badge.textContent = next;

                if ( 0 === next ) {
                    ( badge.closest( '.update-plugins' ) || badge ).remove();
                }
            } );
        }, // End decrementMenuBadge()


        /**
         * Set a status tab's count and relabel it ("Flagged (3)")
         *
         * @param {string} status 'flagged' or 'ignored'
         * @param {number} count
         */
        setStatusCount: function ( status, count ) {
            const label = 'flagged' === status ? strings.flaggedCount : strings.ignoredCount;
            const total = Math.max( 0, parseInt( count, 10 ) || 0 );

            $( '[data-count-status="' + status + '"]' ).attr( 'data-count', total ).text( sprintf( label, total ) );
        }, // End setStatusCount()


        /**
         * Change a status tab's count by a delta
         *
         * @param {string} status 'flagged' or 'ignored'
         * @param {number} delta
         */
        adjustStatusCount: function ( status, delta ) {
            const tab = $( '[data-count-status="' + status + '"]' ).first();

            if ( ! tab.length ) {
                return;
            }

            this.setStatusCount( status, ( parseInt( tab.attr( 'data-count' ), 10 ) || 0 ) + delta );
        }, // End adjustStatusCount()


        /**
         * Increment the admin menu's red count bubble by 1 — used when an
         * action on the "Marked as OK" tab returns an item to flagged status
         * (Unignore) or removes an ignored item (Clear), both of which
         * should visually restore/adjust the flagged count.
         */
        incrementMenuBadge: function () {
            document.querySelectorAll( '#ptscanner-header .ptscanner-tab-count' ).forEach( function ( badge ) {
                const current = parseInt( badge.textContent, 10 );
                badge.textContent = isNaN( current ) ? 1 : current + 1;
            } );

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
            const body = $( '#ptscanner-front-results-body' );
            this.messageRow( body, 7, strings.loading );

            $.post( ptscanner_data.ajaxUrl, {
                action: 'ptscanner_get_results',
                nonce: ptscanner_data.nonce,
                status: this.frontStatus,
                page: this.frontPage,
                term_filter: this.frontTermFilter,
                location_filter: this.frontLocationFilter,
            } ).done( ( response ) => {
                if ( ! response.success ) {
                    this.messageRow( body, 7, this.errorMessage( response ) );
                    return;
                }

                this.renderFrontResults( response.data );
            } ).fail( () => {
                this.messageRow( body, 7, strings.requestFailed );
            } );
        }, // End loadFrontResults()


        /**
         * Render the front-end results table body and pagination
         *
         * @param {Object} data
         */
        renderFrontResults: function ( data ) {
            this.setStatusCount( 'flagged', data.total_flagged );
            this.setStatusCount( 'ignored', data.total_ignored );

            const termSelect = $( '#ptscanner-front-term-filter' );
            const currentTermVal = termSelect.val();
            termSelect.empty().append( $( '<option value=""></option>' ).text( strings.allTerms ) );
            data.distinct_terms.forEach( ( term ) => {
                termSelect.append( $( '<option></option>' ).val( term ).text( term ) );
            } );
            termSelect.val( currentTermVal );

            const locationSelect = $( '#ptscanner-front-location-filter' );
            const currentLocationVal = locationSelect.val();
            locationSelect.empty().append( $( '<option value=""></option>' ).text( strings.allLocations ) );
            $.each( data.distinct_locations, ( slug, label ) => {
                locationSelect.append( $( '<option></option>' ).val( slug ).text( label ) );
            } );
            locationSelect.val( currentLocationVal );

            const body = $( '#ptscanner-front-results-body' );
            body.empty();

            if ( ! data.rows.length ) {
                this.messageRow( body, 7, strings.noResults );
            } else {
                data.rows.forEach( ( row ) => {
                    body.append( this.buildRow( row ) );
                } );
            }

            this.renderPageLinks( $( '#ptscanner-front-pagination' ), data.total_pages, this.frontPage );
        }, // End renderFrontResults()


        loadFrontPages: function () {
            const body = $( '#ptscanner-front-pages-body' );
            this.messageRow( body, 3, strings.loading );

            $.post( ptscanner_data.ajaxUrl, {
                action: 'ptscanner_get_pages_list',
                nonce: ptscanner_data.nonce,
                page: this.frontPagesPage,
            } ).done( ( response ) => {
                if ( ! response.success ) {
                    this.messageRow( body, 3, this.errorMessage( response ) );
                    return;
                }

                this.renderFrontPages( response.data );
            } ).fail( () => {
                this.messageRow( body, 3, strings.requestFailed );
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
                this.messageRow( body, 3, strings.noResults );
            } else {
                data.rows.forEach( ( row ) => {
                    body.append( this.buildPageRow( row ) );
                } );
            }

            this.renderPageLinks( $( '#ptscanner-front-pages-pagination' ), data.total_pages, this.frontPagesPage );
        }, // End renderFrontPages()


        /**
         * Build a single page row for the front-end table
         * 
         * @param {Object} row
         * @return {jQuery}
         */
        buildPageRow: function ( row ) {
            const tr = $( '<tr></tr>' ).attr( 'id', 'ptscanner-front-page-row-' + row.id ).toggleClass( 'ptscanner-row-omitted', row.is_omitted );

            tr.append( $( '<td></td>' ).append( $( '<a target="_blank" rel="noopener noreferrer"></a>' ).attr( 'href', row.permalink ).text( row.title ) ) );
            tr.append( $( '<td></td>' ).text( row.post_type ) );

            tr.append(
                $( '<td class="column-actions"></td>' ).append(
                    $( '<button type="button" class="ptscanner-button ptscanner-button-small ptscanner-button-secondary ptscanner-pages-toggle-omit"></button>' )
                        .attr( 'data-id', row.id )
                        .attr( 'data-type', 'post' )
                        .attr( 'data-omitted', row.is_omitted ? '1' : '0' )
                        .text( row.is_omitted ? strings.unomit : strings.omitPage )
                )
            );

            return tr;
        }, // End buildPageRow()


        /**
         * Build a single result row for the front-end table
         *
         * @param {Object} row
         * @return {jQuery}
         */
        buildRow: function ( row ) {
            const tr = $( '<tr></tr>' ).attr( 'id', 'ptscanner-row-' + row.id ).attr( 'data-id', row.id );
            const isMediaRow = 'filename' === row.location_type || 'alt_text' === row.location_type;

            tr.append( $( '<td class="column-term"></td>' ).append( $( '<strong></strong>' ).text( row.term ) ) );

            const contextCell = $( '<td class="column-context"></td>' ).html( this.highlightTerm( row.context_snippet, row.term ) );
            if ( row.file_page ) {
                contextCell.append( $( '<br>' ) ).append( $( '<em></em>' ).text( sprintf( strings.pageNumber, row.file_page ) ) );
            }
            tr.append( contextCell );

            tr.append( $( '<td class="column-location"></td>' ).text( row.location_label ) );

            const sourceCell = $( '<td class="column-source"></td>' );
            if ( row.source_title ) {
                sourceCell.append( $( '<strong></strong>' ).text( row.source_title ) ).append( $( '<br>' ) );
            }

            const sourceLinks = $( '<span class="ptscanner-source-links"></span>' );
            if ( row.highlight_link ) {
                sourceLinks.append( $( '<a target="_blank" rel="noopener noreferrer"></a>' ).attr( 'href', row.highlight_link ).text( strings.viewSource ) ).append( ' | ' );
            }
            sourceLinks.append(
                $( '<a href="#" class="ptscanner-toggle-omit"></a>' )
                    .attr( 'data-id', row.source_id )
                    .attr( 'data-type', row.source_type )
                    .attr( 'data-omitted', '0' )
                    .text( strings.omitSource )
            );
            sourceCell.append( sourceLinks );
            sourceCell.append( $( '<div class="ptscanner-media-usage-result"></div>' ).attr( 'id', 'ptscanner-media-usage-' + row.id ) );
            tr.append( sourceCell );

            tr.append(
                $( '<td class="column-note"></td>' )
                    .append( $( '<textarea class="ptscanner-note-field" rows="2"></textarea>' ).attr( 'data-id', row.id ).attr( 'placeholder', strings.addNote ).attr( 'aria-label', strings.note ).val( row.note || '' ) )
                    .append( $( '<button type="button" class="ptscanner-link-button ptscanner-save-note"></button>' ).attr( 'data-id', row.id ).text( strings.saveNote ) )
            );

            tr.append( $( '<td class="column-date"></td>' ).text( row.date_display || '' ) );

            const actions = $( '<div class="ptscanner-row-actions"></div>' );
            if ( 'flagged' === row.status ) {
                actions.append( this.smallButton( 'ptscanner-mark-ok', row.id, strings.markOk ) );
            } else {
                actions.append( this.smallButton( 'ptscanner-mark-flagged', row.id, strings.unignore ) );
            }
            if ( isMediaRow ) {
                actions.append( this.smallButton( 'ptscanner-button-secondary ptscanner-check-media-usage', row.source_id, strings.checkUsage ) );
            }
            actions.append( this.smallButton( 'ptscanner-button-danger ptscanner-clear-result', row.id, strings.clear ) );
            tr.append( $( '<td class="column-actions"></td>' ).append( actions ) );

            return tr;
        }, // End buildRow()


        loadFrontExtLinks: function () {
            const body = $( '#ptscanner-front-ext-links-body' );
            this.messageRow( body, 5, strings.loading );

            $.post( ptscanner_data.ajaxUrl, {
                action: 'ptscanner_get_external_links',
                nonce: ptscanner_data.nonce,
                page: this.frontExtLinksPage,
                filter: this.frontExtLinksFilter,
            } ).done( ( response ) => {
                if ( ! response.success ) {
                    this.messageRow( body, 5, this.errorMessage( response ) );
                    return;
                }

                $( '.ptscanner-ext-links-front-filter-tab[data-filter="all"]' ).text( sprintf( strings.allLinksCount, response.data.total_all ) );
                $( '.ptscanner-ext-links-front-filter-tab[data-filter="flagged"]' ).text( sprintf( strings.flaggedLinksCount, response.data.total_flagged ) );

                this.renderFrontExtLinks( response.data );
            } ).fail( () => {
                this.messageRow( body, 5, strings.requestFailed );
            } );
        }, // End loadFrontExtLinks()


        renderFrontExtLinks: function ( data ) {
            const body = $( '#ptscanner-front-ext-links-body' );
            body.empty();

            if ( ! data.rows.length ) {
                this.messageRow( body, 5, strings.noResults );
            } else {
                data.rows.forEach( ( row ) => {
                    body.append( this.buildExtLinkRow( row ) );
                } );
            }

            this.renderPageLinks( $( '#ptscanner-front-ext-links-pagination' ), data.total_pages, this.frontExtLinksPage );
        }, // End renderFrontExtLinks()


        buildExtLinkRow: function ( row ) {
            const tr = $( '<tr></tr>' );

            if ( '1' === String( row.is_flagged ) ) {
                tr.addClass( 'ptscanner-row-flagged' );
            }

            const linkCell = $( '<td></td>' );
            const linkAnchor = $( '<a target="_blank" rel="noopener noreferrer"></a>' ).attr( 'href', row.link );

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

            tr.append( $( '<td></td>' ).append( $( '<a target="_blank" rel="noopener noreferrer"></a>' ).attr( 'href', row.source_link ).text( row.source_title ) ) );
            tr.append( $( '<td></td>' ).text( row.matched_term || '' ) );

            const noteCell = $( '<td></td>' );
            if ( ptscanner_data.canScan ) {
                noteCell.append( $( '<textarea class="ptscanner-ext-link-note" rows="2"></textarea>' ).attr( 'data-id', row.id ).attr( 'placeholder', strings.addNote ).attr( 'aria-label', strings.note ).val( row.note || '' ) );
                noteCell.append( $( '<button type="button" class="ptscanner-link-button ptscanner-ext-link-save-note"></button>' ).attr( 'data-id', row.id ).text( strings.saveNote ) );
            } else {
                noteCell.append( $( '<span class="ptscanner-note-display"></span>' ).text( row.note || '' ) );
            }
            tr.append( noteCell );

            return tr;
        }, // End buildExtLinkRow()


        /**
         * Build a small row action button
         *
         * @param {string} classes Extra classes, including the action class
         * @param {number} id
         * @param {string} label
         * @return {jQuery}
         */
        smallButton: function ( classes, id, label ) {
            return $( '<button type="button"></button>' ).addClass( 'ptscanner-button ptscanner-button-small ' + classes ).attr( 'data-id', id ).text( label );
        }, // End smallButton()


        /**
         * Mark a segmented-control button as current within its group
         *
         * @param {HTMLElement} button
         * @return {jQuery}
         */
        setCurrentTab: function ( button ) {
            const tab = $( button );

            tab.closest( '.ptscanner-segmented' ).children().removeClass( 'current' ).attr( 'aria-pressed', 'false' );
            tab.addClass( 'current' ).attr( 'aria-pressed', 'true' );

            return tab;
        }, // End setCurrentTab()


        /**
         * Render numbered page links into a pagination container, hiding it when there is one page
         *
         * @param {jQuery} container
         * @param {number} totalPages
         * @param {number} currentPage
         */
        renderPageLinks: function ( container, totalPages, currentPage ) {
            container.empty().prop( 'hidden', totalPages <= 1 );

            for ( let i = 1; i <= totalPages; i++ ) {
                const link = $( '<a href="#" class="page-numbers"></a>' ).attr( 'data-page', i ).text( i );

                if ( i === currentPage ) {
                    link.addClass( 'current' ).attr( 'aria-current', 'page' );
                }

                container.append( link );
            }
        }, // End renderPageLinks()


        /**
         * Replace a table body with a single full-width message row
         *
         * @param {jQuery} body
         * @param {number} colspan
         * @param {string} message
         */
        messageRow: function ( body, colspan, message ) {
            body.empty().append( $( '<tr></tr>' ).append( $( '<td></td>' ).attr( 'colspan', colspan ).text( message ) ) );
        }, // End messageRow()


        /**
         * Get the error message from a failed AJAX response
         *
         * @param {Object} response
         * @return {string}
         */
        errorMessage: function ( response ) {
            const data = response && response.data;

            if ( data && 'object' === typeof data && data.message ) {
                return data.message;
            }

            return 'string' === typeof data && '' !== data ? data : strings.actionFailed;
        }, // End errorMessage()


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
                    if ( 'ignored' === status ) {
                        this.decrementMenuBadge();
                    } else {
                        this.incrementMenuBadge();
                    }

                    this.adjustStatusCount( status, 1 );
                    this.adjustStatusCount( 'ignored' === status ? 'flagged' : 'ignored', -1 );

                    row.fadeOut( 200, () => row.remove() );
                } else {
                    $( button ).prop( 'disabled', false );
                    alert( this.errorMessage( response ) );
                }
            } ).fail( () => {
                $( button ).prop( 'disabled', false );
                alert( strings.requestFailed );
            } );
        }, // End setStatus()


        /**
         * Clear (delete) a result row after confirmation
         *
         * @param {HTMLElement} button
         */
        clearResult: function ( button ) {
            const row = $( button ).closest( 'tr' );
            const noteField = row.find( '.ptscanner-note-field' );
            const noteDisplay = row.find( '.ptscanner-note-display' );
            const hasNote = ( noteField.length && '' !== noteField.val().trim() ) || ( noteDisplay.length && '' !== noteDisplay.text().trim() );

            const confirmMessage = hasNote
                ? strings.confirmClearWithNote
                : strings.confirmClear;

            if ( ! confirm( confirmMessage ) ) {
                return;
            }

            const id = $( button ).data( 'id' );
            const isIgnoredTab = 'ignored' === row.closest( '[data-status]' ).data( 'status' );

            $( button ).prop( 'disabled', true );

            $.post( ptscanner_data.ajaxUrl, {
                action: 'ptscanner_delete_result',
                nonce: ptscanner_data.nonce,
                id: id,
            } ).done( ( response ) => {
                if ( response.success ) {
                    if ( ! isIgnoredTab ) {
                        this.decrementMenuBadge();
                    }

                    this.adjustStatusCount( isIgnoredTab ? 'ignored' : 'flagged', -1 );

                    row.fadeOut( 200, () => row.remove() );
                } else {
                    $( button ).prop( 'disabled', false );
                    alert( this.errorMessage( response ) );
                }
            } ).fail( () => {
                $( button ).prop( 'disabled', false );
                alert( strings.requestFailed );
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
                button.prop( 'disabled', true ).text( strings.saving );
            }

            $.post( ptscanner_data.ajaxUrl, {
                action: 'ptscanner_save_note',
                nonce: ptscanner_data.nonce,
                id: id,
                note: note,
            } ).done( ( response ) => {
                if ( ! response.success ) {
                    alert( this.errorMessage( response ) );

                    if ( button ) {
                        button.prop( 'disabled', false ).text( strings.saveNote );
                    }

                    return;
                }

                if ( button ) {
                    button.prop( 'disabled', false ).text( strings.saved );
                    setTimeout( () => button.text( strings.saveNote ), 1500 );
                }
            } ).fail( () => {
                alert( strings.requestFailed );

                if ( button ) {
                    button.prop( 'disabled', false ).text( strings.saveNote );
                }
            } );
        }, // End saveNote()


        saveExtLinkNote: function ( id, note, button ) {
            if ( button ) {
                button.prop( 'disabled', true ).text( strings.saving );
            }

            $.post( ptscanner_data.ajaxUrl, {
                action: 'ptscanner_save_external_link_note',
                nonce: ptscanner_data.nonce,
                id: id,
                note: note,
            } ).done( ( response ) => {
                if ( ! response.success ) {
                    alert( this.errorMessage( response ) );

                    if ( button ) {
                        button.prop( 'disabled', false ).text( strings.saveNote );
                    }

                    return;
                }

                if ( button ) {
                    button.prop( 'disabled', false ).text( strings.saved );
                    setTimeout( () => button.text( strings.saveNote ), 1500 );
                }
            } ).fail( () => {
                alert( strings.requestFailed );

                if ( button ) {
                    button.prop( 'disabled', false ).text( strings.saveNote );
                }
            } );
        }, // End saveExtLinkNote()

    };

    ptscannerResults.init();

    window.ptscannerResults = ptscannerResults;

} );