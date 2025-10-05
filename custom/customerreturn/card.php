<?php
/* Copyright (C) 2025 Nicolas Testori
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) $res = @include __DIR__.'/../../main.inc.php';
if (!$res) die("Include of main fails");

// Required classes
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once __DIR__.'/class/customerreturn.class.php';
require_once __DIR__.'/lib/customerreturn.lib.php';

$langs->loadLangs(array("customerreturn@customerreturn", "other", "products", "companies", "bills"));

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'alpha');

// Security check
if (!$user->hasRight('customerreturn', 'lire') && !$user->admin) {
    accessforbidden();
}

$object = new CustomerReturn($db);
$result = $id > 0 ? $object->fetch($id) : ($ref ? $object->fetch(0, $ref) : -1);

if ($result <= 0) {
    dol_syslog("card.php: Failed to fetch customer return id=".$id." ref=".$ref, LOG_ERR);
    print '<div class="error">'.$langs->trans('ErrorCustomerReturnNotFound').'</div>';
    llxFooter();
    $db->close();
    exit;
}

$soc = new Societe($db);
if ($object->fk_soc > 0) {
    $soc->fetch($object->fk_soc);
}

/*
 * Actions
 */
$reshook = $hookmanager->executeHooks('doActions', array(), $object, $action);
if ($reshook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
    // Cancel action
    if ($cancel) {
        $action = '';
    }

    // Validate action
    if ($action == 'confirm_validate' && $confirm == 'yes') {
        dol_syslog("card.php: Starting validation process for customer return id=".$object->id, LOG_DEBUG);
        
        if (!$user->hasRight('customerreturn', 'creer') && !$user->admin) {
            dol_syslog("card.php: User doesn't have permission to validate", LOG_ERR);
            setEventMessages($langs->trans("NotEnoughPermissions"), null, 'errors');
            $action = '';
        } else {
            $result = $object->validate($user);
            dol_syslog("card.php: Validation returned result=".$result, LOG_DEBUG);
            
            if ($result > 0) {
                setEventMessages($langs->trans("CustomerReturnValidated"), null, 'mesgs');
                header("Location: ".$_SERVER['PHP_SELF']."?id=".$object->id);
                exit;
            } else {
                $error_msg = $object->error ? $object->error : 'Unknown validation error';
                dol_syslog("card.php: Validation failed - ".$error_msg, LOG_ERR);
                setEventMessages($error_msg, $object->errors, 'errors');
                $action = '';
            }
        }
    }

    // Create credit note action
    if ($action == 'confirm_createcreditnote' && $confirm == 'yes') {
        if (!$user->hasRight('customerreturn', 'creer') && !$user->admin) {
            setEventMessages($langs->trans("NotEnoughPermissions"), null, 'errors');
            $action = '';
        } else {
            $creditnote_id = $object->createCreditNote($user);
            if ($creditnote_id > 0) {
                header("Location: ".DOL_URL_ROOT.'/compta/facture/card.php?id='.$creditnote_id);
                exit;
            } else {
                setEventMessages($object->error, $object->errors, 'errors');
                $action = '';
            }
        }
    }

    // Back to draft action (revert validated/closed to draft)
    if ($action == 'confirm_backtodraft' && $confirm == 'yes') {
        if (!$user->hasRight('customerreturn', 'creer') && !$user->admin) {
            setEventMessages($langs->trans("NotEnoughPermissions"), null, 'errors');
            $action = '';
        } else {
            $result = $object->backToDraft($user);
            if ($result > 0) {
                setEventMessages($langs->trans('CustomerReturnSetToDraft'), null, 'mesgs');
                header("Location: ".$_SERVER['PHP_SELF']."?id=".$object->id);
                exit;
            } else {
                setEventMessages($object->error, $object->errors, 'errors');
                $action = '';
            }
        }
    }

    // Set returned to supplier
    if ($action == 'confirm_setreturnedtosupplier' && $confirm == 'yes') {
        if (!$user->hasRight('customerreturn', 'creer') && !$user->admin) {
            setEventMessages($langs->trans("NotEnoughPermissions"), null, 'errors');
            $action = '';
        } else {
            $result = $object->setReturnedToSupplier($user);
            if ($result > 0) {
                setEventMessages($langs->trans('CustomerReturnReturnedToSupplier'), null, 'mesgs');
                header("Location: ".$_SERVER['PHP_SELF']."?id=".$object->id);
                exit;
            } else {
                setEventMessages($object->error, $object->errors, 'errors');
                $action = '';
            }
        }
    }

    // Set changed product for client
    if ($action == 'confirm_setchangedproductforclient' && $confirm == 'yes') {
        if (!$user->hasRight('customerreturn', 'creer') && !$user->admin) {
            setEventMessages($langs->trans("NotEnoughPermissions"), null, 'errors');
            $action = '';
        } else {
            $result = $object->setChangedProductForClient($user);
            if ($result > 0) {
                setEventMessages($langs->trans('CustomerReturnChangedProductForClient'), null, 'mesgs');
                header("Location: ".$_SERVER['PHP_SELF']."?id=".$object->id);
                exit;
            } else {
                setEventMessages($object->error, $object->errors, 'errors');
                $action = '';
            }
        }
    }

    // Set reimbursed money to client
    if ($action == 'confirm_setreimbursedmoneytoclient' && $confirm == 'yes') {
        if (!$user->hasRight('customerreturn', 'creer') && !$user->admin) {
            setEventMessages($langs->trans("NotEnoughPermissions"), null, 'errors');
            $action = '';
        } else {
            $result = $object->setReimbursedMoneyToClient($user);
            if ($result > 0) {
                setEventMessages($langs->trans('CustomerReturnReimbursedMoneyToClient'), null, 'mesgs');
                header("Location: ".$_SERVER['PHP_SELF']."?id=".$object->id);
                exit;
            } else {
                setEventMessages($object->error, $object->errors, 'errors');
                $action = '';
            }
        }
    }

}

/*
 * View
 */
$form = new Form($db);
$formcompany = new FormCompany($db);
$formfile = new FormFile($db);

$title = $langs->trans('CustomerReturn').' - '.$object->ref;
llxHeader('', $title);

$head = customerreturn_prepare_head($object);
print dol_get_fiche_head($head, 'card', $langs->trans("CustomerReturn"), -1, 'customerreturn@customerreturn');

// Confirmation dialogs
if ($action == 'validate') {
    $text = $langs->trans('ConfirmValidateCustomerReturn');
    print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('ValidateCustomerReturn'), $text, 'confirm_validate', '', 0, 1);
}

if ($action == 'createcreditnote') {
    $text = $langs->trans('ConfirmCreateCreditNote');
    print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('CreateCreditNote'), $text, 'confirm_createcreditnote', '', 0, 1);
}

if ($action == 'backtodraft') {
    $text = $langs->trans('ConfirmSetCustomerReturnToDraft');
    print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('SetToDraft'), $text, 'confirm_backtodraft', '', 0, 1);
}

if ($action == 'setreturnedtosupplier') {
    $text = $langs->trans('ConfirmSetReturnedToSupplier');
    print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('SetReturnedToSupplier'), $text, 'confirm_setreturnedtosupplier', '', 0, 1);
}

if ($action == 'setchangedproductforclient') {
    $text = $langs->trans('ConfirmSetChangedProductForClient');
    print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('SetChangedProductForClient'), $text, 'confirm_setchangedproductforclient', '', 0, 1);
}

if ($action == 'setreimbursedmoneytoclient') {
    $text = $langs->trans('ConfirmSetReimbursedMoneyToClient');
    print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('SetReimbursedMoneyToClient'), $text, 'confirm_setreimbursedmoneytoclient', '', 0, 1);
}

// Object card
$linkback = '<a href="'.dol_buildpath('/custom/customerreturn/list.php', 1).'">'.$langs->trans("BackToList").'</a>';

dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref');

print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<div class="underbanner clearboth"></div>';

print '<table class="border centpercent tableforfield">';

// Customer
print '<tr><td class="titlefield">'.$langs->trans("Customer").'</td><td>';
print $soc->getNomUrl(1);
print '</td></tr>';

// Date return
print '<tr><td>'.$langs->trans("DateReturn").'</td><td>';
print dol_print_date($object->date_return, 'day');
print '</td></tr>';

// Return reason
print '<tr><td>'.$langs->trans("ReturnReason").'</td><td>';
print dol_escape_htmltag($object->return_reason);
print '</td></tr>';

// Status
print '<tr><td>'.$langs->trans("Status").'</td><td>';
print $object->getLibStatut(5);
print '</td></tr>';

// Customer ref
if ($object->customer_ref) {
    print '<tr><td>'.$langs->trans("CustomerRef").'</td><td>';
    print dol_escape_htmltag($object->customer_ref);
    print '</td></tr>';
}

// Linked shipment
if ($object->fk_expedition > 0) {
    print '<tr><td>'.$langs->trans("Shipment").'</td><td>';
    require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
    $shipment = new Expedition($db);
    if ($shipment->fetch($object->fk_expedition) > 0) {
        print $shipment->getNomUrl(1);
    }
    print '</td></tr>';
}

print '</table>';

print '</div>';
print '<div class="fichehalfright">';

print '<div class="underbanner clearboth"></div>';
print '<table class="border centpercent tableforfield">';

// Total HT
print '<tr><td class="titlefield">'.$langs->trans("TotalHT").'</td><td>';
print price($object->total_ht, 0, $langs, 0, -1, -1, $conf->currency);
print '</td></tr>';

// Total TTC
print '<tr><td>'.$langs->trans("TotalTTC").'</td><td>';
print price($object->total_ttc, 0, $langs, 0, -1, -1, $conf->currency);
print '</td></tr>';

print '</table>';

print '</div>';
print '</div>';

print '<div class="clearboth"></div>';

print dol_get_fiche_end();

// Lines
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Description").'</td>';
print '<td class="center">'.$langs->trans("SerialNumber").'</td>';
print '<td class="center">'.$langs->trans("Warehouse").'</td>';
print '<td class="right">'.$langs->trans("Qty").'</td>';
print '<td class="right">'.$langs->trans("UnitPrice").'</td>';
print '<td class="right">'.$langs->trans("TotalHT").'</td>';
print '</tr>';

if (!empty($object->lines)) {
    foreach ($object->lines as $line) {
        print '<tr class="oddeven">';
        
        // Product/Description
        print '<td>';
        if ($line->fk_product > 0) {
            $product = new Product($db);
            $product->fetch($line->fk_product);
            print $product->getNomUrl(1);
            if ($line->description) {
                print '<br>'.dol_htmlentitiesbr($line->description);
            }
        } else {
            print dol_htmlentitiesbr($line->description);
        }
        print '</td>';
        
        // Serial number
        print '<td class="center">';
        print $line->batch ? dol_escape_htmltag($line->batch) : '-';
        print '</td>';
        
        // Warehouse
        print '<td class="center">';
        if ($line->fk_entrepot > 0) {
            $warehouse = new Entrepot($db);
            $warehouse->fetch($line->fk_entrepot);
            print $warehouse->getNomUrl(1);
        } else {
            print '-';
        }
        print '</td>';
        
        // Quantity
        print '<td class="right">'.price($line->qty).'</td>';
        
        // Unit price
        print '<td class="right">'.price($line->subprice).'</td>';
        
        // Total HT
        print '<td class="right">'.price($line->total_ht).'</td>';
        
        print '</tr>';
    }
} else {
    print '<tr><td colspan="6" class="center opacitymedium">'.$langs->trans("NoLines").'</td></tr>';
}

print '</table>';
print '</div>';

// Buttons
print '<div class="tabsAction">';

$usercanvalidate = ($user->hasRight('customerreturn', 'creer') || $user->admin);

if ($object->statut == CustomerReturn::STATUS_DRAFT) {
    if ($usercanvalidate) {
        print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=validate&token='.newToken().'">'.$langs->trans('Validate').'</a>';
    } else {
        print '<span class="butActionRefused classfortooltip" title="'.dol_escape_htmltag($langs->trans("NotEnoughPermissions")).'">'.$langs->trans('Validate').'</span>';
    }
}

if ($object->statut == CustomerReturn::STATUS_VALIDATED) {
    if ($usercanvalidate) {
        print '<div class="inline-block" title="'.$langs->trans('SetReturnedToSupplier').'"><a class="butAction btn-primary" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=setreturnedtosupplier&token='.newToken().'">'.$langs->trans('ReturnedToSupplier').'</a></div>';
        print '<div class="inline-block" title="'.$langs->trans('SetChangedProductForClient').'"><a class="butAction btn-secondary" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=setchangedproductforclient&token='.newToken().'">'.$langs->trans('ChangedProductForClient').'</a></div>';
        print '<div class="inline-block" title="'.$langs->trans('SetReimbursedMoneyToClient').'"><a class="butAction btn-success" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=setreimbursedmoneytoclient&token='.newToken().'">'.$langs->trans('ReimbursedMoneyToClient').'</a></div>';
    }
}

if ($object->statut == CustomerReturn::STATUS_CLOSED && empty($object->fk_facture)) {
    if ($usercanvalidate) {
        print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=createcreditnote&token='.newToken().'">'.$langs->trans('CreateCreditNote').'</a>';
    }
}

$reopenable_statuses = array(
    CustomerReturn::STATUS_VALIDATED,
    CustomerReturn::STATUS_RETURNED_TO_SUPPLIER,
    CustomerReturn::STATUS_CHANGED_PRODUCT_FOR_CLIENT,
    CustomerReturn::STATUS_REIMBURSED_MONEY_TO_CLIENT,
    CustomerReturn::STATUS_CLOSED
);

if (in_array($object->statut, $reopenable_statuses) && $usercanvalidate) {
    if ($object->fk_facture > 0) {
        print '<span class="butActionRefused classfortooltip" title="'.dol_escape_htmltag($langs->trans("ErrorCreditNoteAlreadyCreated")).'">'.$langs->trans('SetToDraft').'</span>';
    } else {
        print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=backtodraft&token='.newToken().'">'.$langs->trans('SetToDraft').'</a>';
    }
}

print '</div>';

// Add the missing sections here (attachments, related objects, last events)

print '<div class="clearboth"></div><br>';

/*
 * --- Linked files (Attached files) - robust with fallbacks
 */
print load_fiche_titre($langs->trans("AttachedFiles"), '', 'file-o');

// Build a safe $filedir with fallbacks
$relativepath = 'customerreturn/'.$object->ref;
$urlsource = $_SERVER["PHP_SELF"] . "?id=" . $object->id;
$genallowed = ($user->hasRight('customerreturn', 'creer') || $user->admin);
$delallowed = $genallowed;

// Primary: module specific multidir
$filedir = '';
if (!empty($conf->customerreturn->multidir_output) && !empty($conf->customerreturn->multidir_output[$object->entity])) {
    $filedir = $conf->customerreturn->multidir_output[$object->entity] . '/' . $object->ref;
}
// Secondary: global multidir
if (empty($filedir) && !empty($conf->multidir_output) && !empty($conf->multidir_output[$object->entity])) {
    $filedir = $conf->multidir_output[$object->entity] . '/customerreturn/' . $object->ref;
}
// Fallback: DOL_DATA_ROOT
if (empty($filedir)) {
    $filedir = DOL_DATA_ROOT . '/customerreturn/' . $object->ref;
}

// Log missing dir but still call showdocuments (it will show upload form if allowed)
if (!is_dir($filedir)) {
    dol_syslog("customerreturn: attachments directory not found: ".$filedir, LOG_DEBUG);
}

print $formfile->showdocuments('customerreturn', $relativepath, $filedir, $urlsource, $genallowed, $delallowed, $object->model_pdf ?? '', 1, 0, 0, 0, 0, '', '', '', $soc->default_lang);

/*
 * --- Related Objects (Linked objects)
 */
print '<div class="clearboth"></div><br>';
print load_fiche_titre($langs->trans("RelatedObjects"), '', 'link');

// Ensure element is set
if (empty($object->element)) $object->element = 'customerreturn';
if (empty($object->id)) dol_syslog("customerreturn: show linked objects called with empty id", LOG_WARNING);

// Show links to link elements (default Dolibarr behaviour)
print '<div class="fichecenter"><div class="fichehalfleft">';

// Allow linking with common objects: customerreturn, commande, facture, propal, expedition, societe
$linktoelem = $form->showLinkToObjectBlock($object, null, array('customerreturn','commande','facture','propal','expedition','societe'));
$somethingshown = $form->showLinkedObjectBlock($object, $linktoelem);

print '</div><div class="fichehalfright">';

// Show recent actions / events in right column (same as native modules)
include_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
$formactions = new FormActions($db);
$MAXEVENT = 10;
$morehtmlcenter = dolGetButtonTitle($langs->trans('SeeAll'), '', 'fa fa-bars imgforviewmode', dol_buildpath('/custom/customerreturn/agenda.php', 1).'?id='.$object->id);
$somethingshown = $formactions->showactions($object, $object->element.'@customerreturn', (is_object($object->thirdparty) ? $object->thirdparty->id : 0), 1, '', $MAXEVENT, '', $morehtmlcenter);

print '</div></div>';

/*
 * --- The last 10 events (history) - robust query + debug
 */
print '<div class="clearboth"></div><br>';


// End of page
llxFooter();
$db->close();


