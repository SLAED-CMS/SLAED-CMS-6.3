<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

define('_MASTER_RELEASES', 'Versions');
define('_MASTER_SITES', 'Sites');
define('_MASTER_NOTES', 'Annonces');
define('_MASTER_KEYS', 'Clés');
define('_MASTER_INBOX', 'En attente dans inbox/');
define('_MASTER_ACCEPT', 'Accepter');
define('_MASTER_ACCEPTED', 'Accepté');
define('_MASTER_CHANGED', 'Modifié');
define('_MASTER_EXPIRES', 'Le manifeste expire');
define('_MASTER_EXPWARN', 'Le manifeste de %1$s expire le %2$s : signez-le à nouveau avec php tools/sign.php --refresh %1$s, publiez-le et acceptez-le de nouveau.');
define('_MASTER_WAIT', 'En attente');
define('_MASTER_BYOWNER', 'posée par le propriétaire');
define('_MASTER_BYAUTO', 'automatique : %d échecs, %d réussites');
define('_MASTER_TOBETA', 'Vers beta');
define('_MASTER_TOSTABLE', 'Vers stable');
define('_MASTER_SHARE', 'Part du déploiement');
define('_MASTER_SHAREDESC', 'La part des sites auxquels la version %s est proposée, de 0 à 100. Un site y entre selon son identifiant et la version, chaque version a donc ses propres premiers sites.');
define('_MASTER_DONE', 'Accepté : %s');
define('_MASTER_REFUSED', 'Refusé : %s');
define('_MASTER_RSIGN', 'la signature ne correspond pas à la clé en vigueur');
define('_MASTER_REXP', 'le manifeste a expiré');
define('_MASTER_RZIP', 'une archive diffère de sa taille signée ou de son SHA-256');
define('_MASTER_RMISS', 'les fichiers manquent');
define('_MASTER_RWRITE', 'les fichiers n\'ont pas pu être écrits');
define('_MASTER_RFORM', 'un manifeste est incomplet ou d\'un autre format, d\'une autre version ou d\'un autre canal');
define('_MASTER_SID', 'Identifiant');
define('_MASTER_DOMAIN', 'Domaine');
define('_MASTER_NOHOST', 'non communiqué');
define('_MASTER_CHAN', 'Canal');
define('_MASTER_EXT', 'Extensions');
define('_MASTER_ERRS', 'Erreurs PHP / BD');
define('_MASTER_FIRST', 'Première vérification');
define('_MASTER_LAST', 'Dernière vérification');
define('_MASTER_SILENT', 'Silencieux depuis plus de %d jours');
define('_MASTER_FAILS', 'Installations échouées');
define('_MASTER_STEP', 'étape %d');
define('_MASTER_STEPS', 'Étape');
define('_MASTER_COUNT', 'Sites');
define('_MASTER_NOSOD', 'Sans sodium');
define('_MASTER_NOZIP', 'Sans zip');
define('_MASTER_LEVEL', 'Niveau');
define('_MASTER_TO', 'Destinataire');
define('_MASTER_TOALL', 'tous les sites');
define('_MASTER_RANGE', 'Versions');
define('_MASTER_UNTIL', 'Jusqu\'au');
define('_MASTER_NOEND', 'sans échéance');
define('_MASTER_PRIMARY', 'Clé principale');
define('_MASTER_RECOVERY', 'Clé de récupération');
define('_MASTER_FACTORY', 'd\'usine');
define('_MASTER_INFORCE', 'en vigueur');
define('_MASTER_PRINT', 'Empreinte');
define('_MASTER_KEYERR', 'Une déclaration de clés dans inbox/keys/ échoue à la vérification (%s) ; les clés en vigueur sont celles d\'avant.');
define('_MASTER_KEEPSITE', 'Conserver les sites silencieux, jours');
define('_MASTER_KEEPSTAT', 'Conserver les totaux journaliers, jours');
define('_MASTER_LIMIT', 'Seuil de la pause automatique (part des installations échouées en pour cent)');
define('_MASTER_JOBS', 'L\'enregistrement ajoute une fois au planificateur les tâches masterclean (totaux journaliers et nettoyage) et masterwatch (pause automatique, toutes les heures).');
