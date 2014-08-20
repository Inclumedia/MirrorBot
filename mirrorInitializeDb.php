<?php
// Get the defaults
$defaultsFile = 'mirrorbot_defaults.php';
if ( !file_exists ( $defaultsFile ) ) {
      die ( "File $defaultsFile does not exist\n" );
}
require_once( $defaultsFile );

// Get the passwords
$passwordFile = $passwordPath . "mirrorbot_passwords.php";
if ( !file_exists ( $passwordFile ) ) {
      die ( "File $passwordFile does not exist\n" );
}
require_once( $passwordFile );

// Connect to database
$db = new mysqli( $host, $dbUser, $dbPass );
if ( !$db ) {
      die( 'Could not connect: ' . mysql_error() );
}

// Create database and select it
$db->query ( "CREATE DATABASE IF NOT EXISTS $dbName" );
$db->select_db ( "$dbName" );

$existenceArr = array();
$existenceResult = $db->prepare( "SHOW TABLES FROM $dbName" );
$existenceResult->execute();
$existenceResult->bind_result( $existenceRow );
if ( !$existenceResult ) {
      die( "Could not show tables from $dbName" );
}
while( $existenceResult->fetch() ) {
      $existenceArr[] = $existenceRow;
}
foreach ( $tables as $table => $sqlFile ) {
      echo "Checking table $table...";
      if ( in_array ( $table, $existenceArr ) ) {
            echo "table exists\n";
      } else {
            echo "not found; creating...";
            if ( !file_exists( $sqlFile ) ) {
                  die( "Error: file $sqlFile missing!\n" );
            }
            $sql = file_get_contents ( $sqlFile );
            $dbResult = $db->query ( $sql );
            if ( !$dbResult ) {
                  echo( "failed! Failure data:\n" );
                  var_dump( $db->error_list );
                  die("\n");
            }
            echo "done.\n";
            if( isset( $indexFiles[$table] ) ) {
                  echo "Creating indices for $table...";
                  foreach( $indexFiles[$table] as $indexFile ) {
                        if ( !file_exists( $indexFile ) ) {
                              die( "Error: file $indexFile missing!\n" );
                        }
                        $sql = file_get_contents ( $indexFile );
                        $dbResult = $db->query ( $sql );
                        if ( !$dbResult ) {
                              echo( "failed! Failure data:\n" );
                              var_dump( $db->error_list );
                              die("\n");
                        }
                        echo "done.\n";
                  }
            }
      }
}

function logFailure ( $contents ) {
      // Prepare failure log file
      global $failureLogFile;
      $failures = fopen ( $failureLogFile, 'a' );
      ob_start();
      if ( is_array( $contents ) ) {
            var_dump ( $contents );
      } else {
            echo $contents;
      }
      $writeThis = ob_get_clean();
      fwrite ( $failures, $writeThis );
      if ( is_array( $contents ) ) {
            var_dump ( $contents );
      } else {
            echo $contents;
      }
}