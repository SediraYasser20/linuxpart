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
 * \file    admin/return_reasons.php
 * \ingroup supplierreturns
 * \brief   Configuration des motifs de retour - Style dictionnaire
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

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formadmin.class.php';
require_once '../lib/supplierreturn.lib.php';

// Translations
$langs->loadLangs(array("admin", "errors", "supplierreturn@supplierreturn"));

// Parameters
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$reason_key = GETPOST('reason_key', 'alpha');
$reason_label = GETPOST('reason_label', 'alpha');
$rowid = GETPOST('rowid', 'int');
$search_code = GETPOST('search_code', 'alpha');
$search_label = GETPOST('search_label', 'alpha');

$error = 0;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
if (!$sortfield) $sortfield = "reason_key";
if (!$sortorder) $sortorder = "ASC";

// Security check
if (!$user->admin) {
    accessforbidden();
}

/*
 * Actions
 */

// Add new reason
if ($action == 'add' && !empty($reason_key) && !empty($reason_label)) {
    $existing_reasons = getDolGlobalString('SUPPLIERRETURN_RETURN_REASONS', '');
    $reasons_array = array();
    
    if ($existing_reasons) {
        $reasons_array = json_decode($existing_reasons, true);
        if (!is_array($reasons_array)) {
            $reasons_array = array();
        }
    }
    
    // Check if key already exists
    if (isset($reasons_array[$reason_key])) {
        setEventMessages($langs->trans("ReturnReasonKeyAlreadyExists"), null, 'errors');
    } else {
        $reasons_array[$reason_key] = $reason_label;
        $result = dolibarr_set_const($db, 'SUPPLIERRETURN_RETURN_REASONS', json_encode($reasons_array), 'chaine', 0, '', $conf->entity);
        
        if ($result > 0) {
            setEventMessages($langs->trans("ReturnReasonAdded"), null, 'mesgs');
        } else {
            setEventMessages($langs->trans("Error"), null, 'errors');
        }
    }
    
    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

// Delete reason
if ($action == 'confirm_delete' && $confirm == 'yes' && !empty($reason_key)) {
    $existing_reasons = getDolGlobalString('SUPPLIERRETURN_RETURN_REASONS', '');
    $reasons_array = array();
    
    if ($existing_reasons) {
        $reasons_array = json_decode($existing_reasons, true);
        if (is_array($reasons_array) && isset($reasons_array[$reason_key])) {
            unset($reasons_array[$reason_key]);
            $result = dolibarr_set_const($db, 'SUPPLIERRETURN_RETURN_REASONS', json_encode($reasons_array), 'chaine', 0, '', $conf->entity);
            
            if ($result > 0) {
                setEventMessages($langs->trans("ReturnReasonDeleted"), null, 'mesgs');
            } else {
                setEventMessages($langs->trans("Error"), null, 'errors');
            }
        }
    }
    
    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

// Update reason
if ($action == 'update' && !empty($reason_key) && !empty($reason_label)) {
    $existing_reasons = getDolGlobalString('SUPPLIERRETURN_RETURN_REASONS', '');
    $reasons_array = array();
    
    if ($existing_reasons) {
        $reasons_array = json_decode($existing_reasons, true);
        if (is_array($reasons_array)) {
            $reasons_array[$reason_key] = $reason_label;
            $result = dolibarr_set_const($db, 'SUPPLIERRETURN_RETURN_REASONS', json_encode($reasons_array), 'chaine', 0, '', $conf->entity);
            
            if ($result > 0) {
                setEventMessages($langs->trans("ReturnReasonUpdated"), null, 'mesgs');
            } else {
                setEventMessages($langs->trans("Error"), null, 'errors');
            }
        }
    }
    
    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

// Reset to default reasons
if ($action == 'confirm_reset' && $confirm == 'yes') {
    $default_reasons = array(
        'defective' => $langs->trans('defective'),
        'damaged' => $langs->trans('damaged'),
        'wrong_item' => $langs->trans('wrong_item'),
        'excess_quantity' => $langs->trans('excess_quantity'),
        'quality_issue' => $langs->trans('quality_issue'),
        'not_as_described' => $langs->trans('not_as_described'),
        'other' => $langs->trans('other')
    );
    
    $result = dolibarr_set_const($db, 'SUPPLIERRETURN_RETURN_REASONS', json_encode($default_reasons), 'chaine', 0, '', $conf->entity);
    
    if ($result > 0) {
        setEventMessages($langs->trans("DefaultReasonsRestored"), null, 'mesgs');
    } else {
        setEventMessages($langs->trans("Error"), null, 'errors');
    }
    
    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

/*
 * View
 */

$form = new Form($db);
$formadmin = new FormAdmin($db);

$page_name = "SupplierReturnsSetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Configuration header with tabs
$head = supplierreturnAdminPrepareHead();

print dol_get_fiche_head($head, 'return_reasons', $langs->trans($page_name), -1, 'supplierreturn@supplierreturn');

print '<span class="opacitymedium">'.$langs->trans("SupplierReturnReturnReasonsDesc").'</span><br><br>';

// Get current reasons
$existing_reasons = getDolGlobalString('SUPPLIERRETURN_RETURN_REASONS', '');
$reasons_array = array();

if ($existing_reasons) {
    $reasons_array = json_decode($existing_reasons, true);
    if (!is_array($reasons_array)) {
        $reasons_array = array();
    }
}

// If no reasons configured, show default ones
if (empty($reasons_array)) {
    $reasons_array = array(
        'defective' => $langs->trans('defective'),
        'damaged' => $langs->trans('damaged'),
        'wrong_item' => $langs->trans('wrong_item'),
        'excess_quantity' => $langs->trans('excess_quantity'),
        'quality_issue' => $langs->trans('quality_issue'),
        'not_as_described' => $langs->trans('not_as_described'),
        'other' => $langs->trans('other')
    );
}

/*
 * Buttons
 */
if ($action != 'edit') {
    print '<div class="tabsAction">';
    print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=create&token='.newToken().'">'.$langs->trans("AddNewReturnReason").'</a>';
    print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=reset&token='.newToken().'">'.$langs->trans("ResetToDefaultReasons").'</a>';
    print '</div>';
    print '<br>';
}

/*
 * Search form
 */
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';

print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';

// Search fields
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre">';
print '<input type="text" class="maxwidth150" name="search_code" value="'.dol_escape_htmltag($search_code).'" placeholder="'.$langs->trans("Code").'">';
print '</td>';
print '<td class="liste_titre">';
print '<input type="text" class="maxwidth200" name="search_label" value="'.dol_escape_htmltag($search_label).'" placeholder="'.$langs->trans("Label").'">';
print '</td>';
print '<td class="liste_titre center">';
print $form->showFilterButtons();
print '</td>';
print '</tr>';

// Headers
print '<tr class="liste_titre">';
print getTitleFieldOfList($langs->trans("ReturnReasonKey"), 0, $_SERVER["PHP_SELF"], "reason_key", "", "", "", $sortfield, $sortorder, "");
print getTitleFieldOfList($langs->trans("ReturnReasonLabel"), 0, $_SERVER["PHP_SELF"], "reason_label", "", "", "", $sortfield, $sortorder, "");
print getTitleFieldOfList($langs->trans("Action"), 0, $_SERVER["PHP_SELF"], "", "", "", '', '', '', 'maxwidthsearch center ');
print '</tr>';

// Form for new entry
if ($action == 'create') {
    print '<tr class="pair">';
    print '<td>';
    print '<input type="text" name="reason_key" size="20" maxlength="50" value="'.dol_escape_htmltag($reason_key).'" placeholder="defective" required>';
    print '</td>';
    print '<td>';
    print '<input type="text" name="reason_label" size="40" maxlength="255" value="'.dol_escape_htmltag($reason_label).'" placeholder="'.$langs->trans("DefectiveProduct").'" required>';
    print '</td>';
    print '<td class="center">';
    print '<input type="hidden" name="action" value="add">';
    print '<input type="submit" class="button small" value="'.$langs->trans("Add").'">';
    print ' <input type="submit" class="button button-cancel small" name="cancel" value="'.$langs->trans("Cancel").'">';
    print '</td>';
    print '</tr>';
}

// Apply search filters
$filtered_reasons = $reasons_array;
if (!empty($search_code) || !empty($search_label)) {
    $filtered_reasons = array();
    foreach ($reasons_array as $key => $label) {
        $match = true;
        if (!empty($search_code) && stripos($key, $search_code) === false) {
            $match = false;
        }
        if (!empty($search_label) && stripos($label, $search_label) === false) {
            $match = false;
        }
        if ($match) {
            $filtered_reasons[$key] = $label;
        }
    }
}

// Sort results
if ($sortfield == 'reason_key') {
    if ($sortorder == 'DESC') {
        krsort($filtered_reasons);
    } else {
        ksort($filtered_reasons);
    }
} else { // sort by label
    if ($sortorder == 'DESC') {
        arsort($filtered_reasons);
    } else {
        asort($filtered_reasons);
    }
}

// List existing reasons
$i = 0;
foreach ($filtered_reasons as $key => $label) {
    $i++;
    print '<tr class="oddeven">';
    
    if ($action == 'edit' && $reason_key == $key) {
        // Edit mode
        print '<td>'.dol_escape_htmltag($key);
        print '<input type="hidden" name="reason_key" value="'.dol_escape_htmltag($key).'">';
        print '</td>';
        print '<td>';
        print '<input type="text" name="reason_label" value="'.dol_escape_htmltag($label).'" size="40" maxlength="255" required>';
        print '</td>';
        print '<td class="center">';
        print '<input type="hidden" name="action" value="update">';
        print '<input type="submit" class="button small" value="'.$langs->trans("Save").'">';
        print ' <a class="button button-cancel small" href="'.$_SERVER["PHP_SELF"].'">'.$langs->trans("Cancel").'</a>';
        print '</td>';
    } else {
        // View mode
        print '<td>'.dol_escape_htmltag($key).'</td>';
        print '<td>'.dol_escape_htmltag($label).'</td>';
        print '<td class="center">';
        print '<a class="editfielda marginrightonly" href="'.$_SERVER["PHP_SELF"].'?action=edit&token='.newToken().'&reason_key='.urlencode($key).'">';
        print img_edit($langs->trans("Edit"));
        print '</a>';
        print '<a class="marginleftonly" href="'.$_SERVER["PHP_SELF"].'?action=delete&token='.newToken().'&reason_key='.urlencode($key).'">';
        print img_delete($langs->trans("Delete"));
        print '</a>';
        print '</td>';
    }
    
    print '</tr>';
}

if (empty($filtered_reasons)) {
    print '<tr><td colspan="3" class="center opacitymedium">';
    if (!empty($search_code) || !empty($search_label)) {
        print $langs->trans("NoRecordFound");
    } else {
        print $langs->trans("NoReturnReasonsConfigured");
    }
    print '</td></tr>';
}

print '</table>';
print '</div>';
print '</form>';

print dol_get_fiche_end();

llxFooter();
$db->close();

// Confirm delete
if ($action == 'delete') {
    $form = new Form($db);
    print $form->formconfirm($_SERVER["PHP_SELF"].'?reason_key='.urlencode($reason_key), $langs->trans('DeleteReturnReason'), $langs->trans('ConfirmDeleteReturnReason'), 'confirm_delete', '', 0, 1);
}

// Confirm reset
if ($action == 'reset') {
    $form = new Form($db);
    print $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans('ResetToDefaultReasons'), $langs->trans('ConfirmResetToDefaultReasons'), 'confirm_reset', '', 0, 1);
}
?>