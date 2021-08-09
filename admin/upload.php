<?php
require_once("../admin/common.php");
if (!authorized()) { exit(); }

require_once("upload_common.php");

// Limit requests to POST only
if($_SERVER['REQUEST_METHOD'] != 'POST'){
    error_log("Invalid request method");
    die();
}

// Run the action the user requests
if(isset($_POST['action'])){
    $action = $_POST['action'];

    switch($action){
        case ClientAction::NONE:
            break;
        case ClientAction::VALIDATE:
            validate(); 
            break;
        case ClientAction::CANCEL:
            cancel();
            break;
        case ClientAction::INSTALL:
            install();
            break;
        case ClientAction::PAUSE:
            pause();
            break; 
        case ClientAction::UPLOADS;
            getUploads();
            break;
        default:
            break;
    }

    error_exit(null, 
               1001,
               LogLevel::DEBUG,
               "Upload: Called without a valid action. The action provided was " . $action);    
}

// Error if no action was provided. 
echo "<h3>This page is not meant to be directly accessed </h3>";
error_exit(null,
           1002,
           LogLevel::DEBUG,
           "Upload: Called without a valid POST");
die();

// Main validation for initial upload
function validate(){
    $fileName = $_POST['file_name'];

    // Check the file name
    if(isNull($fileName)){
        error_exit(null,
                   1100,
                   LogLevel::USER,
                   "The upload file name was not provided by the client. <br>Please try uploading your file again.");
    }

    // Check the file name is not too long 
    if(strlen($fileName) > MAX_NAME_LENGTH){
        error_exit(null,
                   1101, 
                   LogLevel::USER,
                   "The upload file name is too long. <br>The file name must be less than " . MAX_NAME_LENGTH . " characters.");
    }

    $ext      = pathinfo($fileName, PATHINFO_EXTENSION);
    $fileType = $_POST['file_type'];
    
    // Check that the extension is zip
    if($ext != "zip"){
        error_exit(null,
                   1102,
                   LogLevel::USER,
                   "The upload file extension must be .zip"); 
    }

    // Check the file type was provided
    if(isNull($fileType)){
        error_exit(null,
                   1103,
                   LogLevel::USER,
                   "The upload file type was not provided by the client. <br>Please try uploading your file again.");
    }

    // Check the mimetype is zip 
    if($fileType != "application/x-zip-compressed" && $fileType != "application/zip"){
        error_exit(null,
                   1104,
                   LogLevel::USER,
                   "The upload file is not a valid zip file");
    }

    // Get the file size 
    $fileSize = $_POST['file_size'];

    // Check that the file size was provided
    if(isNull($fileSize)){
        error_exit(null,
                   1105,
                   LogLevel::USER,
                   "The upload file size was not provided by the client. <br>Please try uploading your file again.");
    }

    $modPath   = getAbsModPath();   
    $checkPath = $modPath . "/" . pathinfo($fileName, PATHINFO_FILENAME);

    // Check that the module isn't already installed
    if(is_dir($checkPath)){
        error_exit(null,
                   1106,
                   LogLevel::USER,
                   "A module with the same name as this zip is already installed. <br> Please delete the existing module before uploading it's replacement." );
    }

    $db = getdb();

    if(!$db){
        error_exit(null,
                   1107,
                   LogLevel::DATABASE,
                   "Validate: Failed to get DB handle");
    }

    // Get the zip file name without the extension 
    $fileDirName    = pathinfo($fileName, PATHINFO_FILENAME);
    $db_fileDirName = $db->escapeString($fileDirName);
    
    // Check if modules installed with this same zip name 
    $modExists = $db->querySingle("SELECT * 
                                     FROM modules
                                    WHERE moddir='$db_fileDirName'");

    // Exit if the module exists
    if($modExists){
        error_exit(null,
                   1108,
                   LogLevel::USER,
                   "A module with this name is installed. <br>Please delete the existing module from the install page before uploading this file");
    }

    // Check that the database is updated
    checkDatabase($db);
   
    $db_fileName = $db->escapeString($fileName);
    
    // Check for a previous upload for this file 
    $info = $db->querySingle("SELECT * 
                                FROM uploads 
                               WHERE name ='$db_fileName'", true);

    // Check if we can resume if it exists
    if($info){       
        $token   = $info['token'];
        $db_size = $info['size'];
        
        // Check the file size to make sure we can resume 
        if($db_size != $fileSize){
            error_exit(null,
                       1109,
                       LogLevel::USER,
                       "An upload file with the same name but a different size exists. <br>Please cancel the previous upload before uploading this file.");
        }

        resume($token);
        die();
    }

    // Get the temporary upload directory
    $tmpPath = getAbsTempDir();

    if(!$tmpPath){
        error_exit(null,
                   1110,
                   LogLevel::CONFIG,
                   "Validate: Failed to get temporary directory");
    }

    // The path for this upload 
    $uploadDir = $tmpPath . "/" . $fileDirName;

    if(is_dir($uploadDir)){
        deleteDirectory($uploadDir);
    }

    // Create the upload directory
    try{
        mkdir($uploadDir);
    }
    catch(exception $ex){
        error_exit(null,
                   1111,
                   LogLevel::FILESYSTEM,
                   "Validate: Failed to create temporary upload directory");
    }
    
    // Create the logs dir if it doesn't exist 
    $logsDir = UPLOAD_TMP_DIR . "/logs/";
    
    if(!is_dir($logsDir)){
        try{
            mkdir($logsDir);
        }catch (exception $ex){
            error_exit(null,
               1208,
               LogLevel::DEBUG,
               "Validate: Failed to create log directory");
        }
    }   

    
    // Check the free space available on the upload directory's device 
    if(!hasFreeSpace($uploadDir, $fileSize, MAX_DISK_SPACE)){
        error_exit(null,
                   1112,
                   LogLevel::USER,
                   "There is not enough space on the drive to upload this module"); 
    }
    
    // Use the central directory blob for validation
    if(isset($_FILES['file_cd'])){
        // The central directory 
        $fileCD     = $_FILES['file_cd']['tmp_name'];
        
        // The path to move the CD to 
        $fileCDPath = $uploadDir . "/cd";
        
        move_uploaded_file($fileCD, $fileCDPath);
        
        // Parses and returns info about this CD from zipinfo
        $cdInfo = getCentralDirectoryInfo($fileCDPath);
        
        // The file size from the CD to make sure it's accurate
        $cdSize = $cdInfo['file_size'];

        // The difference between the two 
        $cdDiff = bcsub($fileSize, $cdSize);
       
        // Check that the file size stated is reasonable
        if( $cdDiff < CD_TOLERANCE ){            
            $decompressed = bcadd($cdInfo['decompressed'], ($cdDiff * 2));
            $fullSize     = bcadd($decompressed, $fileSize);

            // Check the free space available on the upload directory's device 
            if(!hasFreeSpace($uploadDir, $fullSize, MAX_DISK_SPACE)){
                error_exit(null,
                           1113,
                           LogLevel::USER,
                           "There is not enough space on the drive to upload and install this module."); 
            }
        }
    } else {
        $fullSize = bcmul($fileSize, 3);

        // Check with a higher space if central directory not available
        if(!hasFreeSpace($uploadDir, $fullSize, MAX_DISK_SPACE)){
            error_exit(null,
                       1114,
                       LogLevel::USER,
                       "There is not enough space on the drive to upload and install this module. "); 
        }
    }    

    // Get the optimal part size for this file 
    $partSize = getPartSize($fileSize);

    // The total number of parts we will use for this file 
    $partTotal = 0;
    
    // The size of the last part we will use for this file 
    $finalPart = 0;

    // If the part size is greater than the file size, we only have 1 part 
    if($partSize > $fileSize){
        // Set the part size to the file size 
        $partSize  = $fileSize;
        
        // Set the part total to 1
        $partTotal = 1;
        
        // Set the final part to the file size 
        $finalPart = $fileSize;
    } else {
        // Otherwise, we get the part total from getPartTotal
        $partTotal  = getPartTotal($fileSize, $partSize);

        // To fix indexing 
        $checkTotal = $partTotal - 1;
        
        // Get the size of the final part 
        $finalPart  = bcsub($fileSize, $checkTotal * $partSize);
    }

    // The unique id used to identify uploads 
    $token = uniqid();
    
    // Hash for logs
    $hash = md5($token);
    
    // Path to the install log 
    $log = $logsDir . $hash  . ".log";
    
    // Remove any existing log as it will be appended to
    if(file_exists($log)){
        try{
            unlink($log);
        } catch(exception $ex){
            error_exit($token,
               11141,
               LogLevel::DEBUG,
               "Validate: Failed to delete existing log file " . $log);            
        }
    }  
    
    // Get the current time 
    $time        = microtime(true);
    $code        = StatusCode::UPLOADING;
    $db_token    = $db->escapeString($token);
    $db_name     = $db->escapeString($fileName);
    $db_size     = $db->escapeString($fileSize);
    $db_dir      = $db->escapeString($uploadDir);
    $db_partSize = $db->escapeString($partSize);

    // Insert our upload record into the uploads table
    $rv = $db->exec("INSERT INTO uploads (
                     name,
                     token,
                     hash,                     
                     size, 
                     dir,
                     log,
                     last_update, 
                     part_size, 
                     part_total, 
                     final_part, 
                     start, 
                     status)
                    VALUES ('$db_name',
                            '$db_token',
                            '$hash',
                            '$db_size',
                            '$db_dir',
                            '$log',
                            '$time',
                            '$db_partSize', 
                            '$partTotal',
                            '$finalPart',
                            '$time',
                            '$code')");

    if(!$rv){
        error_log($db->lastErrorMsg());
        error_exit(null,
                   1115,
                   LogLevel::DATABASE,
                   "Validate: Failed to add upload to database" );
    }

    // Get the record was added 
    $db_upload = $db->querySingle("Select * 
                                     FROM uploads 
                                    WHERE token='$db_token'", true);

    if(!$db_upload){
        error_exit(null,
                   1116,
                   LogLevel::DATABASE,
                   "Validate: Failed to get added upload info");
    }
    
    $db_uploadToken = $db_upload['token'];

    if(isNull($db_uploadToken)){
        error_exit(null,
                   1117,
                   LogLevel::DATABASE,
                   "Validate: Failed to get upload token check from result");
    }

    file_put_contents($log, 
                      "======= Uploading " . $fileName . " =======\n", 
                      FILE_APPEND);
    
    header("HTTP/1.1 200 OK");
    header('Content-Type: application/json; charset=UTF-8');

    $response = json_encode(['code'        => $code, 
                             'token'       => $db_uploadToken,
                             'hash'        => $hash,
                             'last_update' => $time,
                             'part_size'   => $db_partSize,   
                             'part_total'  => $partTotal,
                             'parts_done'  => 0,
                             'start'       => 0]);       

    if(!$response){
        error_exit($db_uploadToken,
                   1118,
                   LogLevel::DEBUG,
                   "Validate: Failed to json encode validation response"); 
    }

    // Validated response
    echo $response;
    die();
} 

// Returns the optimal part size to use 
function getPartSize($fileSize){
    // Set it to the default part size 
    $partSize = DEFAULT_PART_SIZE;

    // Check if it's bigger than 1GB 
    $gbComp = bccomp("$fileSize", "1073741824"); 

    if($gbComp == 1){
        $partSize = GB_PART_SIZE;    
    }
    
    $fourGBComp = bccomp("$fileSize", "4294967296");
    
    if($fourGBComp == 1){
        $partSize = MAX_PART_SIZE;    
    }

    $phpMax = getPHPMaxSize();

    // If we didn't get a PHP max we fail. 
    // This should never happen unless php.ini is misconfigured
    if(!$phpMax){
        error_exit(null,
                   1119,
                   LogLevel::CONFIG,
                   "Validate: Failed to get max part size from PHP");
    }

    // Set the part size to the max part size allowed by PHP
    if($phpMax < PHP_INT_MAX){
        if($partSize > $phpMax){
            $partSize = $phpMax;
        }
    }

    return $partSize;
}

// Checks that the database has been updated
function checkDatabase($db){
    // Check the database for the uploads table 
    $db_check = $db->querySingle("SELECT name 
                                    FROM sqlite_master 
                                   WHERE type='table' 
                                     AND name='uploads'");

    // If we have the uploads table, return 
    if($db_check){
        return; 
    }

    try{
        $db->exec("BEGIN");
        $db->exec("
            CREATE TABLE IF NOT EXISTS uploads (
                name        VARCHAR(255) NOT NULL UNIQUE,
                token       VARCHAR(255) NOT NULL UNIQUE PRIMARY KEY,
                hash        VARCHAR(255) NOT NULL,
                size        INTEGER NOT NULL,
                dir         VARCHAR(255) NOT NULL,
                log         VARCHAR(255) NOT NULL,
                part_size   INTEGER NOT NULL,
                part_total  INTEGER NOT NULL,
                final_part  INTEGER NOT NULL,
                last_update REAL NOT NULL,
                message     TEXT,
                code        INTEGER DEFAULT 0 NOT NULL,
                resumes     INTEGER DEFAULT 0,
                start       REAL NOT NULL,
                status      INTEGER DEFAULT 0 NOT NULL)");
        $db->exec("COMMIT");
    } catch ( exception $ex ){
        error_log($db->lastErrorMsg());
        error_exit(null,
                   1120,
                   LogLevel::DATABASE,
                   "checkDatabase: Failed to commit changes to DB"); 
    }

    // Query for the uploads table 
    $db_check = $db->querySingle("SELECT name 
                                    FROM sqlite_master 
                                   WHERE name='uploads'");

    // Check that it exists now 
    if(!$db_check){
        error_exit(null,
                   1121,
                   LogLevel::DATABASE,
                   "checkDatabase: Failed to update the database"); 
    }
}

// Get the total parts for the upload 
function getPartTotal($file_size, $part_size){
    $count = bcdiv("$file_size", "$part_size", 1);

    // Floor the value if it's a float 
    if(filter_var($count, FILTER_VALIDATE_FLOAT)){
        $count = floor($count);
        $count++;
    }

    return $count;
}

// Module installation 
function install(){
    // Allow this to run forever. Not great, but only way to guarantee this
    set_time_limit(0); 
    
    if(!isset($_POST['token'])){
        error_exit(null,
                   1200,
                   LogLevel::DEBUG,
                   "Install: No token provided");
    }

    $token = $_POST['token'];
    $db    = getdb();
    
    if(!$db){
        error_exit($token,
                   1201,
                   LogLevel::DATABASE,
                   "Install: Failed to get database handle");
    }
    
    $db_token = $db->escapeString($token);
    $info     = $db->querySingle("SELECT * 
                                    FROM uploads 
                                   WHERE token='$db_token'", true);

    /*If true, the upload was likely canceled between the time 
      the last part finished and install started. This is not 
      considered an error at the moment so we respond with an
      error that can be noted but ignored       
    */      
    if(empty($info)){
        header("HTTP/1.1 200 OK");
        header('Content-Type: application/json; charset=UTF-8');

        $code     = StatusCode::NONE;
        $message  = "Install: Failed to find a record for the upload. This usually means the upload was canceled between the time the upload finished and install was called. If you are seeing this issue and did not cancel the upload, please check that your database is intact";
        $response = json_encode(["code"    => $code,
                                 "message" => $message ]);

        if(!$response){
            error_exit($token,
                       1202,
                       LogLevel::DEBUG,
                       "Install: Failed to json encode completed response");
        }

        // Install complete 
        echo $response;
        die();     
    }

    // Check if the query failed 
    if($info == false){
        error_exit($token,
                   1203,
                   LogLevel::DATABASE,
                   "Install: Couldn't get completed upload record" ); 
    }
    
    // Get the upload log 
    $installLog = $info['log'];
    
    // Create the log if it doesn't exist 
    if(isNull($installLog) || !file_exists($installLog)){
        $logsDir    = UPLOAD_TMP_DIR . "/logs/";
    
        if(!is_dir($logsDir)){
            try{
                mkdir($logsDir);
            }catch (exception $ex){
                error_exit(null,
                   1208,
                   LogLevel::DEBUG,
                   "Install: Failed to create logs directory");
            }
        }   

        $installLog = $logsDir . $info['hash'] . ".log";
    }
    
    $modPath   = getAbsModPath();
    $partSize  = $info['part_size'];
    $partTotal = $info['part_total'];
    $uploadDir = $info['dir'];
    $fileSize  = $info['size'];
    $fileName  = $info['name'];
    $finalPart = $info['final_part'];
    
    // Check the upload dir still exists 
    if(!is_dir($uploadDir)){
        error_exit($token,
                   1204,
                   LogLevel::DEBUG,
                   "Install: The upload directory no longer exists");
    }

    // This is the base file name we will be using for our compiled file 
    $base   = $uploadDir . "/" . $fileName;
    $time   = microtime(true);
    $status = StatusCode::INSTALLING;
    $rv     = $db->exec("UPDATE uploads 
                            SET status='$status',
                                last_update='$time'
                          WHERE token='$db_token'");

    if(!$rv){
        error_exit($token,
                   1205,
                   LogLevel::DATABASE,
                   "Install: Failed to set the upload status to installing in the DB." );
    }
 
    // Get a list of the file parts in our upload directory 
    $foundParts = glob($uploadDir . "/*.z*");
    $foundCount = count($foundParts);
    
    // Check that our part total matches our found part count 
    if($foundCount != $partTotal){
        // There are more part files 
        if($foundCount > $partTotal){
           error_exit($token,
                      1206,
                      LogLevel::DEBUG,
                      "Install: There are more part files than expected"); 
        }
   
        // There are less part files 
        error_exit($token,
                   1207,
                   LogLevel::DEBUG,
                   "Install: There are fewer part files than expected");
    }

    // Update the log 
    file_put_contents($installLog, 
                      "======= Beginning Installation =======\n", 
                      FILE_APPEND);
    
    // This section handles multi-part file uploads 
    if($partTotal > 1){
    
        updateProgress( $uploadDir, 
                        "Building " . $fileName);
                        
        // Loop through the part total
        for($i = 0; $i < $partTotal; $i++){          
            // The expected part name           
            $part = $base . ".z" . ($i + 1); 

            // Make sure the expected part file exists 
            if(!file_exists($part)){
                error_exit($token,
                           1210,
                           LogLevel::DEBUG,
                           "Install: Missing part file " . $part);
            }
            
            // Get the size of the part file 
            $size = filesize($part);
            
            // Check that this is the final part for size difference
            if(($i + 1) == $partTotal){
                // Check the final part size matches 
                if($size != $finalPart){
                    error_exit($token,
                               1211,
                               LogLevel::DEBUG,
                               "install: Invalid final part size for " . $part);                    
                }                
            } else {
                // Check that part the size is the right part size 
                if($size != $partSize){
                    error_exit($token,
                               1212,
                               LogLevel::DEBUG,
                               "install: Invalid part size for " . $part); 
                }                
            }

            // Append this part to the base file and log to installLog
            exec("cat " . $part . " >> " . $base, $output, $rval);
            
            if($rval === 1){
                error_exit($token,
                           1213,
                           LogLevel::FILESYSTEM,
                           "Install: Failed to compile file");
            }

            try{
                unlink($part); 
            } catch (exception $ex){
                error_exit($token,
                           1214,
                           LogLevel::FILESYSTEM,
                           "Install: Failed to delete part while compiling zip file");
            }
            
            // Update the install log as cat is not verbose
            try{
                $line = "Part ".($i + 1) . " of " . $partTotal . " added to " . basename($base) . "\n";
                
                if(($i + 1) == $partTotal){
                    $line = $line . "======= Finished Building " . basename($base) . " =======\n";
                }
                
                file_put_contents($installLog, 
                                  $line, 
                                  FILE_APPEND);
            } catch (exception $ex){
                error_exit($token,
                           1215,
                           LogLevel::FILESYSTEM,
                           "Install: Failed to write to build.log");
            }
        } // Parts for loop
    }
    
    // If there is only one part rename it to the zip  
    if($partTotal == 1){
        $part = $uploadDir . "/" . $fileName . ".z1";
        rename($part, $base);
    }

    file_put_contents($installLog, 
                      "======= Verifying " . basename($base). " =======\n", 
                      FILE_APPEND);

    updateProgress( $uploadDir,
                    "Verifying " . $fileName);

    $mimeType = mime_content_type($base);
    
    if($mimeType != "application/zip"){
        error_exit($token,
                   1216,
                   LogLevel::USER,
                   "The provided file is not recognized as a zip file. The file may be corrupt");       
    }

    // Get the final size of our base 
    $finalSize = getFileSize($base);

    // Check that the final size is the expected file size  
    if($finalSize != $fileSize){
        try{
            unlink($base);
        }
        catch(exception $ex){
            error_exit($token,
                       1217,
                       LogLevel::FILESYSTEM,
                       "Install: Failed to delete base file");   
        }        
        
        error_exit($token,
                   1218,
                   LogLevel::DEBUG,
                   "Install: The compiled size does not match the expected file size");
    }
    
    // Check the zip contains a single directory before unzip.
    if(!hasOneDir($token, $base)){
        error_exit($token,
                   1219,
                   LogLevel::USER,
                   "The zip file must contain a single directory at it's root");
    }
    
    // The zip file name that we have ready to unzip 
    $zipName = $uploadDir . "/" . $fileName;
    
    // Get the decompressed size of the zip before unzip
    $unzippedSize = getDecompressedSize($zipName);

    // Check the device has space after unzip 
    if(!hasFreeSpace($uploadDir, $unzippedSize, MAX_DISK_SPACE)){
        error_exit($token,
                   1220, 
                   LogLevel::USER, 
                   "There is not enough space to install module"); 
    }
    
    // Directory to unzip to  
    $unzipPath = $uploadDir . "/zip";

    // Check if the unzip path exists and delete if it does 
    if(is_dir($unzipPath)){        
        deleteTempDir($token);
    }

    // *CHECK* - permissions on folder 777 or 755?
    // Create the unzip dir 
    try{       
        mkdir($unzipPath, 0777, true);
    }
    catch(exception $ex){
        error_exit($token,
                   1221,
                   LogLevel::FILESYSTEM,
                   "Install: Failed to create temporary module dir" );
    }

    updateProgress($uploadDir,
                   "Unzipping " . $fileName);

    $line = "======= " . basename($base). " Verified =======\n";
    $line = $line . "======= Starting Extraction =======\n";
    
    // Update the log 
    file_put_contents($installLog, 
                      $line, 
                      FILE_APPEND);

    // Run the unzip command
    exec("unzip $zipName -d $unzipPath >> $installLog", $output, $rvuz);

    // If the unzip process failed we delete 
    if($rvuz === 1){
        deleteTempDir($token);
        error_exit($token,
                   1222,
                   LogLevel::USER,
                   "Failed to unzip the module zip");
    }

    updateProgress($uploadDir,
                   "Checking " . $fileName . " config");
                   
    if(!is_dir($unzipPath)){
        error_exit($token,
                   1223,
                   LogLevel::DEBUG,
                   "Unzip path does not exist. <br>Failed to unzip the zip file. ");        
    }

    // Get the number of items in the base unzip directory 
    $itemCount = count(glob($unzipPath));

    if(!$itemCount){
        deleteTempDir($token);
        error_exit($token,
                   1224,
                   LogLevel::DEBUG,
                   "Install: Item count is empty ");
    }

    // Check we only have a single item
    if($itemCount != 1){
        deleteTempDir($token);
        error_exit($token,
                   1225, 
                   LogLevel::USER, 
                   "There are too many files in the base zip folder. <br>The zip should contain a single folder"); 
    }
    
    if($itemCount == 0){
        deleteTempDir($token);
        error_exit($token,
                   1226, 
                   LogLevel::USER, 
                   "There are no files in the unzip directory. Failed to unzip the zip file");         
    }

    // Get a list of directories at the module path 
    $dirs = glob($unzipPath . "/*", GLOB_ONLYDIR); 

    if(!$dirs){
        deleteTempDir($token);
        error_exit($token, 
                   1227, 
                   LogLevel::USER, 
                   "Failed to get directories from unzipped module. ");
    }
    
    // Check that there is only 1 directory
    if(count($dirs) != 1){
        deleteTempDir($token);        
        error_exit($token, 
                   1228, 
                   LogLevel::USER, 
                   "There are too many directories in the root of the zip file. <br>There must only be one directory in the root of the zip file.");
    }
    
    // Get the zip's module directory from the glob output
    $zipModDir = $dirs[0];

    // Double check that it exists
    if(!is_dir($zipModDir)){
        deleteTempDir($token);
        error_exit($token,
                   1229, 
                   LogLevel::DEBUG, 
                   "Install: The new module directory does not exist");
    }

    // The expected path to the rachel-index.php file 
    $indexFile = $zipModDir . "/rachel-index.php";
    
    // Check that the rachel-index.php exists
    if(!file_exists($indexFile)){
        deleteTempDir($token);
        error_exit($token, 
                   1230,
                   LogLevel::USER,
                   "There is no rachel-index.php in the module directory inside the zip file");
    }
    
    // Warn about suspicious keywords
    checkIndex($indexFile, $installLog);

    // The final target path in the modules dir for this module 
    $finalModDir = $modPath . "/" . basename($zipModDir);
    
    if(is_dir($finalModDir)){
        deleteTempDir($token);
        error_exit($token,
                   1231,
                   LogLevel::USER,
                   "This module already exists. <br>Please delete the module from the Install tab before uploading a new version");
    }
    
    updateProgress($uploadDir,
                   "Installing " . $fileName);

    $moved = exec("mv $zipModDir $finalModDir", $output, $rval);
     
    // Check the result. If it failed, delete the temp dir
    if ($rval === 1) {
        deleteTempDir($token);
        error_exit($token,
                   1232,
                   LogLevel::FILESYSTEM,
                   "Install: Failed to move module to modules directory");
    }
    
    // Path to the expected finish_install.sh script 
    $finishScript = $finalModDir . "/finish_install.sh";

    // Run it if exists
    if(file_exists($finishScript)){
        updateProgress($uploadDir,
                       "Running " . $fileName . "'s install script");
        
        // Update the log 
        file_put_contents($installLog, 
                          "======= Running finish_install.sh =======\n", 
                          FILE_APPEND);
                      
        // Redirect script output to installLog
        exec("cd $finalModDir && bash $finishScript 2>&1 | tee --a $installLog", $fsoutput, $fsrv);

        // Check the result. If it failed, delete the temp dir
        if ($fsrv === 1) {
            if(is_dir($finalModDir)){
                deleteDirectory($finalModDir);
            }
            
            error_exit($token, 
                       1233, 
                       LogLevel::CONFIG, 
                       "Install: Failed to run finish script. Please check system logs for more information");
        }
    }
    
    // Delete the temp dir 
    deleteTempDir($token);

    // Sync the modules database 
    syncmods_fs2db();
    
    // Restart the kiwix server 
    kiwix_restart();

    // Prepare the token for query
    $db_token = $db->escapeString($token);
    
    // Delete the upload record. 
    $deleted = $db->exec("DELETE FROM uploads 
                                WHERE token='$db_token'");     
    
    // Check that the upload was deleted
    if(!$deleted){
        error_exit($token,
                   1234, 
                   LogLevel::FILESYSTEM,
                   "Install: Failed to delete completed upload from the DB");
    }

    // Update the log 
    file_put_contents($installLog, 
                      "======= Upload and Install Complete =======\n", 
                      FILE_APPEND);
        
    // Success/json response header
    header("HTTP/1.1 200 OK");
    header('Content-Type: application/json; charset=UTF-8');

    $code     = StatusCode::COMPLETE;
    $response = json_encode(["code" => $code]);

    if(!$response){
        error_exit($token,
                   1235,
                   LogLevel::DEBUG,
                   "Install: Failed to json encode completed response");
    }

    // Install complete 
    echo $response;
    die();    
}

// Checks a rachel-index.php for keywords
function checkIndex($index, $log){
    $content  = file_get_contents($index);
    $keyWords = Array( 'exec(',
                       'fopen',
                       'passthru',
                       'proc_open',
                       'shell_exec(',
                       'sudo',
                       'system(');
                       
    $found = "";
                
    for($i = 0; $i < count($keyWords); $i++){
        $word = $keyWords[$i];
        
        if(stripos($content, $word)){
            $found = $found . ", " . $word;
        }
    }

    if(file_exists($log)){
        if($found !== ""){
            $lines = "The following security keywords were found in the rachel-index.php file\n";
            $lines = $lines . $found . "\n";
            file_put_contents($log, $lines, FILE_APPEND); 
        }
    }
}

// Resume an upload 
function resume($token){
    $db = getdb();

    if(!$db){
        error_exit($token,
                   1300,
                   LogLevel::DATABASE,
                   "Resume: Failed to get DB handle"); 
    }

    $db_token = $db->escapeString($token);
    $info     = $db->querySingle("SELECT * 
                                    FROM uploads 
                                   WHERE token='$db_token'", true);

    if(!$info){
        error_exit($token,
                   1301,
                   LogLevel::USER,
                   "Failed to get upload for provided token");
    }

    $fileName  = $info['name'];
    $status    = $info['status'];
    $partTotal = $info['part_total'];
    $partSize  = $info['part_size'];
    $finalPart = $info['final_part'];
    $uploadDir = $info['dir'];
    
    if($status == StatusCode::INSTALLING){
        error_exit($token,
                   1302,
                   LogLevel::USER,
                   "This upload can not be resumed as it is either currently installing, or failed during installation. <br>Please cancel and re-upload");        
    }

    if(!is_dir($uploadDir)){
        error_exit($token,
                   1303,
                   LogLevel::USER,
                   "This upload can not be resumed because the upload cache has been cleared. <br>Please cancel and re-upload.");
    }
    
    // Get a count of the parts already uploaded 
    $foundParts = glob($uploadDir . "/*.z*");
    
    if(!$foundParts){
        error_exit($token,
                   1304,
                   LogLevel::USER,
                   "This upload can not be resumed because the cache has been cleared.");
    }

    $foundCount = count($foundParts);
    
    // The last complete part
    $partsDone = 0;
    $remove    = false;

    // Integrity check on existing parts 
    for($i = 0; $i < $foundCount; $i++){
        // The expected name of the file 
        $checkPart = $uploadDir . "/" . $fileName . ".z" . ($i + 1);
        
        // Check that the file exists         
        if(!file_exists($checkPart)){
            $remove = true;
            continue;
        }

        // check name 
        if(strpos($checkPart, $fileName) === false){
            $remove = true;
        }

        $checkSize = filesize($checkPart);
        
        // check size 
        if($checkSize != $partSize){
            // check if it's the final part 
            if($i == $partTotal){
                if($checkSize != $finalPart){
                    $remove = true;
                }
            } else {                
                $remove = true;
            }
        }
        
        if($remove){
            try {
                unlink($checkPart);
            } catch ( exception $ex ){
                error_log("Failed to remove part file " . $checkPart);
                error_log("Exception: " . $ex);
            }
            
            continue;
        }

        // Add this as a valid part 
        $partsDone = $i + 1;         
    }
    
    if($partsDone == 0){
        error_exit($token,
                   1305,
                   LogLevel::USER,
                   "This upload can not be resumed as no valid part files were found. <br>Please cancel and re-upload");
    }

    // Allow uploads that were stopped in an uploading 
    // state to resume without interfering with a 
    // current upload.
    if($status == StatusCode::UPLOADING){
        // The last part file that was uploaded
        $finalPart = $uploadDir . "/" . $fileName . ".z" . $partsDone;
        
        // Get the last write time of the final part
        $finalmTime = filemtime($finalPart);

        if($finalmTime === false){
            error_exit($token,
                       1306,
                       LogLevel::USER,
                       "This upload can not be resumed at this time. The server could not determine if it is still being uploaded. Please cancel the upload and re-upload the file"); 
        }
        
        // The current time after the finalmtime is checked
        $checkTime = microtime(true);
        
        // The elapsed time between the last file write and now 
        $elapsed = bcsub($checkTime, $finalmTime);

        // Check if more less than 15 seconds has elapsed
        if($elapsed < 15){
            error_exit($token,
                       1307,
                       LogLevel::USER,
                       "This upload could not be resumed because it may currently be uploading. Please wait 15 seconds and try again. If you continue to see this message, please cancel the upload and re-upload the file."); 
        }
    }

    $time = microtime(true);

    // Calculate the new start 
    $start = $partsDone * $partSize;
    
    // New status code for the DB
    $code = StatusCode::UPLOADING;

    // Query that we are resuming 
    $stmt  =  "UPDATE uploads 
                  SET status='$code',
                      last_update='$time',
                      resumes=resumes + 1
                WHERE token='$db_token'";

    $success = $db->exec($stmt);  

    if(!$success){
        error_exit($token,
                   1308, 
                   LogLevel::DATABASE,
                   "Resume: Failed to set status to uploading in DB");
    }

    $hash = md5($token);

    header("HTTP/1.1 200 OK");
    header('Content-Type: application/json; charset=UTF-8');

    // The information provided to start the upload again 
    $response = json_encode(['code'        => $code,
                             'token'       => $db_token,     // Token
                             'hash'        => $hash,         // Hash of token 
                             'part_total'  => $partTotal,    // Number of parts
                             'parts_done'  => $partsDone,    // Completed parts 
                             'last_update' => $time,         // The current time
                             'part_size'   => $partSize,     // Part size 
                             'speed'       => "resuming...", // Current speed                
                             'start'       => $start]);      // Start offset
  
    if(!$response){
        error_exit($token,
                   1309, 
                   LogLevel::DEBUG,
                   "Resume: Failed to encode json response" );
    }                             

    echo $response;
    die();
}   

// Cancel an upload
function cancel(){
    if(!isset($_POST['token'])){
        error_exit(null,
                   1400,
                   LogLevel::DEBUG,
                   "Upload token not provided");
    }

    $token = $_POST['token'];
    $db    = getdb();

    if(!$db){
        error_exit($token,
                   1401,
                   LogLevel::DATABASE,
                   "Cancel: Failed to get database handle");
    }

    $db_token = $db->escapeString($token);
    $info     = $db->querySingle("SELECT * 
                                    FROM uploads 
                                   WHERE token='$db_token'", true);
    
    if(!$info){
        error_exit($token,
                   1402,
                   LogLevel::USER,
                   "The upload can not be cancelled as it no longer exists. Please refresh the page.");
    }

    // The status of the upload 
    $status = $info['status'];
    
    // The directory of the upload 
    $uploadDir = $info['dir'];
    
    // Delete the upload directory if it exists
    if(is_dir($uploadDir)){
        deleteDirectory($uploadDir);
    }
    
    if($status == StatusCode::INSTALLING){
        $moduleName  = basename($uploadDir);
        $modPath     = getAbsModPath() . "/";
        $installPath = $modPath . $moduleName;
        
        // Check that the module name isn't empty
        if(trim($moduleName) !== ''){
            // If Modpath exists 
            if(is_dir($installPath)){
                // Double check that it's not the whole modules dir
                if($installPath != getAbsModPath()){
                    deleteDirectory($installPath);                
                }
            }
        }
    }

    // Delete the upload record from the DB 
    $result = $db->exec("DELETE FROM uploads 
                               WHERE token='$db_token'");

    // Check it was deleted 
    if(!$result){
        error_exit($token,
                   1403,
                   LogLevel::DATABASE,
                   "Cancel: Failed to clear upload from database");
    }

    header("HTTP/1.1 200 OK");
    header('Content-Type: application/json; charset=UTF-8');
    $code = StatusCode::CANCELLED;
    $json = json_encode(['code' => $code]);
    echo $json;
    die();
}

// Pause an upload 
function pause(){
    if(!isset($_POST['token'])){
        error_exit(null,
                   1500,
                   LogLevel::DEBUG,
                   "Pause: Token not provided");
    }
    
    $token = $_POST['token'];
    $db    = getdb();
 
    if(!$db){
        error_exit($token,
                   1501,
                   LogLevel::DATABASE,
                   "Pause: Failed to get DB handle");
    }
    
    $db_token = $db->escapeString($token);
    $info     = $db->querySingle("SELECT * 
                                    FROM uploads 
                                   WHERE token='$db_token'", true);
    
    if(!$info){
        error_exit($token,
                   1502,
                   LogLevel::DATABASE,
                   "Pause: Failed to get upload record from DB");
    }
    
    // Check the state of the upload 
    $state = $info['status'];
    
    if($state == StatusCode::INSTALLING){
        // Ignore it without a failure. 
        header("HTTP/1.1 200 OK");
        header('Content-Type: application/json; charset=UTF-8');
        
        $code     = StatusCode::NONE;
        $response = json_encode(['code' => $code]);  
        echo $response;
        die();
    }

    $time    = microtime(true);
    $status  = StatusCode::PAUSED;
    
    // Set the status of the upload 
    $success = $db->exec("UPDATE uploads 
                             SET status='$status',
                                 last_update='$time'                                    
                           WHERE token='$db_token'");

    // Check that it was successful 
    if(!$success){
        error_exit($token,
                   1503,
                   LogLevel::DATABASE,
                   "Pause: Failed to commit pause status to DB");
    }

    header("HTTP/1.1 200 OK");
    header('Content-Type: application/json; charset=UTF-8');
    
    $code     = StatusCode::PAUSED;
    $response = json_encode(['code' => $code]);       

    if(!$response){
        error_exit($token,
                   1504, 
                   LogLevel::DEBUG, 
                   "Pause: Failed to json encode validation response"); 
    }

    echo $response;
    die(); 
}

// Return the existing uploads for cancellation in the UI
function getUploads(){ 
    $db = getdb();

    if(!$db){
        error_exit(null,
                   1600,
                   LogLevel::DATABASE,
                   "getUploads: Failed to get a DB handle");
    }

    $result = $db->query("SELECT * FROM uploads");
  
    if(!$result){
        header("HTTP/1.1 200 OK");
        header('Content-Type: application/json; charset=UTF-8');   
        
        $code     = StatusCode::UPLOADS;
        $response = json_encode(['code'  => $code,
                                 'count' => 0]);
                                                     
        if(!$response){
            error_exit(null,
                       1601,
                       LogLevel::DEBUG,
                       "getUploads: Failed to json encode response"); 
        }                                 
              
        echo $response;
        die();
    }     

    $count   = count($result);   
    $code    = StatusCode::UPLOADS;
    $uploads = array();

    while($row = $result->fetchArray(SQLITE3_ASSOC)){
        array_push($uploads, $row);
    }

    header("HTTP/1.1 200 OK");
    header('Content-Type: application/json; charset=UTF-8');    

    $response = json_encode(['code'    => $code,
                             'count'   => $count,    
                             'uploads' => $uploads]);       

    if(!$response){
        error_exit(null,
                   1602,
                   LogLevel::DEBUG,
                   "getUploads: Failed to json encode response"); 
    }

    echo $response;
    die();
}

// Write progress for an upload to it's progress.json file
function updateProgress($dir, $message){
    
    // Check the directory exists 
    if(!is_dir($dir)){
        error_exit( null,
                   1700,
                   LogLevel::DEBUG,
                   "UpdateProgress: Upload directory " . $dir . " does not exist. Can not write progress to file");
    }
    
    $progPath = $dir . "/progress.json";
    
    // Encode the information for the progress 
    $progData = array('message' => $message);
                          
    $progJson = json_encode($progData);

    if(!$progJson){
        error_exit($token,
                   1701,
                   LogLevel::DEBUG,
                   "Failed to encode json data for progress update");
    }    
    
    try{
        file_put_contents($progPath, $progJson );   
    } catch (exception $ex){
        error_exit($token,
                   1702, 
                   LogLevel::FILESYSTEM,
                   "Failed to write json progress file to disk" );

    }
}

// Function to get file size for the final file 
function getFileSize($fileName){
    if(isNull($fileName)){
        return null;
    }
    
    // Check that the file exists 
    if(!file_exists($fileName)){
        return null;
    }
    
    // Stat for 32bit compat 
    exec("stat -c %s " . $fileName, $output, $rval);
    
    // Make sure it passed 
    if($rval === 1){
        error_exit(null,
                   1800,
                   2, 
                   "GetFileSize: Failed to stat " . $fileName);
    }
    
    // Check that we got the size 
    if(isNull($output[0])){
        error_exit(null, 
        1801, 
        LogLevel::FILESYSTEM,
        "GetFileSize: Failed to get output of stat for " . $fileName);
    }
    
    // Return the size 
    return $output[0];
}

// Returns central directory info for ZIP validation
function getCentralDirectoryInfo($fileName){
    // Check that the file exists 
    if(!file_exists($fileName)){
        error_exit(null,
                   1900,
                   LogLevel::DEBUG,
                   "GetFinalSize: Provided file does not exist");
    }

    // Command to get the uncompressed size 
    exec("zipinfo -t " . $fileName, $output, $rval);
    
    // Check if the command failed 
    if($rval === 1){
        error_exit(null,
                   1901,
                   LogLevel::DEBUG,
                   "GetFinalSize: Failed to get content listing from file");
    }
    
    if(!isset($output[2])){
        return null;
    }

    // Get the third line due to error 
    $line   = $output[2];
    $values = explode(" ", $line);
    
    $fileCount    = "";
    $fileSize     = "";
    $decompressed = "";

    // This should be file count 
    if(isset($values[0])){
       $fileCount = $values[0];
    }
    
    // This should be the decompressed size 
    if(isset($values[2])){
        $decompressed = $values[2];
    }
    
    // This should be the file size 
    if(isset($values[5])){
        $fileSize = $values[5];
    } 
    
    $info = array("decompressed" => $decompressed,
                  "file_size"    => $fileSize,
                  "file_count"   => $fileCount);

    return $info;  
}

// Returns the decompressed size of a zip before unzipping 
function getDecompressedSize($fileName){
    // Check that the file exists 
    if(!file_exists($fileName)){
        error_exit(null,
                   1902,
                   LogLevel::DEBUG,
                   "GetFinalSize: Provided file does not exist");
    }

    // Command to get the uncompressed size 
    exec("zipinfo -t " . $fileName . " | awk '{print $3}'", $output, $rval);
  
    // Check if the command failed 
    if($rval === 1){
        error_exit(null,
                   1903,
                   LogLevel::DEBUG,
                   "GetFinalSize: Failed to get content listing from file");
    }
    
    // Check that the output was set 
    if($output[0] == null){
        error_exit(null,
                   1904,
                   LogLevel::DEBUG, 
                   "GetFinalSize: Failed to get uncompressed zip size");

    }

    // Return the uncompressed size 
    return $output[0];
}

// Checks a zip contains a single dir before unzip
function hasOneDir($token, $fileName){
    // Check that the file exists
    if(!file_exists($fileName)){
        error_exit($token, 
                   1905,
                   LogLevel::DEBUG,
                   $fileName . " does not exist");
    }

    // Command to check the single dir 
    exec("zip -sf $fileName | sed -n 2p", $output, $rval);
    
    // Check that the command succeeded
    if($rval === 1){
        error_exit($token,
                   1906,
                   LogLevel::DEBUG,
                   "Failed to get content listing from file. Please check that your zip file is valid" . $fileName);
    }
    
    // Check the output 
    if(!isset($output[0])){
        error_exit($token,
                   1907,
                   LogLevel::DEBUG,
                   "Failed to get first line of output for file " . $fileName);
        return false;
    }
    
    // Get that the first entry doesn't have an extension 
    if (pathinfo($output[0], PATHINFO_EXTENSION)){
        return false;
    }

    // Trimmed basename of the output 
    $name = trim(basename($output[0]));
    
    // The name of the folder we expect at the root of the zip 
    $expectedName = pathinfo($fileName, PATHINFO_FILENAME);
    
    // Check that the name matches the expected name 
    if($name != $expectedName){
        error_exit($token,
                   1908,
                   LogLevel::USER,
                   "The zip file does not contain a folder that matches the zip name");
        return false;
    }

    return true;
}

// Checks space left for increase in size 
function hasFreeSpace($dir, $increase, $percent){ 
    $free        = bcsub(disk_free_space($dir), $increase);    
    $total       = disk_total_space($dir);
    $used        = bcsub($total, $free);    
    $usedPercent = bcdiv($used, $total, 2) * 100;
    $freePercent = 100 - $usedPercent;

    if($freePercent >= $percent){
        return true;
    }

    return false;
}

// Delete an upload's temporary dir 
function deleteTempDir($token){
    $db = getdb();

    if(!$db){
        // We don't response exit here as it's internal 
        error_log("deleteTempDir: Failed to get DB handle");
        return false;
    }

    $db_token = $db->escapeString($token);
    $info     = $db->querySingle("SELECT * 
                                    FROM uploads 
                                   WHERE token='$db_token'", true);
    
    // Check that we got a record 
    if(!$info){
        error_log("deleteTempDir: Failed to upload for provided token");
        return false;
    }
    
    // The upload temp dir. 
    $uploadDir = $info['dir'];
    
    deleteDirectory($uploadDir);
    return true;
}

// Retrieve the PHP max upload/post size 
function getPHPMaxSize(){
    // Get the upload max size from php.ini
    $phpUploadMax = ini_get('upload_max_filesize');

    if(isNull($phpUploadMax)){
        error_exit(null,
                   2000,
                   LogLevel::CONFIG,
                   "getPHPMaxSize: Failed to get get PHP upload max"); 
    }
    
    // Convert the PHP value to bytes
    $uploadMax = PHPToBytes($phpUploadMax);
    
    // Get the post max size from the php.ini 
    $phpPostMax = ini_get('post_max_size');

    if(isNull($phpPostMax)){
        error_exit(null,
                   2001,
                   LogLevel::CONFIG,
                   "getPHPMaxSize: Failed to get PHP post max");
    }

    // Convert post max value to bytes
    $postMax = PHPToBytes($phpPostMax);  
    
    // Return the greater of the two values
    return max($uploadMax, $postMax);    
}

// Checks and returns the upload directory
function getAbsTempDir(){
    if(is_dir(UPLOAD_TMP_DIR)){
        return UPLOAD_TMP_DIR;        
    }

    // Create the upload directory if it doesn't exist         
    try {
        $created = mkdir(UPLOAD_TMP_DIR);
            
        if(!$created){
            error_log("Failed to create uploads directory. ");
            return null;
        }

        return UPLOAD_TMP_DIR;
    } catch (exception $ex){
        error_log("Failed to create uploads directory. " . $ex);
        return null;
    }
}

// Check if a string is null. For readability
function isNull($val){
    if(!isset($val) || trim($val) === ''){
        return true;
    }
    
    return false;
}

// Converts PHP values to bytes 
function PHPToBytes( $value ) {
    $len    = strlen($value) - 1;
    $size   = substr( $value, 0, $len);
    $letter = strtoupper( substr( $value, $len));
    
    if($letter == "K"){
        return $size * 1024;
    }
    
    if($letter == "M"){
        return $size * 1048576;
    }
    
    if($letter == "G"){
        return $size * 1073741824;
    }
    
    error_exit(null, 
               2002,
               LogLevel::DEBUG, 
               "PHPToBytes: Failed to get letter from string");
}

function deleteDirectory($dir) {
    if(isNull($dir)){
        error_exit(null, 
                   2003,
                   LogLevel::DEBUG, 
                   "Attempt to delete empty directory");        
    }

    // Only delete within the upload temporary dir 
    if(strpos($dir, UPLOAD_TMP_DIR) === false) {
        error_exit(null, 
                   2004,
                   LogLevel::DEBUG, 
                   "Attempt to delete directory outside of upload tmp dir");
    }
    
    // Only delete directories in the UPLOAD_TMP_DIR
    if (!is_dir($dir)) {
        error_log("deleteDirectory: Could not delete " . $dir . " . Directory does not exist.");
        return false;
    }
    
    // Remove the dir as normal user
    exec("rm -rf " . $dir, $output, $rval);
    
    // 
    if($rval === 1){
        error_log("deleteDirectory: Failed to delete directory. Command failed");
        
        if($output){
            error_log($output); 
        }

        return false;
    }

    return true;
}

?>