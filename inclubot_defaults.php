<?php
$localWikiName = 'test29';
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
$tables = array(
    'mb_rc_queue' => 'mb-rc-queue.sql',
    'mb_text' => 'mb-text.sql',
    'mb_cursor' => 'mb-cursor.sql'
);
$defaultStart = array(
    'rc' => '2014-02-15T21:47:37Z'
);
$failureLogFile = "failures.txt";
$rcLimit = 500; // Grab 500 recentchanges at a time
$revLimit = 500; // Grab 500 revisions at a time