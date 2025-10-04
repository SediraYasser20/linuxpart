<?php
/* Copyright (C) 2025 Nicolas Testori
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *      \file       htdocs/custom/supplierreturn/index.php
 *      \ingroup    supplierreturns
 *      \brief      Home page of supplier returns module
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
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once './class/supplierreturn.class.php';

$langs->loadLangs(array("supplierreturn@supplierreturn", "other"));

// Security check
if (!$user->hasRight('supplierreturn', 'lire') && !$user->admin) {
    accessforbidden();
}

$socid = GETPOSTINT('socid');
if ($user->socid) {
    $socid = $user->socid;
}

/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

$title = $langs->trans("SupplierReturnsList");
$helpurl = '';

llxHeader('', $title, $helpurl);

print load_fiche_titre($langs->trans("SupplierReturnsArea"), '', 'object_supplierreturn@supplierreturn');

print '<div class="fichecenter"><div class="fichethirdleft">';

// Statistics
$stats = array();

// Draft
$sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."supplierreturn WHERE entity = ".$conf->entity." AND statut = 0";
if ($socid) $sql .= " AND fk_soc = ".(int) $socid;
$resql = $db->query($sql);
if ($resql) {
    $obj = $db->fetch_object($resql);
    $stats['draft'] = $obj->nb;
}

// Validated
$sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."supplierreturn WHERE entity = ".$conf->entity." AND statut = 1";
if ($socid) $sql .= " AND fk_soc = ".(int) $socid;
$resql = $db->query($sql);
if ($resql) {
    $obj = $db->fetch_object($resql);
    $stats['validated'] = $obj->nb;
}

// Processed
$sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."supplierreturn WHERE entity = ".$conf->entity." AND statut = 2";
if ($socid) $sql .= " AND fk_soc = ".(int) $socid;
$resql = $db->query($sql);
if ($resql) {
    $obj = $db->fetch_object($resql);
    $stats['processed'] = $obj->nb;
}

// Canceled
$sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."supplierreturn WHERE entity = ".$conf->entity." AND statut = 9";
if ($socid) $sql .= " AND fk_soc = ".(int) $socid;
$resql = $db->query($sql);
if ($resql) {
    $obj = $db->fetch_object($resql);
    $stats['canceled'] = $obj->nb;
}

// Total
$stats['total'] = ($stats['draft'] ?? 0) + ($stats['validated'] ?? 0) + ($stats['processed'] ?? 0) + ($stats['canceled'] ?? 0);

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder nohover centpercent">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans("Statistics").'</th></tr>';

// All
print '<tr class="oddeven">';
print '<td><a href="list.php'.($socid ? '?socid='.$socid : '').'">';
print img_object('', 'supplierreturn@supplierreturn', 'class="pictofixedwidth"');
print $langs->trans("AllSupplierReturns").'</a></td>';
print '<td class="right">'.$stats['total'].'</td>';
print '</tr>';

// Draft
print '<tr class="oddeven">';
print '<td><a href="list.php?search_status=0'.($socid ? '&socid='.$socid : '').'">';
print img_picto('', 'statut0', 'class="pictofixedwidth"');
print $langs->trans("SupplierReturnsDraft").'</a></td>';
print '<td class="right">'.($stats['draft'] ?? 0).'</td>';
print '</tr>';

// Validated
print '<tr class="oddeven">';
print '<td><a href="list.php?search_status=1'.($socid ? '&socid='.$socid : '').'">';
print img_picto('', 'statut4', 'class="pictofixedwidth"');
print $langs->trans("SupplierReturnsValidated").'</a></td>';
print '<td class="right">'.($stats['validated'] ?? 0).'</td>';
print '</tr>';

// Processed
print '<tr class="oddeven">';
print '<td><a href="list.php?search_status=2'.($socid ? '&socid='.$socid : '').'">';
print img_picto('', 'statut6', 'class="pictofixedwidth"');
print $langs->trans("SupplierReturnsProcessed").'</a></td>';
print '<td class="right">'.($stats['processed'] ?? 0).'</td>';
print '</tr>';

// Canceled
if (($stats['canceled'] ?? 0) > 0) {
    print '<tr class="oddeven">';
    print '<td><a href="list.php?search_status=9'.($socid ? '&socid='.$socid : '').'">';
    print img_picto('', 'statut5', 'class="pictofixedwidth"');
    print $langs->trans("SupplierReturnsCanceled").'</a></td>';
    print '<td class="right">'.($stats['canceled'] ?? 0).'</td>';
    print '</tr>';
}

print '</table>';
print '</div>';

print '</div><div class="fichetwothirdright">';

// Last supplier returns
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th colspan="5">'.$langs->trans("LastSupplierReturns", 5).'</th>';
print '</tr>';

$sql = "SELECT sr.rowid, sr.ref, sr.date_creation, sr.statut, sr.return_reason, s.nom as company_name";
$sql .= " FROM ".MAIN_DB_PREFIX."supplierreturn sr";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON sr.fk_soc = s.rowid";
$sql .= " WHERE sr.entity = ".$conf->entity;
if ($socid) $sql .= " AND sr.fk_soc = ".(int) $socid;
$sql .= " ORDER BY sr.date_creation DESC";
$sql .= $db->plimit(5, 0);

$resql = $db->query($sql);
if ($resql) {
    $num = $db->num_rows($resql);
    $i = 0;
    
    while ($i < $num) {
        $obj = $db->fetch_object($resql);
        
        $supplierreturn = new SupplierReturn($db);
        $supplierreturn->id = $obj->rowid;
        $supplierreturn->ref = $obj->ref;
        $supplierreturn->statut = $obj->statut;
        
        print '<tr class="oddeven">';
        print '<td class="nowraponall">'.$supplierreturn->getNomUrl(1).'</td>';
        print '<td class="tdoverflowmax150">'.dol_escape_htmltag($obj->company_name).'</td>';
        print '<td>'.dol_print_date($db->jdate($obj->date_creation), 'day').'</td>';
        print '<td class="tdoverflowmax100">'.dol_escape_htmltag($obj->return_reason).'</td>';
        print '<td class="center">'.$supplierreturn->getLibStatut(3).'</td>';
        print '</tr>';
        
        $i++;
    }
    
    if ($num == 0) {
        print '<tr class="oddeven"><td colspan="5" class="opacitymedium">'.$langs->trans("None").'</td></tr>';
    }
} else {
    dol_print_error($db);
}

print '</table>';
print '</div>';

print '</div></div>';

llxFooter();
?>