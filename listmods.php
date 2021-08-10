<?php 

require_once("admin/common.php");

$modules = getmods_db();

if($modules == null){
	$response = json_encode("");
    echo $response;
    exit;	
}

$count = count($modules);

if($count == 0){
	$response = json_encode("");
    echo $response;
    exit;
}

$absModPath = getAbsModPath();
$response   = array();

foreach($modules as $module){
	$absPath = $absModPath . "/" . $module['moddir'];

    if(!file_exists($absPath)){
		continue;
	}

    $moddir   = $module['moddir'];
	$ksize    = get_dir_size($absPath);
	$mod_json = array('moddir' => $moddir,
	                  'ksize' => $ksize);
	$response[$moddir] = $mod_json;
}

echo json_encode($response);

header("HTTP/1.1 200 OK");
header("Content-Type: application/json");
echo "{ \"responseText\" : \"Succesfully generated JSON\" }\n";
return;

function get_dir_size($directory){
    $size = 0;
    $files = glob($directory.'/*');
    foreach($files as $path){
        is_file($path) && $size += filesize($path);
        is_dir($path)  && $size += get_dir_size($path);
    }
    return $size;
}

?>