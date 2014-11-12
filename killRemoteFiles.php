<?php
$commands = array(
    'php killFiles.php -otest117 -p9780451 -cy'
);
foreach ( $commands as $command ) {
    echo "$command\n";
    shell_exec( $command );
}