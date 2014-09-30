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
                  echo "Creating indices for $table...\n";
                  foreach( $config->indexFiles[$table] as $indexKey => $indexFile ) {
                        echo "Creating $indexKey...";
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

class MirrorGlobalFunctions {
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
            $maxQueryStrlenToLog = 1024; // If it's longer than this, don't log it
            if ( !$status ) {
                  mirrorGlobalFunctions::logFailure( $config, "Failure $action\n" );
                  mirrorGlobalFunctions::logFailure( $config, $db->error_list );
                  if ( strlen( $query ) < $maxQueryStrlenToLog ) {
                        mirrorGlobalFunctions::logFailure( $config, $query );
                  }
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

      // This implementation kinda sucks; it just searches for the word "#redirect"
      public static function isRedirect( $config, $text ) {
            return strpos(
                  strtolower( $text ),
                  strtolower( $config->remoteWikiRedirectString[$config->remoteWikiName] )
            );
      }

      public static function addUndesirables( $timestamp ) {
            return substr( $timestamp, 0, 4 ) . '-'
                  . substr( $timestamp, 4, 2 ) . '-'
                  . substr( $timestamp, 6, 2 ) . 'T'
                  . substr( $timestamp, 8, 2 ) . ':'
                  . substr( $timestamp, 10, 2 ) . ':'
                  . substr( $timestamp, 12, 2 ) . 'Z';
      }

      public static function killUndesirables( $timestamp ) {
            $undesirables = array ( '-', ':', 'T', 'Z' );
            return str_replace ( $undesirables, '', $timestamp );
      }
}