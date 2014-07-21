<?php
// Initialize database
include( 'mirrorInitializeDb.php' );

foreach ( $tables as $key => $table ) {
    echo "Dropping $key...\n";
    $db->query( "DROP TABLE $key" );
}