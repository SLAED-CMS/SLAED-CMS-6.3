<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

define('_ADMIN_SE','Installation de l\'administrateur');
define('_CONFIG','Configuration de base');
define('_CONF_1','Serveur de base de données');
define('_CONF_2','Le nom d\'utilisateur de base de données');
define('_CONF_3','Le mot de passe utilisateur de la base de données');
define('_CONF_4','Nom de la base de données');
define('_CONF_9','Préfixe des tables de la base de données');
define('_CONF_10','Le nom de fichier de l\'entrée dans le département de l\'administration');
define('_CONF_10_INFO','À des fins de sécurité modifiez le nom par défaut de fichier admin.php. Renommez-le à ce que Vous arriver à, par exemple: %1$s.php et, indiquez son nouveau titre, mais sans fin «.php» dans notre cas: %1$s');
define('_CONF_11','Code de config/setup.unlock');
define('_CONF_3_INFO','Laissez le champ vide pour conserver le mot de passe actuel du site.');
define('_LANG','Sélectionnez la langue');
define('_NEXT_SE','Continuer');
define('_EXTSETUP','Une extension PHP requise n\'est pas installée sur votre serveur');
define('_PHPSETUP','La version de PHP installée sur Votre serveur ne répond pas aux exigences minimales du système, il devrait être en dessous de PHP 8.4!');
define('_SAVE_NEW','Installation et configuration');
define('_SAVE_UPDATE','La mise à jour et de configuration');
define('_SERRORPERM','n\'a pas les autorisations appropriées pour l\'enregistrement sur le serveur.<br>, Téléchargez les attributs souhaités');
define('_SETUPLOCK','Le site est déjà installé, l\'installateur est donc verrouillé. Pour lancer une mise à jour, déposez le fichier config/setup.unlock contenant un code de votre choix d\'au moins 8 caractères, rouvrez setup.php et saisissez ce code ; une exécution réussie supprime le fichier.');
define('_SETUPCODE','Le code ne correspond pas à celui de config/setup.unlock. Rien n\'a été modifié.');
define('_SETUPJOUR','Une opération de configuration inachevée du site attend dans storage/backup/config. Terminez-la dans l\'écran de restauration de la configuration du panneau d\'administration, puis relancez l\'installateur. Rien n\'a été modifié.');
define('_SETUPAFILE','Le nom du fichier du panneau d\'administration ne peut contenir que des lettres latines, des chiffres, _ et -, et ne peut nommer aucun autre fichier de la racine du site que admin.php ou le panneau actuel ; supprimez d\'abord le fichier du panneau de l\'ancienne version. Rien n\'a été modifié.');
define('_SETUPPREFIX','Le préfixe des tables ne peut contenir que des lettres latines, des chiffres et _, 32 caractères au plus. Rien n\'a été modifié.');
define('_SETUPINNODB','Ces tables ne sont pas en InnoDB, convertissez-les et relancez la mise à jour :');
define('_SETUPTABLES','Les tables %1$s_users et %1$s_admins ne sont pas toutes deux dans la base, vérifiez le préfixe des tables du site.');
define('_SETUPTAKEN','La base contient déjà des tables du préfixe %s_, une nouvelle installation exige une base vide ou un autre préfixe.');
define('_SETUPTYPE','Choisissez une nouvelle installation ou l\'une des mises à jour proposées. Rien n\'a été modifié.');
define('_SETUPVER','Le serveur de base de données %1$s est antérieur à %2$s.');
define('_SETUP_NEW','Nouvelle installation du système');
define('_SETUP_SLAED','Installation de la CMS SLAED');
define('_SUPDATE','Mise à jour du système');
define('_TABLE','The table');
