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
 * \file    lib/customerreturn.lib.php
 * \ingroup customerreturns
 * \brief   Library files with common functions for CustomerReturn
 */

function customerreturn_prepare_head($object)
{
	global $db, $langs, $conf;

	$langs->load("customerreturn@customerreturn");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/custom/customerreturn/card.php", 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans("Card");
	$head[$h][2] = 'card';
	$h++;

	if (isset($object->fields['note_public']) || isset($object->fields['note_private'])) {
		$nbNote = 0;
		if (!empty($object->note_private)) $nbNote++;
		if (!empty($object->note_public)) $nbNote++;
		$head[$h][0] = dol_buildpath("/custom/customerreturn/note.php", 1).'?id='.$object->id;
		$head[$h][1] = $langs->trans('Notes');
		if ($nbNote > 0) $head[$h][1] .= ' <span class="badge">'.$nbNote.'</span>';
		$head[$h][2] = 'note';
		$h++;
	}

	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/core/class/link.class.php';
	$upload_dir = $conf->customerreturn->dir_output."/".dol_sanitizeFileName($object->ref);
	$nbFiles = count(dol_dir_list($upload_dir, 'files', 0, '', '(\.meta|_preview.*\.png)$'));
	$nbLinks = Link::count($db, $object->element, $object->id);
	$head[$h][0] = dol_buildpath("/custom/customerreturn/document.php", 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans('Documents');
	if (($nbFiles + $nbLinks) > 0) $head[$h][1] .= ' <span class="badge">'.($nbFiles + $nbLinks).'</span>';
	$head[$h][2] = 'document';
	$h++;

	$head[$h][0] = dol_buildpath("/custom/customerreturn/agenda.php", 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans("Events");
	$head[$h][2] = 'agenda';
	$h++;

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'customerreturn@customerreturn');
	complete_head_from_modules($conf, $langs, $object, $head, $h, 'customerreturn@customerreturn', 'remove');

	return $head;
}

function customerreturnAdminPrepareHead()
{
	global $langs;
	$langs->load("customerreturn@customerreturn");
	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/custom/customerreturn/admin/customerreturn.php", 1);
	$head[$h][1] = $langs->trans("Miscellaneous");
	$head[$h][2] = 'general';
	$h++;

	$head[$h][0] = dol_buildpath("/custom/customerreturn/admin/customerreturn_extrafields.php", 1);
	$head[$h][1] = $langs->trans("ExtraFields");
	$head[$h][2] = 'extrafields';
	$h++;

	$head[$h][0] = dol_buildpath("/custom/customerreturn/admin/customerreturnline_extrafields.php", 1);
	$head[$h][1] = $langs->trans("ExtraFieldsLines");
	$head[$h][2] = 'extrafields_lines';
	$h++;

	$head[$h][0] = dol_buildpath("/custom/customerreturn/admin/return_reasons.php", 1);
	$head[$h][1] = $langs->trans("ReturnReasons");
	$head[$h][2] = 'return_reasons';
	$h++;

	return $head;
}

function customerreturns_get_return_reasons()
{
    global $langs;
    $langs->load("customerreturn@customerreturn");
    $reasons_array = json_decode(getDolGlobalString('CUSTOMERRETURN_RETURN_REASONS', ''), true) ?: array();
    if (empty($reasons_array)) {
        $reasons_array = array(
            'defective' => $langs->trans('defective'),
            'wrong_product' => $langs->trans('wrong_product'),
            'damaged' => $langs->trans('damaged'),
            'other' => $langs->trans('other')
        );
    }
    return $reasons_array;
}

function customerreturns_get_stats()
{
    global $conf, $db, $user;

    $stats = array(
        'total' => 0,
        'draft' => 0,
        'validated' => 0,
        'processed' => 0,
        'canceled' => 0
    );

    $socid = 0;
    if ($user->socid) $socid = $user->socid;

    $sql = "SELECT statut, COUNT(*) as nb FROM ".MAIN_DB_PREFIX."customerreturn WHERE entity = ".$conf->entity;
    if ($socid) $sql .= " AND fk_soc = ".(int) $socid;
    $sql .= " GROUP BY statut";

    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            if ($obj->statut == 0) $stats['draft'] = $obj->nb;
            if ($obj->statut == 1) $stats['validated'] = $obj->nb;
            if ($obj->statut == 2) $stats['processed'] = $obj->nb;
            if ($obj->statut == 9) $stats['canceled'] = $obj->nb;
        }
    }

    $stats['total'] = $stats['draft'] + $stats['validated'] + $stats['processed'] + $stats['canceled'];

    return $stats;
}