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
 * \ingroup customerreturn
 * \brief   About page for CustomerReturn module.
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) $res = @include __DIR__.'/../../../main.inc.php';
if (!$res) die("Include of main fails");

require_once __DIR__.'/../lib/customerreturn.lib.php';

$langs->loadLangs(array("admin", "customerreturn@customerreturn"));

if (!$user->admin) accessforbidden();

$form = new Form($db);
$page_name = "CustomerReturnsSetup";
llxHeader('', $langs->trans($page_name));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

$head = customerreturnAdminPrepareHead();
print dol_get_fiche_head($head, 'about', $langs->trans($page_name), -1, "customerreturn@customerreturn");

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans("About").'</td></tr>';
print '<tr class="oddeven"><td>';
print '<h3>'.$langs->trans("Module468270Name").' v1.0.0</h3>';
print '<p>'.$langs->trans("Module468270Desc").'</p>';
print '<ul>';
print '<li><strong>'.$langs->trans("ReturnCreation").'</strong>: '.$langs->trans("FromShipmentOrManual").'</li>';
print '<li><strong>'.$langs->trans("StatusManagement").'</strong>: '.$langs->trans("Draft").' → '.$langs->trans("Validated").' → '.$langs->trans("Processed").'</li>';
print '<li><strong>'.$langs->trans("ProvisionalNumbering").'</strong>: '.$langs->trans("ProvisionalRefUntilValidation").'</li>';
print '<li><strong>'.$langs->trans("AutomaticLinking").'</strong>: '.$langs->trans("WithShipmentsOrdersInvoices").'</li>';
print '<li><strong>'.$langs->trans("StockManagement").'</strong>: '.$langs->trans("AutomaticStockUpdateOnProcess").'</li>';
print '<li><strong>'.$langs->trans("AutomaticCreditNote").'</strong>: '.$langs->trans("CreditNoteCreation").'</li>';
print '<li><strong>'.$langs->trans("Extrafields").'</strong>: '.$langs->trans("ExtrafieldsSupport").'</li>';
print '</ul>';

print '<h4>'.$langs->trans("StandardWorkflow").'</h4>';
print '<ol>';
print '<li><strong>'.$langs->trans("Creation").'</strong>: '.$langs->trans("ReturnInDraftStatus").'</li>';
print '<li><strong>'.$langs->trans("Validation").'</strong>: '.$langs->trans("DefinitiveRefGeneration").'</li>';
print '<li><strong>'.$langs->trans("Processing").'</strong>: '.$langs->trans("StockUpdateAndCreditNote").'</li>';
print '</ol>';

print '<h4>'.$langs->trans("Compatibility").'</h4>';
print '<ul>';
print '<li>Dolibarr 15.0+</li>';
print '<li>'.$langs->trans("RequiredModules").': Stock, Shipments, Orders</li>';
print '</ul>';

print '</td>';
print '</tr>';
print '</table>';
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
?>