<?php
#-------------------------------------------
# Language: Spanish
# Use underscores in key names so they can be easily interpolated
# in strings (hyphenated keys do not interpolate in strings)
# ALSO: THIS FILE MUST NOT HAVE ANY BLANK LINES OUTSIDE OF
# THE PHP CODE - OR IT WILL BREAK HEADER CONTROL IN ADMIN.PHP
#-------------------------------------------
 
$lang = array();
 
# for index.php
$lang['langcode'] = 'es';
$lang['home'] = 'Portada';
$lang['about'] = 'Acerca';
$lang['server_address'] = 'Dirección del Servidor';
$lang['main'] = 'Principal';
$lang['admin'] = 'Administración';
$lang['stats'] = 'Estadísticas';
$lang['version'] = 'Versión';
$lang['months'] = array(
    'Null', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
);
$lang['languages'] = 'Idiomas';

$lang['no_mods_error'] = "<h2>No se encontraron módulos.</h2>\n"
    . "Por favor, compruebe que no son módulos en el directorio de módulos,\n"
    . "y que no se esconden en la página de administración.\n";

# for admin.php
$lang['user'] = "Usuario";
$lang['pass'] = "Contraseña";
$lang['next'] = "Siguiente";
$lang['back'] = "Anterior";
$lang['cancel'] = "Cancelar";
$lang['found_in'] = "Se encuentra en";
$lang['hide'] = "esconder";
$lang['save_changes'] = "Guardar Cambios";
$lang['saved'] = "Se Guardó";
$lang['not_saved_error'] = "No Guardado - Error interno";
$lang['login'] = "Acceder";
$lang['logout'] = "Salir";
$lang['no_moddir_found'] = "No se encontró directorio de módulos.";
$lang['shutdown_system'] = "Apagar el Sistema";
$lang['confirm_shutdown'] = "¿Seguro que desea cerrar?";
$lang['restart_system'] = "Reiniciar el Sistema";
$lang['confirm_restart'] = "¿Seguro que desea reiniciar?";
$lang['shutdown_blurb'] = "<p>El cierre de aquí es más seguro para el SD / HD que simplemente desconectar la fuente.</p>\n"
    . "<p>Si se cierra (en oposición a reiniciar), que tendrá que desconectar el sistema y vuelva a conectarlo para reiniciar.</p>\n";
$lang['shutdown_ok'] = "El servidor está cerrando ahora.";
$lang['restart_ok'] = "El servidor está reiniciando ahora.";
$lang['shutdown_failed'] = "No se puede cerrar el servidor.";
$lang['restart_failed'] = "No es posible reiniciar el servidor.";
$lang['admin_instructions'] = "Puede mostrar, ocultar módulos de aquí, o cambiar el orden arrastrándolos. <br> Asegúrese de hacer clic \"Guardar Cambios \" en la parte inferior de la página.";

# for captiveportal-redirect.php
$lang['welcome_to_rachel'] = "Bienvenido a RACHEL";
$lang['worlds_best_edu_cont'] = "La mejor colección de materiales educativos del mundo";
$lang['for_people_wo_int'] = "cuando no haya acceso a Internet";
$lang['click_here_to_start'] = "Haz click aquí para comenzar";
$lang['brought_to_you_by'] = "Traído a usted por";

?>
