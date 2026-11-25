jQuery( function ( $ ) {

    if ( ! $( '#ptscanner-ext-links-body' ).length ) {
        return;
    }

    const ptscannerExtLinks = {


        page: 1,
        filter: 'all',


        init: function () {
            this.bindEvents();
            this.loadPage();
        }, // End init()


        bindEvents: function () {
            $( document ).on( 'click', '#ptscanner-ext-links-start-scan', () => this.startScan() );

            $( document ).on( 'click', '.ptscanner-ext-links-filter-tab', ( event ) => {
                const tab = $( event.currentTarget );

                $( '.ptscanner-ext-links-filter-tab' ).removeClass( 'active' );
                tab.addClass( 'active' );

                this.filter = tab.data( 'filter' );
                this.page = 1;
                this.loadPage();
            } );

            $( document ).on( 'click', '.ptscanner-ext-links-page-link', ( event ) => {
                event.preventDefault();
                this.page = $( event.currentTarget ).data( 'page' );
                this.loadPage();
            } );

            $( document ).on( 'click', '.ptscanner-ext-link-save-note', ( event ) => {
                const button = $( event.currentTarget );
                const row = button.closest( 'tr' );
                const field = row.find( '.ptscanner-ext-link-note' );

                this.saveNote( field.data( 'id' ), field.val(), button );
            } );

            $( document ).on( 'blur', '.ptscanner-ext-link-note', ( event ) => {
                const field = $( event.currentTarget );

                this.saveNote( field.data( 'id' ), field.val() );
            } );
        }, // End bindEvents()


        startScan: function () {
            const button = $( '#ptscanner-ext-links-start-scan' );
            const progress = $( '#ptscanner-ext-links-progress' );
            const fill = $( '#ptscanner-ext-links-progress-fill' );
            const label = $( '#ptscanner-ext-links-progress-label' );

            button.prop( 'disabled', true );
            progress.show();
            fill.css( 'width', '50%' );
            label.text( 'Scanning…' );

            $.post( ptscanner_data.ajaxUrl, {
                action: 'ptscanner_run_external_links_scan',
                nonce: ptscanner_data.nonce,
            } ).done( ( response ) => {
                fill.css( 'width', '100%' ).addClass( 'ptscanner-progress-stopped' );
                $( '#ptscanner-ext-links-progress .ptscanner-progress-spinner' ).hide();
                button.prop( 'disabled', false );

                if ( ! response.success ) {
                    label.text( response.data.message || 'Scan error.' );
                    return;
                }

                label.text( 'Scan complete — ' + response.data.links_found + ' links found across ' + response.data.scanned + ' pages.' );
                this.page = 1;
                this.loadPage();
            } ).fail( () => {
                fill.addClass( 'ptscanner-progress-stopped' );
                $( '#ptscanner-ext-links-progress .ptscanner-progress-spinner' ).hide();
                label.text( ptscanner_data.strings.requestFailed || 'Request failed.' );
                button.prop( 'disabled', false );
            } );
        }, // End startScan()


        loadPage: function () {
            const body = $( '#ptscanner-ext-links-body' );
            body.html( '<tr><td colspan="5">' + ( ptscanner_data.strings.loading || 'Loading…' ) + '</td></tr>' );

            $.post( ptscanner_data.ajaxUrl, {
                action: 'ptscanner_get_external_links',
                nonce: ptscanner_data.nonce,
                page: this.page,
                filter: this.filter,
            } ).done( ( response ) => {
                if ( ! response.success ) {
                    body.html( '<tr><td colspan="5">' + ( response.data.message || 'Error loading links.' ) + '</td></tr>' );
                    return;
                }

                $( '.ptscanner-ext-links-filter-tab[data-filter="all"]' ).text( 'All Links (' + response.data.total_all + ')' );
                $( '.ptscanner-ext-links-filter-tab[data-filter="flagged"]' ).text( 'Flagged Only (' + response.data.total_flagged + ')' );

                this.renderRows( response.data.rows );
                this.renderPagination( response.data.total_pages );
            } ).fail( () => {
                body.html( '<tr><td colspan="5">' + ( ptscanner_data.strings.requestFailed || 'Request failed.' ) + '</td></tr>' );
            } );
        }, // End loadPage()


        renderRows: function ( rows ) {
            const body = $( '#ptscanner-ext-links-body' );
            body.empty();

            if ( ! rows.length ) {
                body.html( '<tr><td colspan="5">No links found.</td></tr>' );
                return;
            }

            rows.forEach( ( row ) => {
                const tr = $( '<tr></tr>' );

                if ( '1' === String( row.is_flagged ) ) {
                    tr.addClass( 'ptscanner-row-flagged' );
                }

                const linkAnchor = $( '<a target="_blank"></a>' ).attr( 'href', row.link );

                if ( row.matched_term ) {
                    linkAnchor.html( this.highlightTerm( row.link, row.matched_term ) );
                } else {
                    linkAnchor.text( row.link );
                }

                tr.append( $( '<td></td>' ).append( linkAnchor ) );

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

                body.append( tr );
            } );
        }, // End renderRows()


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


        renderPagination: function ( totalPages ) {
            const container = $( '#ptscanner-ext-links-pagination' );
            container.empty();

            if ( totalPages <= 1 ) {
                return;
            }

            for ( let i = 1; i <= totalPages; i++ ) {
                const link = $( '<a href="#" class="ptscanner-front-page-link ptscanner-ext-links-page-link"></a>' ).attr( 'data-page', i ).text( i );

                if ( i === this.page ) {
                    link.addClass( 'current' );
                }

                container.append( link ).append( ' ' );
            }
        }, // End renderPagination()


        saveNote: function ( id, note, button ) {
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
        }, // End saveNote()

    };

    ptscannerExtLinks.init();

} );