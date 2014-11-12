<?php
/**
 * MirrorPushBot
 * https://www.mediawiki.org/wiki/Extension:MirrorTools
 * By Leucosticte < https://www.mediawiki.org/wiki/User:Leucosticte >
 * version 1.0.3
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

// "q" (queue) Three options: -qrev, qus
// "r" (repeat) Three options: -ro (onetime), -rd (continuous, using defaults),
// r<number of microsecs to sleep>

if ( !isset( $testing ) ) {
      /* Set up bot classes. */
      require_once( 'mirrorInitializeDb.php' );
      $config = new config();
      require_once( $config->botClassesPath . "/botclasses.php" );
      pushIt( $passwordConfig, $config, $db, getopt( 'q:r:') );
}

function pushIt( $passwordConfig, $config, $db, $options ) {
      $usage = 'Usage: php mirrorpushbot.php -q<option (e.g. rev, us, all)>'
            . '[-r<option (e.g. ro, rd, r<microseconds>>]' . "\n";
      $allowableOptions['q'] = array(
            'rc',
            'rev',
            'us',
            'all'
      );
      $allowableOptions['r'] = array(
            'o',
            'd',
      );
      if ( !isset ( $options['q'] ) ) {
            $options['q'] = 'all';
      }
      if ( !in_array ( $options[ 'q' ], $allowableOptions['q'] ) ) {
            die ( $usage );
      }
      $sleepMicroseconds = 0;
      if ( !isset ( $options['r'] ) ) {
            $options['r'] = 'd'; // Default to continuous, using defaults
      } elseif ( !in_array ( $options[ 'r' ], $allowableOptions['r'] ) ) {
            if ( !is_int ( $options['r'] ) ) { // Microseconds option
                  die ( $usage );
            } else {
                  if ( $options['r'] !== 'o' ) {
                        $sleepMicroseconds = $options['r'];
                  }
            }
      }

      $wiki = new wikipedia;
      $wiki->setUserAgent( $passwordConfig->userAgent );
      $wiki->url = $config->localWikiUrl[$config->localWikiName];
      $wiki->login( $passwordConfig->pushUser[$config->localWikiName],
            $passwordConfig->pushPass[$config->localWikiName] );
      $token = urlencode( $wiki->getedittoken() );
      // Some long URLs will cause problems if we try to display the whole thing
      $wiki->__set( 'quiet', 'soft' );
      $wiki->echoRet = true;

      $keepGoing = true;
      $offset = 0;
      while ( $keepGoing ) {
            $query = "SELECT * FROM mb_queue WHERE mbq_status<>'pushed' AND "
                  . "mbq_status<>'needsundeletion' ORDER BY mbq_timestamp ASC, mbq_rc_id ASC LIMIT 1";
            // Development code that will cause problems in production
            /*if ( $offset > 0 ) {
                  $query .= " OFFSET $offset";
            }*/
            $mbRet = $db->query( $query );
            if ( !$mbRet || !$mbRet->num_rows ) {
                  if( $options['r'] === 'o' ) {
                        $keepGoing = false;
                  }
                  echo( "No more rows to push! Waiting for mirrorPullBot to add more unpushed "
                       . "rows...\n");
                  usleep( $config->defaultMicroseconds['push'] );
                  continue;
            }
            $row = $mbRet->fetch_assoc();
            if ( in_array( $row['mbq_status'], $config->waitStatuses ) ) {
                  echo "Reached a " . $row['mbq_status']
                        . " row! Waiting for mirrorPullBot to add the necessary data...\n";
                  usleep( $config->defaultMicroseconds['push'] );
                  continue;
            }
            if ( $row['mbq_status'] === 'needsmakeremotelylive' ) {
                  // We can either get this data from the log row or the needsmakeremotelylive rows
                  $rcIdForQuery = $row['mbq_rc_id'];
                  // Get 500 rows of these
                  $query = "SELECT * FROM mb_queue WHERE mbq_status='needsmakeremotelylive' "
                        . "AND mbq_action='makeremotelylive' "
                        . "AND mbq_rc_id=" . $rcIdForQuery
                        . " LIMIT 500";
                  $ret = mirrorGlobalFunctions::doQuery( $db, $config, $query,
                        'queueing up makeremotelylive records' );
                  if ( !$ret or !$ret->num_rows ) {
                        echo "No makeremotelylive rows!\n";
                        $query = "UPDATE mb_queue SET mbq_status='readytopush',"
                              . "mbq_action='mirrorlogentry' WHERE "
                              . "(mbq_action='mirrorimport' OR "
                              . "mbq_action='mirrorpagerestore') AND "
                              . "mbq_rc_id=" . $row['mbq_rc_id'];
                        $ret = mirrorGlobalFunctions::doQuery( $db, $config, $query,
                              'setting mirrorpagerestore row to mirrorlogentry readytopush' );
                        continue;
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
                  $data['revidnumbers'] = $queryChunk;
                  $query = "?action=makeremotelylive&format=php";
                  $ret = $wiki->query( $query, $data );
                  if ( !$ret ) {
                        echo "Did not retrieve any revisions from query; skipping back around\n";
                        continue;
                  }
                  if ( isset( $ret['makeremotelylive']['badrevids'] ) ) {
                        $badRevs = $ret['makeremotelylive']['badrevids'];
                        $isFirst = true;
                        $where = 'WHERE mbq_rc_id=' . $rcIdForQuery . ' AND (';
                        foreach ( $badRevs as $badRev ) {
                              if ( !$isFirst ) {
                                    $where .= ' OR ';
                              }
                              $isFirst = false;
                              $where .= "mbq_rev_id=$badRev";
                        }
                        $where .= ')';
                        $query = 'UPDATE mb_queue SET '
                              . "mbq_action='mirrornorcedit'"
                              . ",mbq_status='needsrev' "
                              . $where
                              . " AND mbq_action='makeremotelylive'";
                        $status = mirrorGlobalFunctions::doQuery( $db, $config, $query,
                              "marking revisions as needsrev" );
                  }
                  if ( isset( $ret['makeremotelylive']['remotelyliverevids'] ) ) {
                        $remotelyLiveRevs = $ret['makeremotelylive']['remotelyliverevids'];
                        $isFirst = true;
                        $where = 'WHERE mbq_rc_id=' . $rcIdForQuery . ' AND (';
                        foreach ( $remotelyLiveRevs as $remotelyLiveRev ) {
                              if ( !$isFirst ) {
                                    $where .= ' OR ';
                              }
                              $isFirst = false;
                              $where .= "mbq_rev_id=$remotelyLiveRev";
                        }
                        $where .= ')';
                        $query = 'UPDATE mb_queue SET '
                              . "mbq_status='pushed', mbq_push_timestamp="
                              . $ret['makeremotelylive']['timestamp'] . ' '
                              . $where
                              . " AND mbq_action='makeremotelylive'";
                        $status = mirrorGlobalFunctions::$doQuery( $db, $config, $query,
                              "marking revision ID $remotelyLiveRevId as pushed" );
                  }
                  continue;
            }
            $action = $row['mbq_action'];
            $pushMapping = null;
            $continueThis = false;
            $data = array();
            if ( $action === 'mirrornorcedit' ) {
                  $row['mbq_rc_id'] = 0;
                  $action = 'mirroredit';
            }
            if ( in_array( $action, $config->textActions ) ) {
                  $query = "SELECT * FROM mb_text WHERE mbt_id=" . $row['mbq_text_id'];
                  $textRet = $db->query( $query );
                  if ( !$textRet || !$textRet->num_rows ) {
                        die( "mbt_id " . $row['mbq_text_id'] . " not found in mb_text!\n" );
                  }
                  $textRow = $textRet->fetch_assoc();
                  $data['oldtext'] = $textRow['mbt_text'];
            }
            if ( in_array( $action, $config->redirectRevActions ) && $row['mbq_text_id2'] ) {
                  $query = "SELECT * FROM mb_text WHERE mbt_id=" . $row['mbq_text_id2'];
                  $textRet = $db->query( $query );
                  if ( !$textRet || !$textRet->num_rows ) {
                        die( "mbt_id " . $row['mbq_text_id2'] . " not found in mb_text!\n" );
                  }
                  $textRow = $textRet->fetch_assoc();
                  $data['oldtext2'] = $textRow['mbt_text'];
            }
            $row['mbq_rc_type'] = 3;
            switch ( $action ) {
                  case 'mirrorlogentry':
                        $mappings = getMirrorLogEntryPushMapping( $row );
                        break;
                  case 'mirrormove':
                        $mappings = getMirrorMovePushMapping( $row );
                        break;
                  case 'mirrordelete':
                        $mappings = getMirrorDeletePushMapping( $row );
                        break;
                  case 'mirrormerge':
                        $mappings = getMirrorMergePushMapping( $row );
                        break;
                  case 'mirrorprotect':
                        $mappings = getMirrorLogEntryPushMapping( $row );
                        $action = 'mirrorlogentry';
                        break;
                  case 'mirroredit':
                        $mappings = getMirrorEditPushMapping( $row );
                        break;
                  case 'mirrorupload':
                        $mappings = getMirrorUploadPushMapping( $row );
                        break;
                  default:
                        // Development code that will cause problems in production
                        #$offset = $row['mbq_id'];
                        echo "Reached a " . $row['mbq_action'] . " row; continuing...\n";
                        usleep( $config->defaultMicroseconds['push'] );
                        $continueThis = true;
            }
            if ( $continueThis ) {
                  continue;
            }
            $metadata = '';
            // Unserialize the params2
            if ( isset( $mappings['params2Mapping'] ) ) {
                  $unserializedParams2 = unserialize( $row['mbq_params2'] );
                  // Reserialize the metadata
                  if ( isset( $unserializedParams2['metadata'] ) ) {
                        $row['metadata'] = serialize( $unserializedParams2['metadata'] );
                  }
                  foreach( $mappings['params2Mapping'] as $params2MappingKey => $params2MappingValue ) {
                        $data[$params2MappingKey] = $unserializedParams2[$params2MappingValue];
                  }
            }
            $row['uploadincomplete'] = 0;
            // Handle uploads
            if ( $action === 'mirrorupload' ) {
                  $filenameArr =
                        MirrorGlobalFunctions::convertUrlToPath(
                              $config, $unserializedParams2['url'],
                              $config->remoteWikiName, $row['mbq_id'] );
                  $filename = $filenameArr['createfilename'];
                  $file = fopen( $filename, "rb" );
                  $row['file'] = '';
                  if ( !$file ) {
                        die( "Failure opening file $filename" );
                  }
                  if( isset( $unserializedParams2['seek'] ) ) {
                        fseek( $file, $unserializedParams2['seek'] );
                  }
                  $row['file'] = base64_encode( fread( $file, $config->chunkSize ) );
                  if ( !feof( $file ) ) {
                        $row['uploadincomplete'] = 1;
                        $unserializedParams2['seek'] = ftell( $file );
                  }
                  /*$header = array(
                        "Content-Type: multipart/form-data",
                        "Content-Disposition: form-data; filename=\"$tempFilename\""
                  );*/
                  $row['filename'] = $row['mbq_title']; # Debugging
                  #$wiki->setHttpHeader( $header );
            } else {
                  $wiki->useDefaultHttpHeader();
            }
            #$action = 'upload'; # Debugging
            $query = "?action=$action&format=php&token=$token";
            if ( !isset( $row['metadata'] ) ) {
                  $row['metadata'] = '';
            }
            foreach( $mappings['pushMapping'] as $pushMappingKey => $pushMappingValue ) {
                  $data[$pushMappingKey] = $row[$pushMappingValue];
            }
            #$data['ignorewarnings'] = ''; # Debugging
            var_dump( $data );
            $localRet = $wiki->query ( $query, $data ); // POST
            var_dump( $localRet );
            if ( !$localRet ) {
                  die ( "Nothing was returned\n" );
            }
            if ( isset( $localRet['error'] ) ) {
                  die( "The " . $config->localWikiName . " API returned an error message!\n" );
            }
            $pushTimestamp = null;
            $result = null;
            foreach( $config->mirrorPushModules as $mirrorPushModule ) {
                  if ( isset( $localRet[$mirrorPushModule]['timestamp'] ) ) {
                        $pushTimestamp = $localRet[$mirrorPushModule]['timestamp'];
                  }
                  if ( isset( $localRet[$mirrorPushModule]['result'] ) ) {
                        $result = $localRet[$mirrorPushModule]['result'];
                  }
            }
            if ( $result != 'Success' ) {
                  die( "The " . $config->localWikiName . " API did not return a success result!\n" );
            }
            if ( !$pushTimestamp ) {
                  die( "The " . $config->localWikiName . " API did not return a push timestamp!\n" );
            }
            if ( $row['uploadincomplete'] ) {
                  $reserializedParams2 = serialize( $unserializedParams2 );
                  $pushStatusQuery = "UPDATE mb_queue SET mbq_params2='{$reserializedParams2}'"
                  . " WHERE mbq_id=" . $row['mbq_id'];
            } else {
                  $pushStatusQuery = "UPDATE mb_queue SET mbq_status='pushed', mbq_push_timestamp="
                  . $pushTimestamp
                  . " WHERE mbq_id=" . $row['mbq_id'];
            }
            if ( $action === 'mirrorupload' ) {
                  #echo 'Bytes so far: ' . strlen( $data['file'] ) . "\n";
            }
            $pushResult = $db->query ( $pushStatusQuery );
            if ( !$pushResult ) {
                  logFailure( "Failure updating mb_queue of mbq_id {$row['mbq_id']}\n" );
                  logFailure ( $db->error_list );
                  echo "\n";
            }
      }
}
            
function getMirrorLogEntryPushMapping( $row ) {
      $mappings = array(
            'pushMapping' => array(
                  'logid' => 'mbq_log_id',
                  'logtype' => 'mbq_log_type',
                  'logaction' => 'mbq_log_action',
                  'logtimestamp' => 'mbq_timestamp',
                  'loguser' => 'mbq_user',
                  'lognamespace' => 'mbq_namespace',
                  'logusertext' => 'mbq_user_text',
                  'logtitle' => 'mbq_title',
                  'logcomment' => 'mbq_comment',
                  'logparams' => 'mbq_log_params',
                  'logpage' => 'mbq_page_id',
                  'logdeleted' => 'mbq_deleted',
                  'tstags' => 'mbq_tags',
                  'rcid' => 'mbq_rc_id',
                  'rcbot' => 'mbq_rc_bot',
                  'rcpatrolled' => 'mbq_rc_patrolled',
                  'rctype' => 'mbq_rc_type',
                  'rcsource' => 'mbq_rc_source',
                  'rcip' => 'mbq_rc_ip',
            ),
            'params2Mapping' => array(
                  'nullrevid' => 'nullrevid',
                  'nullrevparentid' => 'nullrevparentid',
                  'nullrevtimestamp' => 'nullrevtimestamp',
                  'nullrevsize' => 'nullrevsize',
                  'nullrevsha1' => 'nullrevsha1',
                  'nullrevcontentmodel' => 'nullrevcontentmodel',
                  'nullrevcomment' => 'nullrevcomment',
            )
      );
      return $mappings;
}

function getMirrorMergePushMapping( $row ) {
      $mappings = array(
            'pushMapping' => array(
                  'logid' => 'mbq_log_id',
                  'logtype' => 'mbq_log_type',
                  'logaction' => 'mbq_log_action',
                  'logtimestamp' => 'mbq_timestamp',
                  'loguser' => 'mbq_user',
                  'lognamespace' => 'mbq_namespace',
                  'logusertext' => 'mbq_user_text',
                  'logtitle' => 'mbq_title',
                  'logcomment' => 'mbq_comment',
                  'logparams' => 'mbq_log_params',
                  'logpage' => 'mbq_page_id',
                  'logdeleted' => 'mbq_deleted',
                  'tstags' => 'mbq_tags',
                  'rcid' => 'mbq_rc_id',
                  'rcbot' => 'mbq_rc_bot',
                  'rcpatrolled' => 'mbq_rc_patrolled',
                  'rctype' => 'mbq_rc_type',
                  'rcsource' => 'mbq_rc_source',
                  'rcip' => 'mbq_rc_ip',
                  'redirectrevid' => 'mbq_rev_id2',
                  'comment2' => 'mbq_comment2'
            ),
            'params2Mapping' => array(
                  'redirectrevid' => 'redirectrevid',
                  'redirectrevparentid' => 'redirectrevparentid',
                  'redirectrevtimestamp' => 'redirectrevtimestamp',
                  'redirectrevsize' => 'redirectrevsize',
                  'redirectrevsha1' => 'redirectrevsha1',
                  'redirectrevcontentmodel' => 'redirectrevcontentmodel',
                  'redirectrevcomment' => 'redirectrevcomment'
            )
      );
      return $mappings;
}

function getMirrorMovePushMapping ( $row ) {
      $mappings = array(
            'pushMapping' => array(
                  'logid' => 'mbq_log_id',
                  'logaction' => 'mbq_log_action',
                  'logtimestamp' => 'mbq_timestamp',
                  'loguser' => 'mbq_user',
                  'logusertext' => 'mbq_user_text',
                  'lognamespace' => 'mbq_namespace',
                  'logtitle' => 'mbq_title',
                  'logcomment' => 'mbq_comment',
                  'logparams' => 'mbq_log_params',
                  'logpage' => 'mbq_page_id',
                  'logdeleted' => 'mbq_deleted',
                  'tstags' => 'mbq_tags',
                  'rcid' => 'mbq_rc_id',
                  'rcbot' => 'mbq_rc_bot',
                  'rcpatrolled' => 'mbq_rc_patrolled',
                  'nullrevid' => 'mbq_rev_id',
                  'nullrevparentid' => 'mbq_rc_last_oldid',
                  'redirectrevid' => 'mbq_rev_id2',
                  'redirectpageid' => 'mbq_page_id2'
            ),
            'params2Mapping' => array(
                  'nullrevid' => 'nullrevid',
                  'nullrevparentid' => 'nullrevparentid',
                  'nullrevtimestamp' => 'nullrevtimestamp',
                  'nullrevsize' => 'nullrevsize',
                  'nullrevsha1' => 'nullrevsha1',
                  'nullrevcontentmodel' => 'nullrevcontentmodel',
                  'nullrevcomment' => 'nullrevcomment',
                  'redirectrevid' => 'redirectrevid',
                  'redirectrevparentid' => 'redirectrevparentid',
                  'redirectrevtimestamp' => 'redirectrevtimestamp',
                  'redirectrevsize' => 'redirectrevsize',
                  'redirectrevsha1' => 'redirectrevsha1',
                  'redirectrevcontentmodel' => 'redirectrevcontentmodel',
                  'redirectrevcomment' => 'redirectrevcomment'
            )
      );
      return $mappings;
}

function getMirrorDeletePushMapping ( $row ) {
      $mappings = array(
            'pushMapping' => array(
                  'logid' => 'mbq_log_id',
                  'logtimestamp' => 'mbq_timestamp',
                  'loguser' => 'mbq_user',
                  'logusertext' => 'mbq_user_text',
                  'lognamespace' => 'mbq_namespace',
                  'logtitle' => 'mbq_title',
                  'logcomment' => 'mbq_comment',
                  'logparams' => 'mbq_log_params',
                  'logpage' => 'mbq_page_id',
                  'logdeleted' => 'mbq_deleted',
                  'tstags' => 'mbq_tags',
                  'rcid' => 'mbq_rc_id',
                  'rcbot' => 'mbq_rc_bot',
                  'rcpatrolled' => 'mbq_rc_patrolled'
            )
      );
      return $mappings;
}

function getMirrorEditPushMapping ( $row ) {
      $mappings = array(
            'pushMapping' => array(
                  'revid' => 'mbq_rev_id',
                  'revpage' => 'mbq_page_id',
                  'revcomment' => 'mbq_comment',
                  'revuser' => 'mbq_user',
                  'revusertext' => 'mbq_user_text',
                  'revtimestamp' => 'mbq_timestamp',
                  'revminoredit' => 'mbq_minor',
                  'revlen' => 'mbq_len',
                  'revsha1' => 'mbq_rev_sha1',
                  'revcontentmodel' => 'mbq_rev_content_model',
                  'revcontentformat' => 'mbq_rev_content_format',
                  'revdeleted' => 'mbq_deleted',
                  'rcid' => 'mbq_rc_id',
                  'rcnamespace' => 'mbq_namespace',
                  'rctitle' => 'mbq_title',
                  'rcbot' => 'mbq_rc_bot',
                  'rcnew' => 'mbq_rc_new',
                  'rcoldlen' => 'mbq_rc_old_len',
                  'rclastoldid' => 'mbq_rc_last_oldid',
                  'rctype' => 'mbq_rc_type',
                  'rcsource' => 'mbq_rc_source',
                  'rcpatrolled' => 'mbq_rc_patrolled',
                  'tstags' => 'mbq_tags'
            )
      );
      return $mappings;
}

function getMirrorUploadPushMapping ( $row ) {
      $mappings = array(
            'pushMapping' => array(
                  'logid' => 'mbq_log_id',
                  'logtype' => 'mbq_log_type',
                  'logaction' => 'mbq_log_action',
                  'logtimestamp' => 'mbq_timestamp',
                  'loguser' => 'mbq_user',
                  'lognamespace' => 'mbq_namespace',
                  'logusertext' => 'mbq_user_text',
                  'logtitle' => 'mbq_title',
                  'logcomment' => 'mbq_comment',
                  'logparams' => 'mbq_log_params',
                  'logpage' => 'mbq_page_id',
                  'logdeleted' => 'mbq_deleted',
                  'tstags' => 'mbq_tags',
                  'rcid' => 'mbq_rc_id',
                  'rcbot' => 'mbq_rc_bot',
                  'rcpatrolled' => 'mbq_rc_patrolled',
                  'rctype' => 'mbq_rc_type',
                  'rcsource' => 'mbq_rc_source',
                  'rcip' => 'mbq_rc_ip',
                  'nullrevid' => 'mbq_rev_id',
                  'nullrevparentid' => 'mbq_rc_last_oldid',
                  'file' => 'file', // Can be the whole file or just a chunk
                  'filename' => 'filename', // Debugging
                  'uploadincomplete' => 'uploadincomplete',
                  'imgmetadata' => 'metadata', // This was un- and re-serialized
            ),
            'params2Mapping' => array(
                  'nullrevid' => 'nullrevid',
                  'nullrevparentid' => 'nullrevparentid',
                  'nullrevtimestamp' => 'nullrevtimestamp',
                  'nullrevsize' => 'nullrevsize',
                  'nullrevsha1' => 'nullrevsha1',
                  'nullrevcontentmodel' => 'nullrevcontentmodel',
                  'nullrevcomment' => 'nullrevcomment',
                  'imgtimestamp' => 'img_timestamp',
                  'imgsize' => 'size',
                  'imgwidth' => 'width',
                  'imgheight' => 'height',
                  'mime' => 'mime',
                  'imgbits' => 'bitdepth',
                  'imgmediatype' => 'mediatype',
                  'imgsha1' => 'sha1'
            )
      );
      return $mappings;
}