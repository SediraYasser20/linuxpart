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
 * \ingroup supplierreturns
 * \brief   Events agenda tab for SupplierReturn
 */

// Load Dolibarr environment
// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once './class/supplierreturn.class.php';
require_once './lib/supplierreturn.lib.php';

// Load translation files required by the page
$langs->loadLangs(array("supplierreturn@supplierreturn", "other"));

// Get parameters
$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

if (GETPOST('actioncode', 'array')) {
    $actioncode = GETPOST('actioncode', 'array', 3);
    if (!count($actioncode)) {
        $actioncode = '0';
    }
} else {
    $actioncode = GETPOST("actioncode", "alpha", 3) ? GETPOST("actioncode", "alpha", 3) : (GETPOST("actioncode") == '0' ? '0' : getDolGlobalString('AGENDA_DEFAULT_FILTER_TYPE_FOR_OBJECT'));
}

$search_agenda_label = GETPOST('search_agenda_label');

$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT("page");
if (empty($page) || $page == -1) {
    $page = 0;
}     // If $page is not defined, or '' or -1
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (!$sortfield) {
    $sortfield = 'a.datep,a.id';
}
if (!$sortorder) {
    $sortorder = 'DESC,DESC';
}

// Initialize technical objects
$object = new SupplierReturn($db);
$extrafields = new ExtraFields($db);
$diroutputmassaction = $conf->supplierreturns->dir_output.'/temp/massgeneration/'.$user->id;
$hookmanager->initHooks(array('supplierreturnagenda', 'globalcard')); // Note that conf->hooks_modules contains array
// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);

// Load object
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php'; // Must be include, not include_once  // Must be include, not include_once. Include fetch and fetch_thirdparty but not fetch_optionals
if ($id > 0 || !empty($ref)) {
    $upload_dir = $conf->supplierreturns->dir_output."/supplierreturn/".dol_sanitizeFileName($object->ref);
}

// Security check - Protection if external user
$socid = 0;
if ($user->socid > 0) {
    $socid = $user->socid;
}
$result = restrictedArea($user, 'supplierreturn', $object->id, '', '', 'fk_soc', 'rowid');

$permissionnote = $user->hasRight('supplierreturn', 'creer');
$permissiondellink = $user->hasRight('supplierreturn', 'creer');
$permissiontoadd = $user->hasRight('supplierreturn', 'creer');

/*
 * Actions
 */

$parameters = array('id'=>$id);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
    // Cancel
    if (GETPOST('cancel', 'alpha') && !empty($backtopage)) {
        header("Location: ".$backtopage);
        exit;
    }

    // Purge search criteria
    if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) { // All tests are required to be compatible with all browsers
        $actioncode = '';
        $search_agenda_label = '';
    }
}

/*
 * View
 */

$form = new Form($db);

if ($object->id > 0) {
    $title = $langs->trans("Agenda");
    $help_url = '';
    llxHeader('', $title, $help_url);

    if (isModEnabled('notification')) {
        $langs->load("mails");
    }
    $head = supplierreturn_prepare_head($object);


    print dol_get_fiche_head($head, 'agenda', $langs->trans("SupplierReturn"), -1, $object->picto);

    // Object card
    // ------------------------------------------------------------
    $linkback = '<a href="'.dol_buildpath('/custom/supplierreturn/list.php', 1).'?restore_lastsearch_values=1'.(!empty($socid) ? '&socid='.$socid : '').'">'.$langs->trans("BackToList").'</a>';

    $morehtmlref = '<div class="refidno">';
    // Thirdparty
    if (isModEnabled('societe')) {
        $morehtmlref .= '<br>'.$langs->trans('ThirdParty').' : ';
        if ($action != 'edit' && $permissiontoadd) {
            $morehtmlref .= '<a class="editfielda" href="'.$_SERVER['PHP_SELF'].'?action=edit&token='.newToken().'&id='.$object->id.'">'.img_edit($langs->transnoentitiesnoconv('SetThirdParty')).'</a> ';
        }
        $morehtmlref .= $form->form_thirdparty($_SERVER['PHP_SELF'].'?id='.$object->id, $object->fk_soc, 'none', '', 1, 0, 0, array(), 1);
    }
    $morehtmlref .= '</div>';

    dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

    print '<div class="fichecenter">';
    print '<div class="underbanner clearboth"></div>';

    $object->info($object->id);
    dol_print_object_info($object, 1);

    print '</div>';

    print dol_get_fiche_end();



    // Actions buttons

    $objthirdparty = $object;
    $objcon = new stdClass();

    $out = '&origin='.urlencode($object->element.'@'.$object->module).'&originid='.urlencode($object->id);
    $urlbacktopage = $_SERVER['PHP_SELF'].'?id='.$object->id;
    $out .= '&backtopage='.urlencode($urlbacktopage);
    $permok = $user->hasRight('agenda', 'myactions', 'create');
    if ((!empty($objthirdparty->id) || !empty($objcon->id)) && $permok) {
        //$out.='<a href="'.DOL_URL_ROOT.'/comm/action/card.php?action=create';
        if (get_class($objthirdparty) == 'Societe') {
            $out .= '&socid='.urlencode($objthirdparty->id);
        }
        $out .= (!empty($objcon->id) ? '&contactid='.urlencode($objcon->id) : '');
        //$out.='">'.$langs->trans("AddAnAction").' ';
        //$out.=img_picto($langs->trans("AddAnAction"),'filenew');
        //$out.="</a>";
    }


    print '<div class="tabsAction">';

    if (isModEnabled('agenda')) {
        if ($user->hasRight('agenda', 'myactions', 'create') || $user->hasRight('agenda', 'allactions', 'create')) {
            print '<a class="butAction" href="'.DOL_URL_ROOT.'/comm/action/card.php?action=create'.$out.'">'.$langs->trans("AddAnAction").'</a>';
        } else {
            print '<a class="butActionRefused classfortooltip" href="#">'.$langs->trans("AddAnAction").'</a>';
        }
    }

    print '</div>';

    if (isModEnabled('agenda') && ($user->hasRight('agenda', 'myactions', 'read') || $user->hasRight('agenda', 'allactions', 'read'))) {
        $param = '&id='.$object->id.(!empty($socid) ? '&socid='.$socid : '');
        if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) {
            $param .= '&contextpage='.urlencode($contextpage);
        }
        if ($limit > 0 && $limit != $conf->liste_limit) {
            $param .= '&limit='.((int) $limit);
        }


        //print load_fiche_titre($langs->trans("ActionsOnSupplierReturn"), '', '');

        // List of all actions
        $filters = array();
        $filters['search_agenda_label'] = $search_agenda_label;
        $filters['search_rowid'] = $search_rowid;

        // TODO Replace this with same code than into list.php
        show_actions_done($conf, $langs, $db, $object, null, 0, $actioncode, '', $filters, $sortfield, $sortorder, $object->module);
    }
}

// End of page
llxFooter();
$db->close();