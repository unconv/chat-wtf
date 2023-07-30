<?php
spl_autoload_register( function( $class ) {
    require( __DIR__ . "/src/" . $class . ".php" );
} );
