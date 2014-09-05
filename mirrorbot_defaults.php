<?php
class config {
    public $localWikiName = 'test112';
    public $remoteWikiName = 'test113';
    public $localWikiUrl = array(
        'test112' => "http://localhost/test112/w/api.php"
    );
    public $remoteWikiUrl = array(
        'test113' => "http://localhost/test113/w/api.php",
        'enwiki' => "http://en.wikipedia.org/w/api.php"
    );
    public $botClassesPath = "/home/nathan/Chris-G-botclasses";
    public $mirrorBotPath = "/home/nathan/MirrorBot/";
    public $tables = array(
        'mb_queue' => 'mb_queue.sql',
        'mb_text' => 'mb_text.sql',
        'mb_cursor' => 'mb_cursor.sql'
    );
    public $indexFiles = array(
        'mb_queue' => array(
            'mbq_rc_id-index.sql',
            'mbq_timestamp-index.sql',
            'mbq_status-index.sql'
        )
    );
    public $defaultStart = array( // rcstart parameter
        'rc' => '2014-08-15T00:00:00Z'
    );
    // Every log entry before this timestamp, qrc and pushbot will treat as a mirrorlogentry; every log
    // entry after, they will treat as a mirrormove, etc. Also, logPuller will not import anything
    // beyond this cutoff into the queue.
    public $importCutoff = '20140720000000';
    public $failureLogFile = "failures.txt";
    public $rcLimit = 5; // Grab 500 recentchanges at a time
    public $revLimit = 500; // Grab 500 revisions at a time
    public $makeRemotelyLiveLimit = 500; // Do 500 makeremotelive items at a time
    public $dbDefaults = array( // These are for xmlReader.php
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
        'mbq_rc_lastoldid' => 0,
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
    public $fields = array (
        'rc' => array(
            'mbq_action' => 'mbqaction',
            'mbq_status' => 'mbqstatus',
            'mbq_deleted' => 'mbqdeleted',
            'mbq_rc_source' => 'mbqrcsource',
            'mbq_rc_ip' => 'mbqrcip',
            'mbq_rc_id' => 'rcid',
            'mbq_rc_anon' => 'anon',
            'mbq_rc_bot' => 'bot',
            'mbq_comment' => 'comment',
            'mbq_log_action' => 'logaction',
            'mbq_log_id' => 'logid',
            'mbq_log_params' => 'params',
            'mbq_log_type' => 'logtype',
            'mbq_minor' => 'minor',
            'mbq_rc_new' => 'new',
            'mbq_len' => 'newlen',
            'mbq_namespace' => 'ns',
            'mbq_rc_old_len' => 'oldlen',
            'mbq_page_id' => 'pageid',
            'mbq_rc_patrolled' => 'patrolled',
            'mbq_rev_id' => 'revid',
            'mbq_rc_last_oldid' => 'old_revid',
            'mbq_page_is_redirect' => 'redirect',
            'mbq_timestamp' => 'timestamp',
            'mbq_title' => 'title',
            'mbq_rc_type' => 'type',
            'mbq_rev_content_model' => 'contentmodel',
            'mbq_rev_content_format' => 'contentformat',
            'mbq_rev_sha1' => 'sha1',
            'mbq_user' => 'userid',
            'mbq_user_text' => 'user',
        ),
        'rev' => array(
            'mbq_rev_id' => 'revid',
            'mbq_rc_last_oldid' => 'parentid',
            'mbq_user_text' => 'user',
            'mbq_user' => 'userid',
            'mbq_timestamp' => 'timestamp',
            'mbq_len' => 'size',
            'mbq_sha1' => 'sha1',
            'mbq_rev_content_model' => 'contentmodel'
        ),
        'pagerestorerevids' => array(
            'mbq_action' => 'mbqaction',
            'mbq_status' => 'mbqstatus',
            'mbq_rc_id2' => 'mbqrcid2',
            'mbq_rev_id' => 'revid',
            'mbq_rc_last_oldid' => 'parentid',
            'mbq_user' => 'userid',
            'mbq_user_text' => 'user',
            'mbq_timestamp' => 'timestamp',
            'mbq_len' => 'size',
            'mbq_rev_sha1' => 'sha1',
            'mbq_rev_content_model' => 'contentmodel',
            'mbq_comment' => 'comment',
            'mbq_title' => 'title',
            'mbq_namespace' => 'namespace',
        )
    );
    public $stringFields = array(
        'rc' => array(
            'mbqaction',
            'mbqstatus',
            'mbqrcip',
            'mbqrcsource',
            'title',
            'type',
            'action',
            'user',
            'comment',
            'tags',
            'logaction',
            'logtype',
            'params',
            'contentmodel',
            'contentformat',
            'sha1'
        ),
        'rev' => array(
            'user',
            'timestamp',
            'size',
            'sha1',
            'contentmodel'
        )
    );
    public $booleanFields = array(
        'rc' => array(
            'anon',
            'bot',
            'minor',
            'new',
            'patrolled',
            'redirect'
        )
    );
    public $defaultFields = array(
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
            'params' => "'a:0:{}'", // This works for deletion events
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
    public $mirrorActions = array(
        'move' => 'mirrormove',
        'delete' => 'mirrordelete',
        'restore' => 'mirrorpagerestore'
    );
    // Namespaces to truncate
    public $namespacesToTruncate = array(
        1 => 'Talk:',
        2 => 'User:',
        3 => 'User talk:',
        4 => 'Wikipedia:',
        5 => 'Wikipedia talk:',
        6 => 'File:',
        7 => 'File talk:',
        8 => 'MediaWiki:',
        9 => 'MediaWiki talk:',
        10 => 'Template:',
        11 => 'Template talk:',
        12 => 'Help:',
        13 => 'Help talk:',
        14 => 'Category:',
        15 => 'Category talk:',
        100 => 'Portal:',
        101 => 'Portal talk:',
        108 => 'Book:',
        109 => 'Book talk:',
        118 => 'Draft:',
        119 => 'Draft talk:',
        446 => 'EducationProgram:',
        447 => 'EducationProgram talk:',
        710 => 'TimedText:',
        711 => 'TimedText talk:',
        828 => 'Module:',
        829 => 'Module talk:'
    );
    public $sources = array(
        'new' => 'mw.new',
        'edit' => 'mw.edit',
        'log' => 'mw.log'
    );

    function __construct() {
        $this->passwordPath = $this->mirrorBotPath . "passwords/";
        $this->databasesPath = $this->mirrorBotPath . "databases/";
        $this->scenariosPath = $this->mirrorBotPath . "scenarios/";
        $this->defaultMicroseconds = array(
                'pull' => array (
                    'rc' => 2000 * 1000,
                    'rev' => 2000 * 1000,
                ),
                'push' => 1000 * 1000
        );
    }
}