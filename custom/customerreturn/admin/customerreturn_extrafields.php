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
 * \file    admin/customerreturn_extrafields.php
 * \ingroup customerreturns
 * \brief   CustomerReturn extrafields setup page
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) $res = @include __DIR__.'/../../../main.inc.php';
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once __DIR__.'/../lib/customerreturn.lib.php';

$langs->loadLangs(array("admin", "other", "customerreturn@customerreturn"));

$action = GETPOST('action', 'aZ09');
$attrname = GETPOST('attrname', 'alpha');
$elementtype = 'customerreturn'; // Must match the table
$label = GETPOST('label', 'alpha');
$type = GETPOST('type', 'alpha');
$size = GETPOST('size', 'int');
$pos = GETPOST('pos', 'int');
$param = GETPOST('param', 'alpha');

if (!$user->admin) accessforbidden();

if (empty($extrafields)) {
    require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
    $extrafields = new ExtraFields($db);
}

require DOL_DOCUMENT_ROOT.'/core/actions_extrafields.inc.php';

$title = $langs->trans('CustomerReturnsSetup');
llxHeader('', $title);

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($title, $linkback, 'title_setup');

$head = customerreturnAdminPrepareHead();
print dol_get_fiche_head($head, 'extrafields', $title, -1, 'customerreturn@customerreturn');

require DOL_DOCUMENT_ROOT.'/core/tpl/admin_extrafields_view.tpl.php';

print dol_get_fiche_end();

llxFooter();
$db->close();
?>