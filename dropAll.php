<?php
// Initialize database
include( 'mirrorInitializeDb.php' );

foreach ( $config->tables as $key => $table ) {
    echo "Dropping $key...\n";
    $db->query( "DROP TABLE $key" );
}