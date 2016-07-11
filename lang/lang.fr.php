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
$lang['found_in'] = "Trouvé dans";
$lang['hide'] = "cacher";
$lang['save_changes'] = "Sauvegarder";
$lang['saved'] = "Sauvegardés";
$lang['not_saved_error'] = "Non enregistré - Erreur interne";
$lang['login'] = "Se connecter";
$lang['logout'] = "Se déconnecter";
$lang['no_moddir_found'] = "Aucun répertoire de module trouvé.";
$lang['shutdown_system'] = "Système d'arrêt";
$lang['confirm_shutdown'] = "Êtes-vous sûr de vouloir arrêter?";
$lang['restart_system'] = "Redémarrer le système";
$lang['confirm_restart'] = "Êtes-vous sûr de vouloir redémarrer?";
$lang['shutdown_blurb'] = "<p>Arrêt ici est plus sûr pour le SD / HD que de débrancher simplement le pouvoir.</p>\n"
    . "<p>Si vous arrêtez (par opposition à redémarrer), vous aurez besoin de débrancher votre système et rebranchez pour redémarrer.</p>\n";
$lang['shutdown_ok'] = "Le serveur est en cours d'arrêt maintenant.";
$lang['restart_ok'] = "Le serveur redémarre jusqu'à maintenant.";
$lang['shutdown_failed'] = "Impossible de serveur d'arrêt.";
$lang['restart_failed'] = "Impossible de redémarrer le serveur.";
$lang['admin_instructions'] = "Vous pouvez afficher, masquer modules ici, ou modifier l'ordre en les faisant glisser. <br> Assurez-vous de cliquer sur \"Sauvegarder\" au bas de la page.";

# for captiveportal-redirect.php
$lang['welcome_to_rachel'] = "Bienvenue à RACHEL";
$lang['worlds_best_edu_cont'] = "Meilleur contenu éducatif au monde";
$lang['for_people_wo_int'] = "pour les personnes sans internet";
$lang['click_here_to_start'] = "Cliquez ici pour commencer";
$lang['brought_to_you_by'] = "Présenté par";

?>
