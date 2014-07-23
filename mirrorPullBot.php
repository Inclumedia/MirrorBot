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

// Initialize database
include( 'mirrorInitializeDb.php' );

// "q" (queue) Four options: -qrc, -qrev, qus, qrcrev (rc and rev)
// "r" (repeat) Three options: -ro (onetime), -rd (continuous, using defaults),
// r<number of microsecs to sleep>
// "s" starting timestamp
$usage = 'Usage: php mirrorpullbot.php -q<option (e.g. rc, rev, us, sw)> '
      . '[-r<option (e.g. ro, rd, r<microseconds>>] [-s<starting (e.g. 20120101000000)>])' . "\n";
$options = getopt( 'q:r:s:');
$allowableOptions['q'] = array(
      'rc',
      'rev',
      'us',
      'rcrev'
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

if ( $options['r'] == 'd' ) {
      $sleepMicroseconds = $defaultMicroseconds['pull'][$options['q']];
}

/* Setup my classes. */
require_once("$botClassesPath/botclasses.php");
$wiki      = new wikipedia;
$wiki->url = $remoteWikiUrl[$remoteWikiName];

// Login
$wiki->login( $pullUser[$remoteWikiName], $pullPass[$remoteWikiName] );

$passes = 0; // Which part of the loop are we on?
$rcContinue = '';
$rcStart = '';
$continueValue = '';
$skip = false;
$optionThisTime = $options['q'];
while ( $options['r'] != 'o' || !$passes ) {
      $passes++;
      if ( $optionThisTime == 'rcrev' ) {
            $optionThisTime = 'rev';
      }
      switch ( $optionThisTime ) {
            case 'rc':
                  // Get starting timestamp, from the default if necessary
                  if ( $passes == 1 ) {
                        if ( !$startingTimestamp ) {
                              $startingTimestamp = $defaultStart['rc'];
                        }
                        $rcStart = "&rcstart=$startingTimestamp";
                        $continueResult = $db->query( 'SELECT * FROM mb_cursor WHERE'
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
                        . "patrolled|title|ids|sizes|redirect|loginfo|flags|sha1|tags&rclimit=$rcLimit"
                        . "$rcContinue&format=php", true);
                  if ( isset( $ret['query-continue']['recentchanges']['rccontinue'] ) ) {
                        $rcContinue = 'N' . $ret['query-continue']['recentchanges']['rccontinue'];
                  }
                  if ( !isset( $ret['query'] ) ) {
                        echo( "API did not give the required query\n" );
                        var_dump( $ret );
                        break;
                  }
                  $events = $ret['query']['recentchanges'];
                  $table = 'mb_queue';
                  $dbFields = array_keys ( $fields['rc'] );
                  $userRow = array_values ( $fields['rc'] );
                  $undesirables = array ( '-', ':', 'T', 'Z' );
                  $row = 'insert into ' . $table . ' ( ' . implode ( ', ', $dbFields ) . ' ) values ';
                  $isFirstInEvent = true;
                  $events = $ret['query']['recentchanges'];
                  // For each user creation event in that result set
                  if ( $skip ) {
                        array_shift( $events );
                  }
                  foreach ( $events as $thisLogevent ) {
                        $deleted = 0;
                        if ( !$isFirstInEvent ) {
                              $row .= ', ';
                        }
                        $isFirstInEvent = false;
                        $row .= '( ';
                        $isFirstInItem = true;
                        // Get rid of dashes, colons, Ts and Zs in timestamp
                        $thisLogevent['timestamp'] = str_replace ( $undesirables, '',
                              $thisLogevent['timestamp'] );
                        foreach( $namespacesToTruncate as $namespaceToTruncate ) {
                              if ( substr( $thisLogevent['title'], 0,
                                    strlen( $namespaceToTruncate ) ) == $namespaceToTruncate ) {
                                    $thisLogevent['title'] = substr( $thisLogevent['title'],
                                          strlen( $namespaceToTruncate ),
                                          strlen( $thisLogevent['title'] )
                                          - strlen( $namespaceToTruncate ) );
                                    break;
                              }
                        }
                        if ( isset( $thisLogevent['type'] ) ) {
                              if ( $thisLogevent['type'] == 'edit'
                                    || $thisLogevent['type'] == 'new' ) {
                                    $thisLogevent['mbqaction'] = 'mirroredit';
                                    $thisLogevent['mbqstatus'] = 'needsrev';
                              }
                        }
                        if ( isset( $thisLogevent['logtype'] ) ) {
                              if ( isset( $mirrorActions[$thisLogevent['logtype']] ) ) {
                                    if ( $thisLogevent['timestamp'] > $importCutoff ) {
                                          $thisLogevent['mbqaction'] =
                                                $mirrorActions[$thisLogevent['logtype']];
                                    } else {
                                          $thisLogevent['mbqaction'] = 'mirrorlogentry';
                                    }
                                    $thisLogevent['mbqstatus'] = 'readytopush';
                              }
                              if ( isset( $thisLogevent['actionhidden'] ) ) {
                                    $deleted++;
                              }
                              if ( isset( $thisLogevent['commenthidden'] ) ) {
                                    $deleted += 2;
                              }
                              if ( isset( $thisLogevent['userhidden'] ) ) {
                                    $deleted += 4;
                              }
                              $thisLogevent['mbqdeleted'] = $deleted;
                        }
                        if ( isset( $thisLogevent['move'] ) ) {
                              if ( isset( $thisLogevent['redirect'] ) ) {
                                    $noredirect = '1';
                              } else {
                                    $noredirect = '0';
                              }
                              $thisLogevent['params'] = serialize( array(
                                    '4::target' => $thisLogevent['move']['new_title'],
                                    '5::noredir' => $noredirect
                              ) );
                        }
                        if ( isset( $thisLogevent['ip'] ) ) {
                              $thisLogevent['mbqrcip'] = $thisLogevent['usertext'];
                        }
                        // Iterate over those database fields
                        foreach ( $userRow as $thisRowItem ) {
                              if ( !$isFirstInItem ) {
                                    $row .= ', ';
                              }
                              $isFirstInItem = false;
                              // If it's a boolean field, 1 if it's there, 0 if not
                              if ( in_array( $thisRowItem, $booleanFields['rc'] ) ) {
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
                                          if ( in_array ( $thisRowItem, $stringFields['rc'] ) ) {
                                                $thisLogevent[$thisRowItem] = "'" . $db->real_escape_string
                                                      ( $thisLogevent[$thisRowItem] ) . "'";
                                          }
                                          $row .= $thisLogevent[$thisRowItem];
                                    } else {
                                          $row .= $defaultFields['rc'][$thisRowItem];
                                    }
                              }
                        }
                        $provisionalRccontinue = 'Y' . $thisLogevent['timestamp'] . $thisLogevent['rcid'];
                        $row .= ')';
                  }
                  $row .= ';';
                  #echo $row . "\n";
                  $queryResult = $db->query ( $row );
                  if ( $queryResult ) {
                        echo "Inserted data successfully!\n";
                        if ( !$rcContinue ) {
                              $rcContinue = $provisionalRccontinue;
                              $skip = true;
                        } else {
                              $skip = false;
                        }
                        // Check cursor existence; if it doesn't, then create one
                        $exist = $db->query( 'SELECT * FROM mb_cursor WHERE'
                              . " mbc_key='rccontinue'");
                        if ( $exist && $exist->num_rows ) {
                              $query = "UPDATE mb_cursor SET mbc_value='$rcContinue' "
                                    . "WHERE mbc_key='rccontinue'";
                        } else {
                              $query = "INSERT INTO mb_cursor (mbc_key, mbc_value) "
                                    . " values ('rccontinue', '$rcContinue')";
                        }
                        $success = $db->query( $query );
                        #echo "$query\n";
                        if ( !$success ) {
                              die ( "Failed to set cursor!\n" );
                        } else {
                              echo "Set cursor successfully!\n";
                        }
                  } else {                        
                        // Note this failure in the failure log file
                        logFailure ( "Failure inserting data\n" );
                        logFailure ( $row );
                        logFailure ( $db->error_list );
                        die();
                  }
                  if ( $options['q'] == 'rcrev' ) {
                        $optionThisTime = 'rev';
                  }
                  break;
            case 'rev':
                  $table = 'mb_queue';
                  $where = "mbq_status='needsrev'";
                  $ret = $db->query( "SELECT * FROM mb_queue "
                        ."WHERE $where LIMIT $revLimit" );
                  if ( !$ret || !$ret->num_rows ) {
                        echo ( "No $where items\n" );
                        if ( $options['q'] == 'rcrev' ) {
                              $optionThisTime = 'rc';
                        }
                        break;
                  }
                  $value = array();
                  $revIds = array();
                  while ( $value = $ret->fetch_assoc() ) {
                        $revIds[] = $value[ 'mbq_rev_id' ];
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
                  $query = "?action=query&prop=revisions&rvprop=user|comment|content|ids|contentmodel&revids="
                        . $queryChunk . '&format=php';
                  $ret = $wiki->query( $query, true );
                  if ( !$ret ) {
                        echo "Did not retrieve any revisions from query; skipping back around\n";
                        break;
                  }
                  $pages = $ret['query']['pages'];
                  foreach ( $pages as $page ) { // Get the particular page...
                        $revisions = $page['revisions'];
                        foreach( $revisions as $revision ) { // Get the particular revision...
                              $thisRevId = $revision['revid'];
                              $content = "''";
                              if ( isset( $revision['*'] ) ) {
                                    $content = "'" . $db->real_escape_string(
                                          $revision['*'] ) . "'";
                              }
                              $revid = "'" . $db->real_escape_string(
                                    $revision['revid'] ) . "'";
                              $contentmodel = "'" . $db->real_escape_string(
                                    $revision['contentmodel'] ) . "'";
                              $contentformat = "''";
                              if ( isset( $revision['contentformat'] ) ) {
                                    $contentformat = "'" . $db->real_escape_string(
                                          $revision['contentformat'] ) . "'";
                              }
                              $deleted = 0;
                              if ( isset( $revision['texthidden'] ) ) {
                                          $deleted++;
                              }
                              if ( isset( $revision['commenthidden'] ) ) {
                                    $deleted += 2;
                              }
                              if ( isset( $revision['userhidden' ] ) ) {
                                    $deleted += 4;
                              }
                              // Insert the content, and get the ID for that row
                              // TODO: Begin transaction and commit transaction
                              $query = "INSERT INTO mb_text (mbt_text) VALUES ("
                                    . $content . ")";
                              $status = $db->query ( $query );
                              if ( $status ) {
                                    echo "Success inserting text for rev id $thisRevId\n";
                              } else {
                                    // Note this failure in the failure log file
                                    logFailure ( "Failure inserting text for rev id $thisRevId\n" );
                                    logFailure ( $db->error_list );
                              }
                              $textId = $db->insert_id;
                              // Now update the queue
                              $query = 'UPDATE mb_queue SET '
                                    . 'mbq_text_id=' . $textId
                                    . ',mbq_deleted=' . $deleted
                                    . ',mbq_rev_content_model=' . $contentmodel
                                    . ',mbq_rev_content_format=' . $contentformat
                                    . ",mbq_status='readytopush'"
                                    . " WHERE mbq_rev_id="
                                    . $revid;
                              $status = $db->query ( $query );
                              if ( $status ) {
                                    echo "Success updating rev $thisRevId with text id $textId\n";
                              } else {
                                    // Note this failure in the failure log file
                                    logFailure( "Failure updating rev $thisRevId\n" );
                                    logFailure ( $db->error_list );
                              }
                        }
                  }
                  break;
      }
      if ( $options['r'] != 'o' ) {
            echo "Sleeping $sleepMicroseconds microseconds...";
            usleep ( $sleepMicroseconds );
            echo "done sleeping.\n";
      }
}