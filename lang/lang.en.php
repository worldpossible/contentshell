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
$lang['no_moddir_found'] = "No module directory found.";
$lang['shutdown_system'] = "Shutdown System";
$lang['confirm_shutdown'] = "Are you sure you want to shut down?";
$lang['restart_system'] = "Restart System";
$lang['confirm_restart'] = "Are you sure you want to restart?";
$lang['shutdown_blurb'] = "<p>Shutting down here is safer for the SD/HD than simply unplugging the power.</p>\n"
    . "<p>If you shut down (as opposed to restart), you will need to unplug your system and plug it back in to restart.</p>\n";
$lang['shutdown_ok'] = "The server is shutting down now.";
$lang['restart_ok'] = "The server is restarting down now.";
$lang['shutdown_failed'] = "Unable to shutdown server.";
$lang['restart_failed'] = "Unable to restart server.";
$lang['admin_instructions'] = "You can show and hide modules here, or change the order by dragging them. <br> Be sure to click \"Save Changes\" at the bottom of the page.";

# for captiveportal-redirect.php
$lang['welcome_to_rachel'] = "Welcome to RACHEL";
$lang['worlds_best_edu_cont'] = "The world's best educational content";
$lang['for_people_wo_int'] = "for people without internet";
$lang['click_here_to_start'] = "Click here to start";
$lang['brought_to_you_by'] = "Brought to you by";

?>
