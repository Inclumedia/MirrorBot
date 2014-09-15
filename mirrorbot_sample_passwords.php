<?php
class passwordConfig {
    public $userAgent = 'User-Agent: LeucosticteBot (http://mediawiki.org/wiki/User:LeucosticteBot)';

    // MirrorPullBot bot login
    public $pullUser = array(
        #'test103' => 'Leucosticte',
        'test113' => 'Nate',
        'enwiki' => 'Leucosticte'
    );
    public $pullPass = array(
        'test113' => 'password',
        'enwiki' => 'stuPach4'
    );

    // MirrorPushBot bot login
    public $pushUser = 'Nate';
    public $pushPass = 'password';

    // Credentials for direct access to bot's database
    public $host = "localhost";
    public $dbUser = "root";
    public $dbPass = "9780451";
    public $dbName = "mirrorbot";
}