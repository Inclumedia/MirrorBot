<?php
$reader = new XMLReader();
include( 'mirrorInitializeDb.php' );

if ( !isset( $argv[1] ) ) {
    die( "Usage: php xmlReader <filename>\n" );
}
$fileName = $argv[1];
if ( !$reader->open( $fileName ) ) {
    die( "Failed to open $fileName" );
}
$undesirables = array ( '-', ':', 'T', 'Z' );
 
$nodeName = '';
$whichMostRecent = 'element';
$array = array(
        'timestamp' => 'mbq_timestamp',
        'username' => 'mbq_user_text',
        'comment' => 'mbq_comment',
        'type' => 'mbq_log_type',
        'action' => 'mbq_log_action',
        'params' => 'mbq_log_params',
        'logtitle' => 'mbq_title'
);
$fields = '';
foreach( $dbDefaults as $key => $allField ) {
    if ( $fields ) {
        $fields .= ', ';
    }
    $fields .= $key;
}

$dbArray = array();
$log_id = 0;
    
while($reader->read()) {
    if( $reader->nodeType == XMLReader::ELEMENT ) {
        $nodeName = $reader->name;
        if ( $nodeName == 'contributor' ) {
            $whichMostRecent = 'contributor';
        }
        if ( $nodeName == 'logitem' ) {
            $whichMostRecent = 'logitem';
        }
    }
        if( $reader->nodeType == XMLReader::TEXT ) {
            // ID can be either log_id or log_user
            if ( $nodeName == 'id' ) {
                if ( $whichMostRecent == 'contributor' ) {
                    $dbArray[$log_id]['mbq_user'] = $reader->value;
                } else {
                    $log_id = $reader->value;
                    $dbArray[$log_id]['mbq_log_id'] = $reader->value;
                }
            }
            
            // User ID also should be saved
            if ( $nodeName == 'params' ) {
                $data = @unserialize( $reader->value );
                if ( isset( $data["4::userid"] ) ) {
                    $dbArray[$log_id]['mbq_user'] = $data["4::userid"];
                }
            }
            
            // Log actions should be saved in the appropriate mb_action
            $dbArray[$log_id]['mbq_action'] = 'mirrorlogentry';
 
            // Save the data to the array
            if( isset( $array[$nodeName] ) ) {
                $dbArray[$log_id][$array[$nodeName]] = $reader->value;
            }
        }
 
        // Save log entry to mb_queue
        if ($reader->nodeType == XMLReader::END_ELEMENT && $reader->name == 'logitem') {
            $values = '';
            $firstOne = true;
            foreach( $dbDefaults as $key => $allField ) {
                if( !$firstOne ) {
                    $values .= ", ";
                }
                $firstOne = false;
                $value = $allField;
                if ( isset( $dbArray[$log_id][$key] ) ) {
                    $value = '';
                    $value = $db->real_escape_string( $dbArray[$log_id][$key] );
                    if ( $key == 'mbq_timestamp' ) {
                        $value = str_replace ( $undesirables, '', $value );
                    }
                    $value = "'" . $value . "'";
                }
                $values .= $value;
            }
            $ret = $db->query( "SELECT * FROM mb_queue "
                . "WHERE mbq_log_id=$log_id" );
            if ( !$ret || !$ret->num_rows ) {
                $query = "INSERT INTO mb_queue ($fields) "
                    . " values ($values)";
                $result = $db->query( $query );
                if ( !$result ) {
                    echo "Failed: $query\n";
                    var_dump( $db->error_list );
                    echo "\n";
                } else {
                    echo "Inserted: $log_id\n";
                }
            } else {
                echo "Log id $log_id already in database; skipping\n";
            }
        }
    }
    $reader->close();