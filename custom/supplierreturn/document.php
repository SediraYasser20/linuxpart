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
 * \ingroup supplierreturn
 * \brief   Documents tab for SupplierReturn
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

dol_include_once('/custom/supplierreturn/lib/supplierreturn.lib.php');
dol_include_once('/custom/supplierreturn/class/supplierreturn.class.php');
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';

$langs->loadLangs(array("supplierreturn@supplierreturn", "companies", "other"));

$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');

// Get parameters
$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
if (empty($page) || $page == -1) {
	$page = 0;
}     // If $page is not defined, or '' or -1
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (!$sortorder) {
	$sortorder = "ASC";
}
if (!$sortfield) {
	$sortfield = "name";
}

// Initialize module configuration for document management (matching card.php)
if (empty($conf->supplierreturn)) {
    $conf->supplierreturn = new stdClass();
    $conf->supplierreturn->enabled = 1;
    $conf->supplierreturn->dir_output = DOL_DATA_ROOT.'/supplierreturn';
}

$object = new SupplierReturn($db);

// Load object
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';

if ($object->id > 0) {
	$object->fetch_thirdparty();
	$upload_dir = $conf->supplierreturn->dir_output.'/'.dol_sanitizeFileName(str_replace(array('(', ')'), '', $object->ref));
}

$permissiontoadd = $user->hasRight('supplierreturn', 'creer');
$usercancreate = $permissiontoadd;

// Security check
$result = restrictedArea($user, 'supplierreturn', $id, '');

// Set variables for template (REQUIRED for document_actions_post_headers.tpl.php)
$modulepart = 'supplierreturn';

/*
 * Actions
 */
include DOL_DOCUMENT_ROOT.'/core/actions_linkedfiles.inc.php';

/*
 * View
 */

$title = $langs->trans("SupplierReturn").' - '.$langs->trans("Documents");
llxHeader('', $title, '');

$form = new Form($db);

if ($id > 0 || !empty($ref)) {
	if ($object->fetch($id, $ref)) {
		$object->fetch_thirdparty();

		$head = supplierreturn_prepare_head($object);
		print dol_get_fiche_head($head, 'documents', $langs->trans('SupplierReturn'), -1, 'supplierreturn@supplierreturn');

		// Build file list
		$filearray = dol_dir_list($upload_dir, "files", 0, '', '(\\.meta|_preview.*\\.png)$', $sortfield, (strtolower($sortorder) == 'desc' ? SORT_DESC : SORT_ASC), 1);
		
		// Debug: Log filearray contents
		dol_syslog("SupplierReturn document.php: upload_dir=$upload_dir", LOG_DEBUG);
		dol_syslog("SupplierReturn document.php: relativepathwithnofile=$relativepathwithnofile", LOG_DEBUG);
		foreach ($filearray as $key => $file) {
			dol_syslog("SupplierReturn document.php: filearray[$key] = " . print_r($file, true), LOG_DEBUG);
		}
		$totalsize = 0;
		foreach ($filearray as $key => $file) {
			$totalsize += $file['size'];
		}

		// SupplierReturn card
		$linkback = '<a href="'.dol_buildpath('/custom/supplierreturn/list.php', 1).'?restore_lastsearch_values=1'.(!empty($socid) ? '&socid='.$socid : '').'">'.$langs->trans("BackToList").'</a>';

		$morehtmlref = '<div class="refidno">';
		// Ref supplier
		$morehtmlref .= $form->editfieldkey("RefSupplier", 'supplier_ref', $object->supplier_ref, $object, 0, 'string', '', 0, 1);
		$morehtmlref .= $form->editfieldval("RefSupplier", 'supplier_ref', $object->supplier_ref, $object, 0, 'string', '', null, null, '', 1);
		// Thirdparty
		$morehtmlref .= '<br>'.$object->thirdparty->getNomUrl(1, 'supplier');
		if (!getDolGlobalString('MAIN_DISABLE_OTHER_LINK') && $object->thirdparty->id > 0) {
			$morehtmlref .= ' <div class="inline-block valignmiddle">(<a class="valignmiddle" href="'.dol_buildpath('/custom/supplierreturn/list.php', 1).'?socid='.((int) $object->thirdparty->id).'&search_company='.urlencode($object->thirdparty->name).'">'.$langs->trans("OtherReturns").'</a>)</div>';
		}
		$morehtmlref .= '</div>';

		dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref, '', 0);

		print '<div class="fichecenter">';
		print '<div class="underbanner clearboth"></div>';

		// Number of files
		print '<table class="border tableforfield centpercent">';
		print '<tr><td class="titlefield">'.$langs->trans("NbOfAttachedFiles").'</td><td colspan="3">'.count($filearray).'</td></tr>';
		print '<tr><td>'.$langs->trans("TotalSizeOfAttachedFiles").'</td><td colspan="3">'.dol_print_size($totalsize, 1, 1).'</td></tr>';
		print '</table><br>';

		print '<div class="underbanner clearboth"></div>';

		/*
		 * ACTIONS
		 *
		 * Put here all code to do according to value of $_POST and $_GET
		 */

		$modulepart = 'supplierreturn';
		$param = '&id='.$object->id;
		$relativepathwithnofile = dol_sanitizeFileName(str_replace(array('(', ')'), '', $object->ref)) . '/';
		//$savingdocmask = dol_sanitizeFileName($object->ref).'-__file__';

		// Use native Dolibarr template for document management
		include DOL_DOCUMENT_ROOT.'/core/tpl/document_actions_post_headers.tpl.php';
		
	} else {
		dol_print_error($db);
	}
} else {
	header('Location: index.php');
	exit;
}

print dol_get_fiche_end();

// End of page
llxFooter();
$db->close();