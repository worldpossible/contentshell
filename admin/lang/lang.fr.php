<?php
#-------------------------------------------
# Language: French
# Use underscores in key names so they can be easily interpolated
# in strings (hyphenated keys do not interpolate in strings)
# ALSO: THIS FILE MUST NOT HAVE ANY BLANK LINES OUTSIDE OF
# THE PHP CODE - OR IT WILL BREAK HEADER CONTROL IN ADMIN.PHP
#-------------------------------------------
 
$lang = array();
 
# index.php
$lang['langcode'] = 'fr';
$lang['home'] = 'Accueil';
$lang['about'] = 'À propos';
$lang['server_address'] = 'Adresse du Serveur';
$lang['main'] = 'Principal';
$lang['admin'] = 'Administrateur';
$lang['stats'] = 'Stats';
$lang['version'] = 'Version';
$lang['settings'] = 'Paramètres';
$lang['months'] = array(
    'Null', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
    'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre',
);
$lang['languages'] = 'Langues';

$lang['no_mods_error'] = "<h2>Aucun module trouvé.</h2>\n"
    . "S'il vous plaît vérifier il existe des modules\n"
    . "dans le répertoire des modules, et qu'ils ne sont pas\n"
    . "tous caché sur la page d'administration.\n";

# for admin.php
$lang['user'] = "Nom d’utilisateur";
$lang['pass'] = "Mot de passe";
$lang['next'] = "Suivant";
$lang['back'] = "Retour";
$lang['cancel'] = "Annuler";
$lang['update'] = "Réactualiser";
$lang['found_in'] = "Trouvé dans";
$lang['hide'] = "cacher";
$lang['hide_all'] = "cacher tout";
$lang['show_all'] = "montre tout";
$lang['save_changes'] = "Sauvegarder";
$lang['saved'] = "Sauvegardés";
$lang['not_saved_error'] = "Non enregistré - Erreur interne";
$lang['login'] = "Se connecter";
$lang['logout'] = "Se déconnecter";
$lang['reset'] = "Réinitialiser";
$lang['sort'] = "sorte";
$lang['modules'] = "Modules";
$lang['no_mods_found'] = "Aucun module trouvé.";
$lang['hardware'] = "Matériel";
$lang['system_shutdown'] = "Arrêt du Système";
$lang['shutdown'] = "Arrêt";
$lang['confirm_shutdown'] = "Êtes-vous sûr de vouloir arrêter?";
$lang['restart'] = "Redémarrer";
$lang['confirm_restart'] = "Êtes-vous sûr de vouloir redémarrer?";
$lang['shutdown_blurb'] = "<p>Arrêt ici est plus sûr pour le SD / HD que de débrancher simplement le pouvoir.</p>\n"
    . "<p>Si vous arrêtez (par opposition à redémarrer), vous aurez besoin de débrancher votre système et rebranchez pour redémarrer.</p>\n";
$lang['shutdown_ok'] = "Le serveur s'arrête maintenant";
$lang['restart_ok'] = "Le serveur redémarre maintenant.";
$lang['shutdown_failed'] = "Impossible de serveur d'arrêt.";
$lang['restart_failed'] = "Impossible de redémarrer le serveur.";
$lang['admin_instructions'] = "Vous pouvez afficher et masquer modules ici, ou modifier l'ordre en les faisant glisser. <br> Assurez-vous de cliquer sur \"Sauvegarder\" au bas de la page.";
$lang['rplus_safe_shutdown'] = "<h4>Pour arrêter en toute sécurité, appuyez simplement sur le bouton d'alimentation pendant une seconde puis relâchez.<br>L'appareil sera mis hors tension après quelques instants.</h4><p>Vous pouvez également arrêter ou redémarrer ici:</p>";
$lang['unknown_system'] = "Système Inconnu";
$lang['shutdown_not_supported'] = "Shutdown pas pris en charge sur ce matériel.";
$lang['storage_usage'] = "Stockage Utilisation";
$lang['location'] = "Emplacement";
$lang['size'] = "Taille";
$lang['used'] = "Utilisé";
$lang['available'] = "Disponible";
$lang['percentage'] = "Pourcentage";
$lang['advanced'] = "Avancé";
$lang['install'] = "Installer";
$lang['update_utility'] = "Utilitaire de mise à jour";
$lang['upload_btn'] = "téléversement";


# for settings.php
$lang['change_password'] = "Changer le mot de passe";
$lang['change_pass_success'] = "Mot de passe changé avec succès";
$lang['old_password'] = "Mot de passe actuel";
$lang['new_password'] = "Nouveau mot de passe";
$lang['new_password2'] = "Répétez Nouveau";
$lang['wrong_old_pass'] = "Mauvais mot de passe actuel";
$lang['missing_new_pass'] = "Nouveau mot de passe ne peut pas être vide";
$lang['password_mismatch'] = "Nouveaux mots de passe ne correspondent pas";
$lang['module_upload'] = "téléversement un module";
$lang['upload_req_1']  = "* Le fichier doit être un fichier zip valide";
$lang['upload_req_2']  = "* Le zip doit contenir un seul dossier à sa racine";
$lang['upload_req_3']  = "* Le dossier doit avoir le même nom que le zip";
$lang['upload_req_4']  = "* Le dossier doit contenir un fichier rachel-index.php";
$lang['upload_paused'] = "Votre téléversement a été suspendu. Importez à nouveau le même fichier pour reprendre";
$lang['upload_again']    = "téléversement à nouveau";
$lang['upload_another']  = "téléversement un autre fichier";
$lang['upload_canceled'] = "téléversement annulé";
$lang['upload_error']    = "Erreur de téléversement";
$lang['details']         = "Des détails";
$lang['pause']           = "Pause";
$lang['installed']       = "Installé";
$lang['upload']          = "téléversement";




# for captiveportal-redirect.php
$lang['welcome_to_rachel'] = "Bienvenue à RACHEL";
$lang['worlds_best_edu_cont'] = "Meilleur contenu éducatif au monde";
$lang['for_people_wo_int'] = "pour les personnes sans internet";
$lang['click_here_to_start'] = "Cliquez ici pour commencer";
$lang['brought_to_you_by'] = "Présenté par";

# for hardware.php
$lang['wifi_control'] = "Contrôle WIFI";
$lang['current_status'] = "Statut Actuel";
$lang['turn_on']  = "Activer";
$lang['turn_off'] = "Désactiver";
$lang['is_on']    = "Activé";
$lang['is_off']   = "Désactivé";
$lang['wifi_warning'] = "ATTENTION: Si vous désactivez WIFI tout connecté via WIFI, vous serez déconnecté.<br>WIFI se rallume lorsque l'appareil est redémarré.";
$lang['an_error_occurred'] = "Une Erreur Est Survenue";

?>
