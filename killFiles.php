<?php
require_once( 'mirrorbot_defaults.php' );
$config = new config;
echo "Options:\n";
foreach ( array_flip( $config->killFilesCmd ) as $cmd ) {
    echo $cmd . "\n";
}
$options = getopt ( 'o:p:c:' );
if ( isset( $options['o'] ) ) {
    $option = $options['o'];
} else {
    $option = readline( "Which option (<return> to abort): " );
}
if ( !in_array( $option, array_flip( $config->killFilesCmd ) ) ) {
    die( "Aborted\n" );
}
if ( isset( $options['c'] ) ) {
    $confirm = $options['c'];
} else {
    $confirm = readline( "Confirm that you want to execute shell command: "
	. "{$config->killFilesCmd[$option]} (Y/N) " );
}
if ( substr( $confirm, 0, 1) !== 'Y' && substr( $confirm, 0, 1) !== 'y' ) {
    echo "Aborted\n";
} else {
    if ( isset( $options['p'] ) ) {
	$exec = "echo {$options['p']} | sudo -S {$config->killFilesCmd[$option]}";
    } else {
	$exec =( $config->killFilesCmd[$option] );
    }
    echo $exec . "\n";
    shell_exec ( $exec );
    echo "\nKilled\n";
}