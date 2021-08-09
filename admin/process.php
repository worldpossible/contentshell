<?php
require_once("upload_common.php");

// Get install progress 
if(isset($_GET['name'])){
    
    if(!isset($_GET['hash'])){
        $code     = StatusCode::NONE;
        $response = json_encode(['code'  => $code,
                                 'error' => "No hash provided"],         
                                 JSON_PRETTY_PRINT);
        header("HTTP/1.1 200 OK");
        header('Content-Type: application/json; charset=UTF-8');
        echo $response;                             
        die();    
    }

    $hash     = $_GET['hash']; 
    $name      = $_GET['name'];
    $jsonPath  = UPLOAD_TMP_DIR . "/" . $name . "/progress.json";  
    $logPath   = UPLOAD_TMP_DIR . "/logs/" . $hash . ".log";

    // Not ready for update yet 
    if(!file_exists($jsonPath)){
        $code     = StatusCode::NONE;
        $response = json_encode(['code' => $code], JSON_PRETTY_PRINT);
        header("HTTP/1.1 200 OK");
        header('Content-Type: application/json; charset=UTF-8');
        echo $response;                             
        die();        
    }
    
    $progress = file_get_contents($jsonPath);
    $details  = "";    
    
    if(file_exists($logPath)){
        exec("tail -n 2 $logPath", $output, $rval);
        
        if($rval === 0){
            if(isset($output[0])){
                $split   = explode("\n", $output[0]);
                $details = $split[0];                   
            }
        }
    }
    
    $code     = StatusCode::PROCESSED;
    $response = json_encode(['code'     => $code,
                             'progress' => $progress,
                             'details'  => $details],                         
                             JSON_PRETTY_PRINT);
    header("HTTP/1.1 200 OK");
    header('Content-Type: application/json; charset=UTF-8');
    echo $response;                             
    die();
}

if(isset($_POST['token']) === false){
    error_exit(null,
               3000,
               LogLevel::DEBUG,
               "Process: Failed to get upload token");
}

$token = $_POST['token'];
$db    = fastGetDB();

if($db === null){
    error_exit($token,
               3001,
               LogLevel::DATABASE,
               "Process: Failed to get database handle");
}

$db_token = $db->escapeString($token);
$info     = $db->querySingle("SELECT * 
                                FROM uploads 
                               WHERE token='$db_token'", true);
 
if(!$info){
    error_exit($token,
               3002,
               LogLevel::DEBUG,
               "Process: Failed to get upload with this token from DB");
}

$status = $info['status'];   

if($status === StatusCode::PAUSED){
    header("HTTP/1.1 200 OK");
    header('Content-Type: application/json; charset=UTF-8');
    $code = StatusCode::PAUSED;
    $response = json_encode(['code' => $code]);
    echo $response;    
    die();
}

if($status === StatusCode::CANCELLED){
    header("HTTP/1.1 200 OK");
    header('Content-Type: application/json; charset=UTF-8');
    $code = StatusCode::CANCELLED;
    $response = json_encode(['code' => $code]);
    echo $response;       
    die();
}

if(isset($_FILES['file']) === false){
    error_exit($token,
               3003,
               LogLevel::DEBUG,
               "Process: File part is missing");
}

$part      = $_FILES['file']['tmp_name'];
$fileName  = $info['name'];
$uploadDir = $info['dir'];
$startTime = $info['start'];

if(is_dir($uploadDir) === false){
    error_exit($token,
               3004,
               LogLevel::DEBUG,
               "Process: Upload dir no longer exists");
}

$partTotal = $info['part_total'];
$partSize  = $info['part_size'];
$partCount = (int)$_POST['parts_done'];
$thisPart  = $partCount + 1;

if($partCount >= $partTotal){
    error_exit($token,
               3005,
               LogLevel::DEBUG,
               "Process: The part count exceeds the expected part total");    
}

if($thisPart === $partTotal){
    $partSize = $info['final_part'];
}

if(!$partSize){
    error_exit($token,
               3006,
               LogLevel::DEBUG,
               "Process: Failed to get part size from DB"); 
}

if(file_exists($part) === false){   
    error_exit($token,
               3007,
               LogLevel::DEBUG,
               "Process: Failed to get uploaded part file");
}

$dstFile = $uploadDir . "/" . $fileName .  ".z" . $thisPart;
$moved   = move_uploaded_file($_FILES['file']['tmp_name'], $dstFile);

if($moved === false){
    error_exit($token,
               3008,
               LogLevel::DEBUG,
               " Failed to move temporary file to uploads dir.");
}

$fileSize = filesize($dstFile);

if($fileSize === false){
    error_exit($token,
               3009,
               LogLevel::DEBUG,
               "Process: Failed to get file size of uploaded part");
}

if($fileSize !== $partSize){  
    error_exit($token,
               3010,
               LogLevel::DEBUG,
               "Process: The part is an invalid size");    
}

$last_update = $_POST['last_update']; 
$time        = microtime(true); 

// Update and exit if complete
if($thisPart === $partTotal){
    $status  = StatusCode::COMPLETE;
    $success = $db->exec("UPDATE uploads 
                             SET last_update='$time',                            
                                 status='$status'                              
                           WHERE token='$db_token'");

    if($success === false){
        error_exit($token,
                   3011,
                   LogLevel::DATABASE,
                   "Process: Failed to commit upload completion to DB");
    }

    $code     = StatusCode::COMPLETE;
    $response = json_encode(['code'    => $code,
                             'message' => 'Upload completed']);
    header("HTTP/1.1 200 OK");
    header('Content-Type: application/json; charset=UTF-8');
    echo $response;
    die();     
}

$status  = StatusCode::PROCESSED;
$start   = bcmul($thisPart, $partSize);
$elapsed = bcsub($time, $last_update, 3);
$speed   = round($partSize/$elapsed, 2);
$speedKB = round($speed/1024, 2); 
$speedMB = round($speedKB/1024, 2);
$uiSpeed = $speedMB . " MB/s";

if($speedMB < 1){
    $uiSpeed = $speedKB . " KB/s";
}

$timePast = bcsub($time, $startTime, 3);

# Just in case to avoid NaN in the UI
if($partCount == 0){
    $fullTime    = bcmul($timePast, $partTotal);
    $transferred = $partSize;
} else {
    $fullTime    = bcmul($timePast, ($partTotal/$partCount));
    $transferred = bcmul($thisPart, $partSize);    
}

$timeLeft = bcsub($fullTime, $timePast);
$percent  = floor(($partCount/$partTotal) * 100);
$code     = StatusCode::PROCESSED;
$timeLeft = date('H:i:s', $timeLeft);
$timePast = date('H:i:s', $timePast);
$response = json_encode(['code'        => $code,
                         'parts_done'  => $thisPart,
                         'speed'       => $uiSpeed,
                         'last_update' => $time,
                         'start'       => $start,
                         'elapsed'     => $timePast,
                         'time_left'   => $timeLeft,
                         'transferred' => $transferred,
                         'percent'     => $percent]);

header("X-Accel-Buffering: no");
header("HTTP/1.1 200 OK");
header('Content-Type: application/json; charset=UTF-8');
echo $response;

$db->close();
unset($db);
die();   

?>