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
 * \file    agenda.php
 * \ingroup customerreturns
 * \brief   Events agenda tab for CustomerReturn
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) $res = @include __DIR__.'/../../main.inc.php';
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once __DIR__.'/class/customerreturn.class.php';
require_once __DIR__.'/lib/customerreturn.lib.php';

$langs->loadLangs(array("customerreturn@customerreturn", "other", "agenda"));

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');

if (!$user->hasRight('customerreturn', 'lire') && !$user->admin) {
    accessforbidden();
}

$object = new CustomerReturn($db);
if ($id > 0 || !empty($ref)) {
    $object->fetch($id, $ref);
}

$socid = $user->socid > 0 ? $user->socid : 0;
$result = restrictedArea($user, 'customerreturn', $object->id);

$hookmanager->initHooks(array('customerreturnagenda', 'globalcard'));

$parameters = array('id' => $id);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);

$title = $langs->trans("Agenda");
llxHeader('', $title);

$form = new Form($db);

if ($object->id > 0) {
    $head = customerreturn_prepare_head($object);
    print dol_get_fiche_head($head, 'agenda', $langs->trans("CustomerReturn"), 0, 'customerreturn@customerreturn');

    $linkback = '<a href="'.dol_buildpath('/custom/customerreturn/list.php', 1).'?restore_lastsearch_values=1'.($socid ? '&socid='.$socid : '').'">'.$langs->trans("BackToList").'</a>';
    dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref');

    print '<div class="fichecenter">';
    print '<div class="underbanner clearboth"></div>';
    dol_print_object_info($object, 1);
    print '</div>';
    print dol_get_fiche_end();

    if (isModEnabled('agenda')) {
        $out = '&origin='.urlencode($object->element).'&originid='.urlencode($object->id);
        $out .= '&backtopage='.urlencode($_SERVER['PHP_SELF'].'?id='.$object->id);

        print '<div class="tabsAction">';
        if ($user->hasRight('agenda', 'myactions', 'create') || $user->hasRight('agenda', 'allactions', 'create')) {
            print '<a class="butAction" href="'.DOL_URL_ROOT.'/comm/action/card.php?action=create'.$out.'">'.$langs->trans("AddAnAction").'</a>';
        }
        print '</div>';

        $actioncode = GETPOST("actioncode", "alpha", 3) ?: getDolGlobalString('AGENDA_DEFAULT_FILTER_TYPE_FOR_OBJECT');
        show_actions_done($conf, $langs, $db, $object, null, 0, $actioncode);
    }
}

llxFooter();
$db->close();
?>