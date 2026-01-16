<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Plugin strings
 *
 * @package    local_mentor_core
 * @subpackage entities
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     remi <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['pluginname'] = 'Local Mentor Core';

$string['errorunknownuser'] = 'L\'utilisateur (id: {$a}) n\'est actuellement plus enregistré';
$string['errorentityexist'] = 'Le nom \'{$a}\' est déjà utilisé par un autre espace';
$string['errorentityshortnameexist'] = 'Ce nom abrégé d\'espace dédié existe déjà';
$string['errorentitynameexist'] = 'Ce nom d\'espace dédié existe déjà';
$string['errorentityshortnameexistshort'] = 'Ce nom abrégé d\'espace dédié existe déjà';
$string['errorremovecourse'] = 'Error durant la suppression du cours';
$string['rolenotexisterror'] = 'Le role {$a} n\'exite pas';
$string['trainingupdatefailed'] = 'Une erreur s\'est produite. Merci d\'essayer à nouveau.';
$string['unauthorisedaction'] = 'Désolé, vous n\'avez actuellement pas les permissions requises pour effectuer ceci';
$string['backupnotcreated'] = 'Backup file not created';
$string['inexistantcategory'] = 'Inexistant category: {$a}';
$string['unauthorisedaction'] = 'Désolé, vous n\'avez actuellement pas les permissions requises pour effectuer ceci';
$string['maxloop'] = 'Limit of the number of loops reached!';
$string['gototrainingsheet'] = 'Accèder à la fiche formation';
$string['notamanager'] = 'Your are not a training manager';
$string['entitypresentationparamerror'] = 'La visibilité de la présentation de l\'espace n\'a pas pu être mise à jour';

$string['mentor_core:changetrainingstatus'] = 'Mettre à jour le status de la formation';
$string['mentor_core:changefullname'] = 'Mettre à jour le libellé de la formation';
$string['mentor_core:changethumbnail'] = 'Mettre à jour la vignette de la formation';
$string['mentor_core:changesessionthumbnail'] = 'Mettre à jour la vignette de la session';
$string['mentor_core:changeshortname'] = 'Mettre à jour le nom abrégé du cours';
$string['mentor_core:changecontent'] = 'Mettre à jour le contenu de la formation';
$string['mentor_core:sharetrainings'] = 'Partager des formations à d\'autres espaces';
$string['mentor_core:sharetrainingssubentities'] = 'Partager des formations à ses sous-espaces';
$string['mentor_core:changesessionfullname'] = 'Mettre à jour le libellé de la session';
$string['mentor_core:changesessionshortname'] = 'Mettre à jour le nom abrégé de la session';
$string['mentor_core:changesessionopentoexternal'] = 'Partager une session à d\'autres espaces';
$string['mentor_core:importusers'] = 'Import CSV et inscription d\'utilisateurs au cours';
$string['mentor_core:suspendusers'] = 'Suspendre des comptes utilisateur';
$string['mentor_core:duplicatesessionintotraining'] = 'Dupliquer une session en formation';
$string['mentor_core:changetraininggoal'] = 'Mettre à jour l\'objectifs de la formation';
$string['mentor_core:movetrainings'] = 'Déplacer des formations dans l\'espace et ses sous espaces';
$string['mentor_core:movetrainingsinotherentities'] = 'Déplacer des formations dans d\'autres espaces dédiés';
$string['mentor_core:movesessions'] = 'Déplacer des sessions dans l\'espace et ses sous espaces';
$string['mentor_core:movesessionsinotherentities'] = 'Déplacer des sessions dans d\'autres espaces dédiés';
$string['mentor_core:modifyentity'] = 'Donne la possibilité de modifier son entité dans sa page de profil';

$string['inpreparation'] = 'En préparation';
$string['inprogress'] = 'En cours';
$string['completed'] = 'Terminée';
$string['finished'] = 'Achevée';
$string['archived'] = 'Archivée';
$string['reported'] = 'Reportée';
$string['cancelled'] = 'Annulée';

$string['status'] = 'Statut';
$string['cancelsession'] = 'Annuler la session';
$string['deletesession'] = 'Supprimer la session';
$string['movesession'] = 'Déplacer la session';
$string['manageusers'] = 'Gérer les utilisateurs';
$string['gotosessionsheet'] = 'Accèder à la fiche session';

$string['enrolusers'] = 'Import et inscription d\'utilisateurs';
$string['continue_import'] = 'Poursuivre l\'import';
$string['import_and_enrol'] = 'Importer et inscrire';
$string['import_and_enrol_heading'] = 'Import et inscription d\'utilisateurs';
$string['import_modal_title'] = 'Notification des nouveaux utilisateurs';
$string['import_reactivate_modal_title'] = 'Notification aux utilisateurs créés ou modifiés';
$string['import_modal_content']
    = 'Attention, une notification mél sera envoyée à tous les utilisateurs inscrits à la session. Les utilisateurs ayant un compte créé ou réactivé recevront également une notification spécifique.

Cette opération peut prendre un certain temps selon le volume d\'inscription à traiter, vous recevrez une confirmation par mél dès que l\'opération sera terminée.
Voulez-vous continuer ?';
$string['import_reactivate_modal_content']
    = 'Attention, une notification mél sera envoyée à tous les utilisateurs ayant un compte créé ou réactivé ou dont le choix de l’entité de rattachement a été modifié.
Cette opération peut prendre un certain temps selon le volume à traiter, vous recevrez une confirmation par mél dès que l\'opération sera terminée.
Voulez-vous continuer ?';

$string['recommandedratio'] = '(ratio recommandé : {$a})';
$string['square'] = 'carré';

$string['selfenrolmentnotallowed'] = 'L\'auto-inscription ne vous est pas autorisée pour cette session.';
$string['selfenrolmentdisabled'] = 'L\'auto-inscription est désactivée';

// Import CSV.
$string['csv_line'] = 'Ligne CSV';
$string['preview_report'] = 'Rapport de prévisualisation';
$string['preview_table'] = 'Tableau de prévisualisation de l\'import CSV';
$string['identified_users'] = 'Nombre d\'utilisateurs identifiés : {$a}';
$string['account_creation_number'] = 'Nombre de création de comptes : {$a}';
$string['account_reactivation_number'] = 'Nombre de comptes réactivés : {$a}';
$string['errors_number'] = 'Nombre d\'erreurs : {$a}';
$string['warnings_number'] = 'Nombre d\'avertissements : {$a}';
$string['suspender_users_number'] = 'Nombre d\'utilisateurs désactivés : {$a}';
$string['errors_nbusers']
    = 'Attention, le nombre maximal de participants pour cette session va être dépassé de {$a}.';
$string['ignored_group_list'] = 'Liste des groupes ignorés : {$a}';
$string['import_succeeded'] = 'Import effectué.';
$string['import_inprogress'] = 'Import en cours.';
$string['importusersglobal'] = 'Importer des utilisateurs en lot';
$string['duplicatesessionintotraining'] = 'Duplication de la session en formation';
$string['duplicatesessionintotrainingtitle'] = '{$a} : duplication du contenu de la session en formation';
$string['mergeusers'] = 'Fusionner des utilisateurs';
$string['suspendusers'] = 'Suspendre des utilisateurs en lot';
$string['suspendusersbutton'] = 'Suspendre les utilisateurs';
$string['userssuspension'] = 'Suspension d\'utilisateurs';
$string['mergeusers_help'] = 'N\'utilisez ceci que si vous en comprenez les implications, car les opérations réalisées ici ne sont pas réversibles !';
$string['addtoentity'] = 'Choix de rattachement des utilisateurs à l\'espace dédié';
$string['addtoentity_help']
    = 'En fonction du choix sélectionné, tous les utilisateurs importés seront soit rattachés à l\'espace dédié où l\'import est effectué soit en tant qu\'entité principale (choix sélectionné par défaut) soit en tant qu\'entité secondaire soit rattaché à aucun espace. Ce choix de rattachement une fois l\'import effectué est visible au niveau du profil de chaque utilisateur.';
$string['addtomainentity'] = 'Rattachement des utilisateurs à l\'espace dédié en tant qu\'entité principale';
$string['addtosecondaryentity'] = 'Rattachement des utilisateurs à l\'espace dédié en tant qu\'entité secondaire';
$string['addtoanyentity'] = 'Aucun rattachement';
$string['usercreatandenrol'] = 'L\'utilisateur {$a} sera créé et inscrit.';
$string['externalusercreatandenrol'] = 'L\'utilisateur {$a} sera créé et inscrit en tant qu\'utilisateur externe.';
$string['importuserupdateexternal'] = 'L\'utilisateur {$a} sera mis à jour : externe.';
$string['importuserupdateexternal_already_main_entity'] = 'L\'utilisateur {$a->email} possède déjà une entité principale différente de l\'espace dédié sélectionné : {$a->espace}, il ne sera pas mis à jour dans Mentor.';

// Import errors.
$string['required'] = 'Vous devez remplir ce champ.';
$string['errormissingentityid'] = 'L\'identifiant de l\'entité est manquant';
$string['warning'] = 'Avertissement';
$string['error_encoding'] = 'Le fichier n\'est pas en UTF-8.';
$string['errors_report'] = 'Rapport d\'erreurs';
$string['error_line'] = 'Ligne {$a}';
$string['error_ignore_line'] = 'Cette ligne sera ignorée.';
$string['error_missing_field']
    = 'Au moins un champ parmi "Nom", "Prénom", "Email" dans le fichier CSV n\'est pas renseigné. Cette ligne sera ignorée à l\'import.';
$string['errors_detected'] = 'Des erreurs ont été détectées dans le fichier.';
$string['warnings_detected'] = 'Des avertissements ont été détectés dans le fichier.';
$string['error_specials_chars']
    = 'Des caractères spéciaux sont présents dans le fichier CSV. Cette ligne sera ignorée à l\'import.';
$string['error_too_many_lines'] = 'Le fichier fait plus de 5000 lignes.';
$string['error_user_already_main_entity']
    = 'L\'utilisateur possède déjà une entité principale différente de l\'espace dédié sélectionné il ne sera pas mis à jour dans Mentor.';
$string['error_user_already_secondary_entity']
    = 'L\'utilisateur est déjà rattaché à l\'espace dédié sélectionné en tant qu\'entité principale, il ne peut être rattaché en tant qu\'espace secondaire. L\'utilisateur ne sera pas mis à jour dans Mentor.';
$string['warning_user_main_entity_update']
    = 'Le choix de rattachement de l\'utilisateur sera mis à jour : mise à jour de l\'entité principale.';
$string['warning_user_secondary_entity_already_set']
    = 'Le choix de rattachement de l\'utilisateur sera mis à jour : mise à jour de l\'entité secondaire en entité principale.';
$string['warning_user_secondary_entity_update']
    = 'Le choix de rattachement de l\'utilisateur sera mis à jour : ajout d\'une entité secondaire.';
$string['warning_user_suspended'] = 'Le compte utilisateur est suspendu. Le compte sera réactivé.';
$string['user_already_exists'] = 'L\'utilisateur {$a} ne sera pas créé car il existe déjà dans Mentor.';
$string['email_already_used']
    = 'Deux comptes utilisant cette adresse mail ont été identifiés, merci d\'inscrire manuellement à la session le ou les participant(s) souhaité(s).';
$string['missing_data'] = 'Au moins un champ parmi "lastname", "firstname", "email" n\'est pas renseigné.
                                        Cette ligne sera ignorée à l\'import.';
$string['invalid_column_number'] = 'Le nombre de champs est invalide.';
$string['invalid_email'] = 'La colonne "email" ne contient pas d\'adresse mél. Cette ligne sera ignorée à l\'import.';
$string['email_not_valid'] = 'L\'adresse mél n\'est pas conforme. Cette ligne sera ignorée à l\'import.';
$string['invalid_groupname'] = 'Attention, le groupe {$a} n\'a pas été trouvé. Le groupe sera créé.';
$string['invalid_role']
    = 'Attention, le rôle {$a} n\'a pas été trouvé. Cette ligne sera ignorée à l\'import.';
$string['invalid_headers']
    = 'Les en-têtes du fichier sont incorrects. Les en-têtes attendus sont : "lastname", "firstname", "email", "role" et "group". Si vous avez les bons en-têtes, pensez à vérifier que vous avez sélectionné le bon séparateur.';
$string['missing_headers']
    = 'Les en-têtes du fichier sont incorrects. Les en-têtes attendus sont : "lastname", "firstname", "email", "role" et "group". Si vous avez les bons en-têtes, pensez à vérifier que vous avez sélectionné le bon séparateur.';
$string['missing_data']
    = 'Les en-têtes du fichier sont corrects mais celui-ci ne contient aucune donnée. Veuillez vérifier le fichier déposé.';

$string['enrolmessage'] = 'Confirmez-vous votre inscription à cette session ?';
$string['enrolmessagewithkey']
    = 'Pour confirmer votre inscription, merci de saisir la clé qui vous a été fournie. Dans le cas contraire, nous vous invitons à contacter les organisateurs.';

// Pagination.
$string['next'] = 'Suivant';
$string['previous'] = 'Précédent';

// Session form.
$string['all_user_current_entity'] = 'Les utilisateurs de l\'espace {$a} (rattachement principal ou secondaire ou par la région)';
$string['all_user_current_main_entity'] = 'Les utilisateurs de l\'espace {$a} (rattachement principal uniquement)';
$string['all_user_all_entity'] = 'Tous les utilisateurs de tous les espaces dédiés';
$string['all_user_current_entity_others'] = 'Les utilisateurs de l\'espace {$a} et des espaces dédiés suivants:';
$string['notvisibleincatalog'] = 'Non-visible dans l\'offre de formation';

$string['filter_all_user_current_entity'] = 'Rattachement principal ou secondaire';
$string['filter_all_user_current_main_entity'] = 'Rattachement principal uniquement';
$string['filter_all_user_all_entity'] = 'Ouverte à tous';
$string['filter_all_user_current_entity_others'] = 'Ouverte à d\'autres espaces dédiés';
$string['filter_notvisibleincatalog'] = 'Non-visible dans l\'offre de formation';

$string['sessionstartdate'] = 'Date de début de la session de formation';
$string['sessionenddate'] = 'Date de fin de la session de formation';
$string['opento'] = 'Ouverte à';
$string['maxparticipants'] = 'Nombre maximum de participants';
$string['errorenddate'] = 'Date de fin doit être supérieure à la date de début de session !';
$string['errorzero'] = 'Le nombre de participants ne peut pas être égal à zéro.';

$string['new_date_email'] = 'Bonjour,
Suite au report de la formation {$a->fullname}, nous vous informons que cette session est reprogrammée au {$a->startdate}.
Votre inscription pour cette formation est toujours valide.

En cas d’indisponibilité pour ces nouvelles dates, merci d’annuler votre inscription.

En cas d’annulation de votre part, n’hésitez pas à consulter les offres de formations car elles sont mises à jour régulièrement. Vous trouverez certainement des disponibilités pour les sessions à venir.

A très bientôt sur Mentor
Bien cordialement';
$string['newsessiondate'] = 'Nouvelle date pour votre formation';
$string['reported_session_email'] = 'Bonjour,
La session de formation {$a->fullname} qui devait démarrer le {$a->startdate} est reportée.

Votre inscription pour cette formation est toujours valide. Vous recevrez très prochainement de nouvelles dates de session.

A très bientôt sur Mentor
Bien cordialement';
$string['reported_session'] = 'Report de votre formation';
$string['cancelled_session'] = 'Annulation de votre formation';
$string['cancelled_session_email'] = 'Bonjour,
La session de formation {$a->fullname} qui devait démarrer le {$a->startdate} est annulée.
Nous en sommes désolés.

N’hésitez pas à consulter régulièrement les offres de formations car elles sont fréquemment mises à jour.

Vous trouverez certainement des disponibilités pour les sessions à venir.

A très bientôt sur Mentor
Bien cordialement';

$string['email_complete_session_object'] = 'Fin de la session {$a}';
$string['email_complete_session_content'] = 'Bonjour,

La session de formation {$a} est maintenant terminée. Si vous n\'avez pas suivi l\'ensemble des activités, il vous reste 30 jours pour le faire.
Ensuite cette session sera archivée automatiquement. Néanmoins, vous pourrez la retrouver sur votre page d’accueil dans la section "Vos sessions archivées" et les ressources resteront consultables.

A très bientôt sur Mentor
Bien cordialement';

$string['email_archive_session_object'] = 'Archivage de la session {$a}';
$string['email_archive_session_content'] = 'Bonjour,

La session de formation {$a} est maintenant archivée. Vous pouvez la retrouver sur votre page d’accueil dans la section "Vos sessions archivées" et les ressources restent consultables.

A très bientôt sur Mentor
Bien cordialement';

$string['enrolmentpopuptitle'] = 'Inscription à la session';
$string['trainings_trainer'] = 'Proposée par';
$string['trainings_producer'] = 'Produite par';
$string['thumbnail'] = 'Vignette';

// Training Sheet.
$string['backcatalog'] = 'Retour vers le catalogue';
$string['suggestedby'] = 'Proposée par {$a}';
$string['contact'] = 'Contact : {$a}';
$string['objectivesandcontent'] = 'Objectifs et contenu de la formation';
$string['objectives'] = 'Objectifs';
$string['content'] = 'Contenu';
$string['prerequisite'] = 'Pré-requis';
$string['skills'] = 'Compétences';
$string['typicaljob'] = 'Métier(s)';
$string['termsoflicense'] = 'Termes de la licence';
$string['sessionsoffered'] = 'Sessions proposées';
$string['sessionlisting']
    = 'Retrouvez ci-dessous l’ensemble des sessions dans lesquelles vous pouvez vous inscrire en fonction des places disponibles.';
$string['logoof'] = 'Logo de {$a}';
$string['teaserof'] = 'Teaser de {$a}';
$string['parententity'] = 'Espace dédié parent';
$string['copylink'] = 'Copier le lien';
$string['copy'] = 'Copier';
$string['copyurl'] = 'Copier l\'adresse de cette page';

// Sessions tile.
$string['permanentaccess'] = 'Accès permanent';
$string['ondate'] = 'Le {$a}';
$string['fromto'] = 'Du {$a->from} au {$a->to}';
$string['fromdate'] = 'A partir du {$a}';
$string['alreadyregistered'] = 'Déjà inscrit';
$string['placesavailable'] = '{$a} places disponibles';
$string['placeavailable'] = '{$a} place disponible';
$string['moredetails'] = 'Plus de détails';
$string['complete'] = 'Complet';
$string['deletethumbnail'] = 'Supprimer la vignette';
$string['currentthumbnail'] = 'Vignette actuelle';

// Session Sheet.
$string['backtraining'] = 'Retour vers la fiche formation';
$string['inprogress'] = 'En cours';
$string['modality'] = 'Modalité : {$a}';
$string['access'] = 'Accéder';
$string['registration'] = 'Inscription';
$string['onlineduration'] = 'Durée en ligne {$a}';
$string['presenceduration'] = 'Durée en présence {$a}';
$string['targetaudience'] = 'Public cible';
$string['coaching'] = 'Accompagnement';
$string['locationsession'] = 'Lieu(x) du déroulement de la session';
$string['online'] = 'En ligne';
$string['presentiel'] = 'Présentiel';
$string['mixte'] = 'Présentiel et en ligne';
$string['inpreparation'] = 'En préparation';
$string['openedregistration'] = 'Inscriptions ouvertes';
$string['enrolmentpopuptitle'] = 'Inscription à la session';
$string['certifying'] = 'Certifiante';
$string['thumbnail'] = 'Vignette';
$string['next'] = 'Suivant';
$string['previous'] = 'Précédent';

// Event.

$string['evententitycreated'] = 'Entity created';
$string['evententityupdated'] = 'Entity updated';
$string['eventtrainingcreated'] = 'Training created';
$string['eventtrainingupdated'] = 'Training updated';
$string['eventsessioncreated'] = 'Session created';
$string['eventsessionupdated'] = 'Session updated';

// Ad-hoc.

$string['duplicate_training_object_email'] = '[Mentor] Confirmation de duplication {$a}';
$string['duplicate_training_email'] = 'Bonjour,

Votre demande de duplication a été réalisée avec succès. Vous pouvez dès à présent la consulter <a href="{$a->newtrainingurlsheet}">ici</a>

Attention, les liens de ce message ne sont valides que si vous avez les droits sur les espaces dédiés concernés.

Formation dupliquée :
• {$a->newtrainingfullname} - {$a->newtrainingshortname}
• URL : <a href="{$a->newtrainingurlsheet}">{$a->newtrainingurlsheet}</a>
<br><br>
Formation d’origine :
• {$a->oldtrainingfullname} - {$a->oldtrainingshortname}
• URL : <a href="{$a->oldtrainingurlsheet}">{$a->oldtrainingurlsheet}</a>';

$string['duplicate_training_not_capability_email'] = 'Bonjour,

Votre demande de duplication de la formation {$a->nameold} vient d\'être effectué.

Vous n\'avez pas accès à cette formation, car il n\'est pas dans un espace dédié où vous disposez de ces droits.

À très bientôt sur Mentor
Bien cordialement';
$string['create_session_object_email'] = ' [Mentor] Confirmation de création d’une nouvelle session {$a}';
$string['create_session_email'] = 'Bonjour,

Votre demande de création de session a été réalisée avec succès. Vous pouvez dès à présent la consulter <a href="{$a->sessionurlsheet}">ici</a>.

Session créée :
• {$a->sessionfullname} - {$a->sessionshortname}
• URL : <a href="{$a->sessionurlsheet}">{$a->sessionurlsheet}</a>
<br><br>
Formation d’origine :
• {$a->trainingfullname} - {$a->trainingshortname}
• URL : <a href="{$a->trainingurlsheet}">{$a->trainingurlsheet}</a>';

$string['duplicate_session_new_training_object_email'] = '[Mentor] Confirmation de duplication en formation de la session {$a}';
$string['duplicate_session_new_training_email'] = 'Bonjour,

Votre demande de duplication d\'une session en formation a été réalisée avec succès. Vous pouvez dès à présent la consulter <a href="{$a->trainingurlsheet}">ici</a>.

Nouvelle formation :
• {$a->trainingfullname}
• URL : <a href="{$a->trainingurlsheet}">{$a->trainingurlsheet}</a>
<br><br>
Session d’origine :
• {$a->sessionfullname}
• URL : <a href="{$a->sessionurlsheet}">{$a->sessionurlsheet}</a>';

$string['duplicate_session_into_training_email'] = 'Bonjour,

Votre demande de duplication d\'une session en formation a été réalisée avec succès. Vous pouvez dès à présent la consulter <a href="{$a->trainingurlsheet}">ici</a>.

Formation remplacée :
• {$a->trainingfullname}
• URL : <a href="{$a->trainingurlsheet}">{$a->trainingurlsheet}</a>
<br><br>
Session d’origine :
• {$a->sessionfullname}
• URL : <a href="{$a->sessionurlsheet}">{$a->sessionurlsheet}</a>';

$string['fieldtoolong'] = 'La taille maximale de champ est limitée à {$a} caractères.';
$string['courseshortnameused'] = 'Ce nom abrégé de session existe déjà.';

$string['spacename'] = 'Nom de l\'espace dédié';
$string['space'] = 'Espace dédié';
$string['subspace'] = 'Sous-espace dédié';
$string['newsubspacename'] = 'Nom du sous-espace';
$string['subspacename'] = 'Nom du sous-espace';
$string['responsiblename'] = 'Administrateur';

$string['subentity'] = 'Sous-espace';
$string['subentityaddmember'] = 'Un sous-espace ne peut pas ajouter des membres';

$string['requiredfields'] = 'Les champs marqués d\'un * sont obligatoires';
$string['entitynamelimit'] = 'Le nom de l\'espace ne doit pas dépasser {$a} caractères';
$string['nosubentitytodeletmessage'] = 'Aucun sous-espace n\'est éligible à la suppression (existence d\'au moins une formation ou une session)';

$string['entityshortnamelimit'] = 'Le nom abrégé de l\'espace ne doit pas dépasser {$a} caractères';

$string['erroremailused'] = 'Cette adresse est déjà enregistrée.';
$string['erroreother'] = 'Un problème est survenu';

$string['none'] = 'Aucune';
$string['copylinktext'] = 'Le lien a été copié dans le presse-papier';
$string['copylinkerror'] = 'Impossible de copier le lien dans le presse-papier. Veuillez copier le lien suivant :';
$string['notpermissionscourse'] = 'Désolé, vous n\'avez actuellement pas les permissions requises pour afficher cette formation.';
$string['trainingnotavailable'] = 'Formation non disponible';
$string['choose'] = "Choisir";
$string['invalidemail'] = "Adresse mail incorrecte";
$string['newrole']
    = 'Attention, l\'utilisateur est déjà inscrit en tant que {$a->oldroles}. Il sera maintenant inscrit en tant que {$a->newrole}.';
$string['loseprivilege']
    = 'L\'utilisateur est inscrit en tant que Formateur. Afin que l\'utilisateur ne perde pas son rôle de formateur, cette ligne sera ignorée à l\'import.';

$string['trainingname'] = 'Intitulé de la formation';
$string['sessionname'] = 'Intitulé de la session';

$string['alertrestored'] = '\'{$a->name}\' a été restaurée.';
$string['alertdeleted'] = '\'{$a->name}\'  a été supprimée.';

$string['task_cleanup_trainings_and_sessions'] = 'Nettoyage des formations et sessions orphelines';
$string['task_update_users_course_completion'] = 'Mise à jour du pourcentage de complétion des cours';

$string['close'] = 'Fermer';

// Duplicate session.
$string['chooseduplicationtype'] = 'Choix du type de duplication';
$string['createnewtraining'] = 'Créer une nouvelle formation';
$string['erasetraining'] = 'Écraser la formation d\'origine';
$string['trainingfullname'] = 'Libellé de la formation';
$string['trainingshortname'] = 'Le nom abrégé de la formation';
$string['destinationentity'] = 'L\'espace dédié cible';
$string['destinationsubentity'] = 'Le sous-espace dédié cible';
$string['confirmduplication']
    = 'Votre demande de duplication du contenu d\'une session en formation a bien été prise en compte. Vous recevrez une notification par mél dès que l\'opération sera terminée.';
$string['emptyfield'] = 'Ce champ ne peut pas être vide.';
$string['trainingshortnameexists'] = 'Le nom abrégé de la formation existe déjà.';
$string['confirmation'] = 'Confirmation';
$string['confirmationwarnining']
    = 'Attention, cette opération va écraser le contenu de la formation d\'origine et est irréversible.';

$string['errorcategoryisnotentity'] = '{$a->name} (id: {$a->id}) is not entity';

$string['notaccess']
    = 'Désolé, cette page n\'existe pas ou n\'est pas disponible avec vos permissions actuelles.';
$string['notaccesstitle']
    = 'Page non disponible';

$string['inscriptionlibre'] = 'Inscription libre';
$string['autre'] = 'Autre';

$string['sessionupdatefailed'] = 'Erreur durant la mise à jour de la session.';

$string['email'] = 'Email';
$string['never'] = 'Jamais';

// Training enrolment.
$string['librarynotaccessible'] = 'The user does not have access to the library entity';
$string['trainingnotinthelibrary'] = 'The training must be in the library entity';

$string['lastname'] = 'Nom';
$string['actionmenu'] = 'Menu d\'actions';

$string['more'] = 'Plus';


/***************CSV*****************************/

$string['information'] = 'Information';
$string["result"] = "Résultats";
$string["email_send_report_object_users"] = '[Mentor] Confirmation d\'import CSV dans un espace dédié';
$string["email_send_report_content_users"] = 'Bonjour, Votre demande d\'import CSV dans votre espace dédié est terminée. Les utilisateurs ont bien été notifiés. Vous pouvez retrouver le détail en pièce jointe.';
$string["email_send_report_object_session"] = '[Mentor] Confirmation d\'import CSV dans une session';
$string["email_send_report_content_session"] = 'Bonjour, Votre demande d\'import CSV dans la session {$a} est terminée. Les utilisateurs ont bien été notifiés.  Vous pouvez retrouver le détail en pièce jointe.';
$string["not_processed"] = "non traité";

$string['reactivated'] = "réactivé";
$string['alreadyexists'] = 'déjà existant';
$string['created'] = 'créé';

$string['reactivatedenrolled'] = 'réactivé et inscrit';
$string['createdenrolled'] = 'créé et inscrit';
$string['enrolled'] = 'inscrit';

$string['usercreate'] = 'L\'utilisateur {$a} sera créé.';

$string['info_data_found_in_table'] = 'INFO ==> Des données dans la table \'{$a}\' ont été trouvé.';
$string['info_start_processing'] = 'INFO --> Début du traitement pour l\'id {$a->id} et la colonne \'{$a->column}\'';
$string['warn_already_compliant'] = 'WARN La donnée [\'id\': {$a->id}, \'{$a->column}\': \'{$a->oldlink}\'] est déjà conforme, ou ne peux pas être mise à jour';
$string['warn_no_course_section'] = 'WARN Aucune section de cours n\'a été trouvé pour les données [\'course\': {$a->courseid}, \'section\': {$a->section}]';
$string['ok_data_update'] = 'OK La donnée \'{$a->oldlink}\' a été mise à jour : \'{$a->newlink}\'';
