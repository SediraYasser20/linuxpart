<?php
/* Copyright (C) 2025 Nicolas Testori
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

/**
 * \file    core/hooks/supplierreturnshooks.class.php
 * \ingroup supplierreturns
 * \brief   Hooks for SupplierReturns module - Inter-module links
 */

/**
 *  Class to manage hooks for SupplierReturns module
 */
class SupplierReturnsHooks
{
    /**
     * @var DoliDB $db Database handler
     */
    public $db;

    /**
     * @var array Hook results
     */
    public $results = array();

    /**
     * @var string String displayed by executeHook() immediately after return
     */
    public $resprints;

    /**
     * @var array Errors
     */
    public $errors = array();

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Hook to add supplier returns links in other modules (reception, order, invoice)
     * Called in fetchObjectLinked() method
     *
     * @param array $parameters Hook parameters
     * @param object $object Current object
     * @param string $action Current action
     * @param HookManager $hookmanager Hook manager
     * @return int Return integer <0 if KO, 0 if no action, >0 if OK
     */
    public function fetchObjectLinked($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs;

        if (!isModEnabled('supplierreturns')) {
            return 0;
        }

        $error = 0;
        $this->results = array();
        $this->resprints = '';

        // Only process for specific object types that can be linked to supplier returns
        $supported_objects = array(
            'reception' => 'reception',
            'order_supplier' => 'commande_fournisseur', 
            'invoice_supplier' => 'facture_fourn'
        );

        $object_type = '';
        $table_name = '';
        
        // Detect object type
        if (isset($object->element)) {
            if (array_key_exists($object->element, $supported_objects)) {
                $object_type = $object->element;
                $table_name = $supported_objects[$object->element];
            }
        }

        if (empty($object_type) || empty($object->id)) {
            return 0; // Not a supported object or no ID
        }

        dol_syslog("SupplierReturnsHooks::fetchObjectLinked Processing $object_type ID {$object->id}", LOG_DEBUG);

        try {
            // Search for supplier returns linked to this object
            $supplierreturns_found = $this->findLinkedSupplierReturns($object_type, $object->id);

            if (!empty($supplierreturns_found)) {
                // Add supplier returns to the linked objects
                if (!isset($object->linkedObjects['supplierreturn'])) {
                    $object->linkedObjects['supplierreturn'] = array();
                }

                foreach ($supplierreturns_found as $supplierreturn_data) {
                    // Create a simple object with the necessary properties
                    $linked_return = new stdClass();
                    $linked_return->id = $supplierreturn_data['id'];
                    $linked_return->rowid = $supplierreturn_data['id'];
                    $linked_return->ref = $supplierreturn_data['ref'];
                    $linked_return->label = $supplierreturn_data['ref'];
                    $linked_return->statut = $supplierreturn_data['statut'];
                    $linked_return->status = $supplierreturn_data['statut'];

                    $object->linkedObjects['supplierreturn'][$supplierreturn_data['id']] = $linked_return;
                    
                    dol_syslog("SupplierReturnsHooks::fetchObjectLinked Added supplier return {$supplierreturn_data['ref']} to $object_type ID {$object->id}", LOG_INFO);
                }

                $this->results['supplierreturns_added'] = count($supplierreturns_found);
            }

        } catch (Exception $e) {
            dol_syslog("SupplierReturnsHooks::fetchObjectLinked Error: " . $e->getMessage(), LOG_ERR);
            $error++;
        }

        if ($error) {
            $this->errors[] = 'Error in SupplierReturnsHooks::fetchObjectLinked';
            return -1;
        }

        return 1;
    }

    /**
     * Hook to add supplier returns tab in other modules
     * Called in various card.php files
     *
     * @param array $parameters Hook parameters
     * @param object $object Current object
     * @param string $action Current action
     * @param HookManager $hookmanager Hook manager
     * @return int Return integer <0 if KO, 0 if no action, >0 if OK
     */
    public function printTabsHead($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs, $user;

        if (!isModEnabled('supplierreturns')) {
            return 0;
        }

        // Only add tab for supported objects
        $supported_objects = array('reception', 'order_supplier', 'invoice_supplier');
        
        if (!isset($object->element) || !in_array($object->element, $supported_objects)) {
            return 0;
        }

        // Check if object has linked supplier returns
        $nb_supplier_returns = $this->countLinkedSupplierReturns($object->element, $object->id);
        
        if ($nb_supplier_returns > 0) {
            $langs->load('supplierreturn@supplierreturn');
            
            // Add the tab
            $this->resprints = '<li class="noborder">';
            $this->resprints .= '<a href="' . DOL_URL_ROOT . '/custom/supplierreturn/list.php?search_linked_object=' . $object->element . '&search_linked_id=' . $object->id . '">';
            $this->resprints .= $langs->trans('SupplierReturns') . ' (' . $nb_supplier_returns . ')';
            $this->resprints .= '</a>';
            $this->resprints .= '</li>';
        }

        return 1;
    }

    /**
     * Find supplier returns linked to a specific object
     *
     * @param string $object_type Type of object (reception, order_supplier, invoice_supplier)
     * @param int $object_id ID of the object
     * @return array Array of supplier returns data
     */
    public function findLinkedSupplierReturns($object_type, $object_id)
    {
        $supplier_returns = array();

        // Search in direct links (fk_ fields)
        $direct_field = '';
        switch ($object_type) {
            case 'reception':
                $direct_field = 'fk_reception';
                break;
            case 'order_supplier':
                $direct_field = 'fk_commande_fournisseur';
                break;
            case 'invoice_supplier':
                $direct_field = 'fk_facture_fourn';
                break;
        }

        if ($direct_field) {
            $sql = "SELECT rowid as id, ref, statut FROM " . MAIN_DB_PREFIX . "supplierreturn";
            $sql .= " WHERE " . $direct_field . " = " . (int) $object_id;
            $sql .= " AND entity IN (" . getEntity('supplierreturn') . ")";

            $resql = $this->db->query($sql);
            if ($resql) {
                while ($obj = $this->db->fetch_object($resql)) {
                    $supplier_returns[] = array(
                        'id' => $obj->id,
                        'ref' => $obj->ref,
                        'statut' => $obj->statut
                    );
                }
                $this->db->free($resql);
            }
        }

        // Search in element_element table (manual links)
        $sql = "SELECT sr.rowid as id, sr.ref, sr.statut";
        $sql .= " FROM " . MAIN_DB_PREFIX . "supplierreturn sr";
        $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "element_element ee ON ee.fk_source = sr.rowid";
        $sql .= " WHERE ee.sourcetype = 'supplierreturn'";
        $sql .= " AND ee.targettype = '" . $this->db->escape($object_type) . "'";
        $sql .= " AND ee.fk_target = " . (int) $object_id;
        $sql .= " AND sr.entity IN (" . getEntity('supplierreturn') . ")";

        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                // Avoid duplicates
                $found = false;
                foreach ($supplier_returns as $existing) {
                    if ($existing['id'] == $obj->id) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $supplier_returns[] = array(
                        'id' => $obj->id,
                        'ref' => $obj->ref,
                        'statut' => $obj->statut
                    );
                }
            }
            $this->db->free($resql);
        }

        return $supplier_returns;
    }

    /**
     * Count supplier returns linked to a specific object
     *
     * @param string $object_type Type of object
     * @param int $object_id ID of the object
     * @return int Number of linked supplier returns
     */
    public function countLinkedSupplierReturns($object_type, $object_id)
    {
        $supplier_returns = $this->findLinkedSupplierReturns($object_type, $object_id);
        return count($supplier_returns);
    }
}