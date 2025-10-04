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
 *      \file       htdocs/custom/supplierreturn/list.php
 *      \ingroup    supplierreturns
 *      \brief      List of supplier returns
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
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once './class/supplierreturn.class.php';

$langs->loadLangs(array("supplierreturn@supplierreturn", "other", "companies", "bills"));

$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'supplierreturnslist';

// Security check
if (!$user->admin && !$user->hasRight('supplierreturn', 'lire')) {
    accessforbidden();
}

$socid = GETPOSTINT('socid');
if ($user->socid) {
    $socid = $user->socid;
}

// Search and mass actions
$action = GETPOST('action', 'alpha');
$massaction = GETPOST('massaction', 'alpha');
$toselect = GETPOST('toselect', 'array');
$optioncss = GETPOST('optioncss', 'alpha');
$mode = GETPOST('mode', 'alpha');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'alpha');

// Search criteria
$search_ref = GETPOST('search_ref', 'alpha');
$search_company = GETPOST('search_company', 'alpha');
$search_town = GETPOST('search_town', 'alpha');
$search_zip = GETPOST('search_zip', 'alpha');
$search_state = GETPOST('search_state', 'alpha');
$search_country = GETPOST('search_country', 'aZ09');
$search_type_thirdparty = GETPOST('search_type_thirdparty', 'intcomma');
$search_return_reason = GETPOST('search_return_reason', 'alpha');
$search_status = GETPOST('search_status', 'intcomma');
$search_linked_object = GETPOST('search_linked_object', 'alpha');
$search_linked_id = GETPOSTINT('search_linked_id');
$search_all = GETPOST('search_all', 'alphanohtml') ? GETPOST('search_all', 'alphanohtml') : GETPOST('sall', 'alphanohtml');

// Date filters
$search_date_create_startday = GETPOSTINT('search_date_create_startday');
$search_date_create_startmonth = GETPOSTINT('search_date_create_startmonth');
$search_date_create_startyear = GETPOSTINT('search_date_create_startyear');
$search_date_create_endday = GETPOSTINT('search_date_create_endday');
$search_date_create_endmonth = GETPOSTINT('search_date_create_endmonth');
$search_date_create_endyear = GETPOSTINT('search_date_create_endyear');
$search_date_create_start = dol_mktime(0, 0, 0, $search_date_create_startmonth, $search_date_create_startday, $search_date_create_startyear);
$search_date_create_end = dol_mktime(23, 59, 59, $search_date_create_endmonth, $search_date_create_endday, $search_date_create_endyear);

$search_date_return_startday = GETPOSTINT('search_date_return_startday');
$search_date_return_startmonth = GETPOSTINT('search_date_return_startmonth');
$search_date_return_startyear = GETPOSTINT('search_date_return_startyear');
$search_date_return_endday = GETPOSTINT('search_date_return_endday');
$search_date_return_endmonth = GETPOSTINT('search_date_return_endmonth');
$search_date_return_endyear = GETPOSTINT('search_date_return_endyear');
$search_date_return_start = dol_mktime(0, 0, 0, $search_date_return_startmonth, $search_date_return_startday, $search_date_return_startyear);
$search_date_return_end = dol_mktime(23, 59, 59, $search_date_return_endmonth, $search_date_return_endday, $search_date_return_endyear);

// Pagination and sorting
$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if (!$sortfield) {
    $sortfield = 't.ref';
}
if (!$sortorder) {
    $sortorder = 'DESC';
}
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
    $page = 0;
}
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

// Initialize objects
$form = new Form($db);
$formother = new FormOther($db);
$formcompany = new FormCompany($db);
$object = new SupplierReturn($db);

// Initialize technical object to manage hooks
$hookmanager->initHooks(array('supplierreturnslist'));
$extrafields = new ExtraFields($db);

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);
$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

// List of fields to search into when doing a "search in all"
$fieldstosearchall = array(
    't.ref' => 'Ref',
    's.nom' => 'ThirdParty',
    't.return_reason' => 'ReturnReason',
    't.note_public' => 'NotePublic',
);
if (empty($user->socid)) {
    $fieldstosearchall['t.note_private'] = 'NotePrivate';
}

// Define array fields for display
$checkedtypetiers = 0;
$arrayfields = array(
    't.ref' => array('label' => $langs->trans('Ref'), 'checked' => 1, 'position' => 10),
    's.nom' => array('label' => $langs->trans('ThirdParty'), 'checked' => 1, 'position' => 20),
    's.town' => array('label' => $langs->trans('Town'), 'checked' => 0, 'position' => 30),
    's.zip' => array('label' => $langs->trans('Zip'), 'checked' => 0, 'position' => 40),
    'state.nom' => array('label' => $langs->trans('StateShort'), 'checked' => 0, 'position' => 50),
    'country.code_iso' => array('label' => $langs->trans('Country'), 'checked' => 0, 'position' => 60),
    'typent.code' => array('label' => $langs->trans('ThirdPartyType'), 'checked' => $checkedtypetiers, 'position' => 70),
    't.return_reason' => array('label' => ($langs->trans('ReturnReason') != 'ReturnReason' ? $langs->trans('ReturnReason') : 'Raison du retour'), 'checked' => 1, 'position' => 80),
    't.date_return' => array('label' => ($langs->trans('DateReturn') != 'DateReturn' ? $langs->trans('DateReturn') : 'Date retour'), 'checked' => 1, 'position' => 90),
    't.date_creation' => array('label' => $langs->trans('DateCreation'), 'checked' => 0, 'position' => 500),
    't.date_modification' => array('label' => $langs->trans('DateModificationShort'), 'checked' => 0, 'position' => 510),
    't.total_ht' => array('label' => $langs->trans('AmountHT'), 'checked' => 1, 'position' => 520),
    't.total_ttc' => array('label' => $langs->trans('AmountTTC'), 'checked' => 0, 'position' => 530),
    't.statut' => array('label' => $langs->trans('Status'), 'checked' => 1, 'position' => 1000)
);

// Extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_array_fields.tpl.php';

// Sort array fields
$arrayfields = dol_sort_array($arrayfields, 'position');

/*
 * Actions
 */

if (GETPOST('cancel', 'alpha')) {
    $action = 'list';
    $massaction = '';
}
if (!GETPOST('confirmmassaction', 'alpha') && $massaction != 'presend' && $massaction != 'confirm_presend') {
    $massaction = '';
}

$parameters = array('socid' => $socid, 'arrayfields' => &$arrayfields);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
    // Selection of new fields
    include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

    // Mass actions
    if ($massaction == 'validate' && $user->hasRight('supplierreturn', 'creer')) {
        dol_syslog("SupplierReturns list: Starting mass validate action for ".count($toselect)." items", LOG_INFO);
        $db->begin();
        $error = 0;
        $validated = 0;
        
        foreach ($toselect as $toselectid) {
            $objecttovalidate = new SupplierReturn($db);
            $result = $objecttovalidate->fetch($toselectid);
            if ($result > 0 && $objecttovalidate->statut == SupplierReturn::STATUS_DRAFT) {
                $result = $objecttovalidate->validate($user);
                if ($result > 0) {
                    $validated++;
                    dol_syslog("SupplierReturns list: Validated return ID ".$toselectid, LOG_INFO);
                } else {
                    $error++;
                    dol_syslog("SupplierReturns list: Failed to validate return ID ".$toselectid.": ".$objecttovalidate->error, LOG_ERR);
                    setEventMessages($objecttovalidate->error, $objecttovalidate->errors, 'errors');
                }
            } else {
                dol_syslog("SupplierReturns list: Return ID ".$toselectid." not in draft status or fetch failed", LOG_WARNING);
            }
        }
        
        if (!$error) {
            $db->commit();
            if ($validated > 0) {
                setEventMessages($langs->trans('RecordsValidated', $validated), null, 'mesgs');
            }
        } else {
            $db->rollback();
        }
    }
    
    // Close
    if ($massaction == 'close' && $user->hasRight('supplierreturn', 'creer')) {
        dol_syslog("SupplierReturns list: Starting mass close action for ".count($toselect)." items: ".implode(', ', $toselect), LOG_INFO);
        
        // Optimize mass operations by skipping linked object loading
        define('SKIP_LINKED_OBJECTS_LOADING', true);
        
        $db->begin();
        $error = 0;
        $closed = 0;
        $skipped = 0;
        
        foreach ($toselect as $toselectid) {
            $objecttoclose = new SupplierReturn($db);
            $result = $objecttoclose->fetch($toselectid);
            if ($result > 0) {
                dol_syslog("SupplierReturns list: Return ID ".$toselectid." has status ".$objecttoclose->statut." (need ".SupplierReturn::STATUS_VALIDATED." to close)", LOG_INFO);
                if ($objecttoclose->statut == SupplierReturn::STATUS_VALIDATED) {
                    $objecttoclose->statut = SupplierReturn::STATUS_CLOSED;
                    $result = $objecttoclose->update($user);
                    if ($result > 0) {
                        $closed++;
                        dol_syslog("SupplierReturns list: Closed return ID ".$toselectid, LOG_INFO);
                    } else {
                        $error++;
                        dol_syslog("SupplierReturns list: Failed to close return ID ".$toselectid.": ".$objecttoclose->error, LOG_ERR);
                        setEventMessages($objecttoclose->error, $objecttoclose->errors, 'errors');
                    }
                } else {
                    $skipped++;
                    dol_syslog("SupplierReturns list: Return ID ".$toselectid." not in validated status (status=".$objecttoclose->statut.")", LOG_WARNING);
                }
            } else {
                $error++;
                dol_syslog("SupplierReturns list: Failed to fetch return ID ".$toselectid, LOG_ERR);
            }
        }
        
        if (!$error) {
            $db->commit();
            $message = '';
            if ($closed > 0) {
                $message .= $closed.' '.($closed > 1 ? 'retours fermés' : 'retour fermé');
            }
            if ($skipped > 0) {
                if ($message) $message .= ', ';
                $message .= $skipped.' '.($skipped > 1 ? 'retours ignorés' : 'retour ignoré').' (pas au bon statut)';
            }
            if ($message) {
                setEventMessages($message, null, 'mesgs');
            }
        } else {
            $db->rollback();
        }
    }
    
    // Delete
    if ($massaction == 'delete' && $user->hasRight('supplierreturn', 'supprimer')) {
        dol_syslog("SupplierReturns list: Starting mass delete action for ".count($toselect)." items", LOG_INFO);
        $db->begin();
        $error = 0;
        $deleted = 0;
        
        foreach ($toselect as $toselectid) {
            $objecttodelete = new SupplierReturn($db);
            $result = $objecttodelete->fetch($toselectid);
            if ($result > 0) {
                $result = $objecttodelete->delete($user);
                if ($result > 0) {
                    $deleted++;
                    dol_syslog("SupplierReturns list: Deleted return ID ".$toselectid, LOG_INFO);
                } else {
                    $error++;
                    dol_syslog("SupplierReturns list: Failed to delete return ID ".$toselectid.": ".$objecttodelete->error, LOG_ERR);
                    setEventMessages($objecttodelete->error, $objecttodelete->errors, 'errors');
                }
            } else {
                $error++;
                dol_syslog("SupplierReturns list: Failed to fetch return ID ".$toselectid, LOG_ERR);
            }
        }
        
        if (!$error) {
            $db->commit();
            if ($deleted > 0) {
                setEventMessages($langs->trans('RecordsDeleted', $deleted), null, 'mesgs');
            }
        } else {
            $db->rollback();
        }
    }
    
    // Create credit note
    if ($massaction == 'createcreditnote' && $user->hasRight('supplierreturn', 'creer')) {
        dol_syslog("SupplierReturns list: Starting create credit note action for ".count($toselect)." items", LOG_INFO);
        // Redirection vers une page dédiée à la création d'avoir
        $selectedids = implode(',', $toselect);
        header('Location: create_credit_note.php?ids='.$selectedids);
        exit;
    }

    // Purge search criteria
if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
    $search_ref = '';
    $search_company = '';
    $search_town = '';
    $search_zip = '';
    $search_state = '';
    $search_country = '';
    $search_type_thirdparty = '';
    $search_return_reason = '';
    $search_status = '';
    $search_all = '';
    $search_date_create_startday = '';
    $search_date_create_startmonth = '';
    $search_date_create_startyear = '';
    $search_date_create_endday = '';
    $search_date_create_endmonth = '';
    $search_date_create_endyear = '';
    $search_date_create_start = '';
    $search_date_create_end = '';
    $search_date_return_startday = '';
    $search_date_return_startmonth = '';
    $search_date_return_startyear = '';
    $search_date_return_endday = '';
    $search_date_return_endmonth = '';
    $search_date_return_endyear = '';
    $search_date_return_start = '';
    $search_date_return_end = '';
    $toselect = array();
    $search_array_options = array();
}
}

/*
 * View
 */

// Construction de la requête améliorée
$sql = "SELECT";
$sql .= " t.rowid, t.ref, t.fk_soc, t.date_creation, t.date_modification, t.date_return,";
$sql .= " t.statut, t.return_reason, t.total_ht, t.total_ttc,";
$sql .= " s.nom as company_name, s.town, s.zip,";
$sql .= " state.nom as state_name,";
$sql .= " country.code_iso as country_code,";
$sql .= " typent.code as typent_code";

// Add fields from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListSelect', $parameters, $object);
$sql .= $hookmanager->resPrint;

$sql .= " FROM ".MAIN_DB_PREFIX."supplierreturn as t";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON t.fk_soc = s.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."c_departements as state ON s.fk_departement = state.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."c_country as country ON s.fk_pays = country.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."c_typent as typent ON s.fk_typent = typent.id";

// Add tables from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListFrom', $parameters, $object);
$sql .= $hookmanager->resPrint;

$sql .= " WHERE t.entity = ".$conf->entity;

if ($socid > 0) {
    $sql .= " AND t.fk_soc = ".(int) $socid;
}
if ($search_ref) {
    $sql .= natural_search('t.ref', $search_ref);
}
if ($search_company) {
    $sql .= natural_search('s.nom', $search_company);
}
if ($search_town) {
    $sql .= natural_search('s.town', $search_town);
}
if ($search_zip) {
    $sql .= natural_search('s.zip', $search_zip);
}
if ($search_state) {
    $sql .= natural_search('state.nom', $search_state);
}
if ($search_country) {
    $sql .= " AND country.code_iso = '".$db->escape($search_country)."'";
}
if ($search_type_thirdparty != '' && $search_type_thirdparty != '-1') {
    $sql .= " AND typent.code = '".$db->escape($search_type_thirdparty)."'";
}
if ($search_return_reason) {
    $sql .= natural_search('t.return_reason', $search_return_reason);
}
if ($search_status != '' && $search_status != '-1') {
    $sql .= " AND t.statut IN (".$db->sanitize($search_status).")";
}
if ($search_date_create_start) {
    $sql .= " AND t.date_creation >= '".$db->idate($search_date_create_start)."'";
}
if ($search_date_create_end) {
    $sql .= " AND t.date_creation <= '".$db->idate($search_date_create_end)."'";
}
if ($search_date_return_start) {
    $sql .= " AND t.date_return >= '".$db->idate($search_date_return_start)."'";
}
if ($search_date_return_end) {
    $sql .= " AND t.date_return <= '".$db->idate($search_date_return_end)."'";
}
if ($search_all) {
    $sql .= natural_search(array_keys($fieldstosearchall), $search_all);
}

// Recherche par document lié (pour les hooks inter-modules)
if ($search_linked_object && $search_linked_id) {
    switch ($search_linked_object) {
        case 'reception':
            $sql .= " AND t.fk_reception = " . (int) $search_linked_id;
            break;
        case 'order_supplier':
            $sql .= " AND t.fk_commande_fournisseur = " . (int) $search_linked_id;
            break;
        case 'invoice_supplier':
            $sql .= " AND t.fk_facture_fourn = " . (int) $search_linked_id;
            break;
        default:
            // Recherche dans element_element pour les liens manuels
            $sql .= " AND EXISTS (SELECT 1 FROM " . MAIN_DB_PREFIX . "element_element ee 
                      WHERE ee.fk_source = t.rowid AND ee.sourcetype = 'supplierreturn' 
                      AND ee.targettype = '" . $db->escape($search_linked_object) . "' 
                      AND ee.fk_target = " . (int) $search_linked_id . ")";
            break;
    }
}

// Add where from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListWhere', $parameters, $object);
$sql .= $hookmanager->resPrint;

// Count total nb of records
$nbtotalofrecords = '';
if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST)) {
    // Build count query manually to avoid regex issues
    $sqlforcount = "SELECT COUNT(t.rowid) as nbtotalofrecords";
    $sqlforcount .= " FROM ".MAIN_DB_PREFIX."supplierreturn as t";
    $sqlforcount .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON t.fk_soc = s.rowid";
    $sqlforcount .= " LEFT JOIN ".MAIN_DB_PREFIX."c_departements as state ON s.fk_departement = state.rowid";
    $sqlforcount .= " LEFT JOIN ".MAIN_DB_PREFIX."c_country as country ON s.fk_pays = country.rowid";
    $sqlforcount .= " LEFT JOIN ".MAIN_DB_PREFIX."c_typent as typent ON s.fk_typent = typent.id";
    $sqlforcount .= " WHERE t.entity = ".$conf->entity;
    
    if ($socid > 0) {
        $sqlforcount .= " AND t.fk_soc = ".(int) $socid;
    }
    if ($search_ref) {
        $sqlforcount .= natural_search('t.ref', $search_ref);
    }
    if ($search_company) {
        $sqlforcount .= natural_search('s.nom', $search_company);
    }
    if ($search_town) {
        $sqlforcount .= natural_search('s.town', $search_town);
    }
    if ($search_zip) {
        $sqlforcount .= natural_search('s.zip', $search_zip);
    }
    if ($search_state) {
        $sqlforcount .= natural_search('state.nom', $search_state);
    }
    if ($search_country) {
        $sqlforcount .= " AND country.code_iso = '".$db->escape($search_country)."'";
    }
    if ($search_type_thirdparty != '' && $search_type_thirdparty != '-1') {
        $sqlforcount .= " AND typent.code = '".$db->escape($search_type_thirdparty)."'";
    }
    if ($search_return_reason) {
        $sqlforcount .= natural_search('t.return_reason', $search_return_reason);
    }
    if ($search_status != '' && $search_status != '-1') {
        $sqlforcount .= " AND t.statut IN (".$db->sanitize($search_status).")";
    }
    if ($search_date_create_start) {
        $sqlforcount .= " AND t.date_creation >= '".$db->idate($search_date_create_start)."'";
    }
    if ($search_date_create_end) {
        $sqlforcount .= " AND t.date_creation <= '".$db->idate($search_date_create_end)."'";
    }
    if ($search_date_return_start) {
        $sqlforcount .= " AND t.date_return >= '".$db->idate($search_date_return_start)."'";
    }
    if ($search_date_return_end) {
        $sqlforcount .= " AND t.date_return <= '".$db->idate($search_date_return_end)."'";
    }
    if ($search_all) {
        $sqlforcount .= natural_search(array_keys($fieldstosearchall), $search_all);
    }
    
    // Recherche par document lié
    if ($search_linked_object && $search_linked_id) {
        switch ($search_linked_object) {
            case 'reception':
                $sqlforcount .= " AND t.fk_reception = " . (int) $search_linked_id;
                break;
            case 'order_supplier':
                $sqlforcount .= " AND t.fk_commande_fournisseur = " . (int) $search_linked_id;
                break;
            case 'invoice_supplier':
                $sqlforcount .= " AND t.fk_facture_fourn = " . (int) $search_linked_id;
                break;
            default:
                $sqlforcount .= " AND EXISTS (SELECT 1 FROM " . MAIN_DB_PREFIX . "element_element ee 
                          WHERE ee.fk_source = t.rowid AND ee.sourcetype = 'supplierreturn' 
                          AND ee.targettype = '" . $db->escape($search_linked_object) . "' 
                          AND ee.fk_target = " . (int) $search_linked_id . ")";
                break;
        }
    }
    
    $resql = $db->query($sqlforcount);
    if ($resql) {
        $objforcount = $db->fetch_object($resql);
        $nbtotalofrecords = $objforcount->nbtotalofrecords;
        $db->free($resql);
    } else {
        dol_print_error($db);
    }

    if (($page * $limit) > $nbtotalofrecords) {
        $page = floor($nbtotalofrecords / $limit);
        $offset = $limit * $page;
    }
}

// Complete request and execute it with limit
$sql .= $db->order($sortfield, $sortorder);
if ($limit) {
    $sql .= $db->plimit($limit + 1, $offset);
}

$resql = $db->query($sql);
if ($resql) {
    $num = $db->num_rows($resql);
    
    // Build param string
    $param = '';
    if (!empty($mode)) {
        $param .= '&mode='.urlencode($mode);
    }
    if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) {
        $param .= '&contextpage='.urlencode($contextpage);
    }
    if ($limit > 0 && $limit != $conf->liste_limit) {
        $param .= '&limit='.((int) $limit);
    }
    if ($optioncss != '') {
        $param .= '&optioncss='.urlencode($optioncss);
    }
    if ($search_ref) {
        $param .= '&search_ref='.urlencode($search_ref);
    }
    if ($search_company) {
        $param .= '&search_company='.urlencode($search_company);
    }
    if ($search_town) {
        $param .= '&search_town='.urlencode($search_town);
    }
    if ($search_zip) {
        $param .= '&search_zip='.urlencode($search_zip);
    }
    if ($search_state) {
        $param .= '&search_state='.urlencode($search_state);
    }
    if ($search_country) {
        $param .= '&search_country='.urlencode($search_country);
    }
    if ($search_type_thirdparty != '' && $search_type_thirdparty != '-1') {
        $param .= '&search_type_thirdparty='.urlencode($search_type_thirdparty);
    }
    if ($search_return_reason) {
        $param .= '&search_return_reason='.urlencode($search_return_reason);
    }
    if ($search_status != '' && $search_status != '-1') {
        $param .= '&search_status='.urlencode($search_status);
    }
    if ($search_all) {
        $param .= '&search_all='.urlencode($search_all);
    }
    if ($search_date_create_startday) {
        $param .= '&search_date_create_startday='.urlencode($search_date_create_startday);
    }
    if ($search_date_create_startmonth) {
        $param .= '&search_date_create_startmonth='.urlencode($search_date_create_startmonth);
    }
    if ($search_date_create_startyear) {
        $param .= '&search_date_create_startyear='.urlencode($search_date_create_startyear);
    }
    if ($search_date_create_endday) {
        $param .= '&search_date_create_endday='.urlencode($search_date_create_endday);
    }
    if ($search_date_create_endmonth) {
        $param .= '&search_date_create_endmonth='.urlencode($search_date_create_endmonth);
    }
    if ($search_date_create_endyear) {
        $param .= '&search_date_create_endyear='.urlencode($search_date_create_endyear);
    }
    if ($search_date_return_startday) {
        $param .= '&search_date_return_startday='.urlencode($search_date_return_startday);
    }
    if ($search_date_return_startmonth) {
        $param .= '&search_date_return_startmonth='.urlencode($search_date_return_startmonth);
    }
    if ($search_date_return_startyear) {
        $param .= '&search_date_return_startyear='.urlencode($search_date_return_startyear);
    }
    if ($search_date_return_endday) {
        $param .= '&search_date_return_endday='.urlencode($search_date_return_endday);
    }
    if ($search_date_return_endmonth) {
        $param .= '&search_date_return_endmonth='.urlencode($search_date_return_endmonth);
    }
    if ($search_date_return_endyear) {
        $param .= '&search_date_return_endyear='.urlencode($search_date_return_endyear);
    }
    if ($socid) {
        $param .= '&socid='.urlencode($socid);
    }
    
    // Add parameters from extra fields
    include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_param.tpl.php';
    
    // Mass actions
    $arrayofmassactions = array();
    if ($user->hasRight('supplierreturn', 'creer')) {
        $arrayofmassactions['validate'] = img_picto('', 'check', 'class=\"paddingright\"').$langs->trans('Validate');
        $arrayofmassactions['close'] = img_picto('', 'close_title', 'class=\"paddingright\"').$langs->trans('Close');
        $arrayofmassactions['createcreditnote'] = img_picto('', 'bill', 'class=\"paddingright\"').$langs->trans('CreateCreditNote');
    }
    if ($user->hasRight('supplierreturn', 'supprimer')) {
        $arrayofmassactions['delete'] = img_picto('', 'delete', 'class=\"paddingright\"').$langs->trans('Delete');
    }
    
    $massactionbutton = $form->selectMassAction('', $arrayofmassactions);
    
    $arrayofselected = array();
    
    llxHeader('', $langs->trans('SupplierReturnsList'));
    
    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
    if ($optioncss != '') print '<input type="hidden" name="optioncss" value="'.$optioncss.'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
    print '<input type="hidden" name="action" value="list">';
    print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
    print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
    print '<input type="hidden" name="page" value="'.$page.'">';
    print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';
    if ($socid) print '<input type="hidden" name="socid" value="'.$socid.'">';
    if ($search_linked_object) print '<input type="hidden" name="search_linked_object" value="'.$search_linked_object.'">';
    if ($search_linked_id) print '<input type="hidden" name="search_linked_id" value="'.$search_linked_id.'">';
    
    $newcardbutton = '';
    $newcardbutton .= dolGetButtonTitle($langs->trans('ViewList'), '', 'fa fa-bars imgforviewmode', $_SERVER["PHP_SELF"].'?mode=common'.preg_replace('/(&|\?)*mode=[^&]+/', '', $param), '', ((empty($mode) || $mode == 'common') ? 2 : 1), array('morecss'=>'reposition'));
    $newcardbutton .= dolGetButtonTitle($langs->trans('ViewKanban'), '', 'fa fa-th-list imgforviewmode', $_SERVER["PHP_SELF"].'?mode=kanban'.preg_replace('/(&|\?)*mode=[^&]+/', '', $param), '', (($mode == 'kanban') ? 2 : 1), array('morecss'=>'reposition'));
    $newcardbutton .= dolGetButtonTitle('Depuis réception', '', 'fa fa-undo', 'create_from_reception.php', '', ($user->admin || $user->hasRight('supplierreturn', 'creer')), array('morecss'=>'reposition'));
    $newcardbutton .= dolGetButtonTitle($langs->trans('NewSupplierReturn'), '', 'fa fa-plus-circle', 'card.php?action=create', '', ($user->admin || $user->hasRight('supplierreturn', 'creer')), array('morecss'=>'reposition'));
    
    print_barre_liste($langs->trans("SupplierReturnsList"), $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'supplierreturn@supplierreturn', 0, $newcardbutton, '', $limit, 0, 0, 1);
    
    $moreforfilter = '';
    
    $object = new SupplierReturn($db);
    $parameters = array();
    $reshook = (isset($hookmanager) ? $hookmanager->executeHooks('printFieldPreListTitle', $parameters, $object) : 0);
    if (isset($hookmanager)) {
        if (empty($reshook)) $moreforfilter .= $hookmanager->resPrint;
        else $moreforfilter = $hookmanager->resPrint;
    }
    
    if (!empty($moreforfilter)) {
        print '<div class="liste_titre liste_titre_bydiv centpercent">';
        print $moreforfilter;
        print '</div>';
    }
    
    $contextpage = 'supplierreturnslist';
    $varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
    $selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage, getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN', ''));
    $selectedfields .= (count($arrayofmassactions) ? $form->showCheckAddButtons('checkforselect', 1) : '');
    
    print '<div class="div-table-responsive">';
    print '<table class="tagtable nobottomiftotal liste'.($moreforfilter ? " listwithfilterbefore" : "").'">';
    
    // En-têtes des colonnes
    print '<tr class="liste_titre">';
    
    // Action column (checkbox pour sélection multiple) - à gauche si activé
    if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
        print_liste_field_titre($selectedfields, $_SERVER["PHP_SELF"], "", '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ');
    }
    
    // Display column headers based on arrayfields
    foreach ($arrayfields as $key => $val) {
        if (!empty($val['checked'])) {
            $align = (isset($val['align']) ? 'align="'.$val['align'].'"' : '');
            $sortable = 1;
            print_liste_field_titre($val['label'], $_SERVER["PHP_SELF"], $key, '', $param, '', $sortfield, $sortorder, $align.' ');
        }
    }
    
    // Action column (checkbox pour sélection multiple) - à droite par défaut
    if (!getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
        print_liste_field_titre($selectedfields, $_SERVER["PHP_SELF"], "", '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ');
    }
    print '</tr>';
    
    // Ligne de recherche
    print '<tr class="liste_titre_filter">';
    
    // Action column - à gauche si activé
    if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
        print '<td class="liste_titre center maxwidthsearch">';
        $searchpicto = $form->showFilterButtons('left');
        print $searchpicto;
        print '</td>';
    }
    
    // Search fields based on arrayfields
    foreach ($arrayfields as $key => $val) {
        if (!empty($val['checked'])) {
            $align = (isset($val['align']) ? $val['align'] : 'left');
            print '<td class="liste_titre '.$align.'">';
            
            if ($key == 't.ref') {
                print '<input class="flat maxwidth75" type="text" name="search_ref" value="'.dol_escape_htmltag($search_ref).'">';
            } elseif ($key == 's.nom') {
                print '<input class="flat maxwidth100" type="text" name="search_company" value="'.dol_escape_htmltag($search_company).'">';
            } elseif ($key == 's.town') {
                print '<input class="flat maxwidth75" type="text" name="search_town" value="'.dol_escape_htmltag($search_town).'">';
            } elseif ($key == 's.zip') {
                print '<input class="flat maxwidth50" type="text" name="search_zip" value="'.dol_escape_htmltag($search_zip).'">';
            } elseif ($key == 'state.nom') {
                print '<input class="flat maxwidth75" type="text" name="search_state" value="'.dol_escape_htmltag($search_state).'">';
            } elseif ($key == 'country.code_iso') {
                print $form->select_country($search_country, 'search_country', '', 0, 'minwidth100imp maxwidth100');
            } elseif ($key == 'typent.code') {
                print $form->selectarray('search_type_thirdparty', $formcompany->typent_array(0), $search_type_thirdparty, 1, 0, 0, '', 0, 0, 0, 'ASC', 'maxwidth100', 1);
            } elseif ($key == 't.return_reason') {
                print '<input class="flat maxwidth100" type="text" name="search_return_reason" value="'.dol_escape_htmltag($search_return_reason).'">';
            } elseif ($key == 't.date_creation') {
                print '<div class="nowrap">';
                print $form->selectDate($search_date_create_start ? $search_date_create_start : -1, 'search_date_create_start', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('From'));
                print '<br>';
                print $form->selectDate($search_date_create_end ? $search_date_create_end : -1, 'search_date_create_end', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('to'));
                print '</div>';
            } elseif ($key == 't.date_return') {
                print '<div class="nowrap">';
                print $form->selectDate($search_date_return_start ? $search_date_return_start : -1, 'search_date_return_start', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('From'));
                print '<br>';
                print $form->selectDate($search_date_return_end ? $search_date_return_end : -1, 'search_date_return_end', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('to'));
                print '</div>';
            } elseif ($key == 't.statut') {
                $liststatus = array(
                    '0' => $langs->trans('Draft'),
                    '1' => $langs->trans('Validated'),
                    '2' => $langs->trans('Processed')
                );
                print $form->selectarray('search_status', $liststatus, $search_status, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth100', 1);
            } else {
                print '&nbsp;';
            }
            
            print '</td>';
        }
    }
    
    // Action column - à droite par défaut  
    if (!getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
        print '<td class="liste_titre center maxwidthsearch">';
        $searchpicto = $form->showFilterButtons();
        print $searchpicto;
        print '</td>';
    }
    print '</tr>';
    
    // Lignes de données
    $i = 0;
    $totalarray = array();
    $totalarray['nbfield'] = 0;
    $savnbfield = 0;
    
    while ($i < min($num, $limit)) {
        $obj = $db->fetch_object($resql);
        if (empty($obj)) break;
        
        // Create supplier return object for display
        $supplierreturn = new SupplierReturn($db);
        $supplierreturn->id = $obj->rowid;
        $supplierreturn->ref = $obj->ref;
        $supplierreturn->fk_soc = $obj->fk_soc;
        $supplierreturn->date_creation = $obj->date_creation;
        $supplierreturn->date_modification = $obj->date_modification;
        $supplierreturn->date_return = $obj->date_return;
        $supplierreturn->statut = $obj->statut;
        $supplierreturn->return_reason = $obj->return_reason;
        $supplierreturn->total_ht = $obj->total_ht;
        $supplierreturn->total_ttc = $obj->total_ttc;
        
        if ($mode == 'kanban') {
            // Kanban mode would go here
        } else {
            // List mode
            print '<tr class="oddeven">';
            
            // Action column - à gauche si activé
            if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
                print '<td class="nowrap center">';
                if (($obj->statut == SupplierReturn::STATUS_DRAFT && $user->hasRight('supplierreturn', 'creer')) || 
                    ($obj->statut != SupplierReturn::STATUS_DRAFT && $user->hasRight('supplierreturn', 'supprimer'))) {
                    print '<input id="cb'.$obj->rowid.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$obj->rowid.'"'.(!empty($arrayofselected) && in_array($obj->rowid, $arrayofselected) ? ' checked="checked"' : '').'>';
                }
                print '</td>';
                if (!$i) {
                    $totalarray['nbfield']++;
                }
            }
            
            // Display columns based on arrayfields
            foreach ($arrayfields as $key => $val) {
                if (!empty($val['checked'])) {
                    $cssforfield = (empty($val['css']) ? '' : $val['css']);
                    if (in_array($val['type'], array('date', 'datetime', 'timestamp'))) {
                        $cssforfield .= ($cssforfield ? ' ' : '').'center';
                    } elseif ($key == 't.statut') {
                        $cssforfield .= ($cssforfield ? ' ' : '').'center';
                    }
                    
                    if (in_array($val['type'], array('timestamp'))) {
                        $cssforfield .= ($cssforfield ? ' ' : '').'nowrap';
                    } elseif ($key == 't.ref') {
                        $cssforfield .= ($cssforfield ? ' ' : '').'nowraponall';
                    }
                    
                    if (in_array($val['type'], array('double(24,8)', 'double(6,3)', 'integer', 'real', 'price')) && !in_array($key, array('t.rowid', 't.ref', 't.status'))) {
                        $cssforfield .= ($cssforfield ? ' ' : '').'right';
                    }
                    
                    print '<td'.($cssforfield ? ' class="'.$cssforfield.'"' : '').'>';
                    
                    if ($key == 't.ref') {
                        print $supplierreturn->getNomUrl(1);
                    } elseif ($key == 's.nom') {
                        if ($obj->fk_soc > 0) {
                            $companystatic = new Societe($db);
                            $companystatic->id = $obj->fk_soc;
                            $companystatic->name = $obj->company_name;
                            print $companystatic->getNomUrl(1);
                        }
                    } elseif ($key == 's.town') {
                        print dol_escape_htmltag($obj->town);
                    } elseif ($key == 's.zip') {
                        print dol_escape_htmltag($obj->zip);
                    } elseif ($key == 'state.nom') {
                        print dol_escape_htmltag($obj->state_name);
                    } elseif ($key == 'country.code_iso') {
                        if ($obj->country_code) {
                            $tmparray = getCountry($obj->country_code, 'all');
                            print $tmparray['label'];
                        }
                    } elseif ($key == 'typent.code') {
                        if ($obj->typent_code) {
                            $formcompany_static = new FormCompany($db);
                            $tmparray = $formcompany_static->typent_array(1);
                            if (isset($tmparray[$obj->typent_code])) {
                                print $tmparray[$obj->typent_code];
                            }
                        }
                    } elseif ($key == 't.return_reason') {
                        print dol_escape_htmltag($obj->return_reason);
                    } elseif ($key == 't.date_creation') {
                        print dol_print_date($db->jdate($obj->date_creation), 'day');
                    } elseif ($key == 't.date_modification') {
                        print dol_print_date($db->jdate($obj->date_modification), 'dayhour');
                    } elseif ($key == 't.date_return') {
                        print dol_print_date($db->jdate($obj->date_return), 'day');
                    } elseif ($key == 't.total_ht') {
                        print '<span class="amount">'.price($obj->total_ht).'</span>';
                        if (!$i) {
                            $totalarray['nbfield']++;
                        }
                        if (!$i) {
                            $totalarray['totalhtfield'] = $totalarray['nbfield'];
                        }
                        $totalarray['totalht'] += $obj->total_ht;
                    } elseif ($key == 't.total_ttc') {
                        print '<span class="amount">'.price($obj->total_ttc).'</span>';
                        if (!$i) {
                            $totalarray['nbfield']++;
                        }
                        if (!$i) {
                            $totalarray['totalttcfield'] = $totalarray['nbfield'];
                        }
                        $totalarray['totalttc'] += $obj->total_ttc;
                    } elseif ($key == 't.statut') {
                        print $supplierreturn->getLibStatut(5);
                    } else {
                        print '&nbsp;';
                    }
                    
                    print '</td>';
                    if (!$i) {
                        $totalarray['nbfield']++;
                    }
                }
            }
            
            // Action column - à droite par défaut
            if (!getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
                print '<td class="nowrap center">';
                if (($obj->statut == SupplierReturn::STATUS_DRAFT && $user->hasRight('supplierreturn', 'creer')) || 
                    ($obj->statut != SupplierReturn::STATUS_DRAFT && $user->hasRight('supplierreturn', 'supprimer'))) {
                    print '<input id="cb'.$obj->rowid.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$obj->rowid.'"'.(!empty($arrayofselected) && in_array($obj->rowid, $arrayofselected) ? ' checked="checked"' : '').'>';
                }
                if ($user->admin || $user->hasRight('supplierreturn', 'creer')) {
                    print '<a class="editfielda marginleftonly" href="card.php?id='.$obj->rowid.'&action=edit">';
                    print img_edit($langs->transnoentitiesnoconv('Edit'));
                    print '</a>';
                }
                print '</td>';
                if (!$i) {
                    $totalarray['nbfield']++;
                }
            }
            
            print '</tr>';
        }
        $i++;
    }
    
    // Show total line
    include DOL_DOCUMENT_ROOT.'/core/tpl/list_print_total.tpl.php';
    
    print '</table>';
    print '</div>';
    
    print '</form>';
    
    $db->free($resql);
} else {
    dol_print_error($db);
}

llxFooter();