<?php

return [
    'prefix' => 'PTScannerVendor',
    'finders' => [
        \Isolated\Symfony\Component\Finder\Finder::create()
            ->files()
            ->in( 'vendor/smalot' )
            ->name( '*.php' ),
    ],
    'exclude-namespaces' => [],
    'expose-global-functions' => false,
    'expose-global-classes' => false,
];