<?php
/* Copyright (C) 2025 Nicolas Testori
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

/**
 * \file    admin/about.php
 * \ingroup supplierreturn
 * \brief   About page for SupplierReturn module.
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
    $i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
    $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
    $res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

// Libraries
require_once '../lib/supplierreturn.lib.php';

// Translations
$langs->loadLangs(array("admin", "supplierreturn@supplierreturn"));

// Access control
if (!$user->admin) {
    accessforbidden();
}

// Parameters
$backtopage = GETPOST('backtopage', 'alpha');

/*
 * View
 */

$form = new Form($db);

$help_url = '';
$page_name = "SupplierReturnSetup";

llxHeader('', $langs->trans($page_name), $help_url);

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Configuration header
$head = supplierreturnAdminPrepareHead();
print dol_get_fiche_head($head, 'about', $langs->trans($page_name), -1, "supplierreturn@supplierreturn");

// About page content
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("About").'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>';
print '<h3>Module Retours Fournisseurs v1.0.0</h3>';
print '<p>Ce module permet de gérer les retours de produits aux fournisseurs avec les fonctionnalités suivantes :</p>';
print '<ul>';
print '<li><strong>Création de retours</strong> : Depuis une réception (validée OU traitée) ou manuellement</li>';
print '<li><strong>Gestion des statuts</strong> : Brouillon → Validé → Traité</li>';
print '<li><strong>Numérotation provisoire</strong> : Référence (PROV) jusqu\'à validation</li>';
print '<li><strong>Liaisons automatiques</strong> : Avec réceptions, commandes et factures</li>';
print '<li><strong>Gestion du stock</strong> : Mise à jour automatique lors du traitement</li>';
print '<li><strong>Avoir automatique</strong> : Création d\'avoirs fournisseurs</li>';
print '<li><strong>Triggers</strong> : Intégration avec l\'agenda Dolibarr</li>';
print '<li><strong>Extrafields</strong> : Support des champs personnalisés</li>';
print '<li><strong>Réceptions étendues</strong> : Retours possibles depuis réceptions validées ET traitées</li>';
print '</ul>';

print '<h4>Workflow Standard</h4>';
print '<ol>';
print '<li><strong>Création</strong> : Retour en statut brouillon avec référence (PROV)</li>';
print '<li><strong>Validation</strong> : Génération de la référence définitive (ex: RF2506-0001)</li>';
print '<li><strong>Traitement</strong> : Mise à jour du stock et création optionnelle d\'avoir</li>';
print '</ol>';

print '<h4>Configuration</h4>';
print '<p>Le module peut être configuré via l\'onglet <strong>Configuration</strong> :</p>';
print '<ul>';
print '<li>Module de numérotation</li>';
print '<li>Validation automatique des avoirs</li>';
print '<li>Génération automatique de PDF</li>';
print '</ul>';

print '<h4>Compatibilité</h4>';
print '<ul>';
print '<li>Dolibarr 15.0+</li>';
print '<li>Modules requis : Stock, Fournisseurs</li>';
print '<li>Module optionnel : Réceptions</li>';
print '</ul>';

print '<h4>Support & Documentation</h4>';
print '<p>Pour toute question ou problème :</p>';
print '<ul>';
print '<li>Vérifiez les logs dans <code>dolibarr_documents/dolibarr.log</code></li>';
print '<li>Utilisez les outils de diagnostic dans le module</li>';
print '<li>Consultez le fichier <code>LIAISONS_CORRECTIONS.md</code></li>';
print '</ul>';

print '</td>';
print '</tr>';
print '</table>';
print '</div>';

// Page end
print dol_get_fiche_end();

llxFooter();
$db->close();
?>