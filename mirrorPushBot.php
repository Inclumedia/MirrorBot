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

$keepGoing = true;
while ( $keepGoing ) {
      $mbRet = $db->query( "SELECT * FROM mb_queue WHERE mbq_status='' LIMIT 1" );
      if ( !$mbRet || !$mbRet->num_rows ) {
            break;
      }
      $row = $mbRet->fetch_assoc();
      $action = $row['mbq_action'];
      $pushMapping = null;
      if ( $row['mbq_action'] == 'mirrorcreateuser' ) {
            $pushMapping = getMirrorCreateUserPushMapping( $row );
            if ( $row['mbq_log_type'] == 'create2' ) {
                  $data = @unserialize( $row['mbq_log_params'] );
                  if ( isset( $data["4::userid"] ) ) {
                        $row['mbq_user'] = $data["4::userid"];
                  }
                  if ( substr( $row['mbq_user_text'], 0, 5 ) == 'User:' ) {
                  $row['mbq_user_text'] = substr( $row['mbq_title'], 5,
                       strlen ( $row['mbq_title'] ) - 5 );
                  }
            }
            $row['password'] = randomPassword();
      }
      if ( !$pushMapping ) {
            $db->query ( "UPDATE mb_queue SET mbq_status='Ignore'"
            . " WHERE mbq_id=" . $row['mbq_id'] );
            continue;
      }
      $query = "?action=$action&format=php&token=$token";
      foreach( $pushMapping as $pushMappingKey => $pushMappingValue ) {
            if ( $pushMappingValue != '' ) {
                  $query .= "&$pushMappingKey=" . ( urlencode( $row[$pushMappingValue] ) );
            }
      }
      #die();
      $localRet = $wiki->query ( $query, true,
            'Content-Type: application/x-www-form-urlencoded' ); // POST
      var_dump ( $localRet );
      if ( !$localRet ) {
            die ( "Nothing was returned\n" );
      }
      if ( isset( $localRet['error' ] ) ) {
            die( "The $localWikiName API returned an error message!\n" );
      }
      $pushStatusQuery = "UPDATE mb_queue SET mbq_status='Pushed', mbq_push_timestamp='"
            . $localRet['mirrorcreateuser']['timestamp']
            . "' WHERE mbq_id=" . $row['mbq_id'];
      echo $pushStatusQuery . "\n";
      $pushResult = $db->query ( $pushStatusQuery );
      if ( !$pushResult ) {
            var_dump( $db->error_list );
            echo "\n";
      }
}
            
function getMirrorCreateUserPushMapping( $row ) {
      $pushMapping = array(
            'userid' => 'mbq_user',
            'username' => 'mbq_user_text',
	    'usertouched' => 'mbq_timestamp',
            'userregistration' => 'mbq_timestamp',
            'userpassword' => 'password',
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
	    'logpage' => 'mbq_page_id'
      );
      return $pushMapping;
}

function getMirrorEditPushMapping ( $row ) {
      $pushMapping = array(
            'pageid' => 'mb_rc_cur_id',
            'text' => 'mbrcq_text',
            'summary' => 'mbrcq_rc_comment',
            'minor' => 'mbrcq_rc_minor',
            'bot' => 'mbrcq_rc_bot',
            'title' => 'mbrcq_rc_title',
            'namespace' => 'mbrcq_rc_namespace',
            'contentmodel' => 'mbrcq_content_model',
            'rcid' => 'mbrcq_rc_id',
            'revid' => 'mbrcq_rc_thisoldid',
            'user' => 'mbrcq_rc_user_text',
            'userid' => 'mbrcq_rc_user',
            'timestamp' => 'mbrcq_rc_timestamp',
            'comment' => 'mbrcq_rc_comment',
            'tags' => 'mbrcq_tags',
      );
      return $pushMapping;
}

function randomPassword() {
    $alphabet = "abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789";
    $pass = array(); //remember to declare $pass as an array
    $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
    for ($i = 0; $i < 8; $i++) {
        $n = rand(0, $alphaLength);
        $pass[] = $alphabet[$n];
    }
    return implode($pass); //turn the array into a string
}