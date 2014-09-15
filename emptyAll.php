<?php
// Initialize database
include( 'mirrorInitializeDb.php' );

$wikis = array();
$wikis[] = $config->localWikiName;
$wikis[] = $config->remoteWikiName;
foreach ( $wikis as $wiki ) {
    $db->select_db( $wiki );
    foreach ( $config->tablesToTruncate as $table ) {
	echo "Emptying $wiki's $table...\n";
	$db->query( "TRUNCATE TABLE $table" );
    }
}