<?php

return [
    'prefix' => 'PTScannerVendor',
    'finders' => [
        \Isolated\Symfony\Component\Finder\Finder::create()
            ->files()
            ->in( 'vendor/smalot' )
            ->name( '*.php' ),
    ],
    'patchers' => [
        static function ( string $file_path, string $prefix, string $contents ) : string {
            return str_replace( "'\\\\Smalot\\\\PdfParser\\\\", "'\\\\" . $prefix . "\\\\Smalot\\\\PdfParser\\\\", $contents );
        },
    ],
    'exclude-namespaces' => [],
    'expose-global-functions' => false,
    'expose-global-classes' => false,
];