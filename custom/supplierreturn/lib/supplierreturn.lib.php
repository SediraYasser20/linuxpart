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
 * \file    lib/supplierreturn.lib.php
 * \ingroup supplierreturns
 * \brief   Library files with common functions for SupplierReturn
 */

/**
 * Prepare array of tabs for SupplierReturn
 *
 * @param	SupplierReturn	$object		SupplierReturn
 * @return 	array					Array of tabs
 */
function supplierreturn_prepare_head($object)
{
	global $db, $langs, $conf;

	$langs->load("supplierreturn@supplierreturn");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/custom/supplierreturn/card.php", 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans("Card");
	$head[$h][2] = 'card';
	$h++;

	if (isset($object->fields['note_public']) || isset($object->fields['note_private'])) {
		$nbNote = 0;
		if (!empty($object->note_private)) {
			$nbNote++;
		}
		if (!empty($object->note_public)) {
			$nbNote++;
		}
		$head[$h][0] = dol_buildpath("/custom/supplierreturn/note.php", 1).'?id='.$object->id;
		$head[$h][1] = $langs->trans('Notes');
		if ($nbNote > 0) {
			$head[$h][1] .= ' <span class="badge">'.$nbNote.'</span>';
		}
		$head[$h][2] = 'note';
		$h++;
	}

	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/core/class/link.class.php';
	$upload_dir = $conf->supplierreturns->dir_output."/".dol_sanitizeFileName($object->ref);
	$nbFiles = count(dol_dir_list($upload_dir, 'files', 0, '', '(\.meta|_preview.*\.png)$'));
	$nbLinks = Link::count($db, $object->element, $object->id);
	$head[$h][0] = dol_buildpath("/custom/supplierreturn/document.php", 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans('Documents');
	if (($nbFiles + $nbLinks) > 0) {
		$head[$h][1] .= ' <span class="badge">'.($nbFiles + $nbLinks).'</span>';
	}
	$head[$h][2] = 'document';
	$h++;

	$head[$h][0] = dol_buildpath("/custom/supplierreturn/agenda.php", 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans("Events");
	$head[$h][2] = 'agenda';
	$h++;

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@supplierreturn:/custom/supplierreturn/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@supplierreturn:/custom/supplierreturn/mypage.php?id=__ID__'
	//); // to remove a tab
	complete_head_from_modules($conf, $langs, $object, $head, $h, 'supplierreturn@supplierreturn');

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'supplierreturn@supplierreturn', 'remove');

	return $head;
}

/**
 * Prepare array of tabs for SupplierReturn admin
 *
 * @return 	array					Array of tabs
 */
function supplierreturnAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load("supplierreturn@supplierreturn");

	$h = 0;
	$head = array();

	// General tab (like commande module)
	$head[$h][0] = dol_buildpath("/custom/supplierreturn/admin/supplierreturn.php", 1);
	$head[$h][1] = $langs->trans("Miscellaneous");
	$head[$h][2] = 'general';
	$h++;

	// Extra fields for returns
	$head[$h][0] = dol_buildpath("/custom/supplierreturn/admin/supplierreturn_extrafields.php", 1);
	$head[$h][1] = $langs->trans("ExtraFields");
	$head[$h][2] = 'extrafields';
	$h++;

	// Extra fields for lines
	$head[$h][0] = dol_buildpath("/custom/supplierreturn/admin/supplierreturnline_extrafields.php", 1);
	$head[$h][1] = $langs->trans("ExtraFieldsLines");
	$head[$h][2] = 'extrafields_lines';
	$h++;

	// Return reasons configuration
	$head[$h][0] = dol_buildpath("/custom/supplierreturn/admin/return_reasons.php", 1);
	$head[$h][1] = $langs->trans("ReturnReasons");
	$head[$h][2] = 'return_reasons';
	$h++;

	return $head;
}

/**
 * Build supplier returns left sidebar menu
 *
 * @param  array  $parameters  Parameters array
 * @return string              HTML for left menu
 */
function supplierreturns_left_menu($parameters = array())
{
    global $langs, $conf, $db, $user;
    
    $langs->load("supplierreturn@supplierreturn");
    
    if (!$user->hasRight('supplierreturn', 'lire')) {
        return '';
    }
    
    // Get statistics
    $stats = supplierreturns_get_stats();
    
    $leftmenu = '';
    
    $leftmenu .= '<div class="blockvmenuend">';
    $leftmenu .= '<div class="menu_titre">' . img_picto('', 'object_supplierreturn@supplierreturn', 'class="pictofixedwidth"') . $langs->trans("SupplierReturns") . '</div>';
    $leftmenu .= '<div class="menu_contenu">';
    
    // All supplier returns
    $leftmenu .= '<div class="menu_element"><div class="menu_element_div">';
    $leftmenu .= '<a class="vmenu" href="' . dol_buildpath('/custom/supplierreturn/list.php', 1) . '">';
    $leftmenu .= img_picto('', 'object_supplierreturn@supplierreturn', 'class="pictofixedwidth"');
    $leftmenu .= $langs->trans("AllSupplierReturns");
    if ($stats['total'] > 0) {
        $leftmenu .= ' <span class="badge marginleftonlyshort">' . $stats['total'] . '</span>';
    }
    $leftmenu .= '</a>';
    $leftmenu .= '</div></div>';
    
    // Draft
    if ($stats['draft'] > 0) {
        $leftmenu .= '<div class="menu_element"><div class="menu_element_div">';
        $leftmenu .= '<a class="vmenu" href="' . dol_buildpath('/custom/supplierreturn/list.php', 1) . '?search_status=0">';
        $leftmenu .= img_picto('', 'statut0', 'class="pictofixedwidth"');
        $leftmenu .= $langs->trans("SupplierReturnsDraft");
        $leftmenu .= ' <span class="badge marginleftonlyshort">' . $stats['draft'] . '</span>';
        $leftmenu .= '</a>';
        $leftmenu .= '</div></div>';
    }
    
    // Validated
    if ($stats['validated'] > 0) {
        $leftmenu .= '<div class="menu_element"><div class="menu_element_div">';
        $leftmenu .= '<a class="vmenu" href="' . dol_buildpath('/custom/supplierreturn/list.php', 1) . '?search_status=1">';
        $leftmenu .= img_picto('', 'statut4', 'class="pictofixedwidth"');
        $leftmenu .= $langs->trans("SupplierReturnsValidated");
        $leftmenu .= ' <span class="badge marginleftonlyshort">' . $stats['validated'] . '</span>';
        $leftmenu .= '</a>';
        $leftmenu .= '</div></div>';
    }
    
    // Processed
    if ($stats['processed'] > 0) {
        $leftmenu .= '<div class="menu_element"><div class="menu_element_div">';
        $leftmenu .= '<a class="vmenu" href="' . dol_buildpath('/custom/supplierreturn/list.php', 1) . '?search_status=2">';
        $leftmenu .= img_picto('', 'statut6', 'class="pictofixedwidth"');
        $leftmenu .= $langs->trans("SupplierReturnsProcessed");
        $leftmenu .= ' <span class="badge marginleftonlyshort">' . $stats['processed'] . '</span>';
        $leftmenu .= '</a>';
        $leftmenu .= '</div></div>';
    }
    
    // Canceled (only if > 0)
    if ($stats['canceled'] > 0) {
        $leftmenu .= '<div class="menu_element"><div class="menu_element_div">';
        $leftmenu .= '<a class="vmenu" href="' . dol_buildpath('/custom/supplierreturn/list.php', 1) . '?search_status=9">';
        $leftmenu .= img_picto('', 'statut5', 'class="pictofixedwidth"');
        $leftmenu .= $langs->trans("SupplierReturnsCanceled");
        $leftmenu .= ' <span class="badge marginleftonlyshort">' . $stats['canceled'] . '</span>';
        $leftmenu .= '</a>';
        $leftmenu .= '</div></div>';
    }
    
    $leftmenu .= '</div>';
    $leftmenu .= '</div>';
    
    return $leftmenu;
}

/**
 * Get configured return reasons with full translation support
 *
 * @return array Array of return reasons (key => label) 
 */
function supplierreturns_get_return_reasons()
{
    global $conf, $langs;
    
    $langs->load("supplierreturn@supplierreturn");
    
    // Get configured reasons from database
    $existing_reasons = getDolGlobalString('SUPPLIERRETURN_RETURN_REASONS', '');
    $reasons_array = array();
    
    if ($existing_reasons) {
        $reasons_array = json_decode($existing_reasons, true);
        if (!is_array($reasons_array)) {
            $reasons_array = array();
        }
    }
    
    // If no reasons configured, return default ones with all available translations
    if (empty($reasons_array)) {
        $reasons_array = array(
            'defective' => $langs->trans('defective'),
            'wrong_product' => $langs->trans('wrong_product'),
            'damaged' => $langs->trans('damaged'),
            'expired' => $langs->trans('expired'),
            'overdelivery' => $langs->trans('overdelivery'),
            'quality_issue' => $langs->trans('quality_issue'),
            'not_ordered' => $langs->trans('not_ordered'),
            'wrong_item' => $langs->trans('wrong_item'),
            'excess_quantity' => $langs->trans('excess_quantity'),
            'not_as_described' => $langs->trans('not_as_described'),
            'late_delivery' => $langs->trans('late_delivery'),
            'other' => $langs->trans('other')
        );
    }
    
    return $reasons_array;
}

/**
 * Get supplier returns statistics
 *
 * @return array Statistics array
 */
function supplierreturns_get_stats()
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
    if ($user->socid) {
        $socid = $user->socid;
    }
    
    // Draft
    $sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."supplierreturn WHERE entity = ".$conf->entity." AND statut = 0";
    if ($socid) $sql .= " AND fk_soc = ".(int) $socid;
    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        $stats['draft'] = $obj->nb;
    }
    
    // Validated
    $sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."supplierreturn WHERE entity = ".$conf->entity." AND statut = 1";
    if ($socid) $sql .= " AND fk_soc = ".(int) $socid;
    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        $stats['validated'] = $obj->nb;
    }
    
    // Processed
    $sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."supplierreturn WHERE entity = ".$conf->entity." AND statut = 2";
    if ($socid) $sql .= " AND fk_soc = ".(int) $socid;
    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        $stats['processed'] = $obj->nb;
    }
    
    // Canceled
    $sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."supplierreturn WHERE entity = ".$conf->entity." AND statut = 9";
    if ($socid) $sql .= " AND fk_soc = ".(int) $socid;
    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        $stats['canceled'] = $obj->nb;
    }
    
    // Total
    $stats['total'] = $stats['draft'] + $stats['validated'] + $stats['processed'] + $stats['canceled'];
    
    return $stats;
}