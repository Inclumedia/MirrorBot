<?php
/* Set up bot classes. */
require_once( 'mirrorInitializeDb.php' );
# Make it so we can include stuff without running it
$testing = true;
require_once( 'mirrorPullBot.php' );
require_once( 'mirrorPushBot.php' );
$config = new config();
require_once( $config->botClassesPath . "/botclasses.php" );
echo "Logging into remote wiki...\n";
$remoteWiki = new wikipedia;
$remoteWiki->setUserAgent( $passwordConfig->userAgent );
$remoteWiki->url = $config->remoteWikiUrl[$config->remoteWikiName];
$remoteWiki->login( $passwordConfig->pullUser[$config->remoteWikiName],
      $passwordConfig->pullPass[$config->remoteWikiName] );
// Some long URLs will cause problems if we try to display the whole thing
$remoteWiki->__set( 'quiet', 'soft' );
$remoteWiki->echoRet = true;

echo "Logging into local wiki...\n";
$localWiki = new wikipedia;
$localWiki->setUserAgent( $passwordConfig->userAgent );
$localWiki->url = $config->remoteWikiUrl[$config->remoteWikiName];
$localWiki->login( $passwordConfig->pullUser[$config->remoteWikiName],
      $passwordConfig->pullPass[$config->remoteWikiName] );

$localWiki->__set( 'quiet', 'soft' );
$localWiki->echoRet = true;

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
startTestMsg( "Scen1010" );
editRemoteMsg();

$ret = $wiki->edit( 'Foo', 'Bar' );
pullRc( $passwordConfig, $config, $db );
pullRev( $passwordConfig, $config, $db );
push( $passwordConfig, $config, $db );
checkIdenticalTables( $dbLocal, $dbRemote, array( 'revision' ), array( 'rev_text_id' ) );
endTestMsg( "Scen1010" );

# scen1020


function startTestMsg( $text ) {
    echo "Initiating test $text...\n";
}

function endTestMsg( $text ) {
    echo "Completed test $text.\n";
}

function editLocalMsg( $text ) {
    echo "Editing " . $config->localWikiName . "...\n";
}

function editRemoteMsg( $text ) {
    echo "Editing " . $config->remoteWikiName . "...\n";
}

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
		$row = false;
		continue;
	    }
	    $localRow = $localRet->fetch_assoc();
	    if ( !$localRow ) {
		die( "Ran out of local rows!\n" );
	    }
	    foreach( $remoteRow as $key => $value ) {
		/*if ( !isset( $localRow[$key] ) ) {
		    #echo $value;
		    echo( "Local is missing field $key\n. Remote:" );
		    var_dump( $remoteRow );
		    die();
		}*/
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
	$remoteRow = $remoteRet->fetch_assoc();
	if ( $remoteRow ) {
	    die( "There were superfluous local rows!\n" );
	}
    }
}