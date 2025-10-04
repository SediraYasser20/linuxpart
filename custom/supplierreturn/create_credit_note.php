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
 *      \file       htdocs/custom/supplierreturn/create_credit_note.php
 *      \ingroup    supplierreturns
 *      \brief      Create credit note from supplier returns
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

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once './class/supplierreturn.class.php';

$langs->loadLangs(array("supplierreturn@supplierreturn", "other", "companies", "bills"));

// Get Parameters
$ids = GETPOST('ids', 'alpha');
$action = GETPOST('action', 'alpha');
$confirm = GETPOST('confirm', 'alpha');

// Security check
if (!$user->hasRight('supplierreturn', 'creer') && !$user->admin) {
    accessforbidden();
}

$form = new Form($db);
$formcompany = new FormCompany($db);

// Parse IDs
$selected_ids = array();
if ($ids) {
    $selected_ids = array_map('intval', explode(',', $ids));
}

if (empty($selected_ids)) {
    header('Location: list.php');
    exit;
}

// Load supplier returns
$supplier_returns = array();
$total_amount = 0;
$supplier_id = 0;

foreach ($selected_ids as $id) {
    $supplierreturn = new SupplierReturn($db);
    if ($supplierreturn->fetch($id) > 0) {
        if ($supplierreturn->statut == SupplierReturn::STATUS_VALIDATED) {
            $supplier_returns[] = $supplierreturn;
            $total_amount += $supplierreturn->total_ttc;
            
            if ($supplier_id == 0) {
                $supplier_id = $supplierreturn->fk_soc;
            } elseif ($supplier_id != $supplierreturn->fk_soc) {
                // Different suppliers - not allowed
                setEventMessages($langs->trans('ErrorDifferentSuppliers'), null, 'errors');
                header('Location: list.php');
                exit;
            }
        }
    }
}

if (empty($supplier_returns)) {
    setEventMessages($langs->trans('ErrorNoValidatedReturns'), null, 'errors');
    header('Location: list.php');
    exit;
}

// Load supplier
$supplier = new Societe($db);
$supplier->fetch($supplier_id);

/*
 * Actions
 */

if ($action == 'create' && $confirm == 'yes') {
    $db->begin();
    
    // Create credit note
    $creditnote = new FactureFournisseur($db);
    $creditnote->type = FactureFournisseur::TYPE_CREDIT_NOTE;
    $creditnote->socid = $supplier_id;
    $creditnote->date = dol_now();
    $creditnote->note_private = $langs->trans('CreatedFromSupplierReturns').': ';
    
    $refs = array();
    foreach ($supplier_returns as $return) {
        $refs[] = $return->ref;
    }
    $creditnote->note_private .= implode(', ', $refs);
    
    // Generate unique ref_supplier to avoid constraint conflict
    $creditnote->ref_supplier = 'AVR-'.date('Ymd').'-'.substr(uniqid(), -6);
    
    $result = $creditnote->create($user);
    
    if ($result > 0) {
        // Add lines from supplier returns
        foreach ($supplier_returns as $return) {
            $lines = $return->getLines();
            dol_syslog("CreateCreditNote: Processing return ".$return->ref." with ".count($lines)." lines", LOG_INFO);
            
            foreach ($lines as $line) {
                dol_syslog("CreateCreditNote: Line - desc='".$line->description."', subprice=".$line->subprice.", qty=".$line->qty.", original_tva_tx=".$line->original_tva_tx, LOG_INFO);
                
                // Paramètres: $desc, $pu, $txtva, $txlocaltax1, $txlocaltax2, $qty, $fk_product, $remise_percent
                $result = $creditnote->addline(
                    $line->description,
                    $line->subprice,
                    $line->original_tva_tx ? $line->original_tva_tx : 0,
                    $line->original_localtax1_tx ? $line->original_localtax1_tx : 0,
                    $line->original_localtax2_tx ? $line->original_localtax2_tx : 0,
                    $line->qty,
                    $line->fk_product,
                    0,      // remise_percent
                    '',     // date_start
                    '',     // date_end
                    0,      // ventil
                    '',     // info_bits
                    'HT',   // price_base_type
                    0       // product_type (0 = service par défaut)
                );
                
                if ($result <= 0) {
                    dol_syslog("CreateCreditNote: Failed to add line: ".$creditnote->error, LOG_ERR);
                    break 2;
                } else {
                    dol_syslog("CreateCreditNote: Successfully added line with ID ".$result, LOG_INFO);
                }
            }
        }
        
        if ($result > 0) {
            // Mark supplier returns as processed
            foreach ($supplier_returns as $return) {
                $return->statut = SupplierReturn::STATUS_CLOSED;
                $return->update($user);
            }
            
            $db->commit();
            setEventMessages($langs->trans('CreditNoteCreated'), null, 'mesgs');
            header('Location: '.DOL_URL_ROOT.'/fourn/facture/card.php?id='.$creditnote->id);
            exit;
        } else {
            $db->rollback();
            setEventMessages($creditnote->error, $creditnote->errors, 'errors');
        }
    } else {
        $db->rollback();
        setEventMessages($creditnote->error, $creditnote->errors, 'errors');
    }
}

/*
 * View
 */

$title = $langs->trans('CreateCreditNote');
llxHeader('', $title);

print load_fiche_titre($title);

// Confirmation form
if ($action == 'create') {
    print $form->formconfirm(
        $_SERVER["PHP_SELF"].'?ids='.$ids,
        $langs->trans('CreateCreditNote'),
        $langs->trans('ConfirmCreateCreditNote', count($supplier_returns)),
        'create',
        '',
        '',
        1
    );
}

print '<div class="fichecenter">';
print '<div class="fichethirdleft">';

// Supplier information
print '<div class="div-table-responsive-no-min">';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefield">'.$langs->trans('Supplier').'</td><td>'.$supplier->getNomUrl(1).'</td></tr>';
print '<tr><td>'.$langs->trans('SupplierReturnsCount').'</td><td>'.count($supplier_returns).'</td></tr>';
print '<tr><td>'.$langs->trans('TotalAmount').'</td><td><span class="amount">'.price($total_amount).'</span></td></tr>';
print '</table>';
print '</div>';

print '</div>';
print '<div class="fichetwothirdright">';

// List of supplier returns
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('Ref').'</th>';
print '<th>'.$langs->trans('Date').'</th>';
print '<th class="right">'.$langs->trans('Amount').'</th>';
print '</tr>';

foreach ($supplier_returns as $return) {
    print '<tr class="oddeven">';
    print '<td>'.$return->getNomUrl(1).'</td>';
    print '<td>'.dol_print_date($return->date_creation, 'day').'</td>';
    print '<td class="right"><span class="amount">'.price($return->total_ttc).'</span></td>';
    print '</tr>';
}

print '</table>';
print '</div>';

print '</div>';
print '</div>';

print '<div class="tabsAction">';
if ($action != 'create') {
    print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?ids='.$ids.'&action=create">'.$langs->trans('CreateCreditNote').'</a>';
}
print '<a class="butActionDelete" href="list.php">'.$langs->trans('Cancel').'</a>';
print '</div>';

llxFooter();