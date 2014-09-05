<?php
// Get the defaults
$defaultsFile = 'mirrorbot_defaults.php';
if ( !file_exists ( $defaultsFile ) ) {
      die ( "File $defaultsFile does not exist\n" );
}
require_once( $defaultsFile );
$config = new config();

// Get the passwords
$passwordFile = $config->passwordPath . "mirrorbot_passwords.php";
if ( !file_exists ( $passwordFile ) ) {
      die ( "File $passwordFile does not exist\n" );
}
require_once( $passwordFile );
$passwordConfig = new passwordConfig();

// Connect to database
$db = new mysqli( $passwordConfig->host, $passwordConfig->dbUser, $passwordConfig->dbPass );
if ( !$db ) {
      die( 'Could not connect: ' . mysql_error() );
}

// Create database and select it
$db->query ( "CREATE DATABASE IF NOT EXISTS " . $passwordConfig->dbName );
$db->select_db ( $passwordConfig->dbName );

$existenceArr = array();
$existenceResult = $db->prepare( "SHOW TABLES FROM " . $passwordConfig->dbName );
$existenceResult->execute();
$existenceResult->bind_result( $existenceRow );
if ( !$existenceResult ) {
      die( "Could not show tables from " . $config->dbName );
}
while( $existenceResult->fetch() ) {
      $existenceArr[] = $existenceRow;
}
foreach ( $config->tables as $table => $sqlFile ) {
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
            if( isset( $config->indexFiles[$table] ) ) {
                  echo "Creating indices for $table...";
                  foreach( $config->indexFiles[$table] as $indexFile ) {
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

class mirrorGlobalFunctions {
      public static function logFailure ( $config, $contents ) {
            // Prepare failure log file;
            $failures = fopen ( $config->failureLogFile, 'a' );
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

      public static function doQuery( $db, $config, $query, $action, $failureInfos = null ) {
            $status = $db->query( $query );
            if ( !$status ) {
                  mirrorGlobalFunctions::logFailure( $config, "Failure $action\n" );
                  mirrorGlobalFunctions::logFailure( $config, $db->error_list );
                  if ( $failureInfos ) {
                        if ( !is_array ( $failureInfos ) ) {
                              $failureInfos = array( $failureInfos );
                        }
                        foreach( $failureInfos as $failureInfo ) {
                              mirrorGlobalFunctions::logFailure( $config, $failureInfo );
                        }
                  }
            }
            echo "Success $action\n";
            return $status;
      }
}