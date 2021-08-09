<?php
#-------------------------------------------
# Language: English
# Use underscores in key names so they can be easily interpolated
# in strings (hyphenated keys do not interpolate in strings)
# ALSO: THIS FILE MUST NOT HAVE ANY BLANK LINES OUTSIDE OF
# THE PHP CODE - OR IT WILL BREAK HEADER CONTROL IN ADMIN.PHP
#-------------------------------------------
 
$lang = array();
 
# for index.php
$lang['langcode'] = 'en';
$lang['home'] = 'Home';
$lang['about'] = 'About';
$lang['server_address'] = 'Server Address';
$lang['main'] = 'Main';
$lang['admin'] = 'Admin';
$lang['stats'] = 'Stats';
$lang['version'] = 'Version';
$lang['settings'] = 'Settings';
$lang['months'] = array(
    'Null', 'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
);
$lang['languages'] = 'Languages';

$lang['no_mods_error'] = "<h2>No modules found.</h2>\n"
    . "Please check there are modules in the modules directory,\n"
    . "and that they are not all hidden on the admin page.\n";

# for admin.php
$lang['user'] = "Username";
$lang['pass'] = "Password";
$lang['next'] = "Next";
$lang['back'] = "Back";
$lang['cancel'] = "Cancel";
$lang['update'] = "Update";
$lang['found_in'] = "Found in";
$lang['hide'] = "hide";
$lang['hide_all'] = "hide all";
$lang['show_all'] = "show all";
$lang['save_changes'] = "Save Changes";
$lang['saved'] = "Saved";
$lang['not_saved_error'] = "Not Saved - Internal Error";
$lang['login'] = "Login";
$lang['logout'] = "Logout";
$lang['reset'] = "Reset";
$lang['sort'] = "sort";
$lang['modules'] = "Modules";
$lang['no_mods_found'] = "No modules found.";
$lang['hardware'] = "Hardware";
$lang['system_shutdown'] = "System Shutdown";
$lang['shutdown'] = "Shutdown";
$lang['confirm_shutdown'] = "Are you sure you want to shut down?";
$lang['restart'] = "Restart";
$lang['confirm_restart'] = "Are you sure you want to restart?";
$lang['shutdown_blurb'] = "<p>Shutting down here is safer for the SD/HD than simply unplugging the power.</p>\n"
    . "<p>If you shut down (as opposed to restart), you will need to unplug your system and plug it back in to restart.</p>\n";
$lang['shutdown_ok'] = "The server is shutting down now.";
$lang['restart_ok'] = "The server is restarting now.";
$lang['shutdown_failed'] = "Unable to shutdown server.";
$lang['restart_failed'] = "Unable to restart server.";
$lang['admin_instructions'] = "You can show and hide modules here, or change the order by dragging them. <br> Be sure to click \"Save Changes\" at the bottom of the page.";
$lang['rplus_safe_shutdown'] = "<h4>To safely shut down, just press the power button for one second and release.<br>The unit will power off after a few moments.</h4><p>You can also shut down or restart here:</p>";
$lang['unknown_system'] = "Unknown System";
$lang['shutdown_not_supported'] = "Shutdown not supported on this hardware.";
$lang['storage_usage'] = "Storage Usage";
$lang['location'] = "Location";
$lang['size'] = "Size";
$lang['used'] = "Used";
$lang['available'] = "Available";
$lang['percentage'] = "Percentage";
$lang['advanced'] = "Advanced";
$lang['install'] = "Install";
$lang['upload_btn'] = "Upload";
$lang['update_utility'] = "Update Utility";
$lang['upload']         = "Upload";

# for settings.php
$lang['change_password'] = "Change Password";
$lang['change_pass_success'] = "Password Successfully Changed";
$lang['old_password'] = "Current Password";
$lang['new_password'] = "New Password";
$lang['new_password2'] = "Repeat New";
$lang['wrong_old_pass'] = "Wrong current password";
$lang['missing_new_pass'] = "New password cannot be blank";
$lang['password_mismatch'] = "New passwords do not match";
$lang['module_upload'] = "Module Upload";
$lang['upload_req_1']  = "* The file must be a valid zip file";
$lang['upload_req_2']  = "* The zip must contain a single folder at it's root";
$lang['upload_req_3']  = "* The folder must have the same name as the zip";
$lang['upload_req_4']  = "* The folder must contain a rachel-index.php file";
$lang['upload_paused'] = "Your upload has been Paused. Upload the same file again to resume.";
$lang['upload_again']     = "Upload again";
$lang['upload_another']   = "Upload another file";
$lang['upload_error']     = "Upload Error";
$lang['upload_canceled'] = "Upload Canceled";
$lang['details']          = "Details";
$lang['pause']            = "Pause";
$lang['installed']        = "Installed";


# for captiveportal-redirect.php
$lang['welcome_to_rachel'] = "Welcome to RACHEL";
$lang['worlds_best_edu_cont'] = "The world's best educational content";
$lang['for_people_wo_int'] = "for people without internet";
$lang['click_here_to_start'] = "Click here to start";
$lang['brought_to_you_by'] = "Brought to you by";

# for hardware.php
$lang['wifi_control'] = "WIFI Control";
$lang['current_status'] = "Current Status";
$lang['turn_on']  = "Turn On";
$lang['turn_off'] = "Turn Off";
$lang['is_on']    = "On";
$lang['is_off']   = "Off";
$lang['wifi_warning'] = "WARNING: If you turn off WIFI while connected through WIFI, you will be disconnected.<br>WIFI will turn on again when the device is restarted.";
$lang['an_error_occurred'] = "An Error Occurred";

?>
