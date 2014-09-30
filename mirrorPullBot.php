<?php
/**
 * MirrorPullBot
 * Version 1.0.2
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
require_once( 'mirrorInitializeDb.php' );
require_once( $config->botClassesPath . "/botclasses.php" );

$botOperations = new botOperations( $passwordConfig, $config, $db, getopt( 'q:r:s:') );
$botOperations->botLoop();

class botOperations {
      public $passes = 0; // Which part of the loop are we on?
      public $wiki;
      public $options;
      public $sleepMicroseconds;
      public $startingTimestamp;
      public $db;
      public $config;
      public $passwordConfig;
      public $wikiName;

      function __construct( $passwordConfig, $config, $db, $options ) {
            $this->config = $config;
            $this->options = $options;
            $this->db = $db;
            $this->passwordConfig = $passwordConfig;
            $this->initialize();
      }

      function botLoop() {
            $optionThisTime = $this->options['q'];
            while ( $this->options['r'] != 'o' || !$this->passes ) {
                  $this->passes++;
                  if ( $this->options['q'] == 'rcrev' ) {
                        $result = $db->select( "SELECT * FROM mb_queue "
                              . "ORDER BY 'mbq_id ASC' LIMIT 1" );
                        $nextRow = $result->fetch_assoc();
                        // If there's something blocking the pushbot, do that now
                        if ( isset( $mirrorFunctions[$nextRow['mbq_status']] ) ) {
                              $optionThisTime =
                                    $this->config->mirrorFunctions[$nextRow['mbq_status']];
                        // Otherwise, stay in the rotation
                        } else {
                              $flipped = array_flip( $this->config->optionRotation );
                              if ( $flipped[$optionThisTime] === count( $flipped ) ) {
                                    $optionThisTime = $this->config->optionRotation[0];
                              } else {
                                    $optionThisTime = $this->config->optionRotation
                                          [$flipped[$optionThisTime] + 1];
                              }
                        }
                  }
                  switch ( $optionThisTime ) {
                        case 'rc':
                              $this->rc();
                              break;
                        case 'rev':
                              $this->rev();
                              break;
                        case 'nullrev':
                              $this->nullrev();
                              break;
                        case 'redirectrev':
                              $this->redirectrev();
                              break;
                        case 'revids':
                              $this->revids();
                              break;
                        case 'imageinfo':
                              $this->imageinfo();
                              break;
                        case 'imagedownload':
                              $this->imagedownload();
                              break;
                  }
                  if ( $this->options['r'] != 'o' ) {
                        echo "Sleeping " . $this->sleepMicroseconds . " microseconds...";
                        usleep ( $this->sleepMicroseconds );
                        echo "done sleeping.\n";
                  }
            }
      }

      function rc() {
            $rcStart = '';
            $continueValue = '';
            $skip = false;
            $rcContinue = '';
            $rcContinueForQuery = '';
            $mbcId = 0;
            $mbcNumRows = 0;
            // Get starting timestamp, from the default if necessary
            if ( $this->passes === 1 ) {
                  if ( !$this->startingTimestamp ) {
                        $this->startingTimestamp = $this->config->defaultStart['rc'];
                  }
                  $rcStart = "&rcstart=" . $this->startingTimestamp;
                  $continueResult = $this->db->query( 'SELECT * FROM mb_cursor WHERE'
                        . " mbc_key='rccontinue'" );
                  if ( $continueResult ) {
                        $continueValueArr = $continueResult->fetch_assoc();
                        $continueValue = $continueValueArr['mbc_value'];
                        $mbcId = $continueValueArr['mbc_id'];
                        $mbcNumRows = $continueResult->num_rows;
                        // Y = Yes, skip. N = No, don't skip.
                        $skipVal = substr( $continueValue, 0, 1 );
                        if ( $skipVal === 'Y' ) {
                              $skip = true;
                        }
                        $continueValue = substr( $continueValue, 1,
                              strlen( $continueValue ) - 1 );
                        if ( $continueValue ) {
                              $rcContinueForQuery = "&rccontinue=$continueValue";
                        }
                  }
            }
            $ret = $this->wiki->query ( "?action=query&list=recentchanges"
                  . "$rcStart&rcdir=newer&rcprop=user|userid|comment|timestamp|"
                  . "patrolled|title|ids|sizes|redirect|loginfo|flags|sha1|tags&rclimit="
                  . $this->config->rcLimit
                  . "$rcContinueForQuery&format=php", true );
            if ( isset( $ret['query-continue']['recentchanges']['rccontinue'] ) ) {
                  $rcContinue = 'N' . $ret['query-continue']['recentchanges']['rccontinue'];
            }
            if ( !isset( $ret['query']['recentchanges'] ) ) {
                  echo( "API did not give the required query\n" );
                  var_dump( $ret );
                  return;
            }
            if ( !$ret['query']['recentchanges'] ) {
                  echo( "There were no recent changes results\n" );
                  return;
            }
            $events = $ret['query']['recentchanges'];
            $table = 'mb_queue';
            $dbFields = array_keys ( $this->config->fields['rc'] );
            $userRow = array_values ( $this->config->fields['rc'] );
            $row = 'insert into ' . $table . ' ( ' . implode ( ', ', $dbFields ) . ' ) values ';
            $isFirstInEvent = true;
            $events = $ret['query']['recentchanges'];
            // For each event in that result set
            if ( $skip ) {
                  $exploded = explode( '|', $continueValue );
                  if ( $events[0]['rcid'] === intval( $exploded['1'] ) ) {
                        array_shift( $events );
                  }
            }
            if ( !$events ) {
                  echo "No events\n";
                  return;
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
                  foreach ( $this->config->timestampFields as $timestampField ) {
                        if ( isset( $thisLogevent[$timestampField] ) ) {
                        $thisLogevent[$timestampField] = MirrorGlobalFunctions::killUndesirables(
                              $thisLogevent[$timestampField] );
                        }
                  }
                  foreach( $this->config->namespacesToTruncate[$this->wikiName]
                        as $namespaceToTruncate ) {
                        if ( $namespaceToTruncate ) { // Beware the empty string of namespace 0
                              if ( substr( $thisLogevent['title'], 0,
                                    strlen( $namespaceToTruncate ) ) === $namespaceToTruncate ) {
                                    $thisLogevent['title'] = substr( $thisLogevent['title'],
                                          strlen( $namespaceToTruncate ),
                                          strlen( $thisLogevent['title'] )
                                          - strlen( $namespaceToTruncate ) );
                                    // Make sure we only strip off the first occurrence of a prefix
                                    break;
                              }
                        }
                  }
                  if ( isset( $thisLogevent['type'] ) ) {
                        if ( $thisLogevent['type'] === 'edit'
                              || $thisLogevent['type'] === 'new' ) {
                              $thisLogevent['mbqaction'] = 'mirroredit';
                              $thisLogevent['mbqstatus'] = 'needsrev';
                        }
                  }
                  if ( isset( $thisLogevent['logtype'] )
                        && isset( $thisLogevent['logaction'] ) ) {
                        if ( isset( $this->config->mirrorTypeActions
                              [$thisLogevent['logtype']]
                              [$thisLogevent['logaction']] ) ) {
                              $thisLogevent['mbqstatus'] = 'readytopush'; // Default
                              if ( $thisLogevent['timestamp'] > $this->config->importCutoff ) {
                                    $thisLogevent['mbqaction'] =
                                          $this->config->mirrorTypeActions
                                          [$thisLogevent['logtype']]
                                          [$thisLogevent['logaction']]['mirroraction'];
                              } else {
                                    $thisLogevent['mbqaction'] = 'mirrorlogentry';
                              }
                              if ( isset( $this->config->mirrorTypeActions
                                    [$thisLogevent['logtype']]
                                    [$thisLogevent['logaction']]['status'] ) ) {
                                    $thisLogevent['mbqstatus'] = $this->config->mirrorTypeActions
                                    [$thisLogevent['logtype']]
                                    [$thisLogevent['logaction']]['status'];
                              }
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
                        if ( $thisLogevent['logaction'] === 'merge' ) {
                              $thisLogevent['params'] = $thisLogevent[0] . "\n" . $thisLogevent[1];
                        }
                  }
                  if ( isset( $thisLogevent['move'] ) ) {
                        if ( isset( $thisLogevent['move']['suppressedredirect'] ) ) {
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
                  if ( isset( $thisLogevent['type'] ) ) {
                        $thisLogevent['mbqrcsource'] = $this->config->sources[$thisLogevent['type']];
                  }
                  // Handle the params2 stuff
                  $params2Arr = array();
                  foreach ( $this->config->params2['rc'] as $param2 ) {
                        if ( isset( $thisLogevent[$param2] ) ) {
                              $params2Arr[$param2] = $thisLogevent[$param2];
                        }
                  }
                  $thisLogevent['mbqparams2'] = serialize( $params2Arr );
                  // Iterate over those database fields
                  foreach ( $userRow as $thisRowItem ) {
                        if ( !$isFirstInItem ) {
                              $row .= ', ';
                        }
                        $isFirstInItem = false;
                        // If it's a boolean field, 1 if it's there, 0 if not
                        if ( in_array( $thisRowItem, $this->config->booleanFields['rc'] ) ) {
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
                                    if ( in_array ( $thisRowItem, $this->config->stringFields['rc'] ) ) {
                                          $thisLogevent[$thisRowItem] = "'" . $this->db->real_escape_string
                                                ( $thisLogevent[$thisRowItem] ) . "'";
                                    }
                                    $row .= $thisLogevent[$thisRowItem];
                              } else {
                                    $row .= $this->config->defaultFields['rc'][$thisRowItem];
                              }
                        }
                  }
                  $provisionalRccontinue = 'Y' . $thisLogevent['timestamp'] . '|' . $thisLogevent['rcid'];
                  $row .= ')';
            }
            $row .= ';';
            $queryResult = $this->db->query ( $row );
            if ( $queryResult ) {
                  echo "Inserted " . count( $events ) . " changes successfully!\n";
                  if ( !$rcContinue ) {
                        $rcContinue = $provisionalRccontinue;
                        $skip = true;
                  } else {
                        $skip = false;
                  }
                  // Check cursor existence. If it exists, update it. If it doesn't, then
                  // insert it.
                  if ( $mbcNumRows ) {
                        $query = "UPDATE mb_cursor SET mbc_value='$rcContinue' "
                              . "WHERE mbc_key='rccontinue'";
                  } else {
                        $query = "INSERT INTO mb_cursor (mbc_key, mbc_value) "
                              . " values ('rccontinue', '$rcContinue')";
                  }
                  $success = $this->db->query( $query );
                  if ( !$success ) {
                        die ( "Failed to set cursor!\n" );
                  } else {
                        echo "Set cursor successfully!\n";
                  }
            } else {
                  // Note this failure in the failure log file
                  MirrorGlobalFunctions::logFailure( $this->config, "Failure inserting data\n" );
                  MirrorGlobalFunctions::logFailure( $this->config, $row );
                  MirrorGlobalFunctions::logFailure( $this->config, $this->db->error_list );
                  die();
            }
      }

      function rev() {
            $table = 'mb_queue';
            $where = "mbq_status='needsrev'";
            $options = "ORDER BY mbq_id ASC";
            $ret = $this->db->query( "SELECT * FROM mb_queue "
                  . "WHERE $where $options LIMIT ". $this->config->revLimit );
            if ( !$ret || !$ret->num_rows ) {
                  echo ( "No $where items\n" );
                  return;
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
            $data['revids'] = $queryChunk;
            $query = "?action=query&prop=revisions&rvprop=user|comment|content|ids"
                  . "|contentmodel" . '&format=php';
            $ret = $this->wiki->query( $query, $data );
            if ( !$ret ) {
                  echo "Did not retrieve any revisions from query; skipping back around\n";
                  return;
            }
            // Handle revisions of deleted pages. Mark these as rows to ignore unless/until
            // the pages are restored on the remote wiki.
            if ( isset( $ret['query']['badrevids'] ) ) {
                  $badRevs = $ret['query']['badrevids'];
                  foreach ( $badRevs as $badRev ) {
                        $badRevId = $badRev['revid'];
                        $query = 'UPDATE mb_queue SET '
                              . "mbq_status='needsundeletion'"
                              . " WHERE mbq_rev_id="
                              . $badRevId . " AND mbq_status='needsrevid'";
                        MirrorGlobalFunctions::doQuery ( $this->db, $this->config, $query,
                              "marking bad revision ID $badRevId as needsundeletion" );
                  }
            }
            if ( !isset( $ret['query']['pages'] ) ) {
                  echo "There was nothing to put in the queue table.\n";
                  return;
            }
            $pages = $ret['query']['pages'];
            foreach ( $pages as $page ) { // Get the particular page...
                  $revisions = $page['revisions'];
                  foreach( $revisions as $revision ) { // Get the particular revision...
                        $content = "''";
                        if ( isset( $revision['*'] ) ) {
                              $content = "'" . $this->db->real_escape_string(
                                    $revision['*'] ) . "'";
                        }
                        $row = 'UPDATE mb_queue SET ';
                        // Iterate over those database fields
                        $isFirstInItem = true;
                        foreach ( $revision as $revisionKey => $revisionValue ) {
                              if ( in_array( $revisionKey, $this->config->fields['rev'] )
                                    && $revisionKey != 'revid' ) {
                                    if ( !$isFirstInItem ) {
                                          $row .= ', ';
                                    }
                                    $isFirstInItem = false;
                                    $row .= array_search( $revisionKey, $this->config->fields['rev'] ) . '=';
                                    // If it's a boolean field, 1 if it's there, 0 if not
                                    if ( in_array( $revisionKey, $this->config->booleanFields['rc'] ) ) {
                                          if ( isset ( $revision[$revisionField] ) ) {
                                                $row .= '1';
                                          } else {
                                                $row .= '0';
                                          }
                                    } else {
                                          // If it's an array (e.g. tag array), implode it
                                          if ( is_array( $revisionValue ) ) {
                                                $revisionValue = implode( '|',
                                                      $revision[$revisionField] );
                                          }
                                          // If it's a string field, escape it
                                          if ( in_array( $revisionKey,
                                                $this->config->stringFields['rev'] ) ) {
                                                $revisionValue = "'" . $this->db->real_escape_string
                                                      ( $revisionValue ) . "'";
                                          }
                                    $row .= $revisionValue;
                                    }
                              }
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
                        $row .= ",mbq_deleted=$deleted";
                        // Insert the content, and get the ID for that row
                        // TODO: Begin transaction and commit transaction
                        $query = "INSERT INTO mb_text (mbt_text) VALUES ("
                              . $content . ")";
                        $status = MirrorGlobalFunctions::doQuery ( $this->db, $this->config, $query,
                              "inserting text for rev id " . $revision['revid'] );
                        $textId = $this->db->insert_id;
                        // Now update the queue
                        $row .= ",mbq_text_id=$textId"
                              . ",mbq_status='readytopush'"
                              . " WHERE mbq_rev_id="
                              . $revision['revid'] . " AND mbq_status='needsrev'";
                        MirrorGlobalFunctions::doQuery ( $this->db, $this->config, $row,
                              'updating rev ' . $revision['revid']
                              . " with text id $textId" );
                  }
            }
      }

      function revids() {
            $mbcId = 0;
            $mbcNumRows = 0;
            $rvContinueForQuery = '';
            $continueValue = null;
            $continueValueArr = null;
            // Get starting rev_id
            $continueResult = $this->db->query( 'SELECT * FROM mb_cursor WHERE'
                  . " mbc_key='mirrorpagerestore-needsrevids-rvcontinue'" );
            if ( $continueResult ) {
                  $continueValueArr = $continueResult->fetch_assoc();
                  $continueValue = $continueValueArr['mbc_value'];
                  $mbcId = $continueValueArr['mbc_id'];
                  $mbcNumRows = $continueResult->num_rows;
                  if ( $continueValue ) {
                        $rvContinueForQuery = "&rvcontinue=$continueValue";
                  }
            }
            // Get the needsrevids row from the mb_queue
            $needsRevIdsWhere = "(mbq_status='needspagerestorerevids' OR "
                  . "mbq_status='needsprotectrevids')";
            $needsRevIdsQueryOptions = "";
            if ( $continueValueArr ) {
                  $needsRevIdsWhere .= " AND mbq_rc_id=" . $continueValueArr['mbc_misc'];
            } else {
                  $needsRevIdsQueryOptions = "ORDER BY mbq_id ASC";
            }
            $needsRevIdsQueryOptions .= " LIMIT 1";
            $dbQuery = "SELECT * FROM mb_queue WHERE $needsRevIdsWhere "
                  . $needsRevIdsQueryOptions;
            $needsRevIds = $this->db->query( $dbQuery );
            if ( !$needsRevIds || !$needsRevIds->num_rows ) {
                  echo "No mirrorpagerestore needsrevids items\n";
                  return;
            }
            $needsRevIdsArr = $needsRevIds->fetch_assoc();
            $rcIdForRows = $needsRevIdsArr['mbq_rc_id']; // Put this in the rows' mbq_rc_id
            $ret = $this->wiki->query ( "?action=query&prop=revisions&pageids="
                  . $needsRevIdsArr['mbq_page_id']
                  . "&rvdir=newer&rvprop=ids|flags|timestamp|user|userid|sha1|contentmodel"
                  . "|comment|size|tags&rvlimit=" . $this->config->revLimit
                  . "$rvContinueForQuery&format=php", true );
            if ( isset( $ret['query-continue']['revisions']['rvcontinue'] ) ) {
                  $rvContinue = $ret['query-continue']['revisions']['rvcontinue'];
            } else {
                  // Delete the cursor and switch over to needsmakeremotelylive at the end of
                  // this
                  $rvContinue = null;
            }
            if ( !isset( $ret['query']['pages'] ) ) {
                  echo "There was nothing to put in the queue table.\n";
                  return;
            }
            $pages = $ret['query']['pages'];
            foreach ( $pages as $page ) { // Get the particular page...
                  // This page's revisions were deleted, apparently, so this will just be a
                  // mirrorlogentry
                  if ( !isset( $page['revisions'] ) ) {
                        $query = "UPDATE mb_queue SET "
                              . "mbq_status='readytopush',mbq_action='mirrorlogentry' "
                              . "WHERE $needsRevIdsWhere ";
                        MirrorGlobalFunctions::doQuery( $this->db,
                              $this->config, $query,
                              'changing mirrorpagerestore needsrevids to '
                              . 'mirrorlogentry readytopush' );
                        return;
                  }
                  $events = $page['revisions'];
                  $table = 'mb_queue';
                  $dbFields = array_keys ( $this->config->fields['pagerestorerevids'] );
                  $userRow = array_values ( $this->config->fields['pagerestorerevids'] );
                  $undesirables = array ( '-', ':', 'T', 'Z' );
                  $row = 'insert into ' . $table . ' ( ' . implode ( ', ', $dbFields ) . ' ) values ';
                  $isFirstInEvent = true;
                  if ( !$events ) {
                        echo "No events\n";
                        return;
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
                        $thisLogevent['pageid'] = $needsRevIdsArr['mbq_page_id'];
                        $thisLogevent['mbqaction'] = 'makeremotelylive';
                        $thisLogevent['mbqstatus'] = 'needsmakeremotelylive';
                        $thisLogevent['namespace'] = $needsRevIdsArr['mbq_namespace'];
                        $thisLogevent['title'] = $needsRevIdsArr['mbq_title'];
                        $thisLogevent['mbqrcid'] = 0; // These rows need to be given priority by rev()
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
                        $thisLogevent['mbqrcid'] = $rcIdForRows;
                        // Iterate over those database fields
                        foreach ( $userRow as $thisRowItem ) {
                              if ( !$isFirstInItem ) {
                                    $row .= ', ';
                              }
                              $isFirstInItem = false;
                              // If it's a boolean field, 1 if it's there, 0 if not
                              if ( in_array( $thisRowItem, $this->config->booleanFields['rc'] ) ) {
                                    if ( isset ( $thisLogevent[ $thisRowItem ] ) ) {
                                          $row .= '1';
                                    } else {
                                          $row .= '0';
                                    }
                              } elseif( isset ( $thisLogevent[$thisRowItem] ) ) {
                                    // If it's an array (e.g. tag array), implode it
                                    if ( is_array ( $thisLogevent[$thisRowItem] ) ) {
                                          // TODO: Figure out what the glue actually should be
                                          $thisLogevent[$thisRowItem] = implode( '|',
                                                $thisLogevent[$thisRowItem] );
                                    }
                                    // If it's a string field, escape it
                                    if ( in_array ( $thisRowItem, $this->config->stringFields['rc'] ) ) {
                                          $thisLogevent[$thisRowItem] = "'" . $this->db->real_escape_string
                                                ( $thisLogevent[$thisRowItem] ) . "'";
                                    }
                                    $row .= $thisLogevent[$thisRowItem];
                              } else {
                                    $row .= $this->config->defaultFields['rc'][$thisRowItem];
                              }
                        }
                        $row .= ')';
                  }
                  $row .= ';';
                  $queryResult = MirrorGlobalFunctions::doQuery( $this->db, $this->config,
                        $row, 'inserting ' . count( $events ) . ' changes' );
                  if ( $queryResult ) {
                        // Check cursor existence. If it exists, update it. If it doesn't, then
                        // insert it.
                        $query = '';
                        if ( $mbcNumRows ) {
                              if ( !$rvContinue ) {
                              $query = 'DELETE FROM mb_cursor '
                                    . "WHERE mbc_key='mirrorpagerestore-needsrevids-rvcontinue'";
                                    $verb = 'deleting';
                              } else {
                              $query = "UPDATE mb_cursor SET mbc_value='$rvContinue' "
                                    . "WHERE mbc_key='mirrorpagerestore-needsrevids-rvcontinue'";
                                    $verb = 'updating';
                              }
                        } elseif ( $rvContinue ) {
                              $query = "INSERT INTO mb_cursor (mbc_key, mbc_value) "
                                    . " values ('mirrorpagerestore-needsrevids-rvcontinue',
                                    '$rvContinue')";
                              $verb = 'inserting';
                        }
                        if ( $query ) {
                              MirrorGlobalFunctions::doQuery( $this->db, $this->config,
                                    $query, $verb . ' pagerestorerevids cursor' );
                        }
                        // If we've reached the end of the pull, then update the
                        // mirrorpagerestore row accordingly
                        if ( !$rvContinue ) {
                              $query = "UPDATE mb_queue SET "
                                    . "mbq_status='needsmakeremotelylive' "
                                    . "WHERE $needsRevIdsWhere ";
                              MirrorGlobalFunctions::doQuery( $this->db,
                                    $this->config, $query,
                                    'changing mirrorpagerestore needsrevids to '
                                    . 'needsmakeremotelylive' );
                        }
                  }
            }
      }

      // Get the null revision ID and comment2. This is used for log entries. For uploads, it's
      // not necessarily a null revision, but we're not given the revision ID, so we have to use
      // this.
      function nullrev() {
            $table = 'mb_queue';
            $where = "(mbq_status='needsmovenullrev' or mbq_status='needsprotectnullrev' "
                  . "or mbq_status='needsuploadnullrev')";
            $options = "ORDER BY mbq_id ASC";
            $keepLooping = true;
            $firstLoop = true;
            while ( $keepLooping ) {
                  $ret = $this->db->query( "SELECT * FROM mb_queue "
                        ."WHERE $where $options LIMIT 1" );
                  if ( !$ret || !$ret->num_rows ) {
                        if ( $firstLoop ) {
                              echo ( "No $where items for nullrev\n" );
                        }
                        $keepLooping = false;
                        continue;
                  }
                  $firstLoop = false;
                  $value = $ret->fetch_assoc();
                  $params = $value['mbq_log_params'];
                  $status = $this->config->nullRevStatus[$value['mbq_status']];
                  // Page moves sometimes create redirects
                  if ( $value['mbq_status'] == 'needsmovenullrev' ) {
                        $unserialized = unserialize( $params );
                        if ( $unserialized['5::noredir'] !== '1' ) {
                              $status = 'needsmoveredirectrev';
                        }
                  }
                  $timestamp = MirrorGlobalFunctions::addUndesirables( $value['mbq_timestamp'] );
                  $pageId = $value['mbq_page_id'];
                  $query = "?action=query&prop=revisions&rvprop=comment|ids|content"
                        . "&pageids=$pageId&rvstart=$timestamp&rvlimit=1&format=php";
                  $ret = $this->wiki->query( $query, true );
                  if ( !$ret ) {
                        echo "Did not retrieve any revisions from nullrev query; "
                              . "skipping back around\n";
                        continue;
                  }
                  // If there's no comment2 in the mb_queue row yet...
                  if ( !$value['mbq_comment2'] ) {
                        if ( !isset( $ret['query']['badrevids'] ) ) {
                              if ( isset( $ret['query']['pages'] ) ) {
                                    $pages = $ret['query']['pages'];
                                    foreach ( $pages as $page ) { // Get the particular page...
                                          if ( isset( $page['missing'] ) ) {
                                                // These missing pages for comments can't be
                                                // allowed to hold up the works.
                                                echo "Missing page for comment!\n";
                                          } else {
                                                $revisions = $page['revisions'];
                                                // Get the particular revision...
                                                foreach( $revisions as $revision ) {
                                                      $thisRevId = $revision['revid'];
                                                      $comment = $revision['comment'];
                                                      $parentId = $revision['parentid'];
                                                      $content = "'" . $this->db->real_escape_string(
                                                            $revision['*'] ) . "'";
                                                      // Insert the content, and get the ID for that row
                                                      $query = "INSERT INTO mb_text (mbt_text) VALUES ("
                                                            . $content . ")";
                                                      MirrorGlobalFunctions::doQuery ( $this->db,
                                                            $this->config, $query,
                                                            "inserting text for null rev id "
                                                            . $thisRevId );
                                                      $textId = $this->db->insert_id;
                                                      // Now update the queue
                                                      $query = 'UPDATE mb_queue SET '
                                                            . 'mbq_rev_id=' . $thisRevId
                                                            . ",mbq_rc_last_oldid="
                                                                  . $parentId
                                                            . ",mbq_comment2='"
                                                                  . $this->db->real_escape_string( $comment )
                                                            . "',mbq_text_id=$textId"
                                                            . ",mbq_status='$status"
                                                            . "' WHERE mbq_id=" . $value['mbq_id'];
                                                      MirrorGlobalFunctions::doQuery( $this->db,
                                                            $this->config, $query, "updating rev id $thisRevId "
                                                            ."with comment" );
                                                }
                                          }
                                    }
                              } else {
                                    echo "No query for comment!\n";
                              }
                        } else {
                              // These bad revision IDs for null revisions can't be allowed to hold
                              // up the works.
                              echo "Bad revision for comment!\n";
                              $query = "UPDATE mb_queue SET mbq_status='$status'"
                                    . "' WHERE mbq_id=" . $value['mbq_id'];
                              MirrorGlobalFunctions::doQuery( $this->db,
                                    $this->config, $query, "changing status to $status" );
                        }
                  }
            }
      }

      // Get the revision ID for the redirect, if there is one
      function redirectrev() {
            $table = 'mb_queue';
            $where = "(mbq_status='needsmoveredirectrev' OR mbq_status='needsmergeredirectrev')";
            $options = 'ORDER BY mbq_id ASC';
            $keepLooping = true;
            $firstLoop = true;
            while ( $keepLooping ) {
                  $ret = $this->db->query( "SELECT * FROM mb_queue "
                        ."WHERE $where $options LIMIT 1" );
                  if ( !$ret || !$ret->num_rows ) {
                        if ( $firstLoop ) {
                              echo ( "No $where items for redirectrev\n" );
                        }
                        $keepLooping = false;
                        continue;
                  }
                  $firstLoop = false;
                  $value = $ret->fetch_assoc();
                  $params = $value['mbq_log_params'];
                  if ( $value['mbq_action'] === 'mirrormove' ) {
                        $unserialized = unserialize( $params );
                        $queryCond = 'titles=' . $this->config->namespacesToTruncate
                              [$this->wikiName]
                              [$value['mbq_namespace']]
                              . $value['mbq_title'];
                  } else {
                        $queryCond = 'pageids=' . $value['mbq_page_id'];
                  }
                  $timestamp = MirrorGlobalFunctions::addUndesirables( $value['mbq_timestamp'] );
                  // If there's no redirect revision ID in the mb_queue row yet...
                  if ( !$value['mbq_rev_id2'] ) {
                        // Is there a redirect? If not, break, because there's nothing for us to
                        // do here
                        if ( isset( $unserialized['5::noredir'] )
                              && $unserialized['5::noredir'] === '1' ) {
                              $query = 'UPDATE mb_queue SET '
                                    . "mbq_status='readytopush' "
                                    . "WHERE mbq_id=" . $value['mbq_id'];
                              MirrorGlobalFunctions::doQuery( $this->db,
                                    $this->config, $query, "changing status to readytopush" );
                              continue;
                        }
                        // Get the redirect rev ID
                        $query = "?action=query&prop=revisions&rvprop=comment|ids|content&"
                              . "$queryCond&rvstart=$timestamp&rvlimit=1&format=php";
                        $ret = $this->wiki->query( $query, true );
                        if ( !$ret ) {
                              echo "Did not retrieve any revisions from redirect query; "
                                    . "skipping back around\n";
                              continue;
                        }
                        // Handle revisions whose pages on the remote wiki were deleted. These bad
                        // revision IDs for redirect revisions can't be allowed to hold up the
                        // works; the pages must move (or be protected)
                        if ( isset( $ret['query']['badrevids'] ) ) {
                              $query = 'UPDATE mb_queue SET '
                                    . "mbq_status='readytopush' "
                                    . "WHERE mbq_id=" . $value['mbq_id'];
                              MirrorGlobalFunctions::doQuery( $this->db,
                                    $this->config, $query,
                                    "marking as readytopush bad revision ID for page "
                                    . $queryCond );
                        } elseif ( isset( $ret['query']['pages'] ) ) {
                              $pages = $ret['query']['pages'];
                              foreach ( $pages as $page ) { // Get the particular page...
                                    $thisRedirectPageId = $page['pageid'];
                                    if ( isset( $page['missing'] ) ) {
                                          // These missing pages for revision IDs can't be
                                          // allowed to hold up the works.
                                          echo "Missing page for revision revid!\n";
                                          $query = 'UPDATE mb_queue SET '
                                                . "mbq_status='readytopush' "
                                                . "WHERE mbq_id=" . $value['mbq_id'];
                                          MirrorGlobalFunctions::doQuery( $this->db,
                                                $this->config, $query,
                                                "marking as readytopush bad revision ID for page "
                                                . $prefixedMoveFrom );
                                    } else {
                                          $revisions = $page['revisions'];
                                          // Get the particular revision...
                                          foreach( $revisions as $revision ) {
                                                // See if this revision is a redirect; if not,
                                                // treat it like a bad revision.
                                                if( MirrorGlobalFunctions::isRedirect(
                                                      $this->config, $revision['*'] ) === false ) {
                                                      echo "Can't find redirect revision!\n";
                                                      $query = 'UPDATE mb_queue SET '
                                                            . "mbq_status='readytopush' "
                                                            . "WHERE mbq_id=" . $value['mbq_id'];
                                                      MirrorGlobalFunctions::doQuery( $this->db,
                                                            $this->config, $query,
                                                            "marking as readytopush page "
                                                            . $queryCond );
                                                      return;
                                                }
                                                $thisRedirectRevId = $revision['revid'];
                                                $commentString = '';
                                                $content = "'" . $this->db->real_escape_string(
                                                      $revision['*'] ) . "'";
                                                // Insert the content, and get the ID for that row
                                                $query = "INSERT INTO mb_text (mbt_text) VALUES ("
                                                      . $content . ")";
                                                MirrorGlobalFunctions::doQuery ( $this->db,
                                                      $this->config, $query,
                                                      "inserting text for redirect rev id "
                                                      . $thisRedirectRevId );
                                                $textId = $this->db->insert_id;
                                                if ( $value['mbq_action'] === 'mirrormerge' ) {
                                                      $commentString = ",mbq_comment2='"
                                                      . $revision['comment']
                                                      . "',mbq_text_id=$textId";
                                                }
                                                // Now update the queue
                                                $query = 'UPDATE mb_queue SET '
                                                      . 'mbq_rev_id2=' . $thisRedirectRevId
                                                      . ',mbq_page_id2=' . $thisRedirectPageId
                                                      . $commentString
                                                      . ",mbq_status='readytopush'"
                                                      . " WHERE mbq_id=" . $value['mbq_id'];
                                                $updateMsg = $value['mbq_action'] === 'mirrormove' ?
                                                      "updating rev id " . $value['mbq_rev_id'] :
                                                      "updating mbq id " . $value['mbq_id'];
                                                MirrorGlobalFunctions::doQuery( $this->db,
                                                      $this->config, $query, $updateMsg
                                                      . " with redirect rev id $thisRedirectRevId" );
                                          }
                                    }
                              }
                        } else {
                              echo "No query for redirect revision!\n";
                        }
                  }
            }
      }

      function imageinfo() {
            $table = 'mb_queue';
            $where = "mbq_status='needsimageinfo'";
            $options = "ORDER BY mbq_id ASC";
            $ret = $this->db->query( "SELECT * FROM mb_queue "
                  . "WHERE $where LIMIT 1" );
            if ( !$ret || !$ret->num_rows ) {
                  echo ( "No $where items\n" );
                  return;
            }
            $value = $ret->fetch_assoc();
            $params2 = unserialize( $value['mbq_params2'] );
            $imgTimestamp = intval( $params2['img_timestamp'] );
            $query = "?action=query&prop=imageinfo&titles="
                  . $this->config->namespacesToTruncate[$this->wikiName][$value['mbq_namespace']]
                  . $value['mbq_title']
                  . "&iistart=$imgTimestamp&iiprop=timestamp|user|userid|comment|url|size"
                  . "|sha1|mime|mediatype|metadata|archivename"
                  . "|bitdepth|uploadwarning|contentmodel&format=php";
            $ret = $this->wiki->query( $query, true );
            if ( !$ret ) {
                  echo "Did not retrieve any imageinfo from query; skipping back around\n";
                  return;
            }
            if ( !isset( $ret['query']['pages'] ) ) {
                  echo "There was nothing to put in the queue table.\n";
                  return;
            }
            $pages = $ret['query']['pages'];
            foreach ( $pages as $page ) { // Get the particular page...
                  // Handle missing (i.e. deleted) files
                  if ( isset( $page['missing'] ) ) {
                        // TODO: Should this be needsimageundeletion?
                        $query = 'UPDATE mb_queue SET '
                              . "mbq_status='needsundeletion'"
                              . " WHERE mbq_id={$value['mbq_id']}";
                        MirrorGlobalFunctions::doQuery ( $this->db, $this->config, $query,
                              "marking mbq_id {$value['mbq_id']} as needsundeletion "
                              . "(missing file)" );
                        return;
                  }
                  $revisions = $page['imageinfo'];
                  foreach( $revisions as $revision ) { // Get the particular imageinfo...
                        if ( intval( MirrorGlobalFunctions::killUndesirables(
                              $revision['timestamp'] ) ) !== $imgTimestamp ) {
                              // TODO: Should this be needsimageundeletion?
                              $query = 'UPDATE mb_queue SET '
                                    . "mbq_status='needsundeletion'"
                                    . " WHERE mbq_id={$value['mbq_id']}";
                              MirrorGlobalFunctions::doQuery ( $this->db, $this->config, $query,
                                    "marking mbq_id {$value['mbq_id']} as needsundeletion "
                                    . "(timestamps don't match)");
                              return;
                        }
                        foreach ( $this->config->params2['imageinfo'] as $param2 ) {
                              if ( isset( $revision[$param2] ) ) {
                                    $params2[$param2] = $revision[$param2];
                              }
                        }
                        $query = 'UPDATE mb_queue SET '
                              . "mbq_params2='" . $this->db->real_escape_string(
                                    serialize( $params2 ) )
                              . "', mbq_status='needsimagedownload'"
                              . " WHERE mbq_id={$value['mbq_id']}";
                        MirrorGlobalFunctions::doQuery ( $this->db, $this->config,
                              $query, "inserting imageinfo for mbq_id " . $value['mbq_id'] );
                  }
            }
      }

      function imageDownload() {
            $table = 'mb_queue';
            $where = "mbq_status='needsimagedownload'";
            $options = "ORDER BY mbq_id ASC";
            $ret = $this->db->query( "SELECT * FROM mb_queue "
                  . "WHERE $where LIMIT 1" );
            if ( !$ret || !$ret->num_rows ) {
                  echo ( "No $where items\n" );
                  return;
            }
            $value = $ret->fetch_assoc();
            $params2 = unserialize( $value['mbq_params2'] );
            if ( !isset( $params2['url'] ) ) {
                  die( "URL parameter wasn't set in mbq_id {$value['mbq_id']}\n" );
            }
            $file = fopen ( $params2['url'], "rb" );
            // Die if we can't open the remote wiki image file
            // TODO: Mark it as needsundelete or needsfile instead
            if ( !$file ) {
                  die( "Couldn't open file {$params2['url']}" );
            }
            // Does the remote wiki image URL start with the expected path?
            if ( strpos( $params2['url'], $this->config->stripFromFront[$this->wikiName] )
                  !== 0 ) {
                  die( "Path {$params2['url']} did not start with "
                        . $this->config->stripFromFront[$this->wikiName] . "\n" );
            }
            // Strip part of the path from the filename
            $stripped = substr( $params2['url'],
                  strlen( $this->config->stripFromFront[$this->wikiName] ),
                  strlen( $params2['url'] )
                  - strlen( $this->config->stripFromFront[$this->wikiName] ) );
            // We just want the intermediate stuff, i.e. the image subfolders
            $strippedOfFilenameToo = substr( $stripped, 0,
                  strlen( $stripped ) - strlen( $value['mbq_title'] ) );
            // Add our local path to the filename
            $createFilename = $this->config->addToFront[$this->wikiName] . $stripped;
            // Create directory
            $dirname = $this->config->addToFront[$this->wikiName] . $strippedOfFilenameToo;
            if ( !file_exists( $dirname ) ) {
                  $newdir = mkdir( $dirname, 0755, true );
                  if ( !$newdir ) {
                        die( "Failure creating directory $dirname" );
                  }
            }
            // Create file
            $newf = fopen ( $createFilename, "wb" );
            if ( !$newf ) {
                  die( "Failure creating file $createFilename\n" );
            }
            while( !feof($file ) ) {
                  fwrite( $newf, fread( $file, 1024 * 8 ), 1024 * 8 );
            }
            if ($file) {
                  fclose( $file );
            }
            if ($newf) {
                  fclose($newf);
            }
            $query = 'UPDATE mb_queue SET '
                  . "mbq_status='readytopush'"
                  . " WHERE mbq_id={$value['mbq_id']}";
            MirrorGlobalFunctions::doQuery ( $this->db, $this->config,
                  $query, "downloading file $createFilename for mbq_id " . $value['mbq_id'] );
      }

      function initialize() {
            $usage = 'Usage: php mirrorpullbot.php -q<option (e.g. ';
            $firstOption = true;
            foreach( $this->config->allowableOptions['q'] as $allowableOption ) {
                  if ( !$firstOption ) {
                        $usage .= ', ';
                  }
                  $usage .= $allowableOption;
                  $firstOption = false;
            }
            $usage .= '> [-r<option (e.g. ro, rd, r<microseconds>>] '
                  .'[-s<starting (e.g. 20120101000000)>])' . "\n";
            if ( !isset( $this->options['q'] ) ) {
                  die( $usage );
            }
            if ( !isset( $this->options['r'] ) ) {
                  $this->options['r'] = 'o'; // Default to onetime
            }
            if ( !in_array( $this->options['q'], $this->config->allowableOptions['q'] ) ) {
                  die ( $usage );
            }
            if ( !in_array( $this->options['r'], $this->config->allowableOptions['r'] ) ) {
                  if ( !is_numeric( $this->options['r'] ) ) { // Microseconds option
                        echo "You did not select an acceptable option for r\n";
                        die ( $usage );
                  } else {
                        $this->sleepMicroseconds = $this->options['r'];
                  }
            }
            $this->startingTimestamp = '';
            if ( isset ( $this->options['s'] ) ) {
                  if ( is_numeric ( $this->options['s'] ) ) {
                        if ( $this->options['s'] < 10000000000000
                              || $this->options['s'] > 30000000000000 ) {
                              die( "Error: Timestamp must be after C.E. 1000 and before C.E. 3000\n" );
                        }
                  } else {
                        die( "Starting timestamp supposed to be an integer\n" );
                  }
                  $this->startingTimestamp = $this->options['s'];
            }

            if ( $this->options['r'] === 'd' ) {
                  $this->sleepMicroseconds =
                        $config->defaultMicroseconds['pull'][$this->options['q']];
            }

            $this->wiki = new wikipedia;
            // TODO: Make this possible to override by command prompt
            $this->wikiName = $this->config->remoteWikiName;
            $this->wiki->url = $this->config->remoteWikiUrl[$this->wikiName];
            // Login
            if ( !isset( $this->passwordConfig->pullUser[$this->wikiName] )
                  || !isset( $this->passwordConfig->pullPass[$this->wikiName] ) ) {
                  die( "No login credentials for " . $this->wikiName ) . "\n";
            }
            $this->wiki->login( $this->passwordConfig->pullUser[$this->wikiName],
                  $this->passwordConfig->pullPass[$this->wikiName] );
      }
}