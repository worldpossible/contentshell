<?php

require_once("common.php");

$page_title  = $lang['storage'];
$page_script = "";
$page_nav    = "storage";

if (!authorized()) {
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if(isset($_POST['listDrives'])){
      listDrives();
      return;
  }
  
  if(isset($_POST['mountPath'])){
      ejectDrive();
      return;
  }
}

function ejectDrive(){
    $mountPath = $_POST['mountPath'];

    # CMAL100 mounted in /media/usb, CMAL150 mounts in /media/root
    if (strpos($mountPath, '/media/usb') === 0
        || strpos($mountPath, '/media/root') === 0
    ) {

        exec("umount " . escapeshellarg($mountPath) . " 2>&1", $output, $returnCode);

        if ($returnCode === 0) {
            header("HTTP/1.1 200 OK");
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => true]);
            die();
        } else {
            header('HTTP/1.1 500 Internal Server');
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'message' => 'Unmount failed']);
            die();
        }
    } else {
        header('HTTP/1.1 500 Internal Server');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => false, 'message' => 'Invalid mount path']);
        die();
    }
}

function listDrives() {
    $drives = array();

    exec("df -h", $output);

    # this is to handle volume names with spaces
    exec("df --output=target", $volNames);
    $i = 0;

    foreach ($output as $line) {
        $info = explode(" ", preg_replace('/\s+/', ' ', $line));

        if (count($info) >= 6) {

            $mountPath = $volNames[$i];
            ++$i;

            # CMAL100 mounted in /media/usb, CMAL150 mounts in /media/root
            if (strpos($mountPath, '/media/usb') === 0
                || strpos($mountPath, '/media/root') === 0
            ) {
                $driveInfo = array(
                    'mountPath' => $mountPath,
                    'label'     => preg_replace("/\/media\/(usb|root)\//", "", $mountPath),
                    'size'      => $info[1],
                    'used'      => $info[4],
                );

                #exec("blkid -s LABEL -o value $info[0]", $output2, $returnCode);
               # 
               # if ($returnCode === 0 && count($output2) > 0) {
               #     $driveInfo['label'] = trim($output2[0]);
               # }
                
                array_push($drives, $driveInfo);
            }
        }
    }

    header("HTTP/1.1 200 OK");
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($drives);
    die();
}

include "head.php";

?>
  <style>
  th { text-align: left; }
  td { text-align: left; padding-right:20px; }
  h2 { border-bottom: 1px solid #ccc; }
  </style>

  <h2 id='title'>USB Drives</h2>

  <table id='drives'></table>  

  <script>
    $(function() {
      populateDrives();
    });
    
//    refreshRate = 1000 * 10; // poll every ten seconds
    function populateDrives(){
      $("#drives").html("");

      $.ajax({
        type: 'POST',
        url:  'storage.php',
        data: { listDrives: 'listDrives' },
        dataType: 'json',
        success: function(response) {
          if(response.length == 0){
            let html  = "<table><tr><td>No USB drives found</td></tr></table>";
            $("#drives").html(html);
            return;
          }

          let html  = "<table><tr><th>Name</th><th>Size</th><th>Used</th><th>&nbsp;</th></tr>";

          for(let i=0; i < response.length; i++){
            html += "<tr><td>" + response[i].label + "</td>";
            html += "<td>" + response[i].size  + "</td>";
            html += "<td>" + response[i].used  + "</td>";
            html += "<td><button onclick=\"ejectDrive('" + response[i].mountPath + "');\">&#x23CF; Eject</button></td></tr>";
          }
          
          html += "</table>";
          $("#drives").html(html);
        },
        error: function(response) {
          console.log('Failed populating drives');
        },
// if we wanted regular polling
//        complete: function() {
//          setTimeout(populateDrives, refreshRate);
//        }

      });
    }
    
    function ejectDrive(mountPath) {
      $.ajax({
        type: 'POST',
        url:  'storage.php',
        data: { mountPath: mountPath },
        dataType: 'json',
        success: function(response) {
          alert('Eject successful');
          populateDrives();
        },
        error: function(response) {
          alert('Eject request failed');
        }
      });
    }
  </script>

<?php include "foot.php"; ?>

