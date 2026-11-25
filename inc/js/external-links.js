jQuery( function ( $ ) {

    if ( ! $( '#ptscanner-ext-links-body' ).length || ! window.ptscannerResults ) {
        return;
    }

    const strings = ptscanner_data.strings;
    const sprintf = wp.i18n.sprintf;
    const results = window.ptscannerResults;

    /**
     * External Links tab controller
     *
     * Owns the scan button, filter and pagination. Rows, highlighting and note
     * saving are shared with the front-end shortcode through results.js.
     */
    const ptscannerExtLinks = {


        page: 1,
        filter: 'all',


        /**
         * Init
         */
        init: function () {
            this.bindEvents();
            this.loadPage();
        }, // End init()


        /**
         * Bind DOM events
         */
        bindEvents: function () {
            $( document ).on( 'click', '#ptscanner-ext-links-start-scan', () => this.startScan() );

            $( document ).on( 'click', '.ptscanner-ext-links-filter-tab', ( event ) => {
                this.filter = results.setCurrentTab( event.currentTarget ).data( 'filter' );
                this.page = 1;
                this.loadPage();
            } );

            $( document ).on( 'click', '#ptscanner-ext-links-pagination .page-numbers', ( event ) => {
                event.preventDefault();
                this.page = $( event.currentTarget ).data( 'page' );
                this.loadPage();
            } );
        }, // End bindEvents()


        /**
         * Run the external links scan
         */
        startScan: function () {
            const button = $( '#ptscanner-ext-links-start-scan' ).prop( 'disabled', true );
            const fill = $( '#ptscanner-ext-links-progress-fill' ).css( 'width', '50%' ).removeClass( 'ptscanner-progress-stopped' );
            const label = $( '#ptscanner-ext-links-progress-label' ).text( strings.scanning + '…' );
            const spinner = $( '#ptscanner-ext-links-progress .ptscanner-progress-spinner' ).prop( 'hidden', false );

            $( '#ptscanner-ext-links-progress' ).prop( 'hidden', false );

            $.post( ptscanner_data.ajaxUrl, {
                action: 'ptscanner_run_external_links_scan',
                nonce: ptscanner_data.nonce,
            } ).done( ( response ) => {
                if ( ! response.success ) {
                    label.text( results.errorMessage( response ) );
                    return;
                }

                fill.css( 'width', '100%' );
                label.text( sprintf( strings.extLinksDone, response.data.links_found, response.data.scanned ) );
                this.page = 1;
                this.loadPage();
            } ).fail( () => {
                label.text( strings.requestFailed );
            } ).always( () => {
                fill.addClass( 'ptscanner-progress-stopped' );
                spinner.prop( 'hidden', true );
                button.prop( 'disabled', false );
            } );
        }, // End startScan()


        /**
         * Fetch and render the current page of links
         */
        loadPage: function () {
            const body = $( '#ptscanner-ext-links-body' );

            results.messageRow( body, 5, strings.loading );

            $.post( ptscanner_data.ajaxUrl, {
                action: 'ptscanner_get_external_links',
                nonce: ptscanner_data.nonce,
                page: this.page,
                filter: this.filter,
            } ).done( ( response ) => {
                if ( ! response.success ) {
                    results.messageRow( body, 5, results.errorMessage( response ) );
                    return;
                }

                $( '.ptscanner-ext-links-filter-tab[data-filter="all"]' ).text( sprintf( strings.allLinksCount, response.data.total_all ) );
                $( '.ptscanner-ext-links-filter-tab[data-filter="flagged"]' ).text( sprintf( strings.flaggedLinksCount, response.data.total_flagged ) );

                if ( ! response.data.rows.length ) {
                    results.messageRow( body, 5, strings.noLinks );
                } else {
                    body.empty();

                    response.data.rows.forEach( ( row ) => {
                        body.append( results.buildExtLinkRow( row ) );
                    } );
                }

                results.renderPageLinks( $( '#ptscanner-ext-links-pagination' ), response.data.total_pages, this.page );
            } ).fail( () => {
                results.messageRow( body, 5, strings.requestFailed );
            } );
        }, // End loadPage()

    };

    ptscannerExtLinks.init();

} );
