<?php
$localWikiName = 'test102';
$defaultMicroseconds = array(
    'pull' => array (
        'rc' => 2000,
        'rev' => 2000,
        'us' => 2000,
    ),
    'push' => array (
        'us' => 2000,
    )
);
$botClassesPath = "/home/nathan/Chris-G-botclasses";
$passwordPath = "/home/nathan/MirrorBot/passwords/";
$tables = array(
    'mb_queue' => 'mb-queue.sql',
    'mb_text' => 'mb-text.sql',
    'mb_cursor' => 'mb-cursor.sql'
);
$indexFiles = array(
    'mb_queue' => array( 'mb-queue-indices.sql' )
);
$defaultStart = array( // rcstart parameter
    'rc' => '2014-07-15T21:47:37Z'
);
// Every log entry before this timestamp, qrc and pushbot will treat as a mirrorlogentry; every log
// entry after, they will treat as a mirrormove, etc. Also, logPuller will not import anything
// beyond this cutoff into the queue.
$importCutoff = '20140720000000';
$failureLogFile = "failures.txt";
$rcLimit = 5; // Grab 500 recentchanges at a time
$revLimit = 500; // Grab 500 revisions at a time
$dbDefaults = array( // These are for xmlReader.php
    'mbq_text_id' => 0,
    'mbq_push_timestamp' => "''",
    'mbq_action' => "''",
    'mbq_comment' => "''",
    'mbq_deleted' => 0,
    'mbq_len' => 0,
    'mbq_log_action' => 'NULL',
    'mbq_log_id' => 0,
    'mbq_log_params' => "''",
    'mbq_log_type' => "''",
    'mbq_minor' => 0,
    'mbq_namespace' => 0,
    'mbq_page_id' => 0,
    'mbq_rev_id' => 0,
    'mbq_timestamp' => "''",
    'mbq_title' => "''",
    'mbq_user' => 0,
    'mbq_user_text' => "''",
    'mbq_rc_anon' => 0,
    'mbq_rc_bot' => 0,
    'mbq_rc_id' => 0,
    'mbq_rc_ip' => "''",
    'mbq_rc_lastoldidid' => 0,
    'mbq_rc_new' => 0,
    'mbq_rc_old_len' => 0,
    'mbq_rc_patrolled' => 0,
    'mbq_rc_source' => "''",
    'mbq_rc_type' => 'NULL',
    'mbq_rev_content_model' => 'NULL',
    'mbq_rev_content_format' => 'NULL',
    'mbq_rev_sha1' => "''",
    'mbq_page_is_redirect' => 0,
    'mbq_tags' => "''"
);
// Used by the pullbot
$fields = array (
    'rc' => array(
        'mbq_action' => 'mbqaction',
        'mbq_status' => 'mbqstatus',
        'mbq_deleted' => 'mbqdeleted',
        'mbq_rc_ip' => 'mbqrcip',
        'mbq_rc_id' => 'rcid',
        'mbq_rc_anon' => 'anon',
        'mbq_rc_bot' => 'bot',
        'mbq_comment' => 'comment',
        'mbq_log_action' => 'logaction',
        'mbq_log_id' => 'logid',
        'mbq_log_type' => 'logtype',
        'mbq_minor' => 'minor',
        'mbq_rc_new' => 'new',
        'mbq_len' => 'newlen',
        'mbq_namespace' => 'ns',
        'mbq_rc_old_len' => 'oldlen',
        'mbq_page_id' => 'pageid',
        'mbq_rc_patrolled' => 'patrolled',
        'mbq_rev_id' => 'revid',
        'mbq_rc_last_oldidid' => 'revoldid',
        'mbq_page_is_redirect' => 'redirect',
        'mbq_timestamp' => 'timestamp',
        'mbq_title' => 'title',
        'mbq_rc_type' => 'type',
        'mbq_rev_content_model' => 'contentmodel',
        'mbq_rev_content_format' => 'contentformat',
        'mbq_rev_sha1' => 'sha1',
        'mbq_user' => 'userid',
        'mbq_user_text' => 'user'
    )
);
$stringFields = array (
    'rc' => array(
        'mbqaction',
        'mbqstatus',
        'mbqrcip',
        'title',
        'type',
        'action',
        'user',
        'comment',
        'tags',
        'logaction',
        'logtype',
        'contentmodel',
        'contentformat',
        'sha1'
    )
);
$booleanFields = array (
    'rc' => array(
        'anon',
        'bot',
        'minor',
        'new',
        'patrolled',
        'redirect'
    )
);
$defaultFields = array (
    'rc' => array(
        'mbqaction' => "''",
        'mbqstatus' => "''",
        'mbqdeleted' => 0,
        'mbqrcip' => "''",
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
        'contentmodel' => "''",
        'contentformat' => "''",
        'sha1' => "''"
    )
);
// Log actions and their mirrortools counterparts
$mirrorActions = array(
    'move' => 'mirrormove',
    'delete' => 'mirrordelete',
    
);