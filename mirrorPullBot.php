<?php
/**
 * MirrorPullBot
 * Version 1.0.1
 * https://www.mediawiki.org/wiki/Extension:MirrorTools
 * By Leucosticte < https://www.mediawiki.org/wiki/User:Leucosticte >
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

// "q" (queue) Three options: -qrc, -qrev, qus
// "r" (repeat) Three options: -ro (onetime), -rd (continuous, using defaults),
// r<number of microsecs to sleep>
// "s" starting timestamp
$usage = 'Usage: php mirrorpullbot.php -q<option (e.g. rc, rev, us)> '
      . '[-r<option (e.g. ro, rd, r<microseconds>>] [-s<starting (e.g. 20120101000000)>])' . "\n";
$options = getopt( 'q:r:s:');
$allowableOptions['q'] = array(
      'rc',
      'rev',
      'us'
);
$allowableOptions['r'] = array(
      'o',
      'd',
);
if ( !isset ( $options['q'] ) ) {
      die ( $usage );
}
if ( !isset ( $options['r'] ) ) {
      $options['r'] = 'o'; // Default to onetime
}
if ( !in_array ( $options[ 'q' ], $allowableOptions['q'] ) ) {
      die ( $usage );
}
if ( !in_array ( $options[ 'r' ], $allowableOptions['r'] ) ) {
      if ( !is_numeric ( $options['r'] ) ) { // Microseconds option
            echo "You did not select an acceptable option for r\n";
            die ( $usage );
      } else {
            $sleepMicroseconds = $options['r'];
      }
}
$startingTimestamp = '';
if ( isset ( $options['s'] ) ) {
      if ( is_numeric ( $options['s'] ) ) {
            if ( $options['s'] < 10000000000000 || $options['s'] > 30000000000000 ) {
                  die ( "Error: Timestamp must be after C.E. 1000 and before C.E. 3000\n" );
            }
      } else {
            die ( "Starting timestamp supposed to be an integer\n" );
      }
      $startingTimestamp = $options['s'];
}

include( 'mirrorInitializeDb.php' );

if ( $options['r'] == 'd' ) {
      $sleepMicroseconds = $defaultMicroseconds['pull'][$options['q']];
}

/* Setup my classes. */
include( 'botclasses.php' );
$wiki      = new wikipedia;
$wiki->url = $remoteWikiUrl;

// Login
$wiki->login( $pullUser, $pullPass );

$passes = 0; // Which part of the loop are we on?
$rcContinue = '';
$rcStart = '';
$continueValue = '';
$skip = false;
while ( $options['r'] != 'o' || !$passes ) {
      $passes++;
      switch ( $options['q'] ) {
            case 'rc':
                  // Get starting timestamp, from the default if necessary
                  if ( $passes == 1 ) {
                        if ( !$startingTimestamp ) {
                              $startingTimestamp = $defaultStart['rc'];
                        }
                        $rcStart = "&rcstart=$startingTimestamp";
                        $continueResult = $con->query( 'SELECT * FROM mb_cursor WHERE'
                              . " mbc_key='rccontinue'" );
                        if ( $continueResult ) {
                              $continueValueArr = $continueResult->fetch_assoc();
                              $continueValue = $continueValueArr['mbc_value'];
                              // Y = Yes, skip. N = No, don't skip.
                              $skipVal = substr( $continueValue, 0, 1 );
                              if ( $skipVal == 'Y' ) {
                                    $skip = true;
                              }
                              $continueValue = substr( $continueValue, 1,
                                    strlen( $continueValue ) - 1 );
                              if ( $continueValue ) {
                                    $rcContinue = "&rccontinue=$continueValue";
                              }
                        }
                  }
                  $ret = $wiki->query ( "?action=query&list=recentchanges"
                        . "$rcStart&rcdir=newer&rcprop=user|userid|comment|timestamp|"
                        . "patrolled|title|ids|sizes|redirect|loginfo|flags|loginfo|sha1|tags&rclimit=$rcLimit"
                        . "$rcContinue&format=php", true);
                  if ( isset( $ret['query-continue']['recentchanges']['rccontinue'] ) ) {
                        $rcContinue = 'N' . $ret['query-continue']['recentchanges']['rccontinue'];
                  }
                  if ( !isset( $ret['query'] ) ) {
                        echo( 'API did not give the required query' );
                        break;
                  }
                  $events = $ret['query']['recentchanges'];
                  $table = 'mb_rc_queue';
                  $fields = array (
                        'mbrcq_rc_id' => 'rcid',
                        'mbrcq_anon' => 'anon',
                        'mbrcq_rc_bot' => 'bot',
                        'mbrcq_rc_comment' => 'comment',
                        'mbrcq_rc_log_action' => 'logaction',
                        'mbrcq_rc_logid' => 'logid',
                        'mbrcq_rc_logtype' => 'logtype',
                        'mbrcq_rc_minor' => 'minor',
                        'mbrcq_rc_new' => 'new',
                        'mbrcq_rc_new_len' => 'newlen',
                        'mbrcq_rc_namespace' => 'ns',
                        'mbrcq_rc_old_len' => 'oldlen',
                        'mbrcq_rc_cur_id' => 'pageid',
                        'mbrcq_rc_patrolled' => 'patrolled',
                        'mbrcq_rc_thisoldid' => 'revid',
                        'mbrcq_rc_lastoldidid' => 'revoldid',
                        'mbrcq_redirect' => 'redirect',
                        'mbrcq_rc_timestamp' => 'timestamp',
                        'mbrcq_rc_title' => 'title',
                        'mbrcq_rc_type' => 'type',
                        'mbrcq_sha1' => 'sha1',
                        'mbrcq_rc_user' => 'userid',
                        'mbrcq_rc_user_text' => 'user',
                        'mbrcq_user' => 'addeduserid', // This isn't actually in the API result
                        'mbrcq_user_text' => 'addeduser' // This isn't actually in the API result
                  );
                  $stringFields = array (
                        'title',
                        'type',
                        'action',
                        'user',
                        'comment',
                        'tags',
                        'logaction',
                        'logtype',
                        'addeduser',
                        'sha1'
                  );
                  $booleanFields = array (
                        'anon',
                        'bot',
                        'minor',
                        'new',
                        'patrolled',
                        'redirect',
                  );
                  $defaultFields = array (
                        'rcid' => 0,
                        'anon' => 0,
                        'bot' => 0,
                        'comment' => "''",
                        'logaction' => 'NULL',
                        'logid' => 0,
                        'logtype' => "''",
                        'minor' => 0,
                        'new' => 0,
                        'newlen' => 0,
                        'ns' => 0,
                        'oldlen' => 0,
                        'pageid' => 0,
                        'patrolled' => 0,
                        'revid' => 0,
                        'revoldid' => 0,
                        'redirect' => 0,
                        'timestamp' => "''",
                        'title' => "''",
                        'type' => 'NULL',
                        'user' => "''",
                        'userid' => 0,
                        'addeduser' => "''",
                        'addeduserid' => 0,
                        'sha1' => "''"
                  );
                  break;
            case 'rev':
                  $table = 'mb_rc_queue';
                  $where = "(mbrcq_rc_type='edit' or mbrcq_rc_type='new') AND mbrcq_text='' AND mbrcq_push_result=''";
                  $ret = $con->query( "SELECT * FROM mb_rc_queue "
                        ."WHERE $where LIMIT $revLimit" );
                  // TODO: What about situations in which it's expected that you'll run out of
                  // items, and need to keep looping anyway?
                  if ( !$ret ) {
                        echo ( "No $where items\n" );
                        break;
                  }
                  $value = array();
                  $revIds = array();
                  while ( $value = $ret->fetch_assoc() ) {
                        $revIds[] = $value[ 'mbrcq_rc_thisoldid' ];
                  }
                  $firstRevId = true;
                  $queryChunk = '';
                  foreach( $revIds as $revId ) {
                        if( !$firstRevId ) {
                              $queryChunk .= '|';
                        }
                        $firstRevId = false;
                        $queryChunk .= $revId;
                  }
                  $ret = $wiki->query ("?action=query&prop=revisions&rvprop=content|ids|contentmodel&revids="
                        . $queryChunk . '&format=php' , true );
                  if ( !$ret ) {
                        echo "Did not retrieve any revisions from query; skipping back around\n";
                        break;
                  }
                  $events = $ret['query']['pages'];
                  foreach ( $events as $thisEvent ) { // Get the particular page...
                        $thisRevId = $thisEvent[ 'revisions' ][0]['revid' ];
                        $content = "'" . $con->real_escape_string( $thisEvent[ 'revisions' ][0]['*'] ) . "'";
                        $revid = "'" . $con->real_escape_string( $thisEvent[ 'revisions' ][0]['revid' ] ) . "'";
                        $contentmodel = "'" . $con->real_escape_string( $thisEvent[ 'revisions' ][0]['contentmodel' ] ) . "'";
                        // Insert the content, and get the ID for that row
                        // TODO: Begin transaction and commit transaction
                        $query = 'INSERT INTO mb_text SET '
                              . 'mbt_text=' . $content;
                        $status = $con->query ( $query );
                        if ( $status ) {
                              echo "Success inserting text for $thisRevId\n";
                        } else {
                              echo "Failure inserting text for $thisRevId\n";
                              // Note this failure in the failure log file
                              fwrite ( $failures, $query . "\n" );
                        }
                        $textId = $con->$insert_id();
                        // Now update the queue
                        $query = 'UPDATE mb_rc_queue SET '
                              . 'mbrcq_textid=' . $textId
                              . ',mbrcq_content_model=' . $contentmodel
                              . " WHERE mbrcq_rc_thisoldid="
                              . $revid;
                        $status = $con->query ( $query );
                        if ( $status ) {
                              echo "Success updating $thisRevId\n";
                        } else {
                              echo "Failure updating $thisRevId\n";
                              // Note this failure in the failure log file
                              fwrite ( $failures, $query . "\n" );
                        }
                  }
                  break;
            case 'us': // The usefulness of this entire section of code is questionable.
                  $table = 'mb_rc_queue';
                  $where = "mbrcq_rc_log_action='create' AND mbrcq_rc_logtype='newusers'";
                  $ret = $con->query( "SELECT * FROM mb_rc_queue "
                        ."WHERE $where LIMIT 500" );
                  if ( !$ret ) {
                        die ( "No $where items\n" );
                  }
                  $userTitle = array();
                  while ( $value = $ret->fetch_assoc() ) {
                        // Strip "User:" off the front
                        if ( substr( $value['mbrcq_rc_title'], 0, 5 ) == 'User:' ) {
                              $value['mbrcq_rc_title'] =
                                    substr( $value['mbrcq_rc_title'], 5,
                                           strlen( $value['mbrcq_rc_title'] ) - 5 );
                        }
                        $userTitle[] = $value[ 'mbrcq_rc_title' ];
                  }
                  $firstUserTitle = true;
                  $queryChunk = '';
                  foreach ( $userTitle as $thisUserTitle ) {
                        if ( !$firstUserTitle ) {
                              $queryChunk .= '|';
                        }
                        $firstUserTitle = false;
                        $queryChunk .= urlencode( $thisUserTitle );
                  }
                  // POST, don't use a header, and don't unserialize
                  $ret = $wiki->query ('?action=query&format=php&list=users&ususers=' . $queryChunk
                        , true );
                  if ( !$ret ) {
                        echo "Error: Did not retrieve any user IDs from query\n";
                  }
                  $events = $ret['query']['users'];
                  foreach ( $events as $thisEvent ) {
                        $name = "'" . $con->real_escape_string( $thisEvent[ 'name' ] ) . "'";
                        $pageTitle = "'User:" . $con->real_escape_string( $thisEvent[ 'name' ] ) . "'";
                        $query = 'UPDATE mb_rc_queue SET '
                              . 'mbrcq_user=' . $thisEvent[ 'userid']
                              . ', mbrcq_user_text=' . $name
                              . " WHERE mbrcq_rc_log_action='create' AND"
                              . " mbrcq_rc_logtype='newusers' AND mbrcq_rc_title="
                              . $pageTitle;
                        echo $query . "\n";
                        $status = $con->query ( $query );
                        if ( $status ) {
                              echo "Success\n";
                        } else {
                              echo "Failure\n";
                        }
                  }
                  break;
      }

      if ( $options[ 'q' ] == 'rc' ) {
            $dbFields = array_keys ( $fields );
            $userRow = array_values ( $fields );
            $undesirables = array ( '-', ':', 'T', 'Z' );
            $row = 'insert into ' . $table . ' ( ' . implode ( ', ', $dbFields ) . ' ) values ';
            $isFirstInEvent = true;
            $events = $ret['query']['recentchanges'];
            // For each user creation event in that result set
            if ( $skip ) {
                  array_shift( $events );
            }
            foreach ( $events as $thisLogevent ) {
                  // Default values for adduserid and adduser
                  if ( isset ( $thisLogevent[ 'userid' ] ) ) {
                        $thisLogevent[ 'addeduserid' ] = $thisLogevent[ 'userid' ];
                  }
                  if ( isset ( $thisLogevent[ 'user' ] ) ) {
                        $thisLogevent[ 'addeduser' ] = $thisLogevent[ 'user' ];
                  }
                  // Make those different if it's a create2
                  if ( isset ( $thisLogevent[ 'logaction' ] ) ) {
                        if ( $thisLogevent[ 'logaction' ] == 'create2' ) {
                              $thisLogevent [ 'addeduserid' ] = 0;
                              $title = $thisLogevent[ 'title' ];
                              $strposTitle = strpos ( $title, ':' );
                              $thisLogevent [ 'addeduser' ] = substr ( $title, $strposTitle + 1
                                    , strlen ( $title ) - $strposTitle );
                        }
                  }
                  if ( !$isFirstInEvent ) {
                        $row .= ', ';
                  }
                  $isFirstInEvent = false;
                  $row .= '( ';
                  $isFirstInItem = true;
                  // Get rid of dashes, colons, Ts and Zs in timestamp
                  $thisLogevent['timestamp'] = str_replace ( $undesirables, '', $thisLogevent['timestamp'] );
                  // Iterate over those database fields
                  foreach ( $userRow as $thisRowItem ) {
                        if ( !$isFirstInItem ) {
                              $row .= ', ';
                        }
                        $isFirstInItem = false;
                        // If it's a boolean field, 1 if it's there, 0 if not
                        if ( in_array( $thisRowItem, $booleanFields ) ) {
                              if ( isset ( $thisLogevent[ $thisRowItem ] ) ) {
                                    $row .= '1';
                              } else {
                                    $row .= '0';
                              }
                        } else {
                              if ( isset ( $thisLogevent[$thisRowItem] ) ) {
                                    // If it's an array (e.g. tag array), implode it
                                    if ( is_array ( $thisLogevent[$thisRowItem] ) ) {
                                          $thisLogevent[$thisRowItem] = implode ( $thisLogevent[$thisRowItem] );
                                    }
                                    // If it's a string field, escape it
                                    if ( in_array ( $thisRowItem, $stringFields ) ) {
                                          $thisLogevent[$thisRowItem] = "'" . $con->real_escape_string
                                                ( $thisLogevent[$thisRowItem] ) . "'";
                                    }
                                    $row .= $thisLogevent[$thisRowItem];
                              } else {
                                    $row .= $defaultFields[$thisRowItem];
                              }
                        }
                  }
                  $provisionalRccontinue = 'Y' . $thisLogevent['timestamp'] . $thisLogevent['rcid'];
                  $row .= ')';
            }
            $row .= ';';
            echo $row . "\n";
            $queryResult = $con->query ( $row );
            if ( $queryResult ) {
                  echo "Inserted data successfully!\n";
                  if ( !$rcContinue ) {
                        $rcContinue = $provisionalRccontinue;
                        $skip = true;
                  } else {
                        $skip = false;
                  }
                  // Check cursor existence; if it doesn't, then create one
                  $exist = $con->query( 'SELECT * FROM mb_cursor WHERE'
                        . " mbc_key='rccontinue'");
                  if ( $exist && $exist->num_rows ) {
                        $query = "UPDATE mb_cursor SET mbc_value='$rcContinue' "
                              . "WHERE mbc_key='rccontinue'";
                  } else {
                        $query = "INSERT INTO mb_cursor (mbc_key, mbc_value) "
                              . " values ('rccontinue', '$rcContinue')";
                  }
                  $success = $con->query( $query );
                  echo "$query\n";
                  if ( !$success ) {
                        die ( "Failed to set cursor!\n" );
                  } else {
                        echo "Set cursor successfully!\n";
                  }
            } else {
                  echo "Failure inserting data\n";
                  // Note this failure in the failure log file
                  fwrite ( $failures, $row . "\n" );
            }
      }
      if ( $options['r'] != 'o' ) {
            echo "Sleeping $sleepMicroseconds microseconds...";
            usleep ( $sleepMicroseconds );
            echo "done sleeping.\n";
      }
}
