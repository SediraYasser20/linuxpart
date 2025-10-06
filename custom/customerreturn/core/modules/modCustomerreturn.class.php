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

/**
 *      \file       htdocs/custom/customerreturn/dashboard.php
 *      \ingroup    customerreturns
 *      \brief      Dashboard for customer returns
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) $res = @include __DIR__.'/../../main.inc.php';
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once __DIR__.'/class/customerreturn.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';


$langs->loadLangs(array("customerreturn@customerreturn", "other", "companies", "bills", "main"));

if (!$user->admin && !$user->hasRight('customerreturn', 'lire')) accessforbidden();

$form = new Form($db);
$formcompany = new FormCompany($db);
$customerreturn_static = new CustomerReturn($db);

// Get parameters
$search_date_start = dol_mktime(0, 0, 0, GETPOST('search_date_startmonth', 'int'), GETPOST('search_date_startday', 'int'), GETPOST('search_date_startyear', 'int'));
$search_date_end = dol_mktime(23, 59, 59, GETPOST('search_date_endmonth', 'int'), GETPOST('search_date_endday', 'int'), GETPOST('search_date_endyear', 'int'));
$search_socid = GETPOST('search_socid', 'int');
$search_status = GETPOST('search_status', 'alpha');

// Build base SQL filter
$sql_filter = " WHERE t.entity = ".$conf->entity;
if ($search_date_start) $sql_filter .= " AND t.date_return >= '".$db->idate($search_date_start)."'";
if ($search_date_end) $sql_filter .= " AND t.date_return <= '".$db->idate($search_date_end)."'";
if ($search_socid > 0) $sql_filter .= " AND t.fk_soc = ".(int)$search_socid;
if ($search_status != '' && $search_status != '-1') $sql_filter .= " AND t.statut = '".$db->escape($search_status)."'";


// Main KPI query
$sql_main = "SELECT t.rowid, t.total_ht, t.statut, t.date_creation, t.date_valid, t.date_process FROM ".MAIN_DB_PREFIX."customerreturn as t".$sql_filter;

// KPIs
$total_returns = 0;
$total_value = 0;
$pending_returns = 0;
$total_reimbursed = 0;
$total_processing_time = 0;
$closed_returns_count = 0;
$returns_by_status_raw = array(
    CustomerReturn::STATUS_DRAFT => 0, CustomerReturn::STATUS_VALIDATED => 0, CustomerReturn::STATUS_CHANGED_PRODUCT_FOR_CLIENT => 0,
    CustomerReturn::STATUS_REIMBURSED_MONEY_TO_CLIENT => 0, CustomerReturn::STATUS_CLOSED => 0, CustomerReturn::STATUS_RETURNED_TO_SUPPLIER => 0,
);

$resql_main = $db->query($sql_main);
if ($resql_main) {
    $total_returns = $db->num_rows($resql_main);
    while ($obj = $db->fetch_object($resql_main)) {
        $total_value += $obj->total_ht;
        if ($obj->statut == CustomerReturn::STATUS_DRAFT || $obj->statut == CustomerReturn::STATUS_VALIDATED) $pending_returns++;
        if ($obj->statut == CustomerReturn::STATUS_REIMBURSED_MONEY_TO_CLIENT) $total_reimbursed += $obj->total_ht;
        if ($obj->statut == CustomerReturn::STATUS_CLOSED && !empty($obj->date_creation) && !empty($obj->date_process)) {
            $date_creation = dol_stringtotime($obj->date_creation, 'auto', 'auto');
            $date_process = dol_stringtotime($obj->date_process, 'auto', 'auto');
            if ($date_creation && $date_process) {
                $total_processing_time += ($date_process - $date_creation);
                $closed_returns_count++;
            }
        }
        if (isset($returns_by_status_raw[$obj->statut])) $returns_by_status_raw[$obj->statut]++;
    }
}
$returns_by_status = array();
foreach ($returns_by_status_raw as $status => $count) {
    $returns_by_status[$customerreturn_static->LibStatut($status, 0)] = $count;
}
$avg_value = ($total_returns > 0) ? $total_value / $total_returns : 0;
$avg_processing_time = ($closed_returns_count > 0) ? ($total_processing_time / $closed_returns_count) : 0;

// Top Customers Query
$sql_top_customers = "SELECT s.rowid, s.nom, COUNT(t.rowid) as return_count FROM ".MAIN_DB_PREFIX."customerreturn as t";
$sql_top_customers .= " JOIN ".MAIN_DB_PREFIX."societe as s ON t.fk_soc = s.rowid";
$sql_top_customers .= $sql_filter;
$sql_top_customers .= " GROUP BY s.rowid, s.nom ORDER BY return_count DESC LIMIT 5";
$resql_top_customers = $db->query($sql_top_customers);

// Top Products Query
$sql_top_products = "SELECT p.rowid, p.ref, p.label, SUM(crd.qty) as total_returned FROM ".MAIN_DB_PREFIX."customerreturndet as crd";
$sql_top_products .= " JOIN ".MAIN_DB_PREFIX."product as p ON crd.fk_product = p.rowid";
$sql_top_products .= " JOIN ".MAIN_DB_PREFIX."customerreturn as t ON crd.fk_customerreturn = t.rowid";
$sql_top_products .= $sql_filter;
$sql_top_products .= " GROUP BY p.rowid, p.ref, p.label ORDER BY total_returned DESC LIMIT 5";
$resql_top_products = $db->query($sql_top_products);

// Return Trends Query
$sql_trends = "SELECT DATE_FORMAT(t.date_return, '%Y-%m') as return_month, COUNT(t.rowid) as return_count FROM ".MAIN_DB_PREFIX."customerreturn as t";
$sql_trends .= $sql_filter;
$sql_trends .= " GROUP BY return_month ORDER BY return_month ASC";
$resql_trends = $db->query($sql_trends);
$trends_data = array();
if ($resql_trends) {
    while($obj = $db->fetch_object($resql_trends)) {
        $trends_data[$obj->return_month] = $obj->return_count;
    }
}


llxHeader('', $langs->trans('CustomerReturnsDashboard'));

dol_include_js('/includes/jquery/plugins/chart/Chart.min.js', '1', 1, 1);

print_barre_liste($langs->trans("CustomerReturnsDashboard"), '', $_SERVER["PHP_SELF"], '', '', '', '', 0);

// Filter section
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder nohover" width="100%">';
print '<tr class="noborderbottom"><td colspan="3"><strong>'.$langs->trans('Filters').'</strong></td></tr>';
print '<tr class="noborderbottom">';
print '<td>'.$langs->trans('DateReturn').' from '. $form->select_date($search_date_start, 'search_date_start', 0, 0, 1, '', 1, 0, 1).' to '.$form->select_date($search_date_end, 'search_date_end', 0, 0, 1, '', 1, 0, 1).'</td>';
print '<td>'.$langs->trans('ThirdParty'). ': ';
print $formcompany->select_thirdparty_list($search_socid, 'search_socid', 's.nom', 1, 1, 0, null, 0, '', 0, 0, '', 'maxwidth300');
print '</td>';
print '<td>'.$langs->trans('Status'). ': ';
$status_list = array(
    '-1' => $langs->trans('All'),
    CustomerReturn::STATUS_DRAFT => $langs->trans('Draft'),
    CustomerReturn::STATUS_VALIDATED => $langs->trans('Validated'),
    CustomerReturn::STATUS_RETURNED_TO_SUPPLIER => $langs->trans('CustomerReturnStatusReturnedToSupplier'),
    CustomerReturn::STATUS_CHANGED_PRODUCT_FOR_CLIENT => $langs->trans('CustomerReturnStatusChangedProductForClient'),
    CustomerReturn::STATUS_REIMBURSED_MONEY_TO_CLIENT => $langs->trans('CustomerReturnStatusReimbursedMoneyToClient'),
    CustomerReturn::STATUS_CLOSED => $langs->trans('Closed'),
);
print $form->selectarray('search_status', $status_list, $search_status, 0, 0, 0, '', 0, 0, 0, '', 'maxwidth200', 1);
print '</td>';
print '<td class="right" rowspan="2"><input type="submit" class="button" value="'.$langs->trans('Refresh').'"><a href="export.php?search_date_start='.$search_date_start.'&search_date_end='.$search_date_end.'&search_socid='.$search_socid.'&search_status='.$search_status.'" class="button">'.$langs->trans('Export').'</a></td>';
print '</tr>';
print '</table>';
print '</div>';
print '</form><br>';

print '<div class="fichecenter">';
print '<div class="fichehalfleft">';

// General & Financial KPIs
print '<div class="infobox-container">';
dol_print_infobox($langs->trans('TotalReturns'), $total_returns, 'fa fa-undo');
dol_print_infobox($langs->trans('PendingReturns'), $pending_returns, 'fa fa-hourglass-half');
dol_print_infobox($langs->trans('TotalValue'), price($total_value), 'fa fa-eur');
dol_print_infobox($langs->trans('TotalReimbursed'), price($total_reimbursed), 'fa fa-money');
dol_print_infobox($langs->trans('AverageValue'), price($avg_value), 'fa fa-calculator');
if ($avg_processing_time > 0) dol_print_infobox($langs->trans('AverageProcessingTime'), round($avg_processing_time / 86400, 1) . ' ' . $langs->trans('Days'), 'fa fa-clock-o');
print '</div>';

// Customer & Product Insights
print '<div class="box">';
print '<div class="box_titre">'.$langs->trans('Insights').'</div>';
print '<div class="box_contenu">';
print '<div class="fichehalfleft">';
print '<p class="box-subtitle">'.$langs->trans('TopReturningCustomers').'</p>';
print '<table class="noborder" width="100%">';
if ($resql_top_customers && $db->num_rows($resql_top_customers) > 0) {
    $soc_static = new Societe($db);
    while($obj = $db->fetch_object($resql_top_customers)) {
        $soc_static->id = $obj->rowid;
        $soc_static->name = $obj->nom;
        print '<tr><td>'.$soc_static->getNomUrl(1).'</td><td class="right">'.$obj->return_count.' '.$langs->trans('Returns').'</td></tr>';
    }
} else {
    print '<tr><td>'.$langs->trans('NoData').'</td></tr>';
}
print '</table>';
print '</div>';
print '<div class="fichehalfright">';
print '<p class="box-subtitle">'.$langs->trans('TopReturnedProducts').'</p>';
print '<table class="noborder" width="100%">';
if ($resql_top_products && $db->num_rows($resql_top_products) > 0) {
    $prod_static = new Product($db);
    while($obj = $db->fetch_object($resql_top_products)) {
        $prod_static->id = $obj->rowid;
        $prod_static->ref = $obj->ref;
        print '<tr><td>'.$prod_static->getNomUrl(1).'</td><td class="right">'.(int)$obj->total_returned.' '.$langs->trans('ReturnedUnits').'</td></tr>';
    }
} else {
    print '<tr><td>'.$langs->trans('NoData').'</td></tr>';
}
print '</table>';
print '</div>';
print '</div>';
print '</div>';

print '</div>'; // end fichehalfleft
print '<div class="fichehalfright">';

// Charts
print '<div class="box">';
print '<div class="box_titre">'.$langs->trans('ReturnsByStatus').'</div>';
print '<div class="box_contenu" id="chart-container-status" style="height: 220px;"><canvas id="returns-by-status-chart"></canvas></div>';
print '</div>';
print '<div class="box">';
print '<div class="box_titre">'.$langs->trans('ReturnTrends').'</div>';
print '<div class="box_contenu" id="chart-container-trends" style="height: 220px;"><canvas id="return-trends-chart"></canvas></div>';
print '</div>';

print '</div>'; // end fichehalfright
print '</div>'; // end fichecenter

?>
<script>
$(document).ready(function() {
    var ctxStatus = document.getElementById('returns-by-status-chart').getContext('2d');
    new Chart(ctxStatus, {
        type: 'bar',
        data: {
            labels: <?php print json_encode(array_keys($returns_by_status)); ?>,
            datasets: [{
                label: '<?php echo $langs->trans("NumberOfReturns"); ?>',
                data: <?php print json_encode(array_values($returns_by_status)); ?>,
                backgroundColor: ['rgba(255, 99, 132, 0.2)', 'rgba(54, 162, 235, 0.2)', 'rgba(255, 206, 86, 0.2)', 'rgba(75, 192, 192, 0.2)', 'rgba(153, 102, 255, 0.2)', 'rgba(255, 159, 64, 0.2)'],
                borderColor: ['rgba(255, 99, 132, 1)', 'rgba(54, 162, 235, 1)', 'rgba(255, 206, 86, 1)', 'rgba(75, 192, 192, 1)', 'rgba(153, 102, 255, 1)', 'rgba(255, 159, 64, 1)'],
                borderWidth: 1
            }]
        },
        options: { scales: { yAxes: [{ ticks: { beginAtZero: true, stepSize: 1 } }] }, legend: { display: false }, maintainAspectRatio: false }
    });

    var ctxTrends = document.getElementById('return-trends-chart').getContext('2d');
    new Chart(ctxTrends, {
        type: 'line',
        data: {
            labels: <?php print json_encode(array_keys($trends_data)); ?>,
            datasets: [{
                label: '<?php echo $langs->trans("NumberOfReturns"); ?>',
                data: <?php print json_encode(array_values($trends_data)); ?>,
                borderColor: 'rgba(54, 162, 235, 1)',
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderWidth: 1
            }]
        },
        options: { scales: { yAxes: [{ ticks: { beginAtZero: true, stepSize: 1 } }] }, legend: { display: false }, maintainAspectRatio: false }
    });
});
</script>
<?php

llxFooter();
$db->close();

function dol_print_infobox($title, $value, $icon)
{
    print '<div class="infobox" style="width: 30%;">'; // Adjusted width for more KPIs
    print '<div class="infobox-icon"><i class="fa '.$icon.'"></i></div>';
    print '<div class="infobox-content">';
    print '<span class="infobox-title">'.$title.'</span>';
    print '<span class="infobox-value">'.$value.'</span>';
    print '</div>';
    print '</div>';
}
