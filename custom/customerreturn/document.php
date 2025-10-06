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
 * \file    document.php
 * \ingroup customerreturn
 * \brief   Documents tab for CustomerReturn
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
if (!$res) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

dol_include_once('/custom/customerreturn/lib/customerreturn.lib.php');
dol_include_once('/custom/customerreturn/class/customerreturn.class.php');
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';

$langs->loadLangs(array("customerreturn@customerreturn", "companies", "other"));

$action = GETPOST('action', 'aZ09');
$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');

if (!$user->hasRight('customerreturn', 'lire') && !$user->admin) accessforbidden();

$object = new CustomerReturn($db);
if ($id > 0 || !empty($ref)) {
    $object->fetch($id, $ref);
}

if ($object->id > 0) {
	$object->fetch_thirdparty();
	$upload_dir = $conf->customerreturn->dir_output.'/'.dol_sanitizeFileName($object->ref);
}

$permissiontoadd = $user->hasRight('customerreturn', 'creer');
$result = restrictedArea($user, 'customerreturn', $id, '');
$modulepart = 'customerreturn';

include DOL_DOCUMENT_ROOT.'/core/actions_linkedfiles.inc.php';

$title = $langs->trans("CustomerReturn").' - '.$langs->trans("Documents");
llxHeader('', $title);

$form = new Form($db);

if ($object->id > 0) {
    $head = customerreturn_prepare_head($object);
    print dol_get_fiche_head($head, 'document', $langs->trans("CustomerReturn"), 0, 'customerreturn@customerreturn');

    $filearray = dol_dir_list($upload_dir, "files", 0, '', '(\.meta|_preview.*\.png)$');
    $totalsize = 0;
    foreach ($filearray as $file) {
        $totalsize += $file['size'];
    }

    print '<div class="fichecenter"><div class="underbanner clearboth"></div>';
    print '<table class="border tableforfield centpercent">';
    print '<tr><td class="titlefield">'.$langs->trans("NbOfAttachedFiles").'</td><td colspan="3">'.count($filearray).'</td></tr>';
    print '<tr><td>'.$langs->trans("TotalSizeOfAttachedFiles").'</td><td colspan="3">'.dol_print_size($totalsize, 1, 1).'</td></tr>';
    print '</table><br></div>';

    $param = '&id='.$object->id;
    $relativepathwithnofile = dol_sanitizeFileName($object->ref) . '/';

    include DOL_DOCUMENT_ROOT.'/core/tpl/document_actions_post_headers.tpl.php';
} else {
    dol_print_error($db);
}

print dol_get_fiche_end();
llxFooter();
$db->close();
?>