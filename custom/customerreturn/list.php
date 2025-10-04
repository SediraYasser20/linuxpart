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
 *      \file       htdocs/custom/customerreturn/list.php
 *      \ingroup    customerreturns
 *      \brief      List of customer returns
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) $res = @include __DIR__.'/../../main.inc.php';
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once __DIR__.'/class/customerreturn.class.php';

$langs->loadLangs(array("customerreturn@customerreturn", "other", "companies", "bills"));

if (!$user->admin && !$user->hasRight('customerreturn', 'lire')) accessforbidden();

$socid = GETPOSTINT('socid');
if ($user->socid) $socid = $user->socid;

$search_ref = GETPOST('search_ref', 'alpha');
$search_company = GETPOST('search_company', 'alpha');
$search_status = GETPOST('search_status', 'intcomma');

$sortfield = GETPOST('sortfield', 'aZ09comma') ?: 't.ref';
$sortorder = GETPOST('sortorder', 'aZ09comma') ?: 'DESC';
$page = GETPOSTINT('page', 'int') >= 0 ? GETPOSTINT('page', 'int') : 0;
if (GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) $page = 0;
$limit = GETPOSTINT('limit') ?: $conf->liste_limit;
$offset = $limit * $page;

$form = new Form($db);
$object = new CustomerReturn($db);

$sql = "SELECT t.rowid, t.ref, t.fk_soc, t.date_creation, t.date_return, t.statut, t.total_ht, s.nom as company_name";
$sql .= " FROM ".MAIN_DB_PREFIX."customerreturn as t";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON t.fk_soc = s.rowid";
$sql .= " WHERE t.entity = ".$conf->entity;

if ($socid > 0) $sql .= " AND t.fk_soc = ".(int) $socid;
if ($search_ref) $sql .= natural_search('t.ref', $search_ref);
if ($search_company) $sql .= natural_search('s.nom', $search_company);
if ($search_status != '' && $search_status != '-1') $sql .= " AND t.statut IN (".$db->sanitize($search_status).")";

$sql_count = preg_replace('/SELECT(.*?)FROM/', 'SELECT COUNT(*) as nbtotalofrecords FROM', $sql);

$resql_count = $db->query($sql_count);
$nbtotalofrecords = $db->fetch_object($resql_count)->nbtotalofrecords;

$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
$num = $db->num_rows($resql);

$param = '&socid='.$socid;
if ($search_ref) $param .= '&search_ref='.urlencode($search_ref);
if ($search_company) $param .= '&search_company='.urlencode($search_company);
if ($search_status != '' && $search_status != '-1') $param .= '&search_status='.urlencode($search_status);

llxHeader('', $langs->trans('CustomerReturnsList'));

$newcardbutton = dolGetButtonTitle($langs->trans('NewCustomerReturn'), '', 'fa fa-plus-circle', 'card.php?action=create');
print_barre_liste($langs->trans("CustomerReturnsList"), $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $nbtotalofrecords, 'customerreturn@customerreturn', 0, $newcardbutton, '', $limit);

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste">';
print '<tr class="liste_titre">';
print_liste_field_titre($langs->trans('Ref'), $_SERVER["PHP_SELF"], "t.ref", "", $param, "", $sortfield, $sortorder);
print_liste_field_titre($langs->trans('ThirdParty'), $_SERVER["PHP_SELF"], "s.nom", "", $param, "", $sortfield, $sortorder);
print_liste_field_titre($langs->trans('DateReturn'), $_SERVER["PHP_SELF"], "t.date_return", "", $param, 'align="center"', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('AmountHT'), $_SERVER["PHP_SELF"], "t.total_ht", "", $param, 'align="right"', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Status'), $_SERVER["PHP_SELF"], "t.statut", "", $param, 'align="center"', $sortfield, $sortorder);
print '</tr>';

print '<tr class="liste_titre_filter">';
print '<td><input class="flat" type="text" name="search_ref" value="'.dol_escape_htmltag($search_ref).'"></td>';
print '<td><input class="flat" type="text" name="search_company" value="'.dol_escape_htmltag($search_company).'"></td>';
print '<td></td>';
print '<td></td>';
print '<td class="center">';
$status_list = array('0' => $langs->trans('Draft'), '1' => $langs->trans('Validated'), '2' => $langs->trans('Processed'));
print $form->selectarray('search_status', $status_list, $search_status, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth100', 1);
print '</td>';
print '</tr>';

if ($num > 0) {
    $customerreturn_static = new CustomerReturn($db);
    while ($obj = $db->fetch_object($resql)) {
        print '<tr class="oddeven">';
        print '<td><a href="card.php?id='.$obj->rowid.'">'.img_object($langs->trans('ShowReturn'), 'customerreturn@customerreturn').' '.$obj->ref.'</a></td>';
        print '<td><a href="'.DOL_URL_ROOT.'/comm/card.php?socid='.$obj->fk_soc.'">'.img_object($langs->trans('ShowCompany'), 'company').' '.$obj->company_name.'</a></td>';
        print '<td class="center">'.dol_print_date($db->jdate($obj->date_return), 'day').'</td>';
        print '<td class="right">'.price($obj->total_ht).'</td>';
        print '<td class="center">'.$customerreturn_static->LibStatut($obj->statut, 5).'</td>';
        print '</tr>';
    }
} else {
    print '<tr><td colspan="5" class="center">'.$langs->trans("NoRecordFound").'</td></tr>';
}

print '</table>';
print '</div>';
print '</form>';

$db->free($resql);
llxFooter();
$db->close();