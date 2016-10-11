<?php
require_once("common.php");
if (!authorized()) { exit(); }
$page_title = $lang['settings'];
$page_script = "";
$page_nav = "settings";
include "head.php";

# initialize values to avoid warnings
# we use 0 and 1 because it works in php and javascript
list($wrong_old_pass, $missing_new_pass, $password_mismatch) = array(0, 0, 0);
list($old_password, $new_password, $new_password2) = array("", "", "");
$focus_field = "old_password";
$show_success = 0;

# if it's a POST we check our inputs and return errors or update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

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

}

?>

<h2><?php echo $lang['change_password'] ?></h2>

<?php if ($show_success) { echo "<h3 class='success'>$lang[change_pass_success]</h3>"; } ?>

<style>
    td { padding: 5px; }
    input[type="submit"] {
        margin-top: 10px;
        padding: 5px;
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

</body>
</html>
