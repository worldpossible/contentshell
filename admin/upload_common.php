<?php 
require_once("common.php");

// Path to the upload directory
define("UPLOAD_TMP_DIR", "/.data/RACHEL/rachel/modules/upload_tmp");
 
// Admin path 
define("DB_PATH", "/.data/RACHEL/rachel/admin/admin.sqlite");

if(is_rachelpi){
    define("UPLOAD_TMP_DIR", "/var/www/modules/upload_tmp"); 
    define("DB_PATH", "/var/www/admin/admin.sqlite");    
}

// Max remaining space on disk. 2% + 2% for root 
define("MAX_DISK_SPACE", 4);

// Max zip file name length 
define("MAX_NAME_LENGTH", 76); 

// Default part size - 10MB
define("DEFAULT_PART_SIZE", 10485760);

// Part size for 1GB+ files - 50mb
define("GB_PART_SIZE", 52428800);

// Part size for 2GB+ files - 50mb
define("MAX_PART_SIZE", 52428800);

// Allowed size vs file size difference in
// a zip's central directory 
define("CD_TOLERANCE", 1048576);

// Logging level for errors. 
define("LOG_LEVEL", LogLevel::DEBUG);

// Status codes for database/responses
abstract class StatusCode {
    const NONE       = 0;
    const UPLOADING  = 1;
    const PAUSED     = 2;
    const INSTALLING = 3;
    const ERRORED    = 4;
    const CANCELLED  = 5;
    const PROCESSED  = 6;
    const UPLOADS    = 7;   
    const COMPLETE   = 8;
    const SCRIPT     = 9;
}

// Action codes from the client
abstract class ClientAction {
    const NONE     = 0;
    const VALIDATE = 1;
    const PROCESS  = 2;
    const CANCEL   = 3;    
    const INSTALL  = 4;
    const PAUSE    = 5;
    const PROGRESS = 6;
    const UPLOADS  = 7;
}

// Logging Levels for error_exit only
abstract class LogLevel {
    const ALL        = 0;
    const USER       = 1;
    const DEBUG      = 2;
    const DATABASE   = 3;
    const FILESYSTEM = 4; 
    const CONFIG     = 5;
    const NETWORK    = 6;
    const NONE       = 7;
}

/* Handles all exits with response
   $token - database update 
   $code  - error identification
   $level - LogLevel
   $error - Message added to DB or passed to client 
*/
function error_exit($token, $code, $level, $error){
    // Update the upload record if we have a token     
    if($token != null){
        $db       = getdb();
        $db_token = $db->escapeString($token);
        $db_code  = $db->escapeString($code);
        $db_error = $db->escapeString($error);
        $success  = $db->exec("UPDATE uploads 
                                  SET status=4,
                                      code='$db_code',
                                      message='$db_error'                                     
                                WHERE token='$db_token'");

        if(!$success){
            error_log("error_exit: Failed to set upload status to errored");
        }
        
        // Get the upload record
        $info = $db->querySingle("SELECT * 
                                    FROM uploads 
                                   WHERE token='$db_token'", true);

        if($info){
            $log = $info['log'];

            if(file_exists($log)){
                $lines = "An error with code " . $code . " has occurred\n";
                $lines = $lines . $error;
                file_put_contents($log, $lines, FILE_APPEND);
            }
        }     

        $db->close();        
    }    
    
    header('HTTP/1.1 500 Internal Server');
    header('Content-Type: application/json; charset=UTF-8');
    
    if(LOG_LEVEL != LogLevel::NONE){
        if($level > LOG_LEVEL){
            error_log($error . " code: " . $code);        
        }        
    }

    // Show a generic message with code as to not overwhelm users
    if($level > LogLevel::USER){
        $error = "An error with code '" . $code . "' has occurred.<br> For more detailed information click error message button for your upload in the active uploads panel below.";
    }

    $response = json_encode(["error" => $error, 
                             "code"  => $code]);

    if(!$response){
        error_log("Failed to encode json response with error: " . $error  . " and code: " . $code);
        die();
    }

    echo $response;
    die();
}

function fastGetDB(){
    $dbPath = getAbsAdminPath() . "/admin.sqlite";
    $db     = new SQLite3($dbPath, SQLITE3_OPEN_READWRITE);
    
    if($db === null){
        return null;
    }
    $db->busyTimeout(10000);
    return $db;
}

?>