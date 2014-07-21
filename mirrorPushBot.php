<?php
/**
 * MirrorPushBot
 * https://www.mediawiki.org/wiki/Extension:MirrorTools
 * By Leucosticte < https://www.mediawiki.org/wiki/User:Leucosticte >
 * version 1.0.1
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
$usage = 'Usage: php mirrorpushbot.php -q<option (e.g. rev, us, all)>'
      . '[-r<option (e.g. ro, rd, r<microseconds>>]' . "\n";
$options = getopt( 'q:r:');
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
if ( !isset ( $options['r'] ) ) {
      $options['r'] = 'd'; // Default to continuous, using defaults
} elseif ( !in_array ( $options[ 'r' ], $allowableOptions['r'] ) ) {
      if ( !is_int ( $options['r'] ) ) { // Microseconds option
            die ( $usage );
      } else {
            $sleepMicroseconds = $options['r'];
      }
}

/* Set up bot classes. */
require_once( 'mirrorInitializeDb.php' );
require_once("$botClassesPath/botclasses.php");
$wiki      = new wikipedia;
$wiki->setUserAgent( $userAgent );
$wiki->url = $localWikiUrl;
$wiki->login( $pushUser, $pushPass );
$token = urlencode ( $wiki->getedittoken() );
$wiki->__set('quiet','soft'); // Some long URLs will cause problems

$keepGoing = true;
$offset = 0;
while ( $keepGoing ) {
      $query = "SELECT * FROM mb_queue WHERE mbq_status='readytopush' LIMIT 1";
      if ( $offset > 0 ) {
            $query .= " OFFSET $offset";
      }
      $mbRet = $db->query( $query );
      if ( !$mbRet || !$mbRet->num_rows ) {
            die( "No more rows to push!\n");
      }
      $row = $mbRet->fetch_assoc();
      $action = $row['mbq_action'];
      $pushMapping = null;
      $continueThis = false;
      $data = array();
      switch ( $row['mbq_action'] ) {
            case 'mirrorlogentry':
                  $pushMapping = getMirrorLogEntryPushMapping( $row );
                  $row['mbq_user'] = '0';
                  break;
            case 'mirroredit':
                  $pushMapping = getMirrorEditPushMapping( $row );
                  $query = "SELECT * FROM mb_text WHERE mbt_id=" . $row['mbq_text_id'];
                  $textRet = $db->query( $query );
                  if ( !$textRet || !$textRet->num_rows ) {
                        die( "mbt_id " . $row['mbq_text_id'] . " not found in mb_text!\n" );
                  }
                  $textRow = $textRet->fetch_assoc();
                  $data['oldtext'] = $textRow['mbt_text'];
                  break;
            default:
                  $offset = $row['mbq_id'];
                  #$offset++;
                  #echo $offset;
                  $continueThis = true;
      }
      if ( $continueThis ) {
            $continue;
      }
      $query = "?action=$action&format=php&token=$token";
      foreach( $pushMapping as $pushMappingKey => $pushMappingValue ) {
            #if ( $row[$pushMappingValue] != '' ) {
                  #$query .= "&$pushMappingKey=" . ( urlencode( $row[$pushMappingValue] ) );
                  $data[$pushMappingKey] = $row[$pushMappingValue];
            #}
      }
      
      $localRet = $wiki->query ( $query, $data
            #,'Content-Type: application/x-www-form-urlencoded'
            ); // POST
      var_dump ( $localRet );
      if ( !$localRet ) {
            die ( "Nothing was returned\n" );
      }
      if ( isset( $localRet['error' ] ) ) {
            die( "The $localWikiName API returned an error message!\n" );
      }
      if ( isset( $localRet['mirrorlogentry']['timestamp'] ) ) {
            $pushTimestamp = $localRet['mirrorlogentry']['timestamp'];
      }
      if ( isset( $localRet['mirroredit']['timestamp'] ) ) {
            $pushTimestamp = $localRet['mirroreditpage']['timestamp'];
      }
      $pushStatusQuery = "UPDATE mb_queue SET mbq_status='pushed', mbq_push_timestamp='"
            . $pushTimestamp
            . "' WHERE mbq_id=" . $row['mbq_id'];
      echo $pushStatusQuery . "\n";
      $pushResult = $db->query ( $pushStatusQuery );
      if ( !$pushResult ) {
            var_dump( $db->error_list );
            echo "\n";
      }
}
            
function getMirrorLogEntryPushMapping( $row ) {
      $pushMapping = array(
	    'logid' => 'mbq_log_id',
	    'logtype' => 'mbq_log_type',
	    'logaction' => 'mbq_log_action',
	    'logtimestamp' => 'mbq_timestamp',
	    'loguser' => 'mbq_user', // This will actually be left as zero
	    'lognamespace' => 'mbq_namespace',
	    'logusertext' => 'mbq_user_text',
	    'logtitle' => 'mbq_title',
	    'logcomment' => 'mbq_comment',
	    'logparams' => 'mbq_log_params',
	    'logpage' => 'mbq_page_id',
            'logdeleted' => 'mbq_deleted',
            'tstags' => 'mbq_tags'
      );
      return $pushMapping;
}

function getMirrorEditPushMapping ( $row ) {
      $pushMapping = array(
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
            'rctype' => 'mbq_rc_type',
            'rcsource' => 'mbq_rc_source',
            'rcpatrolled' => 'mbq_rc_patrolled',
            'tstags' => 'mbq_tags'
      );
      return $pushMapping;
}