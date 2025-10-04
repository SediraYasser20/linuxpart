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
 * \file    card.php
 * \ingroup customerreturns
 * \brief   Customer Return card
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) $res = @include __DIR__.'/../../main.inc.php';
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once __DIR__.'/class/customerreturn.class.php';
require_once __DIR__.'/lib/customerreturn.lib.php';

$langs->loadLangs(array("customerreturn@customerreturn", "other", "products", "companies", "bills"));

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');

if (!$user->hasRight('customerreturn', 'lire') && !$user->admin) accessforbidden();

$object = new CustomerReturn($db);
$result = $id > 0 ? $object->fetch($id, '') : $object->fetch(0, $ref);
if ($result <= 0) {
    print '<div class="error">'.$langs->trans('ErrorCustomerReturnNotFound').'</div>';
    llxFooter();
    $db->close();
    exit;
}

$soc = new Societe($db);
if ($object->fk_soc > 0) {
    $soc->fetch($object->fk_soc);
}

// Actions
if ($action == 'confirm_validate' && $confirm == 'yes' && $user->hasRight('customerreturn', 'creer')) {
    $result = $object->validate($user);
    if ($result > 0) {
        header("Location: ".$_SERVER['PHP_SELF']."?id=".$object->id);
        exit;
    } else {
        setEventMessages($object->error, $object->errors, 'errors');
    }
}

if ($action == 'confirm_process' && $confirm == 'yes' && $user->hasRight('customerreturn', 'creer')) {
    $result = $object->process($user);
    if ($result > 0) {
        header("Location: ".$_SERVER['PHP_SELF']."?id=".$object->id);
        exit;
    } else {
        setEventMessages($object->error, $object->errors, 'errors');
    }
}

if ($action == 'confirm_createcreditnote' && $confirm == 'yes' && $user->hasRight('customerreturn', 'creer')) {
    $creditnote_id = $object->createCreditNote($user);
    if ($creditnote_id > 0) {
        header("Location: ".DOL_URL_ROOT.'/compta/facture/card.php?id='.$creditnote_id);
        exit;
    } else {
        setEventMessages($object->error, $object->errors, 'errors');
    }
}

// View
$form = new Form($db);
llxHeader('', $langs->trans('CustomerReturn'));

if ($action == 'create') {
    // Create form
} else {
    $head = customerreturn_prepare_head($object);
    print dol_get_fiche_head($head, 'card', $langs->trans("CustomerReturn"), 0, 'customerreturn@customerreturn');

    if ($action == 'validate') {
        print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('ValidateCustomerReturn'), $langs->trans('ConfirmValidateCustomerReturn'), 'confirm_validate');
    } elseif ($action == 'process') {
        print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('ProcessCustomerReturn'), $langs->trans('ConfirmProcessCustomerReturn'), 'confirm_process');
    } elseif ($action == 'createcreditnote') {
        print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('CreateCreditNote'), $langs->trans('ConfirmCreateCreditNote'), 'confirm_createcreditnote');
    }

    print '<div class="fichecenter"><div class="fichehalfleft">';
    print '<table class="border" width="100%">';
    print '<tr><td width="30%">'.$langs->trans("Ref").'</td><td>'.$object->getNomUrl(1).'</td></tr>';
    print '<tr><td>'.$langs->trans("Customer").'</td><td>'.$soc->getNomUrl(1).'</td></tr>';
    print '<tr><td>'.$langs->trans("DateReturn").'</td><td>'.dol_print_date($object->date_return, 'day').'</td></tr>';
    print '<tr><td>'.$langs->trans("ReturnReason").'</td><td>'.dol_escape_htmltag($object->return_reason).'</td></tr>';
    print '<tr><td>'.$langs->trans("Status").'</td><td>'.$object->getLibStatut(5).'</td></tr>';
    print '</table></div></div><div style="clear:both"></div>';

    print '<br>';

    print '<div class="div-table-responsive">';
    print '<table class="noborder" width="100%">';
    print '<tr class="liste_titre">';
    print '<td>'.$langs->trans("Description").'</td>';
    print '<td class="center">'.$langs->trans("SerialNumber").'</td>';
    print '<td class="center">'.$langs->trans("Warehouse").'</td>';
    print '<td class="right">'.$langs->trans("Qty").'</td>';
    print '<td class="right">'.$langs->trans("UnitPrice").'</td>';
    print '<td class="right">'.$langs->trans("TotalHT").'</td>';
    print '</tr>';
    foreach ($object->lines as $line) {
        print '<tr class="oddeven"><td>';
        $p = new Product($db);
        if ($line->fk_product > 0) {
            $p->fetch($line->fk_product);
            print $p->getNomUrl(1).' - '.dol_escape_htmltag($line->description);
        } else {
            print dol_htmlentitiesbr($line->description);
        }
        print '</td>';
        print '<td class="center">'.($line->batch ? dol_escape_htmltag($line->batch) : '-').'</td>';
        $warehouse = new Entrepot($db);
        if ($line->fk_entrepot > 0) {
            $warehouse->fetch($line->fk_entrepot);
            print '<td class="center">'.$warehouse->getNomUrl(1).'</td>';
        } else {
            print '<td class="center">-</td>';
        }
        print '<td class="right">'.price($line->qty).'</td>';
        print '<td class="right">'.price($line->subprice).'</td>';
        print '<td class="right">'.price($line->total_ht).'</td>';
        print '</tr>';
    }
    if (empty($object->lines)) {
        print '<tr><td colspan="6" class="center">'.$langs->trans("NoLines").'</td></tr>';
    }
    print '</table>';
    print '</div>';

    print '<div class="tabsAction">';
    if ($object->statut == CustomerReturn::STATUS_DRAFT) {
        print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=validate">'.$langs->trans('Validate').'</a>';
    } elseif ($object->statut == CustomerReturn::STATUS_VALIDATED) {
        print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=process">'.$langs->trans('Process').'</a>';
    } elseif ($object->statut == CustomerReturn::STATUS_CLOSED && empty($object->fk_facture)) {
        print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=createcreditnote">'.$langs->trans('CreateCreditNote').'</a>';
    }
    print '</div>';

    print dol_get_fiche_end();
}

llxFooter();
$db->close();
?>
