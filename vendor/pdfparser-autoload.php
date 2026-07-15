<?php
/**
 * Minimal autoloader for the bundled, namespace-scoped smalot/pdfparser
 * library. Avoids Composer's generated autoloader entirely, since we only
 * bundle this one small library.
 */

spl_autoload_register( function ( $class ) {
    $prefix = 'PTScannerVendor\\Smalot\\PdfParser\\';

    if ( 0 !== strpos( $class, $prefix ) ) {
        return;
    }

    $relative = substr( $class, strlen( $prefix ) );
    $relative = str_replace( '\\', '/', $relative );
    $file     = __DIR__ . '/pdfparser/' . $relative . '.php';

    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );