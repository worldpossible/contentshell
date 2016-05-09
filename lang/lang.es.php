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
$lang['home'] = 'Portada';
$lang['about'] = 'Acerca';
$lang['server_address'] = 'Dirección del Servidor';
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
$lang['found_in'] = "Se encuentra en";
$lang['hide'] = "esconder";
$lang['save_changes'] = "Guardar Cambios";
$lang['saved'] = "Se Guardó";
$lang['not_saved_error'] = "No Guardado - Error interno";
$lang['logout'] = "cerrar sesión";
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
$lang['set_lang'] = "Elige Idioma de la Interfaz";
$lang['set_lang_blurb_1'] = "Esta configuración le permite anular la configuración de idioma del navegador para todos los usuarios. Por defecto RACHEL utilizará el idioma que el navegador está configurado para.";
$lang['set_lang_blurb_2'] = "NOTA: esto no cambia el idioma del contenido, sólo el texto del sistema, como la navegación superior y esta página.";
$lang['use_browser'] = "Ajuste el uso del navegador (por defecto)";
$lang['force'] = "Obligar";

?>
