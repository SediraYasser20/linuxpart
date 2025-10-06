<?php
/* Copyright (C) 2025 Nicolas Testori
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *      \file       htdocs/custom/customerreturn/index.php
 *      \ingroup    customerreturns
 *      \brief      Home page of customer returns module
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) $res = @include __DIR__.'/../../main.inc.php';
if (!$res) die("Include of main fails");

require_once __DIR__.'/class/customerreturn.class.php';

$langs->loadLangs(array("customerreturn@customerreturn", "other"));

if (!$user->hasRight('customerreturn', 'lire') && !$user->admin) accessforbidden();

$socid = GETPOSTINT('socid');
if ($user->socid) $socid = $user->socid;

$form = new Form($db);
$title = $langs->trans("CustomerReturnsList");
llxHeader('', $title);

print load_fiche_titre($langs->trans("CustomerReturnsArea"), '', 'customerreturn@customerreturn');

print '<div class="fichecenter"><div class="fichethirdleft">';

$stats = array();
$sql = "SELECT statut, COUNT(*) as nb FROM ".MAIN_DB_PREFIX."customerreturn WHERE entity = ".$conf->entity;
if ($socid) $sql .= " AND fk_soc = ".(int) $socid;
$sql .= " GROUP BY statut";
$resql = $db->query($sql);
if ($resql) {
    while($obj = $db->fetch_object($resql)) {
        if ($obj->statut == 0) $stats['draft'] = $obj->nb;
        if ($obj->statut == 1) $stats['validated'] = $obj->nb;
        if ($obj->statut == 2) $stats['processed'] = $obj->nb;
        if ($obj->statut == 9) $stats['canceled'] = $obj->nb;
    }
}
$stats['total'] = ($stats['draft'] ?? 0) + ($stats['validated'] ?? 0) + ($stats['processed'] ?? 0) + ($stats['canceled'] ?? 0);

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder nohover centpercent">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans("Statistics").'</th></tr>';
print '<tr class="oddeven"><td><a href="list.php'.($socid ? '?socid='.$socid : '').'">'.img_object('', 'customerreturn@customerreturn', 'class="pictofixedwidth"').$langs->trans("AllCustomerReturns").'</a></td><td class="right">'.$stats['total'].'</td></tr>';
print '<tr class="oddeven"><td><a href="list.php?search_status=0'.($socid ? '&socid='.$socid : '').'">'.img_picto('', 'statut0', 'class="pictofixedwidth"').$langs->trans("CustomerReturnsDraft").'</a></td><td class="right">'.($stats['draft'] ?? 0).'</td></tr>';
print '<tr class="oddeven"><td><a href="list.php?search_status=1'.($socid ? '&socid='.$socid : '').'">'.img_picto('', 'statut4', 'class="pictofixedwidth"').$langs->trans("CustomerReturnsValidated").'</a></td><td class="right">'.($stats['validated'] ?? 0).'</td></tr>';
print '<tr class="oddeven"><td><a href="list.php?search_status=2'.($socid ? '&socid='.$socid : '').'">'.img_picto('', 'statut6', 'class="pictofixedwidth"').$langs->trans("CustomerReturnsProcessed").'</a></td><td class="right">'.($stats['processed'] ?? 0).'</td></tr>';
if (($stats['canceled'] ?? 0) > 0) {
    print '<tr class="oddeven"><td><a href="list.php?search_status=9'.($socid ? '&socid='.$socid : '').'">'.img_picto('', 'statut5', 'class="pictofixedwidth"').$langs->trans("CustomerReturnsCanceled").'</a></td><td class="right">'.($stats['canceled'] ?? 0).'</td></tr>';
}
print '</table></div></div><div class="fichetwothirdright">';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="5">'.$langs->trans("LastCustomerReturns", 5).'</th></tr>';

$sql = "SELECT cr.rowid, cr.ref, cr.date_creation, cr.statut, cr.return_reason, s.nom as company_name";
$sql .= " FROM ".MAIN_DB_PREFIX."customerreturn cr";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON cr.fk_soc = s.rowid";
$sql .= " WHERE cr.entity = ".$conf->entity;
if ($socid) $sql .= " AND cr.fk_soc = ".(int) $socid;
$sql .= " ORDER BY cr.date_creation DESC";
$sql .= $db->plimit(5, 0);

$resql = $db->query($sql);
if ($resql) {
    $num = $db->num_rows($resql);
    if ($num > 0) {
        $customerreturn = new CustomerReturn($db);
        while ($obj = $db->fetch_object($resql)) {
            $customerreturn->id = $obj->rowid;
            $customerreturn->ref = $obj->ref;
            $customerreturn->statut = $obj->statut;
            print '<tr class="oddeven">';
            print '<td class="nowraponall">'.$customerreturn->getNomUrl(1).'</td>';
            print '<td class="tdoverflowmax150">'.dol_escape_htmltag($obj->company_name).'</td>';
            print '<td>'.dol_print_date($db->jdate($obj->date_creation), 'day').'</td>';
            print '<td class="tdoverflowmax100">'.dol_escape_htmltag($obj->return_reason).'</td>';
            print '<td class="center">'.$customerreturn->getLibStatut(3).'</td>';
            print '</tr>';
        }
    } else {
        print '<tr class="oddeven"><td colspan="5" class="opacitymedium">'.$langs->trans("None").'</td></tr>';
    }
} else {
    dol_print_error($db);
}

print '</table></div></div></div>';

llxFooter();
$db->close();
?>