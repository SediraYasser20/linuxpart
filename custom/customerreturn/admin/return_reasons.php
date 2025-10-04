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
 * \ingroup customerreturns
 * \brief   Configuration for return reasons
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
if (!$res) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formadmin.class.php';
require_once '../lib/customerreturn.lib.php';

$langs->loadLangs(array("admin", "errors", "customerreturn@customerreturn"));

$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$reason_key = GETPOST('reason_key', 'alpha');
$reason_label = GETPOST('reason_label', 'alpha');

if (!$user->admin) accessforbidden();

$const_name = 'CUSTOMERRETURN_RETURN_REASONS';

if ($action == 'add' && !empty($reason_key) && !empty($reason_label)) {
    $reasons_array = json_decode(getDolGlobalString($const_name, ''), true) ?: array();
    if (isset($reasons_array[$reason_key])) {
        setEventMessages($langs->trans("ReturnReasonKeyAlreadyExists"), null, 'errors');
    } else {
        $reasons_array[$reason_key] = $reason_label;
        if (dolibarr_set_const($db, $const_name, json_encode($reasons_array), 'chaine', 0, '', $conf->entity) > 0) {
            setEventMessages($langs->trans("ReturnReasonAdded"), null, 'mesgs');
        } else {
            setEventMessages($langs->trans("Error"), null, 'errors');
        }
    }
    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

if ($action == 'confirm_delete' && $confirm == 'yes' && !empty($reason_key)) {
    $reasons_array = json_decode(getDolGlobalString($const_name, ''), true) ?: array();
    if (isset($reasons_array[$reason_key])) {
        unset($reasons_array[$reason_key]);
        if (dolibarr_set_const($db, $const_name, json_encode($reasons_array), 'chaine', 0, '', $conf->entity) > 0) {
            setEventMessages($langs->trans("ReturnReasonDeleted"), null, 'mesgs');
        } else {
            setEventMessages($langs->trans("Error"), null, 'errors');
        }
    }
    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

if ($action == 'confirm_reset' && $confirm == 'yes') {
    $default_reasons = array(
        'defective' => $langs->trans('defective'),
        'damaged' => $langs->trans('damaged'),
        'wrong_item' => $langs->trans('wrong_item'),
        'other' => $langs->trans('other')
    );
    if (dolibarr_set_const($db, $const_name, json_encode($default_reasons), 'chaine', 0, '', $conf->entity) > 0) {
        setEventMessages($langs->trans("DefaultReasonsRestored"), null, 'mesgs');
    } else {
        setEventMessages($langs->trans("Error"), null, 'errors');
    }
    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

$page_name = "CustomerReturnsSetup";
llxHeader('', $langs->trans($page_name));
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

$head = customerreturnAdminPrepareHead();
print dol_get_fiche_head($head, 'return_reasons', $langs->trans($page_name), -1, 'customerreturn@customerreturn');

print '<span class="opacitymedium">'.$langs->trans("CustomerReturnReturnReasonsDesc").'</span><br><br>';

if ($action != 'edit') {
    print '<div class="tabsAction">';
    print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=create">'.$langs->trans("AddNewReturnReason").'</a>';
    print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=reset">'.$langs->trans("ResetToDefaultReasons").'</a>';
    print '</div><br>';
}

$reasons = customerreturns_get_return_reasons();
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans("ReturnReasonKey").'</td><td>'.$langs->trans("ReturnReasonLabel").'</td><td class="center">'.$langs->trans("Action").'</td></tr>';

if ($action == 'create') {
    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="add">';
    print '<tr class="pair"><td><input type="text" name="reason_key" required></td>';
    print '<td><input type="text" name="reason_label" size="50" required></td>';
    print '<td class="center"><input type="submit" class="button small" value="'.$langs->trans("Add").'"></td></tr></form>';
}

foreach ($reasons as $key => $label) {
    print '<tr class="oddeven"><td>'.$key.'</td><td>'.$label.'</td>';
    print '<td class="center"><a href="'.$_SERVER["PHP_SELF"].'?action=delete&reason_key='.urlencode($key).'">'.img_delete().'</a></td></tr>';
}
print '</table>';

print dol_get_fiche_end();
llxFooter();
$db->close();

if ($action == 'delete') {
    $form = new Form($db);
    print $form->formconfirm($_SERVER["PHP_SELF"].'?reason_key='.urlencode($reason_key), $langs->trans('DeleteReturnReason'), $langs->trans('ConfirmDeleteReturnReason'), 'confirm_delete');
}
if ($action == 'reset') {
    $form = new Form($db);
    print $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans('ResetToDefaultReasons'), $langs->trans('ConfirmResetToDefaultReasons'), 'confirm_reset');
}
?>