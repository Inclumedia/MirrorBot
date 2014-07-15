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
$defaultStart = array(
    'rc' => '2014-02-15T21:47:37Z'
);
$failureLogFile = "failures.txt";
$rcLimit = 500; // Grab 500 recentchanges at a time
$revLimit = 500; // Grab 500 revisions at a time
$dbDefaults = array(
    'mbq_text_id' => 0,
    'mbq_push_timestamp' => "''",
    'mbq_action' => "''",
    'mbq_comment' => "''",
    'mbq_content_model' => 'NULL',
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
    'mbq_rc_lastoldidid' => 0,
    'mbq_rc_new' => 0,
    'mbq_rc_old_len' => 0,
    'mbq_rc_patrolled' => 0,
    'mbq_rc_source' => "''",
    'mbq_rc_type' => 'NULL',
    'mbq_rev_sha1' => "''",
    'mbq_page_is_redirect' => 0,
    'mbq_tags' => "''"
);