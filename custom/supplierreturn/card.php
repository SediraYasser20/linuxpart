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
 * \ingroup supplierreturns
 * \brief   Supplier Return card
 * \note    Test modification for workflow validation
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

require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.product.class.php';

require_once './class/supplierreturn.class.php';
dol_include_once('/custom/supplierreturn/lib/supplierreturn.lib.php');
require_once './core/modules/supplierreturn/modules_supplierreturn.php';

// Load translation files required by the page
$langs->loadLangs(array("supplierreturn@supplierreturn", "other", "products", "stocks", "companies", "bills"));

// Get Parameters
$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');

$cancel = GETPOST('cancel', 'aZ09');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'supplierreturncard';
$backtopage = GETPOST('backtopage', 'alpha');
$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha');

$socid = GETPOSTINT('socid');
$lineid = GETPOSTINT('lineid');
$rank = (GETPOSTINT('rank') > 0) ? GETPOSTINT('rank') : -1;

// PDF
$hidedetails = (GETPOSTINT('hidedetails') ? GETPOSTINT('hidedetails') : (getDolGlobalString('MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS') ? 1 : 0));
$hidedesc = (GETPOSTINT('hidedesc') ? GETPOSTINT('hidedesc') : (getDolGlobalString('MAIN_GENERATE_DOCUMENTS_HIDE_DESC') ? 1 : 0));
$hideref = (GETPOSTINT('hideref') ? GETPOSTINT('hideref') : (getDolGlobalString('MAIN_GENERATE_DOCUMENTS_HIDE_REF') ? 1 : 0));

// Security check
if (!$user->hasRight('supplierreturn', 'lire') && !$user->admin) {
    accessforbidden();
}

$permissionnote = $user->hasRight('supplierreturn', 'creer') || $user->admin;
$permissiondellink = $user->hasRight('supplierreturn', 'creer') || $user->admin;
$permissiontoadd = $user->hasRight('supplierreturn', 'creer') || $user->admin;
$permissiontodelete = $user->hasRight('supplierreturn', 'supprimer') || $user->admin;

// Initialize supplierreturn configuration for document access
if (empty($conf->supplierreturn)) {
    $conf->supplierreturn = new stdClass();
    $conf->supplierreturn->enabled = 1;
    $conf->supplierreturn->dir_output = DOL_DATA_ROOT.'/supplierreturn';
}


// $upload_dir will be set later before actions_builddoc.inc.php

// Initialize technical object to manage hooks of page
$hookmanager->initHooks(array('supplierreturncard', 'globalcard'));

$object = new SupplierReturn($db);
$extrafields = new ExtraFields($db);

// fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);

// Load object
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';

// Update totals after loading object
if ($object->id > 0) {
    $object->updateTotal();
}

// Initialize technical objects
$form = new Form($db);
$formfile = new FormFile($db);
$formproduct = new FormProduct($db);
$formcompany = new FormCompany($db);

// Load supplier if object exists
$soc = null;
if ($object->id > 0 && $object->fk_soc > 0) {
    $soc = new Societe($db);
    $soc->fetch($object->fk_soc);
}

$title = $langs->trans("SupplierReturn");
$helpurl = '';

/*
 * ACTIONS
 */

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
    $error = 0;

    $backurlforlist = dol_buildpath('/custom/supplierreturn/list.php', 1);

    if (empty($backtopage) || ($cancel && empty($backurlforlist))) {
        if (empty($backtopage) || ($cancel && strpos($backtopage, '__ID__'))) {
            if (empty($id) && (($action != 'add' && $action != 'create') || $cancel)) {
                $backtopage = $backurlforlist;
            } else {
                $backtopage = dol_buildpath('/custom/supplierreturn/card.php', 1).'?id='.((!empty($id) && $id > 0) ? $id : '__ID__');
            }
        }
    }

    $triggermodname = 'SUPPLIERRETURN_MODIFY';

    // Action to update supplier return
    if ($action == 'update' && $permissiontoadd) {
        $error = 0;
        
        $object->fk_soc = GETPOSTINT('socid');
        $object->supplier_ref = GETPOST('supplier_ref', 'alpha');
        $object->return_reason = GETPOST('return_reason', 'alpha');
        $object->note_public = GETPOST('note_public', 'restricthtml');
        $object->note_private = GETPOST('note_private', 'restricthtml');
        
        if (!$error) {
            $result = $object->update($user);
            if ($result > 0) {
                header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
                exit;
            } else {
                setEventMessages($object->error, $object->errors, 'errors');
                $action = 'edit';
            }
        } else {
            $action = 'edit';
        }
    }

    // Handle additional fields for add action
    if ($action == 'add') {
        $object->fk_soc = GETPOSTINT('socid');
        $object->supplier_ref = GETPOST('supplier_ref', 'alpha');
        $object->return_reason = GETPOST('return_reason', 'alpha');
    }

    // Actions cancel, add, update, update_extras, confirm_validate, confirm_delete, confirm_deleteline, confirm_clone, confirm_close, confirm_setdraft, confirm_reopen
    include DOL_DOCUMENT_ROOT.'/core/actions_addupdatedelete.inc.php';

    // Force reload of object and supplier after standard actions
    if ($object->id > 0) {
        $object->fetch($object->id);
        if ($object->fk_soc > 0) {
            $soc = new Societe($db);
            $soc->fetch($object->fk_soc);
        }
    }

    // Actions when linking object each other
    include DOL_DOCUMENT_ROOT.'/core/actions_dellink.inc.php';

    // Actions when printing a doc from card
    include DOL_DOCUMENT_ROOT.'/core/actions_printing.inc.php';

    // Action to move up and down lines of object
    include DOL_DOCUMENT_ROOT.'/core/actions_lineupdown.inc.php';

    // Action to build doc - Set required variables for actions_builddoc.inc.php
    // Ensure supplierreturn config exists
    if (empty($conf->supplierreturn)) {
        $conf->supplierreturn = new stdClass();
        $conf->supplierreturn->enabled = 1;
        $conf->supplierreturn->dir_output = DOL_DATA_ROOT.'/supplierreturn';
    }
    
    $upload_dir = $conf->supplierreturn->dir_output;
    $modulepart = 'supplierreturn';  // Set modulepart for actions_builddoc.inc.php
    $usercangeneretedoc = $permissiontoadd;  // Allow user to generate documents
    
    // Debug: Log before and after actions_builddoc.inc.php
    dol_syslog("SupplierReturn card.php: Before actions_builddoc.inc.php - action=$action, upload_dir=$upload_dir", LOG_DEBUG);
    if ($action == 'builddoc') {
        dol_syslog("SupplierReturn card.php: builddoc action triggered with model=" . GETPOST('model', 'alpha'), LOG_DEBUG);
    }
    
    include DOL_DOCUMENT_ROOT.'/core/actions_builddoc.inc.php';
    
    dol_syslog("SupplierReturn card.php: After actions_builddoc.inc.php", LOG_DEBUG);

    // Actions to send emails
    $triggersendname = 'SUPPLIERRETURN_SENTBYMAIL';
    $autocopy = 'MAIN_MAIL_AUTOCOPY_SUPPLIERRETURN_TO';
    $trackid = 'supplierreturn'.$object->id;
    include DOL_DOCUMENT_ROOT.'/core/actions_sendmails.inc.php';

    // Action clone object
    if ($action == 'confirm_clone' && $confirm == 'yes' && $permissiontoadd) {
        $objectclone = clone $object;

        if ($objectclone->createFromClone($user, $socid) > 0) {
            header("Location: ".$_SERVER['PHP_SELF'].'?id='.$objectclone->id);
            exit;
        } else {
            setEventMessages($objectclone->error, $objectclone->errors, 'errors');
            $action = '';
        }
    }

    // Action to add line
    if ($action == 'addline' && $permissiontoadd) {
        $langs->load('errors');
        $error = 0;

        if (!empty($_SERVER['HTTP_REFERER']) && !preg_match('/card\.php/', $_SERVER['HTTP_REFERER'])) {
            $backtopage = $_SERVER['HTTP_REFERER'];
        }

        $idprodfournprice = GETPOSTINT('idprodfournprice');
        $qty = GETPOST('qty', 'alpha');
        $subprice = GETPOST('price_ht', 'alpha');
        $description = GETPOST('product_desc', 'restricthtml');
        $fk_entrepot = GETPOSTINT('entrepot_id');
        $batch = GETPOST('batch', 'alpha');

        $qty = price2num($qty);
        $subprice = price2num($subprice);

        if (!$error && empty($qty)) {
            setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Qty')), null, 'errors');
            $error++;
        }
        if (!$error && empty($subprice)) {
            setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Price')), null, 'errors');
            $error++;
        }
        
        // Vérifier qu'on a soit un produit soit une description (validation assouplie temporairement)
        if (!$error && empty($idprodfournprice) && empty(GETPOSTINT('productid')) && empty($description)) {
            // Debug pour comprendre le problème
            $debug_msg = 'Validation failed - idprodfournprice: '.$idprodfournprice.', productid: '.GETPOSTINT('productid').', description: "'.$description.'"';
            dol_syslog("SUPPLIERRETURN DEBUG: ".$debug_msg, LOG_WARNING);
            
            // Si pas de produit mais qu'on a qty et price, on accepte avec description par défaut
            if (!empty($qty) && !empty($subprice)) {
                $description = 'Ligne de retour sans produit spécifique';
                dol_syslog("SUPPLIERRETURN DEBUG: Using default description", LOG_WARNING);
            } else {
                setEventMessages('Vous devez sélectionner un produit ou saisir une description', null, 'errors');
                $error++;
            }
        }

        if (!$error && $object->statut == SupplierReturn::STATUS_DRAFT) {
            // Get product information
            $fk_product = 0;
            if ($idprodfournprice > 0) {
                // idprodfournprice est un ID de la table product_fournisseur_price
                $sql = "SELECT pfp.fk_product, pfp.ref_fourn, pfp.desc_fourn, p.ref, p.label 
                        FROM ".MAIN_DB_PREFIX."product_fournisseur_price pfp 
                        LEFT JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = pfp.fk_product 
                        WHERE pfp.rowid = ".(int)$idprodfournprice;
                $resql = $db->query($sql);
                if ($resql && $db->num_rows($resql) > 0) {
                    $obj = $db->fetch_object($resql);
                    $fk_product = $obj->fk_product;
                    if (empty($description)) {
                        // Utiliser la description fournisseur ou le label du produit
                        if (!empty($obj->desc_fourn)) {
                            $description = $obj->desc_fourn;
                        } elseif (!empty($obj->label)) {
                            $description = $obj->label;
                        } elseif (!empty($obj->ref)) {
                            $description = $obj->ref;
                        }
                    }
                    dol_syslog("SUPPLIERRETURN: Found product fournisseur - fk_product: $fk_product, desc: $description", LOG_DEBUG);
                } else {
                    dol_syslog("SUPPLIERRETURN: Product fournisseur price not found for ID: $idprodfournprice", LOG_WARNING);
                }
            } elseif (GETPOSTINT('productid') > 0) {
                // Direct product selection (when no supplier context)
                $fk_product = GETPOSTINT('productid');
                require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
                $product = new Product($db);
                if ($product->fetch($fk_product) > 0 && empty($description)) {
                    $description = $product->label;
                }
            }

            $result = $object->addLine(
                $fk_product,
                $qty,
                $subprice,
                $description,
                $fk_entrepot,
                $batch,
                0,
                $user
            );

            if ($result > 0) {
                // Update totals after adding line
                $object->updateTotal();
                
                unset($_POST['qty']);
                unset($_POST['price_ht']);
                unset($_POST['product_desc']);
                unset($_POST['entrepot_id']);
                unset($_POST['idprodfournprice']);
                unset($_POST['productid']);
                unset($_POST['batch']);
                // Redirect to avoid resubmission and ensure the new line is displayed
                header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
                exit;
            } else {
                setEventMessages($object->error, $object->errors, 'errors');
            }
        }
    }

    // Handle cancel edit line
    if (GETPOST('cancel', 'alpha')) {
        $action = '';
        header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
        exit;
    }
    
    // Action to update line
    if ($action == 'updateline' && $permissiontoadd && $lineid > 0) {
        $qty = GETPOST('qty', 'alpha');
        $subprice = GETPOST('price_ht', 'alpha');
        $description = GETPOST('product_desc', 'restricthtml');
        $fk_entrepot = GETPOSTINT('entrepot_id');
        $batch = GETPOST('batch', 'alpha');

        $qty = price2num($qty);
        $subprice = price2num($subprice);

        if ($object->statut == SupplierReturn::STATUS_DRAFT) {
            $result = $object->updateLine($lineid, $qty, $subprice, $description, $fk_entrepot, $batch, $user);

            if ($result >= 0) {
                // Update totals after updating line
                $object->updateTotal();
                
                // Redirect to avoid resubmission
                header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
                exit;
            } else {
                setEventMessages($object->error, $object->errors, 'errors');
            }
        } else {
            setEventMessages('Cannot modify line when supplier return is not in draft status', null, 'errors');
        }
    }

    // Action to delete line (direct deletion with JavaScript confirmation)
    if ($action == 'deleteline' && $lineid > 0 && $permissiontoadd) {
        if ($object->statut == SupplierReturn::STATUS_DRAFT) {
            $result = $object->deleteLine($lineid, $user);
            if ($result > 0) {
                // Update totals after deleting line
                $object->updateTotal();
                
                setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
                header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
                exit;
            } else {
                setEventMessages($object->error, $object->errors, 'errors');
            }
        } else {
            setEventMessages('Cannot delete line when supplier return is not in draft status', null, 'errors');
        }
    }

    // Action to delete line (with Dolibarr confirmation)
    if ($action == 'confirm_deleteline' && $confirm == 'yes' && $permissiontoadd) {
        if ($object->statut == SupplierReturn::STATUS_DRAFT) {
            $result = $object->deleteLine($lineid, $user);
            if ($result > 0) {
                // Update totals after deleting line
                $object->updateTotal();
                
                setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
                header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
                exit;
            } else {
                setEventMessages($object->error, $object->errors, 'errors');
            }
        } else {
            setEventMessages('Cannot delete line when supplier return is not in draft status', null, 'errors');
        }
    }

    // Actions to validate, approve, refuse, reopen
    if ($action == 'confirm_validate' && $confirm == 'yes' && $permissiontoadd) {
        $result = $object->validate($user);
        if ($result < 0) {
            setEventMessages($object->error, $object->errors, 'errors');
        } else {
            // Redirect to refresh the page and show the validated status
            header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
            exit;
        }
    }

    if ($action == 'confirm_process' && $confirm == 'yes' && $permissiontoadd) {
        $result = $object->process($user);
        if ($result < 0) {
            setEventMessages($object->error, $object->errors, 'errors');
        }
    }

    if ($action == 'confirm_cancel' && $confirm == 'yes' && $permissiontoadd) {
        $result = $object->cancel($user);
        if ($result < 0) {
            setEventMessages($object->error, $object->errors, 'errors');
        }
    }

    // Action to create credit note
    if ($action == 'confirm_createcreditnote' && $confirm == 'yes' && $permissiontoadd) {
        if ($object->statut == SupplierReturn::STATUS_CLOSED) {
            $result = $object->createCreditNote($user);
            if ($result > 0) {
                setEventMessages($langs->trans('CreditNoteCreated'), null, 'mesgs');
                header('Location: '.DOL_URL_ROOT.'/fourn/facture/card.php?id='.$result);
                exit;
            } else {
                setEventMessages($object->error, $object->errors, 'errors');
            }
        }
    }
}

/*
 * VIEW
 */

$form = new Form($db);
$formfile = new FormFile($db);
$formproduct = new FormProduct($db);

$title = $langs->trans("SupplierReturn");
$helpurl = '';
llxHeader('', $title, $helpurl);

// Part to create
if ($action == 'create') {
    print load_fiche_titre($langs->trans("NewObject", $langs->transnoentitiesnoconv("SupplierReturn")), '', 'object_'.$object->picto);

    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="add">';
    if ($backtopage) {
        print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
    }
    if ($backtopageforcancel) {
        print '<input type="hidden" name="backtopageforcancel" value="'.$backtopageforcancel.'">';
    }

    print dol_get_fiche_head(array(), '');

    print '<table class="border centpercent tableforfieldcreate">'."\n";

    // Ref - Hidden during creation, will be auto-generated
    print '<tr style="display: none;"><td>'.$langs->trans("Ref").'</td><td>';
    print '<input type="hidden" name="ref" value="'.(GETPOST("ref") ?: 'PROV').'" />';
    print '<span>'.$langs->trans("ToBeGenerated").'</span>';
    print '</td></tr>';

    // Supplier
    print '<tr><td class="fieldrequired">'.$langs->trans("Supplier").'</td><td>';
    $filter = '(s.fournisseur:=:1)';
    // Pre-select supplier from POST (form submission) or GET (URL parameter)
    $selected_socid = GETPOSTINT('socid');
    if (!$selected_socid && isset($_GET['socid'])) {
        $selected_socid = intval($_GET['socid']);
    }
    print $form->select_company($selected_socid, 'socid', $filter, 'SelectThirdParty', 0, 0, null, 0, 'minwidth300');
    print '</td></tr>';

    // Supplier reference
    print '<tr><td>'.$langs->trans("SupplierRef").'</td><td>';
    print '<input type="text" name="supplier_ref" value="'.dol_escape_htmltag(GETPOST('supplier_ref')).'" maxlength="100" class="flat minwidth200">';
    print '</td></tr>';
    
    // Return reason - Utiliser les motifs configurés depuis l'administration
    print '<tr><td>'.$langs->trans("ReturnReason").'</td><td>';
    dol_include_once('/custom/supplierreturn/lib/supplierreturn.lib.php');
    $return_reasons = supplierreturns_get_return_reasons();
    print $form->selectarray('return_reason', $return_reasons, GETPOST('return_reason'), 1);
    print '</td></tr>';

    // Note public
    print '<tr><td>'.$langs->trans("NotePublic").'</td><td>';
    $doleditor = new DolEditor('note_public', GETPOST("note_public"), '', 80, 'dolibarr_notes', 'In', 0, false, empty($conf->global->FCKEDITOR_ENABLE_NOTE_PUBLIC) ? 0 : 1, ROWS_3, '90%');
    print $doleditor->Create(1);
    print '</td></tr>';

    // Note private
    print '<tr><td>'.$langs->trans("NotePrivate").'</td><td>';
    $doleditor = new DolEditor('note_private', GETPOST("note_private"), '', 80, 'dolibarr_notes', 'In', 0, false, empty($conf->global->FCKEDITOR_ENABLE_NOTE_PRIVATE) ? 0 : 1, ROWS_3, '90%');
    print $doleditor->Create(1);
    print '</td></tr>';

    print '</table>'."\n";

    print dol_get_fiche_end();

    print $form->buttonsSaveCancel("Create");

    print '</form>';
}

// Part to edit record
if (($id || $ref) && $action == 'edit') {
    print load_fiche_titre($langs->trans("SupplierReturn"), '', 'object_'.$object->picto);

    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="update">';
    print '<input type="hidden" name="id" value="'.$object->id.'">';
    if ($backtopage) {
        print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
    }
    if ($backtopageforcancel) {
        print '<input type="hidden" name="backtopageforcancel" value="'.$backtopageforcancel.'">';
    }

    print dol_get_fiche_head();

    print '<table class="border centpercent tableforfieldedit">'."\n";

    // Ref
    print '<tr><td>'.$langs->trans("Ref").'</td><td>';
    print $object->ref;
    print '</td></tr>';

    // Supplier
    print '<tr><td>'.$langs->trans("Supplier").'</td><td>';
    $filter = '(s.fournisseur:=:1)';
    print $form->select_company($object->fk_soc, 'socid', $filter, 'SelectThirdParty', 0, 0, null, 0, 'minwidth300');
    print '</td></tr>';

    // Supplier reference
    print '<tr><td>'.$langs->trans("SupplierRef").'</td><td>';
    print '<input type="text" name="supplier_ref" value="'.dol_escape_htmltag($object->supplier_ref).'" maxlength="100" class="flat minwidth200">';
    print '</td></tr>';
    
    // Return reason - Utiliser les motifs configurés depuis l'administration
    print '<tr><td>'.$langs->trans("ReturnReason").'</td><td>';
    dol_include_once('/custom/supplierreturn/lib/supplierreturn.lib.php');
    $return_reasons = supplierreturns_get_return_reasons();
    print $form->selectarray('return_reason', $return_reasons, $object->return_reason, 1);
    print '</td></tr>';

    // Note public
    print '<tr><td>'.$langs->trans("NotePublic").'</td><td>';
    $doleditor = new DolEditor('note_public', $object->note_public, '', 80, 'dolibarr_notes', 'In', 0, false, empty($conf->global->FCKEDITOR_ENABLE_NOTE_PUBLIC) ? 0 : 1, ROWS_3, '90%');
    print $doleditor->Create(1);
    print '</td></tr>';

    // Note private
    print '<tr><td>'.$langs->trans("NotePrivate").'</td><td>';
    $doleditor = new DolEditor('note_private', $object->note_private, '', 80, 'dolibarr_notes', 'In', 0, false, empty($conf->global->FCKEDITOR_ENABLE_NOTE_PRIVATE) ? 0 : 1, ROWS_3, '90%');
    print $doleditor->Create(1);
    print '</td></tr>';

    print '</table>';

    print dol_get_fiche_end();

    print $form->buttonsSaveCancel();

    print '</form>';
}

// Part to show record
if ($object->id > 0 && (empty($action) || ($action != 'edit' && $action != 'create'))) {
    $res = $object->fetch_optionals();

    // Update soc after object is loaded
    if ($object->fk_soc > 0) {
        if (!is_object($soc) || $soc->id != $object->fk_soc) {
            $soc = new Societe($db);
            $soc->fetch($object->fk_soc);
        }
    }

    $head = supplierreturn_prepare_head($object);
    print dol_get_fiche_head($head, 'card', $langs->trans("SupplierReturn"), -1, $object->picto);

    $formconfirm = '';

    // Confirmation to delete
    if ($action == 'delete') {
        $formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('DeleteSupplierReturn'), $langs->trans('ConfirmDeleteObject'), 'confirm_delete', '', 0, 1);
    }
    // Confirmation to delete line
    if ($action == 'deleteline') {
        $formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id.'&lineid='.$lineid, $langs->trans('DeleteLine'), $langs->trans('ConfirmDeleteLine'), 'confirm_deleteline', '', 0, 1);
    }
    // Clone confirmation
    if ($action == 'clone') {
        $formquestion = array();
        $formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('ToClone'), $langs->trans('ConfirmCloneAsk', $object->ref), 'confirm_clone', $formquestion, 'yes', 1);
    }

    // Confirmation of action xxxx
    if ($action == 'validate') {
        $formquestion = array();
        $formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('ValidateSupplierReturn'), $langs->trans('ConfirmValidateSupplierReturn'), 'confirm_validate', $formquestion, 0, 1, 220);
    }

    if ($action == 'process') {
        $formquestion = array();
        $formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('ProcessSupplierReturn'), $langs->trans('ConfirmProcessSupplierReturn'), 'confirm_process', $formquestion, 0, 1, 220);
    }

    if ($action == 'cancel') {
        $formquestion = array();
        $formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('CancelSupplierReturn'), $langs->trans('ConfirmCancelSupplierReturn'), 'confirm_cancel', $formquestion, 0, 1, 220);
    }

    if ($action == 'createcreditnote') {
        $formquestion = array();
        $formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('CreateCreditNote'), $langs->trans('ConfirmCreateCreditNote'), 'confirm_createcreditnote', $formquestion, 0, 1, 220);
    }

    // Print form confirm
    print $formconfirm;

    // Object card
    $linkback = '<a href="'.dol_buildpath('/custom/supplierreturn/list.php', 1).'?restore_lastsearch_values=1'.(!empty($socid) ? '&socid='.$socid : '').'">'.$langs->trans("BackToList").'</a>';

    $morehtmlref = '<div class="refidno">';
    // Ref supplier (following native Dolibarr pattern)
    $morehtmlref .= $form->editfieldkey("RefSupplier", 'supplier_ref', $object->supplier_ref, $object, $permissiontoadd, 'string', '', 0, 1);
    $morehtmlref .= $form->editfieldval("RefSupplier", 'supplier_ref', $object->supplier_ref, $object, $permissiontoadd, 'string', '', null, null, '', 1);
    // Thirdparty
    if (isModEnabled('societe')) {
        $morehtmlref .= '<br>'.$langs->trans('ThirdParty').' : ';
        if ($action != 'edit' && $permissiontoadd) {
            $morehtmlref .= '<a class="editfielda" href="'.$_SERVER['PHP_SELF'].'?action=edit&token='.newToken().'&id='.$object->id.'">'.img_edit($langs->transnoentitiesnoconv('SetThirdParty')).'</a> ';
        }
        $morehtmlref .= $form->form_thirdparty($_SERVER['PHP_SELF'].'?id='.$object->id, $object->fk_soc, 'none', '', 1, 0, 0, array(), 1);
    }
    $morehtmlref .= '</div>';

    dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

    print '<div class="fichecenter">';
    print '<div class="fichehalfleft">';
    print '<div class="underbanner clearboth"></div>';
    print '<table class="border centpercent tableforfield">'."\n";

    // Return reason
    print '<tr><td>'.$langs->trans("ReturnReason").'</td><td>';
    if ($object->return_reason) {
        dol_include_once('/custom/supplierreturn/lib/supplierreturn.lib.php');
        $return_reasons = supplierreturns_get_return_reasons();
        $reason_label = isset($return_reasons[$object->return_reason]) ? $return_reasons[$object->return_reason] : $object->return_reason;
        print dol_escape_htmltag($reason_label);
    }
    print '</td></tr>';

    // Date creation
    print '<tr><td>'.$langs->trans("DateCreation").'</td><td>';
    print dol_print_date($object->date_creation, 'dayhour');
    print '</td></tr>';

    print '</table>';
    print '</div>';
    
    // RIGHT PANEL - TOTALS (like native modules)
    print '<div class="fichehalfright">';
    print '<div class="underbanner clearboth"></div>';
    print '<table class="border tableforfield centpercent">';
    
    // Display totals in a prominent way like native modules
    if (!empty($object->total_ht) || !empty($object->total_ttc)) {
        print '<tr class="liste_total">';
        print '<td class="liste_titre">' . $langs->trans("AmountHT") . '</td>';
        print '<td class="liste_titre amount">' . price($object->total_ht ? $object->total_ht : 0, 0, '', 1, -1, -1, $conf->currency) . '</td>';
        print '</tr>';
        
        // VAT (if different from 0)
        $total_vat = $object->total_ttc - $object->total_ht;
        if ($total_vat != 0) {
            print '<tr>';
            print '<td>' . $langs->trans("AmountVAT") . '</td>';
            print '<td class="amount">' . price($total_vat, 0, '', 1, -1, -1, $conf->currency) . '</td>';
            print '</tr>';
        }
        
        print '<tr class="liste_total">';
        print '<td class="liste_titre">' . $langs->trans("AmountTTC") . '</td>';
        print '<td class="liste_titre amount"><b>' . price($object->total_ttc ? $object->total_ttc : 0, 0, '', 1, -1, -1, $conf->currency) . '</b></td>';
        print '</tr>';
    } else {
        print '<tr><td colspan="2"><div class="info">' . $langs->trans("NoAmountYet") . '</div></td></tr>';
    }
    
    print '</table>';
    print '</div>';
    print '</div>';

    print '<div class="clearboth"></div>';

    print dol_get_fiche_end();

    /*
     * Lines
     */

    print '<form name="addproduct" id="addproduct" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.(($action != 'editline') ? '' : '#line_'.GETPOSTINT('lineid')).'" method="POST">'."\n";
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="'.(($action != 'editline') ? 'addline' : 'updateline').'">';
    print '<input type="hidden" name="mode" value="">';
    print '<input type="hidden" name="page_y" value="">';
    print '<input type="hidden" name="id" value="'.$object->id.'">';
    if ($action == 'editline') {
        print '<input type="hidden" name="lineid" value="'.GETPOSTINT('lineid').'">';
    }

    if (!empty($conf->use_javascript_ajax) && $object->statut == 0) {
        include DOL_DOCUMENT_ROOT.'/core/tpl/ajaxrow.tpl.php';
    }

    print '<div class="div-table-responsive-no-min">';
    if (!empty($object->lines) || ($object->statut == SupplierReturn::STATUS_DRAFT && $permissiontoadd && $action != 'selectlines' && $action != 'editline')) {
        print '<table id="tablelines" class="noborder noshadow" width="100%">';
    }

    if (!empty($object->lines)) {
        $object->printObjectLines($action, $mysoc, null, GETPOSTINT('lineid'), 1);
    }

    // Form to add new line
    if ($object->statut == SupplierReturn::STATUS_DRAFT && $permissiontoadd && $action != 'selectlines') {
        if ($action != 'editline') {
            // Add products/services form
            $parameters = array();
            $reshook = $hookmanager->executeHooks('formAddObjectLine', $parameters, $object, $action);
            if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
            if (empty($reshook))
                $object->formAddObjectLine(1, $mysoc, $soc);
        }
    }

    if (!empty($object->lines) || ($object->statut == SupplierReturn::STATUS_DRAFT && $permissiontoadd && $action != 'selectlines' && $action != 'editline')) {
        print '</table>';
    }
    print '</div>';

    print "</form>\n";

    // Buttons for actions
    if ($action != 'presend' && $action != 'editline') {
        print '<div class="tabsAction">'."\n";
        $parameters = array();
        $reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action);
        if ($reshook < 0) {
            setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
        }

        if (empty($reshook)) {
            // Send
            if (empty($user->socid)) {
                print dolGetButtonAction('', $langs->trans('SendMail'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=presend&mode=init&token='.newToken().'#formmailbeforetitle');
            }

            // Back to draft
            if ($object->statut == SupplierReturn::STATUS_VALIDATED || $object->statut == SupplierReturn::STATUS_CLOSED) {
                print dolGetButtonAction('', $langs->trans('SetToDraft'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=confirm_cancel&confirm=yes&token='.newToken(), '', $permissiontoadd);
            }

            // Modify
            if ($object->statut == SupplierReturn::STATUS_DRAFT) {
                print dolGetButtonAction('', $langs->trans('Modify'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit&token='.newToken(), '', $permissiontoadd);
            }

            // Validate
            if ($object->statut == SupplierReturn::STATUS_DRAFT) {
                if (empty($object->table_element_line) || (is_array($object->lines) && count($object->lines) > 0)) {
                    print dolGetButtonAction('', $langs->trans('Validate'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=confirm_validate&confirm=yes&token='.newToken(), '', $permissiontoadd);
                } else {
                    $langs->load("errors");
                    print dolGetButtonAction($langs->trans("ErrorAddAtLeastOneLineFirst"), $langs->trans("Validate"), 'default', '#', '', 0);
                }
            }

            // Process
            if ($object->statut == SupplierReturn::STATUS_VALIDATED) {
                print dolGetButtonAction('', $langs->trans('Process'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=confirm_process&confirm=yes&token='.newToken(), '', $permissiontoadd);
            }

            // Create credit note
            if ($object->statut == SupplierReturn::STATUS_CLOSED) {
                print dolGetButtonAction('', $langs->trans('CreateCreditNote'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=confirm_createcreditnote&confirm=yes&token='.newToken(), '', $permissiontoadd);
            }

            // Clone
            print dolGetButtonAction('', $langs->trans('ToClone'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=clone&token='.newToken(), '', $permissiontoadd);

            // Delete
            print dolGetButtonAction('', $langs->trans('Delete'), 'delete', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete&token='.newToken(), '', $permissiontodelete || ($object->statut == SupplierReturn::STATUS_DRAFT && $permissiontoadd));
        }
        print '</div>'."\n";
    }

    // Select mail models is same action as presend
    if (GETPOST('modelselected')) {
        $action = 'presend';
    }

    if ($action != 'presend') {
        print '<div class="fichecenter"><div class="fichehalfleft">';
        print '<a name="builddoc"></a>';

        $includedocgeneration = 1; // Enable PDF generation

        // Documents
        if ($includedocgeneration) {
            $objref = dol_sanitizeFileName(str_replace(array('(', ')'), '', $object->ref));
            $relativepath = $objref.'/'.$objref.'.pdf';
            
            // Initialize module configuration for document management (consistent modulepart)
            if (empty($conf->supplierreturn)) {
                $conf->supplierreturn = new stdClass();
                $conf->supplierreturn->enabled = 1;
                $conf->supplierreturn->dir_output = DOL_DATA_ROOT.'/supplierreturn';
                $conf->supplierreturn->multidir_output = array();
                $conf->supplierreturn->multidir_output[$conf->entity] = DOL_DATA_ROOT.'/supplierreturn';
            }
            
            // Ensure model_pdf is set
            if (empty($object->model_pdf)) {
                $object->model_pdf = 'standard';
            }
            
            $filedir = $conf->supplierreturn->dir_output.'/'.$objref;
            $urlsource = $_SERVER["PHP_SELF"]."?id=".$object->id;
            $genallowed = $permissiontoadd;
            $delallowed = $permissiontodelete;
            $defaultlang = $langs->defaultlang;
            if (!empty($object->thirdparty) && !empty($object->thirdparty->default_lang)) {
                $defaultlang = $object->thirdparty->default_lang;
            }
            
            // Use FormFile system with debug
            include_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
            include_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
            include_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
            
            // Debug log
            dol_syslog("SupplierReturn card.php: object->ref='".$object->ref."', objref='$objref', filedir=$filedir", LOG_DEBUG);
            
            // Debug: Check if PDF model is registered
            $sql = "SELECT nom FROM ".MAIN_DB_PREFIX."document_model WHERE type = 'supplierreturn' AND nom = 'standard'";
            $resql = $db->query($sql);
            if ($resql && $db->num_rows($resql) == 0) {
                dol_syslog("SupplierReturn card.php: PDF model 'standard' not found, registering it", LOG_DEBUG);
                
                // Register PDF model
                $sql = "INSERT INTO ".MAIN_DB_PREFIX."document_model (nom, type, libelle, description) VALUES ('standard', 'supplierreturn', 'Standard supplier return template', 'Default PDF template for supplier returns')";
                $result = $db->query($sql);
                dol_syslog("SupplierReturn card.php: PDF model registration result: " . ($result ? "SUCCESS" : "FAILED: " . $db->lasterror()), LOG_DEBUG);
            } else {
                dol_syslog("SupplierReturn card.php: PDF model 'standard' already exists", LOG_DEBUG);
            }
            
            // Test direct PDF generation if button clicked
            if (GETPOST('action') == 'builddoc' && GETPOST('model') == 'standard') {
                dol_syslog("SupplierReturn card.php: Direct PDF generation triggered", LOG_DEBUG);
                
                dol_include_once('/custom/supplierreturn/core/modules/supplierreturn/pdf/pdf_standard.php');
                
                if (class_exists('pdf_standard')) {
                    $pdf_gen = new pdf_standard($db);
                    $result = $pdf_gen->write_file($object, $langs);
                    
                    if ($result > 0) {
                        dol_syslog("SupplierReturn card.php: Direct PDF generation SUCCESS", LOG_DEBUG);
                        $mesg = "PDF generated successfully";
                    } else {
                        dol_syslog("SupplierReturn card.php: Direct PDF generation FAILED: " . $pdf_gen->error, LOG_DEBUG);
                        $mesg = "PDF generation failed: " . $pdf_gen->error;
                    }
                    
                    setEventMessages($mesg, null, ($result > 0) ? 'mesgs' : 'errors');
                }
            }
            
            $formfile = new FormFile($db);
            print $formfile->showdocuments('supplierreturn', $objref, $filedir, $urlsource, 
                $genallowed, $delallowed, $object->model_pdf, 1, 0, 0, 0, 0, '', '', '', 
                $defaultlang, '', $object);
        }

        // Show links to link elements
        $linktoelem = $form->showLinkToObjectBlock($object, null, array('supplierreturn'));
        $somethingshown = $form->showLinkedObjectBlock($object, $linktoelem);

        print '</div><div class="fichehalfright">';

        $MAXEVENT = 10;

        $morehtmlcenter = dolGetButtonTitle($langs->trans('SeeAll'), '', 'fa fa-bars imgforviewmode', dol_buildpath('/custom/supplierreturn/agenda.php', 1).'?id='.$object->id);

        // List of actions on element
        include_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
        $formactions = new FormActions($db);
        $somethingshown = $formactions->showactions($object, $object->element.'@'.$object->module, (is_object($object->thirdparty) ? $object->thirdparty->id : 0), 1, '', $MAXEVENT, '', $morehtmlcenter);

        print '</div></div>';
    }

    // Presend form
    $modelmail = 'supplierreturn';
    $defaulttopic = 'InformationMessage';
    $diroutput = $conf->supplierreturn->dir_output;
    $trackid = 'supplierreturn'.$object->id;

    include DOL_DOCUMENT_ROOT.'/core/tpl/card_presend.tpl.php';
}

// End of page
llxFooter();
$db->close();
