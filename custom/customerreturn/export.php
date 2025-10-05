<?php
/* Copyright (C) 2025 Your Name
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

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) $res = @include __DIR__.'/../../main.inc.php';
if (!$res) die("Include of main fails");

require_once __DIR__.'/class/customerreturn.class.php';
$langs->loadLangs(array("customerreturn@customerreturn", "companies"));

// Get parameters
$search_date_start = GETPOST('search_date_start', 'int');
$search_date_end = GETPOST('search_date_end', 'int');
$search_socid = GETPOST('search_socid', 'int');
$search_status = GETPOST('search_status', 'alpha');

// Build base SQL filter
$sql = "SELECT t.ref, s.nom as customer_name, t.date_return, t.total_ht, t.statut";
$sql .= " FROM ".MAIN_DB_PREFIX."customerreturn as t";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON t.fk_soc = s.rowid";
$sql .= " WHERE t.entity = ".$conf->entity;

if ($search_date_start) $sql .= " AND t.date_return >= '".$db->idate($search_date_start)."'";
if ($search_date_end) $sql .= " AND t.date_return <= '".$db->idate($search_date_end)."'";
if ($search_socid > 0) $sql .= " AND t.fk_soc = ".(int)$search_socid;
if ($search_status != '' && $search_status != '-1') $sql .= " AND t.statut = '".$db->escape($search_status)."'";

$sql .= " ORDER BY t.date_return DESC";

$resql = $db->query($sql);

if ($resql) {
    $customerreturn_static = new CustomerReturn($db);
    $filename = "customer_returns_export_".dol_print_date(dol_now(), '%Y%m%d_%H%M%S').".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename='.$filename);

    $output = fopen('php://output', 'w');

    // Header row
    fputcsv($output, array(
        $langs->trans('Ref'),
        $langs->trans('ThirdParty'),
        $langs->trans('DateReturn'),
        $langs->trans('AmountHT'),
        $langs->trans('Status')
    ));

    // Data rows
    while ($obj = $db->fetch_object($resql)) {
        fputcsv($output, array(
            $obj->ref,
            $obj->customer_name,
            dol_print_date($db->jdate($obj->date_return), 'day'),
            price($obj->total_ht, 0, $langs, 0, -1, -1, $conf->currency),
            $customerreturn_static->LibStatut($obj->statut, 0)
        ));
    }

    fclose($output);
} else {
    dol_syslog("Export failed: ".$db->lasterror(), LOG_ERR);
    echo "Error generating export.";
}

$db->close();
exit;