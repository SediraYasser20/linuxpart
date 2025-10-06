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

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) $res = @include __DIR__.'/../../main.inc.php';
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
require_once __DIR__.'/class/customerreturn.class.php';
require_once __DIR__.'/class/customerreturnline.class.php';

$langs->loadLangs(array("customerreturn@customerreturn", "other", "expeditions", "products"));

$action = GETPOST('action', 'aZ09');
$shipment_id = GETPOST('shipment_id', 'int');
$socid = GETPOST('socid', 'int');

if (!$user->admin && !$user->hasRight('customerreturn', 'creer')) accessforbidden();

$object = new CustomerReturn($db);
$form = new Form($db);
$formcompany = new FormCompany($db);

if ($action == 'create_return' && $shipment_id > 0) {
    $db->begin();
    $shipment = new Expedition($db);
    if ($shipment->fetch($shipment_id) > 0) {
        $object->ref = '(PROV)';
        $object->fk_soc = $shipment->socid;
        $object->fk_expedition = $shipment_id;
        $object->date_creation = dol_now();
        $object->date_return = dol_mktime(0, 0, 0, GETPOST('date_returnmonth'), GETPOST('date_returnday'), GETPOST('date_returnyear'));
        $object->note_public = GETPOST('note_public', 'restricthtml');
        $object->note_private = GETPOST('note_private', 'restricthtml');
        $object->return_reason = GETPOST('return_reason', 'alpha');
        $object->fk_user_author = $user->id;
        $object->statut = CustomerReturn::STATUS_DRAFT;

        if (!empty($shipment->origin) && !empty($shipment->origin_id)) {
            if ($shipment->origin == 'commande') {
                $object->fk_commande = $shipment->origin_id;
            }
        }

        $result = $object->create($user);
        if ($result > 0) {
            $return_qtys = GETPOST('return_qty', 'array');
            $shipment_lines = $object->getShipmentLines($shipment_id);
            $lines_added = 0;
            foreach ($shipment_lines as $i => $shipment_line) {
                if (isset($return_qtys[$i]) && $return_qtys[$i] > 0) {
                    $qty = (float) $return_qtys[$i];
                    if ($qty <= $shipment_line->qty_available_for_return) {
                        if ($object->addLine($shipment_line->fk_product, $qty, $shipment_line->subprice, $shipment_line->description, $shipment_line->fk_entrepot, $shipment_line->batch, $shipment_line->id, $user) > 0) {
                            $lines_added++;
                        }
                    }
                }
            }

            if ($lines_added > 0) {
                $object->add_object_linked('expedition', $shipment_id);
                if (!empty($object->fk_commande)) {
                    $object->add_object_linked('commande', $object->fk_commande);
                }
                $db->commit();
                header("Location: ".dol_buildpath('/custom/customerreturn/card.php', 1)."?id=".$object->id);
                exit;
            }
        }
    }
    $db->rollback();
}

llxHeader('', $langs->trans("CreateReturnFromShipment"));
print load_fiche_titre($langs->trans("CreateReturnFromShipment"), '', 'customerreturn@customerreturn');

if (!$shipment_id) {
    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'"><input type="hidden" name="token" value="'.newToken().'">';
    dol_fiche_head(array(), '', $langs->trans('SelectShipment'));
    print '<table class="border centpercent">';
    print '<tr><td class="fieldrequired titlefield">'.$langs->trans("Customer").'</td><td>';
    print $formcompany->select_company($socid, 'socid', '', 'SelectThirdParty', 1);
    print '</td></tr></table>';
    dol_fiche_end();
    print '<div class="center"><input type="submit" class="button" value="'.$langs->trans("Search").'"></div></form>';

    if ($socid > 0) {
        print '<br><div class="div-table-responsive"><table class="noborder centpercent">';
        print '<tr class="liste_titre"><td>'.$langs->trans("Ref").'</td><td>'.$langs->trans("Date").'</td><td>'.$langs->trans("Status").'</td><td class="center">'.$langs->trans("Action").'</td></tr>';
        $sql = "SELECT e.rowid, e.ref, e.date_creation, e.fk_statut FROM ".MAIN_DB_PREFIX."expedition as e WHERE e.fk_soc = ".(int) $socid." AND e.entity IN (".getEntity('expedition').") AND e.fk_statut IN (1, 2) ORDER BY e.date_creation DESC LIMIT 20";
        $resql = $db->query($sql);
        if ($resql && $db->num_rows($resql) > 0) {
            $expedition_static = new Expedition($db);
            while ($obj = $db->fetch_object($resql)) {
                print '<tr class="oddeven"><td>'.$obj->ref.'</td><td>'.dol_print_date($db->jdate($obj->date_creation), 'day').'</td>';
                print '<td>'.$expedition_static->LibStatut($obj->fk_statut, 5).'</td>';
                print '<td class="center"><a href="'.$_SERVER["PHP_SELF"].'?shipment_id='.$obj->rowid.'" class="button">'.$langs->trans("Select").'</a></td></tr>';
            }
        } else {
            print '<tr><td colspan="4" class="center">'.$langs->trans('NoShipmentFound').'</td></tr>';
        }
        print '</table></div>';
    }
} else {
    $shipment = new Expedition($db);
    if ($shipment->fetch($shipment_id) > 0) {
        $shipment_lines = $object->getShipmentLines($shipment_id);
        if (empty($shipment_lines)) {
            print '<div class="warning">'.$langs->trans('NoProductInShipment').'</div>';
        } else {
            print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="create_return"><input type="hidden" name="shipment_id" value="'.$shipment_id.'">';
            dol_fiche_head(array(), '', $langs->trans('NewCustomerReturn'));
            print '<table class="border centpercent">';
            print '<tr><td class="titlefield">'.$langs->trans("ShipmentRef").'</td><td><a href="'.DOL_URL_ROOT.'/expedition/card.php?id='.$shipment->id.'" target="_blank">'.$shipment->ref.'</a></td></tr>';
            $customer = new Societe($db);
            $customer->fetch($shipment->socid);
            print '<tr><td>'.$langs->trans("Customer").'</td><td>'.$customer->getNomUrl(1).'</td></tr>';
            print '<tr><td class="fieldrequired">'.$langs->trans("DateReturn").'</td><td>'.$form->selectDate(dol_now(), 'date_return', 0, 0, 0, "add", 1, 1).'</td></tr>';
            print '<tr><td>'.$langs->trans("ReturnReason").'</td><td><input type="text" name="return_reason" size="50"></td></tr>';
            print '</table>';
            dol_fiche_end();

            print '<br><div class="div-table-responsive"><table class="noborder centpercent">';
            print '<tr class="liste_titre"><td>'.$langs->trans("Product").'</td><td class="center">'.$langs->trans("SerialNumber").'</td><td class="center">'.$langs->trans("QtyShipped").'</td><td class="center">'.$langs->trans("QtyAlreadyReturned").'</td><td class="center">'.$langs->trans("QtyAvailableForReturn").'</td><td class="center">'.$langs->trans("QtyToReturn").'</td></tr>';
            foreach ($shipment_lines as $i => $line) {
                print '<tr class="oddeven"><td>';
                if ($line->fk_product > 0) {
                    $product = new Product($db);
                    $product->fetch($line->fk_product);
                    print $product->getNomUrl(1);
                } else {
                    print dol_htmlentitiesbr($line->description);
                }
                print '</td><td class="center">'.($line->batch ? dol_htmlentitiesbr($line->batch) : '-').'</td>';
                print '<td class="center">'.$line->qty_shipped.'</td><td class="center">'.$line->qty_already_returned.'</td><td class="center">'.$line->qty_available_for_return.'</td>';
                print '<td class="center">';
                if ($line->qty_available_for_return > 0) {
                    print '<input type="number" name="return_qty['.$i.']" min="0" max="'.$line->qty_available_for_return.'" value="0" style="width: 80px;">';
                } else {
                    print '0';
                }
                print '</td></tr>';
            }
            print '</table></div>';
            print '<div class="center" style="padding: 20px;"><input type="submit" class="button" value="'.$langs->trans("CreateReturn").'"><a href="'.$_SERVER["PHP_SELF"].'" class="button button-cancel">'.$langs->trans("Cancel").'</a></div>';
            print '</form>';
        }
    } else {
        print '<div class="error">'.$langs->trans('ShipmentNotFound').'</div>';
    }
}
llxFooter();
$db->close();
?>
