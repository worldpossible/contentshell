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
$lang['settings'] = 'Ajustes';
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
$lang['update'] = "Actualizar";
$lang['found_in'] = "Se encuentra en";
$lang['hide'] = "ocultar";
$lang['hide_all'] = "ocultar todo";
$lang['show_all'] = "mostrar todo";
$lang['save_changes'] = "Guardar Cambios";
$lang['saved'] = "Se Guardó";
$lang['not_saved_error'] = "No Guardado - Error interno";
$lang['login'] = "Acceder";
$lang['logout'] = "Salir";
$lang['reset'] = "Reiniciar";
$lang['sort'] = "ordenar";
$lang['modules'] = "Módulos";
$lang['no_mods_found'] = "No se encontraron módulos.";
$lang['hardware'] = "Hardware";
$lang['system_shutdown'] = "Apagado del Sistema";
$lang['shutdown'] = "Apagar";
$lang['confirm_shutdown'] = "¿Seguro que desea cerrar?";
$lang['restart'] = "Reiniciar";
$lang['confirm_restart'] = "¿Seguro que desea reiniciar?";
$lang['shutdown_blurb'] = "<p>El cierre de aquí es más seguro para el SD / HD que simplemente desconectar la fuente.</p>\n"
    . "<p>Si se cierra (en oposición a reiniciar), que tendrá que desconectar el sistema y vuelva a conectarlo para reiniciar.</p>\n";
$lang['shutdown_ok'] = "El servidor está cerrando ahora.";
$lang['restart_ok'] = "El servidor está reiniciando ahora.";
$lang['shutdown_failed'] = "No se puede cerrar el servidor.";
$lang['restart_failed'] = "No es posible reiniciar el servidor.";
$lang['admin_instructions'] = "Puede mostrar y ocultar módulos de aquí, o cambiar el orden arrastrándolos. <br> Asegúrese de hacer clic \"Guardar Cambios \" en la parte inferior de la página.";
$lang['rplus_safe_shutdown'] = "<h4>Para apagar con seguridad, sólo tiene que pulsar el botón de encendido durante un segundo y liberación.<br>La unidad se apagará después de unos momentos.</h4><p>También puede apagar o reiniciar aquí:</p>";
$lang['unknown_system'] = "Sistema desconocido";
$lang['shutdown_not_supported'] = "Cierre no compatible con este hardware.";
$lang['storage_usage'] = "Uso De Almacenamiento";
$lang['location'] = "Ubicación";
$lang['size'] = "Tamaño";
$lang['used'] = "Usado";
$lang['available'] = "Disponible";
$lang['percentage'] = "Porcentaje";
$lang['advanced'] = "Avanzado";
$lang['install'] = "Instalar";
$lang['update_utility'] = "Utilidad de actualización";
$lang['upload_btn'] = "Subir";

# for settings.php
$lang['change_password'] = "Cambia la contraseña";
$lang['change_pass_success'] = "Contraseña cambiada correctamente";
$lang['old_password'] = "Contraseña actual";
$lang['new_password'] = "Nueva contraseña";
$lang['new_password2'] = "Nuevo otra vez";
$lang['wrong_old_pass'] = "Contraseña actual incorrecto";
$lang['missing_new_pass'] = "La nueva contraseña no puede estar en blanco";
$lang['password_mismatch'] = "Las nuevas contraseñas no coinciden";
$lang['module_upload'] = "carga módulo";
$lang['upload_req_1']  = "* El archivo debe ser un archivo zip válido";
$lang['upload_req_2']  = "* El zip debe contener una sola carpeta en su raíz.";
$lang['upload_req_3']  = "* La carpeta debe tener el mismo nombre que el zip";
$lang['upload_req_4']  = "* La carpeta debe contener un archivo rachel-index.php";
$lang['upload_paused'] = "Se pausó la carga del archivo. Vuelva a carga el mismo archivo para reanudar.";
$lang['upload_again']    = "carga archivo de nuevo";
$lang['upload_another']  = "carga otro archivo";
$lang['upload_canceled'] = "carga cancelada";
$lang['upload_error']    = "Error al carga";
$lang['details']         = "Detalles";
$lang['pause']           = "Pausa";
$lang['installed']       = "instalado";
$lang['upload']          = "carga";


# for captiveportal-redirect.php
$lang['welcome_to_rachel'] = "Bienvenido a RACHEL";
$lang['worlds_best_edu_cont'] = "La mejor colección de materiales educativos del mundo";
$lang['for_people_wo_int'] = "cuando no haya acceso a Internet";
$lang['click_here_to_start'] = "Haz click aquí para comenzar";
$lang['brought_to_you_by'] = "Traído a usted por";

# for hardware.php
$lang['wifi_control'] = "Control de WIFI";
$lang['current_status'] = "Estado Actual";
$lang['turn_on']  = "Activar";
$lang['turn_off'] = "Desactivar";
$lang['is_on']    = "Activado";
$lang['is_off']   = "Desactivado";
$lang['wifi_warning'] = "ADVERTENCIA: Si apaga WIFI mientras está conectado a través de Wi-Fi, usted será desconectado.<br>WIFI se encenderá de nuevo cuando se reinicie el dispositivo.";
$lang['an_error_occurred'] = "Ocurrió Un Error";

?>
