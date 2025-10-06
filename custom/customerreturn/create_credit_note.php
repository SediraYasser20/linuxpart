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
 *      \file       htdocs/custom/customerreturn/create_credit_note.php
 *      \ingroup    customerreturns
 *      \brief      Create credit note from customer returns
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
if (!$res) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once './class/customerreturn.class.php';

$langs->loadLangs(array("customerreturn@customerreturn", "other", "companies", "bills"));

$ids = GETPOST('ids', 'alpha');
$action = GETPOST('action', 'alpha');
$confirm = GETPOST('confirm', 'alpha');

if (!$user->hasRight('customerreturn', 'creer') && !$user->admin) accessforbidden();

$form = new Form($db);
$selected_ids = array_map('intval', explode(',', $ids));
if (empty($selected_ids)) {
    header('Location: list.php');
    exit;
}

$customer_returns = array();
$total_amount = 0;
$customer_id = 0;

foreach ($selected_ids as $id) {
    $return = new CustomerReturn($db);
    if ($return->fetch($id) > 0) {
        if ($return->statut == CustomerReturn::STATUS_CLOSED) {
            $customer_returns[] = $return;
            $total_amount += $return->total_ttc;
            if ($customer_id == 0) {
                $customer_id = $return->fk_soc;
            } elseif ($customer_id != $return->fk_soc) {
                setEventMessages($langs->trans('ErrorDifferentCustomers'), null, 'errors');
                header('Location: list.php');
                exit;
            }
        }
    }
}

if (empty($customer_returns)) {
    setEventMessages($langs->trans('ErrorNoClosedReturns'), null, 'errors');
    header('Location: list.php');
    exit;
}

$customer = new Societe($db);
$customer->fetch($customer_id);

if ($action == 'create' && $confirm == 'yes') {
    $db->begin();
    $creditnote = new Facture($db);
    $creditnote->type = Facture::TYPE_CREDIT_NOTE;
    $creditnote->socid = $customer_id;
    $creditnote->date = dol_now();
    $refs = array();
    foreach ($customer_returns as $return) {
        $refs[] = $return->ref;
    }
    $creditnote->note_private = $langs->trans('CreatedFromCustomerReturns').': '.implode(', ', $refs);

    $result = $creditnote->create($user);
    if ($result > 0) {
        foreach ($customer_returns as $return) {
            $lines = $return->getLines();
            foreach ($lines as $line) {
                $creditnote->addline($line->description, $line->subprice, $line->qty, $line->tva_tx);
            }
            $return->fk_facture = $creditnote->id;
            $return->update($user, 1);
        }
        $db->commit();
        setEventMessages($langs->trans('CreditNoteCreated'), null, 'mesgs');
        header('Location: '.DOL_URL_ROOT.'/compta/facture/card.php?id='.$creditnote->id);
        exit;
    } else {
        $db->rollback();
        setEventMessages($creditnote->error, $creditnote->errors, 'errors');
    }
}

$title = $langs->trans('CreateCreditNote');
llxHeader('', $title);
print load_fiche_titre($title);

if ($action != 'create') {
    print $form->formconfirm($_SERVER["PHP_SELF"].'?ids='.$ids, $langs->trans('CreateCreditNote'), $langs->trans('ConfirmCreateCreditNote', count($customer_returns)), 'create');
}

print '<div class="fichecenter"><div class="fichethirdleft">';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefield">'.$langs->trans('Customer').'</td><td>'.$customer->getNomUrl(1).'</td></tr>';
print '<tr><td>'.$langs->trans('CustomerReturnsCount').'</td><td>'.count($customer_returns).'</td></tr>';
print '<tr><td>'.$langs->trans('TotalAmount').'</td><td><span class="amount">'.price($total_amount).'</span></td></tr>';
print '</table></div>';
print '<div class="fichetwothirdright"><table class="noborder centpercent"><tr class="liste_titre">';
print '<th>'.$langs->trans('Ref').'</th><th>'.$langs->trans('Date').'</th><th class="right">'.$langs->trans('Amount').'</th></tr>';
foreach ($customer_returns as $return) {
    print '<tr class="oddeven"><td>'.$return->getNomUrl(1).'</td>';
    print '<td>'.dol_print_date($return->date_creation, 'day').'</td>';
    print '<td class="right"><span class="amount">'.price($return->total_ttc).'</span></td></tr>';
}
print '</table></div></div>';

print '<div class="tabsAction">';
if ($action != 'create') {
    print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?ids='.$ids.'&action=create">'.$langs->trans('CreateCreditNote').'</a>';
}
print '<a class="butActionDelete" href="list.php">'.$langs->trans('Cancel').'</a>';
print '</div>';

llxFooter();
$db->close();
?>