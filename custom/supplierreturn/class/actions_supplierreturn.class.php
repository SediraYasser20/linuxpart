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


class ActionsSupplierreturn
{
    public $db;
    public $error = '';
    public $errors = array();
    public $results = array();
    public $resprints;

    public function __construct($db)
    {
        $this->db = $db;
        
        // Force correct module configuration on every hook call
        $this->forceCorrectModuleName();
    }
    
    private function forceCorrectModuleName()
    {
        global $conf;
        
        // Force module to use correct singular name
        if (isset($conf->global->MAIN_MODULE_SUPPLIERRETURNS)) {
            $conf->global->MAIN_MODULE_SUPPLIERRETURN = $conf->global->MAIN_MODULE_SUPPLIERRETURNS;
        }
        
        // Ensure module is properly enabled with complete configuration
        if (empty($conf->supplierreturn)) {
            $conf->supplierreturn = new stdClass();
        }
        $conf->supplierreturn->enabled = 1;
        
        // Ensure dir_output is defined to avoid undefined property warnings
        if (empty($conf->supplierreturn->dir_output)) {
            $conf->supplierreturn->dir_output = DOL_DATA_ROOT.'/supplierreturn';
        }
        if (empty($conf->supplierreturn->multidir_output)) {
            $conf->supplierreturn->multidir_output = array();
            $conf->supplierreturn->multidir_output[$conf->entity] = DOL_DATA_ROOT.'/supplierreturn';
        }
    }

    /**
     * Add button "Create Supplier Return" on reception card
     */
    public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs;

        $error = 0;
        $this->results = array();
        $this->resprints = '';

        // Debug: log hook execution
        dol_syslog("ActionsSupplierReturns::addMoreActionsButtons context=".$parameters['context'], LOG_DEBUG);

        // Load translations
        $langs->loadLangs(array("supplierreturn@supplierreturn"));

        // Check if we're on a reception page - be more flexible with context detection
        $contexts = explode(':', $parameters['context'] ?? '');
        $isReceptionContext = (
            in_array('receptioncard', $contexts) || 
            in_array('reception', $contexts) ||
            (isset($object->element) && $object->element == 'reception') ||
            empty($parameters['context']) // Sometimes context is not set
        );
        
        if ($isReceptionContext) {
            // Check if module is enabled (use singular name)
            if (!isModEnabled('supplierreturn')) {
                dol_syslog("ActionsSupplierreturn: module not enabled", LOG_WARNING);
                return 0;
            }

            // Check if object is a reception and is validated or processed
            if (isset($object->element) && $object->element == 'reception' && ($object->statut == 1 || $object->statut == 2)) {
                // Check user permissions (use singular name)
                if ($user->admin || $user->hasRight('supplierreturn', 'creer')) {
                    // Print directly instead of storing in resprints
                    print '<div class="inline-block divButAction">';
                    print '<a class="butAction" href="'.dol_buildpath('/custom/supplierreturn/create_from_reception.php', 1).'?reception_id='.$object->id.'">';
                    print $langs->trans('CreateSupplierReturn');
                    print '</a></div>';
                    
                    // Also store in resprints for compatibility
                    $this->resprints .= '<div class="inline-block divButAction">';
                    $this->resprints .= '<a class="butAction" href="'.dol_buildpath('/custom/supplierreturn/create_from_reception.php', 1).'?reception_id='.$object->id.'">';
                    $this->resprints .= $langs->trans('CreateSupplierReturn');
                    $this->resprints .= '</a></div>';
                    
                    dol_syslog("ActionsSupplierReturns: button added for reception ".$object->id, LOG_DEBUG);
                } else {
                    dol_syslog("ActionsSupplierReturns: user has no permission", LOG_DEBUG);
                }
            } else {
                dol_syslog("ActionsSupplierReturns: object not suitable (element=".(isset($object->element)?$object->element:'undefined').", statut=".(isset($object->statut)?$object->statut:'undefined').")", LOG_DEBUG);
            }
        }

        if (!$error) {
            return 0;
        } else {
            $this->errors[] = 'Error in addMoreActionsButtons';
            return -1;
        }
    }

    /**
     * Hook to handle document download for supplierreturn modulepart
     * This intercepts document.php calls for our custom modulepart
     */
    public function downloadDocument($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user;
        
        $modulepart = $parameters['modulepart'];
        
        // Only handle our modulepart
        if ($modulepart === 'supplierreturn' || $modulepart === 'supplierreturns') {
            // Check permissions
            if (!$user->hasRight('supplierreturn', 'lire')) {
                http_response_code(403);
                print "Access denied";
                exit;
            }
            
            // Redirect to our custom viewdoc.php
            $original_file = $parameters['original_file'];
            $entity = $parameters['entity'];
            
            $redirect_url = dol_buildpath('/custom/supplierreturn/viewdoc.php', 1);
            $redirect_url .= '?modulepart=' . urlencode($modulepart);
            $redirect_url .= '&file=' . urlencode($original_file);
            if ($entity) $redirect_url .= '&entity=' . urlencode($entity);
            
            // Redirect and stop execution
            header('Location: ' . $redirect_url);
            exit;
        }
        
        return 0; // Continue normal processing for other moduleparts
    }

    /**
     * Display related returns in reception footer
     */
    public function printCommonFooter($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs;


        $contexts = explode(':', $parameters['context']);
        $isReceptionContext = in_array('receptioncard', $contexts);
        $isSupplierOrderContext = in_array('ordersuppliercard', $contexts);
        
        if ($isReceptionContext || $isSupplierOrderContext) {
            if (($user->admin || $user->hasRight('supplierreturn', 'lire')) && $object->id > 0) {
                
                $langs->loadLangs(array("supplierreturn@supplierreturn"));
                
                // Get related supplier returns
                $sql = "SELECT sr.rowid, sr.ref, sr.date_return, sr.statut, sr.total_ht";
                $sql .= " FROM ".MAIN_DB_PREFIX."supplierreturn as sr";
                
                if ($isReceptionContext) {
                    $sql .= " WHERE sr.fk_reception = ".(int) $object->id;
                } elseif ($isSupplierOrderContext) {
                    $sql .= " WHERE sr.fk_commande_fournisseur = ".(int) $object->id;
                }
                
                $sql .= " ORDER BY sr.date_creation DESC";

                $resql = $this->db->query($sql);
                if ($resql) {
                    $num = $this->db->num_rows($resql);
                    if ($num > 0) {
                        require_once dol_buildpath('/custom/supplierreturn/class/supplierreturn.class.php');
                        
                        print '<div class="fichecenter">';
                        print '<div class="fichehalfleft">';
                        print '<div class="titre">'.$langs->trans("RelatedReturns").' ('.$num.')</div>';
                        print '<table class="noborder centpercent">';
                        print '<tr class="liste_titre">';
                        print '<td>'.$langs->trans("Ref").'</td>';
                        print '<td class="center">'.$langs->trans("DateReturn").'</td>';
                        print '<td class="center">'.$langs->trans("Status").'</td>';
                        print '<td class="right">'.$langs->trans("Amount").'</td>';
                        print '</tr>';

                        while ($obj = $this->db->fetch_object($resql)) {
                            $supplierreturn_static = new SupplierReturn($this->db);
                            
                            print '<tr class="oddeven">';
                            print '<td>';
                            print '<a href="'.dol_buildpath('/custom/supplierreturn/card.php', 1).'?id='.$obj->rowid.'">';
                            print img_object('', 'supplierreturn@supplierreturn').' '.$obj->ref;
                            print '</a>';
                            print '</td>';
                            print '<td class="center">';
                            if ($obj->date_return) {
                                print dol_print_date($this->db->jdate($obj->date_return), 'day');
                            } else {
                                print '-';
                            }
                            print '</td>';
                            print '<td class="center">'.$supplierreturn_static->LibStatut($obj->statut, 3).'</td>';
                            print '<td class="right">';
                            if ($obj->total_ht > 0) {
                                print price($obj->total_ht);
                            } else {
                                print '-';
                            }
                            print '</td>';
                            print '</tr>';
                        }
                        print '</table>';
                        print '</div>';
                        print '</div>';
                        print '<div class="clearboth"></div>';
                    }
                }
            }
        }

        return 0;
    }

    /**
     * Additional column in reception list to show returns count
     */
    public function printFieldListOption($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs;

        // Debug: log hook execution and context
        dol_syslog("ActionsSupplierReturns::printFieldListOption context=".$parameters['context'], LOG_DEBUG);
        
        // Check multiple possible contexts for reception lists
        $contexts = explode(':', $parameters['context'] ?? '');
        $isReceptionListContext = (
            in_array('receptionlist', $contexts) || 
            in_array('reception', $contexts) ||
            (empty($parameters['context']) && $_SERVER['PHP_SELF'] && strpos($_SERVER['PHP_SELF'], '/reception/list.php') !== false)
        );
        
        if ($isReceptionListContext) {
            if ($user->admin || $user->hasRight('supplierreturn', 'lire')) {
                $langs->loadLangs(array("supplierreturn@supplierreturn"));
                print '<td class="center nowrap">'.$langs->trans("Returns").'</td>';
                dol_syslog("ActionsSupplierReturns: printFieldListOption header added", LOG_DEBUG);
            }
        }

        return 0;
    }

    /**
     * Show return count for each reception in list
     */
    public function printFieldListValue($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs;

        // Debug: log hook execution and context
        dol_syslog("ActionsSupplierReturns::printFieldListValue context=".$parameters['context']." object_id=".(isset($object->id)?$object->id:'undefined'), LOG_DEBUG);
        
        // Check multiple possible contexts for reception lists
        $contexts = explode(':', $parameters['context'] ?? '');
        $isReceptionListContext = (
            in_array('receptionlist', $contexts) || 
            in_array('reception', $contexts) ||
            (empty($parameters['context']) && $_SERVER['PHP_SELF'] && strpos($_SERVER['PHP_SELF'], '/reception/list.php') !== false)
        );
        
        if ($isReceptionListContext) {
            if ($user->admin || $user->hasRight('supplierreturn', 'lire')) {
                
                // Ensure we have a valid reception object with ID
                if (!isset($object->id) || $object->id <= 0) {
                    print '<td class="center"><span class="opacitymedium">-</span></td>';
                    return 0;
                }
                
                // Count returns for this reception
                $sql = "SELECT COUNT(*) as nb_returns";
                $sql .= " FROM ".MAIN_DB_PREFIX."supplierreturn";
                $sql .= " WHERE fk_reception = ".(int) $object->id;
                $sql .= " AND entity = ".(int) $conf->entity;
                
                $resql = $this->db->query($sql);
                $nb_returns = 0;
                if ($resql) {
                    $obj_count = $this->db->fetch_object($resql);
                    $nb_returns = (int) $obj_count->nb_returns;
                }
                
                print '<td class="center nowrap">';
                if ($nb_returns > 0) {
                    print '<a href="'.dol_buildpath('/custom/supplierreturn/list.php', 1).'?search_reception='.$object->ref.'" title="'.$langs->trans('SeeReturnsForReception').'">';
                    print '<span class="badge badge-status4">'.$nb_returns.'</span>';
                    print '</a>';
                } else {
                    print '<span class="opacitymedium">0</span>';
                }
                print '</td>';
                
                dol_syslog("ActionsSupplierReturns: printFieldListValue displayed ".$nb_returns." returns for reception ".$object->id, LOG_DEBUG);
            }
        }

        return 0;
    }

    /**
     * Handle custom actions
     */
    public function doActions($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs;

        $error = 0;
        $this->results = array();
        $this->resprints = '';

        return 0;
    }


}
?>