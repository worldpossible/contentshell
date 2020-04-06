<?php
  // Script: upload.php     | Rev: 1.01
  // Author: Steve bashford | Email: Steve@WorldPossible.org

  if (!empty($_FILES['upload_file'])) {
     $path = "/.data/RACHEL/rachel/admin/";
     $path = $path . basename( $_FILES['upload_file']['name']);

     $filename = "Upd_Rescue.zip";

     if ($filename != $_FILES['upload_file']['name']) {
        echo "<br><br>Only Rach Plus update files allowed (Upd_Rescue.zip)";
        echo '<br><br><a href="settings.php">Return to settings tab</a>';
        exit();
     }

     if (move_uploaded_file($_FILES['upload_file']['tmp_name'], $path)) {
        echo "<br><br>Upload successful: " . basename( $_FILES['upload_file']['name']);
        echo "<br><br>Do not reset device while update is in progress";
        echo '<br><br>Check current status here: <a href="log.php">Review update log</a>';
        echo '<br><br><a href="settings.php">Return to settings tab</a>';
        shell_exec("/.data/RACHEL/rachel/admin/Rach_Rescue.sh");
     }

     else {
        echo "<br><br>Unexpected upload event encountered";
        echo '<br><br><a href="settings.php">Return to settings tab</a>';
     }
  }
?>
