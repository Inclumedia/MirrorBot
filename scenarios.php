<?php
require_once( 'mirrorInitializeDb.php' );
$databases = array( $dbName, $localWikiName, $remoteWikiName );
$commands = array( 'S', 'R', 'D' );
echo "\nMain menu:\n";
echo "(S) Save scenario\n";
echo "(R) Restore scenario\n";
echo "(D) Delete scenario\n";
echo "(Q) Quit\n";
$command = strtoupper( readline( "Command: " ) );

if ( !in_array( $command, $commands ) ) {
    die( "Aborted\n" );
}

$first = true;
$dirs = glob( $scenariosPath . '*' , GLOB_ONLYDIR );
if ( !$dirs ) {
    echo "Creating directory $scenariosPath...\n";
    exec( "mkdir $scenariosPath" );
}
$names = array();
foreach ( $dirs as $dir ) {
    if ( $dir == $scenariosPath ) {
        continue;
    }
    if ( $first ) {
        echo "\nScenarios:\n";
        $first = false;
    }
    $name = substr( $dir, strlen( $scenariosPath ),
        strlen( $dir ) - strlen( $scenariosPath ) );
    $names[] = $name;
    echo $name . "\n";
}
$scenario = readline( "\nWhich scenario (<return> to abort): " );
if ( !$scenario ) {
    die ( "Aborted\n" );
}

switch ( $command ) {
    case 'R':
        foreach ( $databases as $database ) {
            restoreDb( $dbUser, $dbPass, $scenariosPath . "$scenario/", $database, $scenario );
        }
        break;
    case 'S':
        if ( in_array( $scenario, $names ) ) {
            $overwrite = readline( "Overwrite scenario '$scenario' (y/n)? " );
            if ( substr( $overwrite, 0, 1 ) != 'Y' && substr( $overwrite, 0, 1 ) != 'y' ) {
                die( "Aborted\n" );
            }
        } else {
            echo "Making directory $scenariosPath" . "$scenario...\n";
            exec( "mkdir $scenariosPath" . "$scenario" );
        }
        foreach ( $databases as $database ) {
            saveDb( $dbUser, $dbPass, $scenariosPath . "$scenario/", $database, $scenario );
        }
        break;
    case 'D':
        if ( !in_array( $scenario, $names ) ) {
            echo "Scenario $scenario does not exist\n";
        } else {
            $overwrite = readline( "Delete scenario '$scenario' (y/n)? " );
            if ( substr( $overwrite, 0, 1 ) != 'Y' && substr( $overwrite, 0, 1 ) != 'y' ) {
                die( "Aborted\n" );
            }
            echo "Deleting directory $scenariosPath" . "$scenario...\n";
            exec( "rm -rf $scenariosPath" . "$scenario" );
        }
        break;
}
echo "Done\n";

function restoreDb( $user, $pass, $path, $database, $scenario, $dryRun = false ) {
    echo "Restoring $database from scenario $scenario...\n";
    if ( $dryRun ) {
        return;
    }
    exec("mysql -u$user -p$pass -e \"drop database $database;create database $database;\"");
    exec("mysql -u$user -p$pass $database < " . $path . "$database" . ".sql");
}

function saveDb( $user, $pass, $path, $database, $scenario, $dryRun = false ) {
    echo "Saving $database in scenario $scenario...\n";
    if ( $dryRun ) {
        return;
    }
    exec("mysqldump -u$user -p$pass $database > $path" . "$database" . ".sql");
}