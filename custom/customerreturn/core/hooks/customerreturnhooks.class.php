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
 * \file    core/hooks/customerreturnhooks.class.php
 * \ingroup customerreturns
 * \brief   Hooks for CustomerReturns module - Inter-module links
 */

class CustomerReturnHooks
{
    public $db;
    public $results = array();
    public $resprints;
    public $errors = array();

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function fetchObjectLinked($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs;
        if (!isModEnabled('customerreturn')) return 0;

        $supported_objects = array(
            'expedition' => 'expedition',
            'commande' => 'commande',
            'facture' => 'facture'
        );
        $object_type = $object->element ?? '';

        if (empty($object_type) || !isset($supported_objects[$object_type]) || empty($object->id)) return 0;

        $customerreturns_found = $this->findLinkedCustomerReturns($object_type, $object->id);
        if (!empty($customerreturns_found)) {
            if (!isset($object->linkedObjects['customerreturn'])) {
                $object->linkedObjects['customerreturn'] = array();
            }
            foreach ($customerreturns_found as $return_data) {
                $linked_return = new stdClass();
                $linked_return->id = $return_data['id'];
                $linked_return->ref = $return_data['ref'];
                $linked_return->statut = $return_data['statut'];
                $object->linkedObjects['customerreturn'][$return_data['id']] = $linked_return;
            }
        }
        return 1;
    }

    public function printTabsHead($parameters, &$object, &$action, $hookmanager)
    {
        global $langs;
        if (!isModEnabled('customerreturn')) return 0;
        $supported_objects = array('expedition', 'commande', 'facture');
        if (!isset($object->element) || !in_array($object->element, $supported_objects)) return 0;

        $nb_returns = $this->countLinkedCustomerReturns($object->element, $object->id);
        if ($nb_returns > 0) {
            $langs->load('customerreturn@customerreturn');
            $this->resprints = '<li class="noborder">';
            $this->resprints .= '<a href="'.DOL_URL_ROOT.'/custom/customerreturn/list.php?search_linked_object='.$object->element.'&search_linked_id='.$object->id.'">';
            $this->resprints .= $langs->trans('CustomerReturns').' ('.$nb_returns.')';
            $this->resprints .= '</a></li>';
        }
        return 1;
    }

    public function findLinkedCustomerReturns($object_type, $object_id)
    {
        $returns = array();
        $direct_field = '';
        switch ($object_type) {
            case 'expedition': $direct_field = 'fk_expedition'; break;
            case 'commande': $direct_field = 'fk_commande'; break;
            case 'facture': $direct_field = 'fk_facture'; break;
        }

        if ($direct_field) {
            $sql = "SELECT rowid as id, ref, statut FROM ".MAIN_DB_PREFIX."customerreturn WHERE ".$direct_field." = ".(int)$object_id." AND entity IN (".getEntity('customerreturn').")";
            $resql = $this->db->query($sql);
            if ($resql) {
                while ($obj = $this->db->fetch_object($resql)) {
                    $returns[$obj->id] = array('id' => $obj->id, 'ref' => $obj->ref, 'statut' => $obj->statut);
                }
            }
        }
        return $returns;
    }

    public function countLinkedCustomerReturns($object_type, $object_id)
    {
        return count($this->findLinkedCustomerReturns($object_type, $object_id));
    }
}