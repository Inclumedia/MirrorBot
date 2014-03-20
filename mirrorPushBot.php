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
$usage = 'Usage: php mirrorpushbot.php -q<option (e.g. rev, us)>'
      . '[-r<option (e.g. ro, rd, r<microseconds>>]' . "\n";
$options = getopt( 'q:r:');
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
      if ( !is_int ( $options['r'] ) ) { // Microseconds option
            die ( $usage );
      } else {
            $sleepMicroseconds = $options['r'];
      }
}

/* Setup my classes. */
include('botclasses.php');
// Get the passwords
$passwordFile = 'inclubot_passwords.php';
if ( !file_exists ( $passwordFile ) ) {
      die ( "File $passwordFile does not exist\n" );
}
include( 'inclubot_passwords.php' );
// Get the defaults
$defaultsFile = 'inclubot_defaults.php';
if ( !file_exists ( $defaultsFile ) ) {
      die ( "File $defaultsFile does not exist\n" );
}
include( 'inclubot_defaults.php' );
$wiki      = new wikipedia;
$wiki->url = $localWikiUrl;

// Database
$db = new mysqli( $host, $dbUser, $dbPass );
if ( !$db ) {
      die( 'Could not connect: ' . mysql_error());
}
$db->select_db ( "$dbName" );
if ( !$db ) {
      die( "Could not select $dbName" );
}
$wiki->login( $pushUser, $pushPass );
$token = urlencode ( $wiki->getedittoken() );

// TODO: Replace this with a system that goes through and gets the earliest first
switch ( $options['q'] ) {
      case 'rev':
            // Get revisions
            // TODO What about when the result is failure or something of the like?
            // TODO iteration, w/ command line options
            // TODO Check whether this JOIN works or not
            $mbRet = $db->query( "SELECT * FROM mb_rc_queue WHERE mbrcq_rc_type='edit' or mbrcq_rc_type='new' "
                  . "AND mbrcq_push_result='' JOIN mb_text ON mb_rc_queue.mbrcq_text_id=mb_text.mbt_id" );
            if ( !$mbRet ) {
                  die ( "No unpushed edits with text were found\n" );
            }
            $row = $mbRet->fetch_assoc(); // Just do one row for testing purposes
            #var_dump ( $row );
            #die();

            $pushMapping = array(
                  'pageid' => 'mbrcq_rc_cur_id',
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
            $query = "?action=mirroredit&format=php&token=$token";
            foreach( $pushMapping as $pushMappingKey => $pushMappingValue ) {
                  // TODO: The API should reject it if we don't have that user. And if rejected, we shouldn't
                  // mark it as having been successfully pushed.
                  if ( $row[$pushMappingValue] || $pushMappingKey == 'namespace' ) { // Namespace can be zero
                        $query .= "&$pushMappingKey=" . ( urlencode( $row[$pushMappingValue] ) );
                  }
            }
            $localRet = $wiki->query ( $query, true,
                  'Content-Type: application/x-www-form-urlencoded' ); // POST
            var_dump ( $localRet );
            if ( !$localRet ) {
                  die ( "Nothing was returned\n" );
            }
            if ( isset( $localRet['error' ] ) ) {
                  die( "The $localWikiName API returned an error message!\n" );
            }
            // TODO: Loop
            $db->query ( "UPDATE mb_rc_queue SET mbrcq_push_result=" . $localRet['result']
                  . ", mbrcq_push_user="
                  . $localRet['userid'] . " WHERE mbrcq_rcid=" . $row['mbrcq_rc_id'] );
            break;
      case 'us':
            // Question: Is there any need to check for mbrcq_rc_logtype='newusers'?
            $mbRet = $db->query( "SELECT * FROM mb_rc_queue WHERE mbrcq_rc_log_action='create' "
                  . "AND mbrcq_push_result=''" );
            if ( !$mbRet ) {
                  die ( "No unpushed edits with text were found\n" );
            }
            $row = $mbRet->fetch_assoc(); // Just do one row for testing purposes
            $pushMapping = array(
                  'user' => 'mbrcq_rc_user_text',
                  'userid' => 'mbrcq_rc_user',
                  'timestamp' => 'mbrcq_rc_timestamp',
                  'rcid' => 'mbrcq_rc_id',
                  'logid' => 'mbrcq_rc_logid',
            );
            $query = "?action=mirroredit&format=php&token=$token";
            foreach( $pushMapping as $pushMappingKey => $pushMappingValue ) {
                  if ( $row[$pushMappingValue] || $pushMappingKey == 'namespace' ) { // Namespace can be zero
                        $query .= "&$pushMappingKey=" . ( urlencode( $row[$pushMappingValue] ) );
                  }
            }
            $localRet = $wiki->query ( $query, true,
                  'Content-Type: application/x-www-form-urlencoded' ); // POST
            var_dump ( $localRet );
            if ( !$localRet ) {
                  die ( "Nothing was returned\n" );
            }
            if ( isset( $localRet['error' ] ) ) {
                  die( "The $localWikiName API returned an error message!\n" );
            }
            // TODO: Loop
            $db->query ( "UPDATE mb_rc_queue SET mbrcq_push_result=" . $localRet['result']
                  . ", mbrcq_push_user="
                  . $localRet['userid'] . " WHERE mbrcq_rcid=" . $row['mbrcq_rc_id'] );
            break;
}