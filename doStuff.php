<?php
$commands = array(
    #'php scenarios.php -r7scen7001pulled',
    #'php scenarios.php -r7scen7002pulled',
    'php scenarios.php -r7scen7010pulled',
    'php killFiles.php -otest116 -p9780451 -cy'
);
foreach ( $commands as $command ) {
    echo "$command\n";
    shell_exec( $command );
}