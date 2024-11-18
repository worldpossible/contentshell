<?php
require_once("common.php");

# this script is allowed to run from the command line
# without further explicit authorization
$is_cli = php_sapi_name() == "cli";
if ($is_cli) {
    if (!(isset($argv[1]) && $argv[1] == "-p" && isset($argv[3]))) {
        echo "Usage: php $argv[0] -p oldpass newpass\n";
        exit(1);
    }
} else if (!authorized()) {
    exit();
}

$page_title = $lang['settings'];
$page_script = "";
$page_nav = "settings";

if (!$is_cli) {
    include "head.php";
}

# initialize values to avoid warnings
# we use 0 and 1 because it works in php and javascript
list($wrong_old_pass, $missing_new_pass, $password_mismatch) = array(0, 0, 0);
list($old_password, $new_password, $new_password2) = array("", "", "");
$focus_field = "old_password";
$show_success = 0;

# check if we're running from the command line

# if it's a POST we check our inputs and return errors or update
# -- we also support a command line option
if ($is_cli || $_SERVER['REQUEST_METHOD'] == 'POST') {

    if(isset($_POST['upload_file'])){
        return;
    }

    if ($is_cli) {
        $_POST['old_password'] = $argv[2];
        $_POST['new_password'] = $argv[3];
        $_POST['new_password2'] = $argv[3];
    }

    # these are the copies that will go into the
    # page if we show errors -- NOT the copies we check
    $old_password  = $_POST['old_password'];
    $new_password  = $_POST['new_password'];
    $new_password2 = $_POST['new_password2'];

    # we do this in reverse order because that works
    # best for determining which field to focus

    # did the new passwords match?
    if ($_POST['new_password'] != $_POST['new_password2']) {
        $password_mismatch = 1;
        $focus_field = "new_password2";
        $new_password2 = "";
    }

    # was there a new password at all?
    if (strlen($_POST['new_password']) < 1) {
        $missing_new_pass = 1;
        $focus_field = "new_password";
    }

    # did the old password match?
    $db = getdb();
    $db_oldpass = $db->escapeString(md5($_POST['old_password']));
    $validpass = $db->querySingle(
        "SELECT 1 FROM users WHERE username = 'admin' AND password = '$db_oldpass'"
    );
    if (!$validpass) {
        $wrong_old_pass = 1;
        $old_password = "";
        $focus_field = "old_password";
    }

    # if there were no errors, we can update the password
    if ($password_mismatch + $missing_new_pass + $wrong_old_pass == 0) {
        $db_newpass = $db->escapeString(md5($_POST['new_password']));
        $db->exec("UPDATE users SET password = '$db_newpass' WHERE username = 'admin'");
        list($old_password, $new_password, $new_password2) = array("", "", "");
        $show_success = 1;
    }

    # notify the results via CLI
    if ($is_cli) {
        if ($show_success) {
            echo "Password updated successfully.\n";
            exit;
        } else {
            echo "Password updated FAILED: ";
            if ($wrong_old_pass) {
                echo "Wrong old password.\n";
            } else {
                echo "Unknown Error. (db permissions?)\n";
            }
            exit(1);
        }
    }

}

?>

<h2><?php echo $lang['change_password'] ?></h2>

<?php if ($show_success) { echo "<h3 class='success'>$lang[change_pass_success]</h3>"; } ?>

<style>
    table { padding: 1px; }
    td { padding: 1px; }
    input[type="submit"] {
        margin-top: 10px;
        padding: 1px;
    }
    .error {
        border: 1px solid #a33;
        background: #fee;
        color: #a33;
        padding: 2px 5px;
    }
    .success {
        border: 1px solid #3a3;
        background: #efe;
        color: #3a3;
        padding: 10px;
    }
    h2 { border-bottom: 1px solid #ccc;
    }
    .none {
      display:none;
    }
    #progback {
      text-align: center;
      vertical-align: middle;
      line-height: 20px;
      min-width:300px;
      width:100%;
      color:black;
      background-color: #ccc;
    }
    #progbar {
      visibilty:visible;
      width: 0%;
      height: 20px;
      background-color: #737373 ;
      transition: width .4s ease-out;
    }
    .show_uploads{
      margin-top:14px;
    }
    .show_uploads th, td {
      text-align: left;
      padding: 5px;
    }
    .show_uploads table {
      padding:6px;
    }
    .upButton {
      padding: 2px;
    }
</style>

<script>
    $(function() {
        $("input[name=<?php echo $focus_field ?>]").focus();
    });

</script>

<form action="settings.php" method="post">
<table>
<tr>
  <td><?php echo $lang['old_password'] ?></td>
  <td><input name="old_password" type="password" value="<?php echo $old_password ?>"></td>
  <td><?php if ($wrong_old_pass) { echo "<div class='error'>$lang[wrong_old_pass]</div>"; } ?></td>
</tr>
<tr>
  <td><?php echo $lang['new_password'] ?></td>
  <td><input name="new_password" type="password" value="<?php echo $new_password ?>"></td>
  <td><?php if ($missing_new_pass) { echo "<div class='error'>$lang[missing_new_pass]</div>"; } ?></td>
</tr>
<tr>
  <td><?php echo $lang['new_password2'] ?></td>
  <td><input name="new_password2" type="password" value="<?php echo $new_password2 ?>"></td>
  <td><?php if ($password_mismatch) { echo "<div class='error'>$lang[password_mismatch]</div>"; } ?></td>
</tr>
<tr>
  <td colspan="2" align="right">
    <input type="submit" value="<?php echo $lang['save_changes'] ?>">
  </td>
  <td></td>
</tr>
</table>
</form>

<br>

<!-- Module upload Section -->
<h2><?php echo $lang['module_upload'] ?></h2>

<div id="upload_select">
  <form id="file_form" name="file_form" method="post">
    <table>
      <tr>
        <td><input id="upload_file" type="file" name="upload_file" accept=".zip"/></td>
        <td><input id="file_submit" type="submit" value="<?php echo $lang['upload'] ?>"/></td>
      </tr>
      <tr><td><br></td></tr>
      <tr><td></td></tr>
      <tr><td><?php echo $lang['upload_req_1'] ?></td></tr>      
      <tr><td><?php echo $lang['upload_req_2'] ?></td></tr>
      <tr><td><?php echo $lang['upload_req_3'] ?></td></tr>
      <tr><td><?php echo $lang['upload_req_4'] ?></td></tr>
    </table>
  </form>
</div>

<!-- Upload progress form -->
<div id="upload_progress" class="none">
  <table>
    <tr style="padding-bottom:1px;">
      <td><p id="progaction"></p></td>
      <td><p id="progspeed" style="table-layout:fixed;">starting...</p></td>
    </tr>
    <tr>
      <td colspan="2">
        <div id="progback">
          <div id="progbar">
            <div id="progpercent" style="color:white;">0%</div>
          </div>
        </div>
      </td>
    </tr>
    <tr>
      <td>
        <button class="upButton" id="details"><?php echo $lang['details'] ?></button>
        <button class="upButton" id="pause"><?php echo $lang['pause'] ?></button>
        <button class="upButton" id="cancel"><?php echo $lang['cancel'] ?></button>
      </td>
    </tr>
  </table>
  <div id="upload_details" style="background-color:#ccc;" class="none"></div>
</div>

<!-- Upload installation info -->
<div id="upload_install" class="none">
  <table>
    <tr>
      <td id="install_message"></td>
      <td id="install_spinner">
        <img src='../art/spinner.gif' style='width:20px; height=20px;'></img>
      </td>
    </tr>
    <tr>
      <td>
        <button id="showInstallDetails"style="margin-top: 10px; padding: 2px;"><?php echo $lang['details'] ?></button>
        <button id="cancelInstall" style="margin-top: 10px; padding: 2px;"><?php echo $lang['cancel'] ?></button>
      </td>
    </tr>
  </table>
  <div id="install_details" style="background-color:#ccc; display:none;">
    <table>
      <tr>
        <td>Last Status: </td>
        <td id="install_status"></td>
      </tr>
      <tr>
        <td>Last Info: </td>
        <td id="install_info"></td>
      </tr>
      <tr>
        <td>View Log: </td>
        <td><button id="openLog">Open Log</button></td>
      </tr>
    </table>
  </div>
</div>

<!-- Upload State Info ( Pause, Cancel, etc )-->
<div id="upload_info" class="none"></div>

<!-- Upload management table -->
<div class="show_uploads" id="show_uploads"></div>

<!-- script is required here -->
<script src="js/upload.js"></script>

<!-- End of module upload section -->

<?php



if (!$is_cli) {

    if ( false && is_rachelplus()) {
        $checked = run_rsyncd() ? " checked" : "";
        echo <<<EOT
<hr style='margin: 20px 0;'>
<script>
    function rsyncToggle() {
        if ( $("#rsyncd").prop('checked') ) {
            $.ajax({ url: "background.php?setRsyncDaemon=1" });
        } else {
            $.ajax({ url: "background.php?setRsyncDaemon=0" });
        }
    }
</script>

<h2>Run Rsync Daemon</h2>
<p style='background: #ddd; padding: 10px 5px; border-radius: 5px; font-size: small;'>
<input type='checkbox' id='rsyncd'$checked onchange='rsyncToggle();'> Run <tt>rsyncd</tt>, so you can clone from this device &mdash;
<strong>automatically turned off on reboot</strong>
</p>
EOT;

    }

    include "foot.php";

}

?>
