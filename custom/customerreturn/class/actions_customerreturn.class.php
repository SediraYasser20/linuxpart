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

class ActionsCustomerreturn
{
    public $db;
    public $error = '';
    public $errors = array();

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
    {
        global $user, $langs;
        if (in_array($parameters['context'], array('expeditioncard', 'shipmentcard')) && $user->hasRight('customerreturn', 'creer')) {
            if ($object->statut > 0) {
                $langs->load("customerreturn@customerreturn");
                print '<a class="butAction" href="'.dol_buildpath('/custom/customerreturn/create_from_shipment.php', 1).'?shipment_id='.$object->id.'">'.$langs->trans('CreateCustomerReturn').'</a>';
            }
        }
        return 0;
    }

    public function printCommonFooter($parameters, &$object, &$action, $hookmanager)
    {
        global $user, $langs, $db;
        if (in_array($parameters['context'], array('expeditioncard', 'ordercard')) && $user->hasRight('customerreturn', 'lire')) {
            $langs->load("customerreturn@customerreturn");
            $sql = "SELECT cr.rowid, cr.ref, cr.date_return, cr.statut, cr.total_ht";
            $sql .= " FROM ".MAIN_DB_PREFIX."customerreturn as cr";
            if ($parameters['context'] == 'expeditioncard') {
                $sql .= " WHERE cr.fk_expedition = ".$object->id;
            } else { // ordercard
                $sql .= " WHERE cr.fk_commande = ".$object->id;
            }
            $sql .= " ORDER BY cr.date_creation DESC";

            $resql = $db->query($sql);
            if ($resql && $db->num_rows($resql) > 0) {
                print '<div class="fichecenter"><div class="fichehalfleft">';
                print '<div class="titre">'.$langs->trans("RelatedReturns").' ('.$db->num_rows($resql).')</div>';
                print '<table class="noborder centpercent">';
                print '<tr class="liste_titre"><td>'.$langs->trans("Ref").'</td><td class="center">'.$langs->trans("DateReturn").'</td><td class="center">'.$langs->trans("Status").'</td><td class="right">'.$langs->trans("Amount").'</td></tr>';

                require_once dol_buildpath('/custom/customerreturn/class/customerreturn.class.php');
                $return_static = new CustomerReturn($db);

                while ($obj = $db->fetch_object($resql)) {
                    print '<tr class="oddeven">';
                    print '<td><a href="'.dol_buildpath('/custom/customerreturn/card.php', 1).'?id='.$obj->rowid.'">'.img_object('', 'customerreturn@customerreturn').' '.$obj->ref.'</a></td>';
                    print '<td class="center">'.dol_print_date($db->jdate($obj->date_return), 'day').'</td>';
                    print '<td class="center">'.$return_static->LibStatut($obj->statut, 3).'</td>';
                    print '<td class="right">'.price($obj->total_ht).'</td>';
                    print '</tr>';
                }
                print '</table></div></div><div class="clearboth"></div>';
            }
        }
        return 0;
    }
}