<?php
require_once( 'mirrorInitializeDb.php' );
$restore = array( '2', '4', '6' );
$databases = array(
    '1' => $dbName,
    '2' => $dbName,
    '3' => $localWikiName,
    '4' => $localWikiName,
    '5' => $remoteWikiName,
    '6' => $remoteWikiName
);
$commands = array( '1', '2', '3', '4', '5', '6' );
echo "\nMain menu:\n";
echo "(1) Save $dbName\n";
echo "(2) Restore $dbName\n";
echo "(3) Save $localWikiName\n";
echo "(4) Restore $localWikiName\n";
echo "(5) Save $remoteWikiName\n";
echo "(6) Restore $remoteWikiName\n";
echo "(7) Quit\n";
$command = readline( "Command: " );
if ( !in_array( $command, $commands ) ) {
    die( "Aborted\n" );
}
#if ( in_array( $command, $restore ) ) {
    $first = true;
    $dir = scandir( substr( $databasesPath, 0, strlen( $databasesPath ) - 1 ) );
    foreach ( $dir as $file ) {
        if ( substr( $file, 0, strlen( $databases[$command] ) ) == $databases[$command] ) {
            $name = substr( $file, strlen( $databases[$command] ),
                strlen( $file ) - strlen( $databases[$command] ) - 4 );
            if ( $name ) {
                if ( $first ) {
                    echo "\nSlots:\n";
                    $first = false;
                }
                echo $name . "\n";
            }
        }
    }
#}
$slot = readline( "\nWhich slot (<return> to abort): " );
if ( !$slot ) {
    die ( "Aborted\n" );
}

switch ( $command ) {
    case '1':
        saveDb( $dbUser, $dbPass, $databasesPath, $dbName, $slot );
        break;
    case '2':
        restoreDb( $dbUser, $dbPass, $databasesPath, $dbName, $slot );
        break;
    case '3':
        saveDb( $dbUser, $dbPass, $databasesPath, $localWikiName, $slot );
        break;
    case '4':
        restoreDb( $dbUser, $dbPass, $databasesPath, $localWikiName, $slot );
        break;
    case '5':
        saveDb( $dbUser, $dbPass, $databasesPath, $remoteWikiName, $slot );
        break;
    case '6':
        restoreDb( $dbUser, $dbPass, $databasesPath, $remoteWikiName, $slot );
        break;
    default:
        die( "Aborted\n" );
}
echo "Done\n";

function restoreDb( $user, $pass, $path, $database, $slot ) {
    echo "Restoring $database from slot '$slot'...\n";
    exec("mysql -u$user -p$pass -e \"drop database $database;create database $database;\"");
    exec("mysql -u$user -p$pass $database < " . $path . "$database" . "$slot.sql");
    
}

function saveDb( $user, $pass, $path, $database, $slot ) {
    if ( file_exists( $path . $database . $slot . '.sql' ) ) {
        $overwrite = readline( "Overwrite slot '$slot' (y/n)? " );
        if ( substr( $overwrite, 0, 1 ) != 'Y' && substr( $overwrite, 0, 1 ) != 'y' ) {
            die( "Aborted\n" );
        }
    }
    echo "Saving $database to slot '$slot'...\n";
    exec("mysqldump -u$user -p$pass $database > $path" . "$database" . "$slot.sql");
    return;
}