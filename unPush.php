<?php
require_once( 'mirrorInitializeDb.php' );
$query = "UPDATE mb_queue SET mbq_push_timestamp='' WHERE mbq_status='pushed'";
$mbRet = $db->query( $query );
$query = "UPDATE mb_queue SET mbq_status='readytopush' WHERE mbq_status='pushed'";
$mbRet = $db->query( $query );