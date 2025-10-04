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
 * \file    admin/customerreturn.php
 * \ingroup customerreturns
 * \brief   CustomerReturn setup page
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) $res = @include __DIR__.'/../../../main.inc.php';
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once __DIR__.'/../lib/customerreturn.lib.php';
require_once __DIR__.'/../class/customerreturn.class.php';

$langs->loadLangs(array("admin", "errors", "customerreturn@customerreturn", "other"));

$action = GETPOST('action', 'aZ09');
$value = GETPOST('value', 'alpha');
$tab = GETPOST('tab', 'aZ09') ? GETPOST('tab', 'aZ09') : 'general';

if (!$user->admin) accessforbidden();

if ($action == 'updateMask') {
    $maskconst = GETPOST('maskconst', 'alpha');
    $maskvalue = GETPOST('maskvalue', 'alpha');
    if ($maskconst) dolibarr_set_const($db, $maskconst, $maskvalue, 'chaine', 0, '', $conf->entity);
    setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
}

if ($action == 'setmod') {
    dolibarr_set_const($db, "CUSTOMERRETURN_ADDON", $value, 'chaine', 0, '', $conf->entity);
    setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
}

if ($action == 'setdoc') {
    dolibarr_set_const($db, "CUSTOMERRETURN_ADDON_PDF", $value, 'chaine', 0, '', $conf->entity);
    setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
}

$title = $langs->trans('CustomerReturnsSetup');
llxHeader('', $title);
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($title, $linkback, 'title_setup');

$head = customerreturnAdminPrepareHead();
print dol_get_fiche_head($head, $tab, $title, -1, 'customerreturn@customerreturn');

$form = new Form($db);

if ($tab == 'general') {
    print load_fiche_titre($langs->trans("NumberingModules"), '', '');
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre"><td>'.$langs->trans("Name").'</td><td>'.$langs->trans("Description").'</td><td class="center" width="60">'.$langs->trans("Status").'</td></tr>';

    $dir = dol_buildpath('/custom/customerreturn/core/modules/customerreturn/');
    $handle = opendir($dir);
    if ($handle) {
        while (($file = readdir($handle)) !== false) {
            if (preg_match('/^mod_([a-zA-Z0-9_]+)\.php$/', $file, $reg)) {
                $modulename = 'mod_'.$reg[1];
                require_once $dir.$file;
                $module = new $modulename($db);
                print '<tr class="oddeven"><td>'.$module->name.'</td><td>'.$module->info().'</td>';
                print '<td class="center">';
                if (getDolGlobalString('CUSTOMERRETURN_ADDON') == $modulename) {
                    print img_picto($langs->trans("Activated"), 'switch_on');
                } else {
                    print '<a href="'.$_SERVER["PHP_SELF"].'?action=setmod&value='.$modulename.'">'.img_picto($langs->trans("Disabled"), 'switch_off').'</a>';
                }
                print '</td></tr>';
            }
        }
        closedir($handle);
    }
    print '</table><br>';

    print load_fiche_titre($langs->trans("DocumentModels"), '', '');
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre"><td>'.$langs->trans("Name").'</td><td>'.$langs->trans("Description").'</td><td class="center" width="60">'.$langs->trans("Status").'</td></tr>';

    $dir = dol_buildpath('/custom/customerreturn/core/modules/customerreturn/pdf/');
    $handle = opendir($dir);
    if ($handle) {
        while (($file = readdir($handle)) !== false) {
            if (preg_match('/^pdf_([a-zA-Z0-9_]+)\.php$/', $file, $reg)) {
                $modelname = $reg[1];
                require_once $dir.$file;
                $classname = 'pdf_'.$modelname;
                $module = new $classname($db);
                print '<tr class="oddeven"><td>'.$module->name.'</td><td>'.$module->description.'</td>';
                print '<td class="center">';
                if (getDolGlobalString('CUSTOMERRETURN_ADDON_PDF') == $modelname) {
                    print img_picto($langs->trans("Activated"), 'switch_on');
                } else {
                    print '<a href="'.$_SERVER["PHP_SELF"].'?action=setdoc&value='.$modelname.'">'.img_picto($langs->trans("Disabled"), 'switch_off').'</a>';
                }
                print '</td></tr>';
            }
        }
        closedir($handle);
    }
    print '</table>';
}

print dol_get_fiche_end();
llxFooter();
$db->close();