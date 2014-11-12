<?php
/* Set up bot classes. */
require_once( 'mirrorInitializeDb.php' );
# Make it so we can include stuff without running it
$testing = true;
require_once( 'mirrorPullBot.php' );
require_once( 'mirrorPushBot.php' );
$config = new config();
require_once( $config->botClassesPath . "/botclasses.php" );
$wiki = new wikipedia;
$wiki->setUserAgent( $passwordConfig->userAgent );
$wiki->url = $config->remoteWikiUrl[$config->remoteWikiName];
$wiki->login( $passwordConfig->pullUser[$config->remoteWikiName],
      $passwordConfig->pullPass[$config->remoteWikiName] );
$token = urlencode( $wiki->getedittoken() );
// Some long URLs will cause problems if we try to display the whole thing
$wiki->__set( 'quiet', 'soft' );
$wiki->echoRet = true;

// Connect to local database
$dbLocal = new mysqli( $passwordConfig->host, $passwordConfig->dbUser, $passwordConfig->dbPass );
if ( !$dbLocal ) {
      die( 'Could not connect: ' . mysql_error() );
}
// Create local database and select it
$dbLocal->query ( "CREATE DATABASE IF NOT EXISTS " . $passwordConfig->dbLocalName );
$dbLocal->select_db ( $passwordConfig->dbLocalName );

// Connect to remote database
$dbRemote = new mysqli( $passwordConfig->host, $passwordConfig->dbUser, $passwordConfig->dbPass );
if ( !$dbRemote ) {
      die( 'Could not connect: ' . mysql_error() );
}
// Create remote database and select it
$dbRemote->query ( "CREATE DATABASE IF NOT EXISTS " . $passwordConfig->dbRemoteName );
$dbRemote->select_db ( $passwordConfig->dbRemoteName );

# Get the empty databases
echo "Loading empty scenario...\n";
shell_exec( 'php scenarios.php -r7empty' );

# scen1010
echo "Scen1010\n";
echo "Editing " . $config->remoteWikiName . "...\n";
$ret = $wiki->edit( 'Foo', 'Bar' );
pullRc( $passwordConfig, $config, $db );
pullRev( $passwordConfig, $config, $db );
push( $passwordConfig, $config, $db );
checkIdenticalTables( $dbLocal, $dbRemote, array( 'revision' ), array( 'rev_text_id' ) );

function pullRc( $passwordConfig, $config, $db ) {
    echo "Pulling rc...\n";
    pullIt( $passwordConfig, $config, $db, array( 'q' => 'rc' ) );
    return;
}

function pullRev( $passwordConfig, $config, $db ) {
    echo "Pulling rev...\n";
    pullIt( $passwordConfig, $config, $db, array( 'q' => 'rev' ) );
    return;
}

function push( $passwordConfig, $config, $db ) {
    echo "Pushing...\n";
    pushIt( $passwordConfig, $config, $db, array( 'r' => 'o' ) );
}

function checkIdenticalTables( $dbLocal, $dbRemote, $tables, $exempt = array() ) {
    foreach ( $tables as $table ) {
	echo "Comparing $table...\n";
	$query = "SELECT * FROM $table";
	$localRet = $dbLocal->query( $query );
	$remoteRet = $dbRemote->query( $query );
	$row = true;
	while ( $row ) {
	    $remoteRow = $remoteRet->fetch_assoc();
	    if ( !$remoteRow ) {
		continue;
	    }
	    $localRow = $localRet->fetch_assoc();
	    if ( !$localRow ) {
		die( "Ran out of local rows!\n" );
	    }
	    foreach( $remoteRow as $key => $value ) {
		if ( !isset( $localRow[$key] ) ) {
		    #echo $value;
		    die( "Local is missing field $key\n" );
		}
		if ( $key === 'rev_user' ) {
		    if ( $remoteRow['rev_user'] !== $localRow['rev_mt_user'] ) {
			echo "rev_user doesn't match rev_mt_user! Remote row:\n";
			var_dump( $remoteRow );
			echo "Local row:\n";
			var_dump( $localRow );
			die();
		    }
		} else {
		    if ( $localRow[$key] !== $remoteRow[$key] && !in_array( $key, $exempt ) ) {
			echo "Discrepancy in $key! Remote row:\n";
			var_dump( $remoteRow );
			echo "Local row:\n";
			var_dump( $localRow );
			die();
		    }
		}
	    }
	}
	$remoteRet = $dbRemote->fetch_assoc();
	if ( $remoteRet ) {
	    die( "There were superfluous local rows!\n" );
	}
    }
}