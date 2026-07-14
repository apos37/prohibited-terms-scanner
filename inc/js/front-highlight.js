jQuery( function ( $ ) {

    const queryString = window.location.search;
    const urlParams = new URLSearchParams( queryString );

    if ( ! urlParams.has( 'ptscanner_term' ) ) {
        return;
    }

    const term = urlParams.get( 'ptscanner_term' );

    if ( ! term ) {
        return;
    }

    /**
     * Highlight the first text node containing the term, inside main content only
     */
    const contentContainer = document.querySelector( '.entry-content, article, main' ) || document.body;
    const walker = document.createTreeWalker( contentContainer, NodeFilter.SHOW_TEXT, null );
    const lowerTerm = term.toLowerCase();
    let node;
    let found = false;

    while ( ( node = walker.nextNode() ) && ! found ) {
        if ( node.nodeValue.toLowerCase().indexOf( lowerTerm ) !== -1 ) {
            const span = document.createElement( 'span' );
            span.className = 'ptscanner-highlight-blink';

            const parent = node.parentNode;
            parent.replaceChild( span, node );
            span.appendChild( node );

            span.scrollIntoView( { behavior: 'smooth', block: 'center' } );
            found = true;
        }
    }

    if ( ! found ) {
        console.log( 'Term not found on this page; it may be hidden or the content has changed since the scan.' );
    }

} );