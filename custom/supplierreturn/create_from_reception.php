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
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/reception/class/reception.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once './class/supplierreturn.class.php';
require_once './class/supplierreturnline.class.php';

$langs->loadLangs(array("supplierreturn@supplierreturn", "other", "receptions", "products"));

$action = GETPOST('action', 'aZ09');
$reception_id = GETPOST('reception_id', 'int');
$socid = GETPOST('socid', 'int');

// Vérification des droits
if (!$user->admin && !$user->hasRight('supplierreturn', 'creer')) {
    accessforbidden();
}

$object = new SupplierReturn($db);
$form = new Form($db);
$formcompany = new FormCompany($db);

// Traitement du formulaire de création
if ($action == 'create_return' && $reception_id > 0) {
    $db->begin();
    
    $reception = new Reception($db);
    if ($reception->fetch($reception_id) > 0) {
        // Création du retour fournisseur
        $object->ref = '(PROV)'; // Référence provisoire, sera remplacée lors de la validation
        $object->supplier_ref = GETPOST('supplier_ref', 'alpha');
        $object->fk_soc = $reception->socid;
        $object->fk_reception = $reception_id;
        $object->date_creation = dol_now();
        $object->date_return = dol_mktime(0, 0, 0, GETPOST('date_returnmonth'), GETPOST('date_returnday'), GETPOST('date_returnyear'));
        $object->note_public = GETPOST('note_public', 'restricthtml');
        $object->note_private = GETPOST('note_private', 'restricthtml');
        // Gestion du motif de retour avec détail pour "Autre"
        $return_reason = GETPOST('return_reason', 'alpha');
        if ($return_reason === 'other') {
            $other_detail = GETPOST('other_reason_detail', 'alpha');
            $object->return_reason = $return_reason . ($other_detail ? ' : ' . $other_detail : '');
        } else {
            $object->return_reason = $return_reason;
        }
        $object->fk_user_author = $user->id;
        $object->statut = SupplierReturn::STATUS_DRAFT;

        // Récupération automatique des liaisons depuis la réception
        if (!empty($reception->origin) && !empty($reception->origin_id)) {
            if ($reception->origin == 'order_supplier' || $reception->origin == 'supplier_order') {
                $object->fk_commande_fournisseur = $reception->origin_id;
                
                // Rechercher la facture fournisseur liée à cette commande
                require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
                $sql = "SELECT ff.rowid FROM ".MAIN_DB_PREFIX."facture_fourn as ff";
                $sql .= " INNER JOIN ".MAIN_DB_PREFIX."element_element as ee ON (ee.fk_target = ff.rowid AND ee.targettype = 'invoice_supplier')";
                $sql .= " WHERE ee.fk_source = ".(int) $reception->origin_id;
                $sql .= " AND ee.sourcetype = 'order_supplier'";
                $sql .= " AND ff.entity IN (".getEntity('invoice').")";
                $sql .= " ORDER BY ff.datef DESC LIMIT 1";
                
                $resql = $db->query($sql);
                if ($resql) {
                    if ($obj = $db->fetch_object($resql)) {
                        $object->fk_facture_fourn = $obj->rowid;
                    }
                    $db->free($resql);
                }
            } elseif ($reception->origin == 'invoice_supplier') {
                $object->fk_facture_fourn = $reception->origin_id;
            }
        }

        if (empty($object->ref)) {
            $object->ref = $object->getNextNumRef();
        }

        $result = $object->create($user);
        if ($result > 0) {
            // Ajout des lignes sélectionnées
            $return_qtys = GETPOST('return_qty', 'array');
            $return_batches = GETPOST('return_batch', 'array'); // Get submitted batch numbers
            $return_fk_product_batches = GETPOST('return_fk_product_batch', 'array'); // Get submitted batch IDs
            $reception_lines = $object->getReceptionLines($reception_id);
            $lines_added = 0;

            foreach ($reception_lines as $i => $reception_line) {
                if (isset($return_qtys[$i]) && $return_qtys[$i] > 0) {
                    $qty = (float) $return_qtys[$i];
                    $batch = isset($return_batches[$i]) ? $return_batches[$i] : ''; // Use submitted batch number
                    $fk_product_batch = isset($return_fk_product_batches[$i]) ? (int) $return_fk_product_batches[$i] : 0;
                    
                    // Vérifier que la quantité ne dépasse pas ce qui est disponible
                    if ($qty <= $reception_line->qty_available_for_return) {
                        $result_line = $object->addLine(
                            $reception_line->fk_product,
                            $qty,
                            $reception_line->subprice,
                            $reception_line->description,
                            $reception_line->fk_entrepot,
                            $batch, // Pass the correct batch number
                            $reception_line->id,
                            $user,
                            $fk_product_batch
                        );
                        
                        if ($result_line > 0) {
                            $lines_added++;
                        } else {
                            setEventMessages('Erreur lors de l\'ajout de la ligne '.$reception_line->product_ref.': '.$object->error, null, 'errors');
                        }
                    } else {
                        setEventMessages('Quantité demandée ('.$qty.') supérieure à la quantité disponible ('.$reception_line->qty_available_for_return.') pour '.$reception_line->product_ref, null, 'warnings');
                    }
                }
            }

            if ($lines_added > 0) {
                // Créer les liaisons dans la table element_element
                // Lien avec la réception
                $object->add_object_linked('reception', $reception_id);
                
                // Lien avec la commande fournisseur si elle existe
                if (!empty($object->fk_commande_fournisseur)) {
                    $object->add_object_linked('order_supplier', $object->fk_commande_fournisseur);
                }
                
                // Lien avec la facture fournisseur si elle existe
                if (!empty($object->fk_facture_fourn)) {
                    $object->add_object_linked('invoice_supplier', $object->fk_facture_fourn);
                }
                
                $db->commit();
                setEventMessages('Retour fournisseur créé avec '.$lines_added.' ligne(s)', null, 'mesgs');
                header("Location: card.php?id=".$object->id);
                exit;
            } else {
                $db->rollback();
                setEventMessages('Aucune ligne valide ajoutée au retour', null, 'errors');
            }
        } else {
            $db->rollback();
            setEventMessages($object->error, $object->errors, 'errors');
        }
    } else {
        $db->rollback();
        setEventMessages('Réception non trouvée', null, 'errors');
    }
}

// Affichage de la page
llxHeader('', $langs->trans("CreateReturnFromReception"));

print load_fiche_titre($langs->trans("CreateReturnFromReception"), '', 'supplierreturn@supplierreturn');

if (!$reception_id) {
    // Étape 1 : Sélection du fournisseur et de la réception
    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';

    dol_fiche_head(array(), '', $langs->trans('SelectReception'));
    
    print '<table class="border centpercent">';
    
    // Sélection du fournisseur
    print '<tr><td class="fieldrequired titlefield">'.$langs->trans("Supplier").'</td><td>';
    print $formcompany->select_company($socid, 'socid', '(s.fournisseur:=:1)', 'SelectThirdParty', 1, 0, null, 0, 'minwidth300');
    print '</td></tr>';

    print '</table>';
    
    dol_fiche_end();

    print '<div class="center">';
    print '<input type="submit" class="button" value="'.$langs->trans("Search").'">';
    print '</div>';
    print '</form>';

    // Liste des réceptions récentes si un fournisseur est sélectionné
    if ($socid > 0) {
        print '<br>';
        print '<div class="div-table-responsive">';
        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre">';
        print '<td>'.$langs->trans("Ref").'</td>';
        print '<td>'.$langs->trans("Date").'</td>';
        print '<td>'.$langs->trans("Status").'</td>';
        print '<td class="center">'.$langs->trans("Action").'</td>';
        print '</tr>';

        $sql = "SELECT r.rowid, r.ref, r.date_creation, r.fk_statut";
        // TODO: Future Dolibarr update - check if column name changed back to r.statut
        // $sql = "SELECT r.rowid, r.ref, r.date_creation, r.statut";
        $sql .= " FROM ".MAIN_DB_PREFIX."reception as r";
        $sql .= " WHERE r.fk_soc = ".(int) $socid;
        $sql .= " AND r.entity IN (".getEntity('reception').")";
        $sql .= " AND r.fk_statut IN (1, 2)"; // Réceptions validées ET traitées
        // TODO: Future Dolibarr update - check if column name changed back to r.statut
        // $sql .= " AND r.statut IN (1, 2)";
        $sql .= " ORDER BY r.date_creation DESC";
        $sql .= " LIMIT 20";

        $resql = $db->query($sql);
        if ($resql) {
            $num = $db->num_rows($resql);
            if ($num > 0) {
                while ($obj = $db->fetch_object($resql)) {
                    print '<tr class="oddeven">';
                    print '<td>'.$obj->ref.'</td>';
                    print '<td>'.dol_print_date($db->jdate($obj->date_creation), 'day').'</td>';
                    // Affichage du statut correct
                    $status_label = '';
                    if ($obj->fk_statut == 1) {
                        $status_label = $langs->trans('StatusReceptionValidated');
                    } elseif ($obj->fk_statut == 2) {
                        $status_label = $langs->trans('StatusReceptionProcessed');
                    }
                    print '<td>'.$status_label.'</td>';
                    print '<td class="center">';
                    print '<a href="'.$_SERVER["PHP_SELF"].'?reception_id='.$obj->rowid.'" class="button">'.$langs->trans("Select").'</a>';
                    print '</td>';
                    print '</tr>';
                }
            } else {
                print '<tr><td colspan="4" class="center">'.$langs->trans('NoReceptionFound').'</td></tr>';
            }
        }
        print '</table>';
        print '</div>';
    }
} else {
    // Étape 2 : Sélection des produits depuis la réception
    $reception = new Reception($db);
    if ($reception->fetch($reception_id) > 0) {
        $reception_lines = $object->getReceptionLines($reception_id);

        if (empty($reception_lines)) {
            print '<div class="warning">'.$langs->trans('NoProductInReception').'</div>';
            print '<div class="center">';
            print '<a href="'.$_SERVER["PHP_SELF"].'" class="button">'.$langs->trans("Back").'</a>';
            print '</div>';
        } else {
            print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
            print '<input type="hidden" name="token" value="'.newToken().'">';
            print '<input type="hidden" name="action" value="create_return">';
            print '<input type="hidden" name="reception_id" value="'.$reception_id.'">';

            dol_fiche_head(array(), '', $langs->trans('NewSupplierReturn'));

            print '<table class="border centpercent">';

            // Informations de la réception
            print '<tr><td class="titlefield">'.$langs->trans("ReceptionRef").'</td><td>';
            print '<a href="'.DOL_URL_ROOT.'/reception/card.php?id='.$reception->id.'" target="_blank">'.$reception->ref.'</a>';
            print '</td></tr>';

            // Fournisseur
            require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
            $supplier = new Societe($db);
            $supplier->fetch($reception->socid);
            print '<tr><td>'.$langs->trans("Supplier").'</td><td>';
            print $supplier->getNomUrl(1);
            print '</td></tr>';

            // Référence du retour (générée automatiquement)
            print '<tr><td>'.$langs->trans("Ref").'</td><td>';
            print '<em>'.$langs->trans("AutoGenerateRef").'</em>';
            print '</td></tr>';
            
            // Référence fournisseur (nouveau champ)
            print '<tr><td>'.$langs->trans("SupplierRef").'</td><td>';
            print '<input name="supplier_ref" size="20" maxlength="255" value="'.GETPOST('supplier_ref').'" type="text">';
            print '</td></tr>';

            // Date de retour
            print '<tr><td class="fieldrequired">'.$langs->trans("DateReturn").'</td><td>';
            print $form->selectDate(dol_now(), 'date_return', 0, 0, 0, "add", 1, 1);
            print '</td></tr>';

            // Motif de retour - Utiliser les motifs configurés depuis l'administration
            print '<tr><td>'.$langs->trans("ReturnReason").'</td><td>';
            dol_include_once('/custom/supplierreturn/lib/supplierreturn.lib.php');
            $return_reasons = supplierreturns_get_return_reasons();
            print $form->selectarray('return_reason', $return_reasons, GETPOST('return_reason'), 1);
            print '</td></tr>';
            
            // Champ texte libre pour "Autre motif" (affiché dynamiquement via JavaScript)
            print '<tr id="other_reason_row" style="display:none;"><td>'.$langs->trans("OtherReasonDetail").'</td><td>';
            print '<input type="text" name="other_reason_detail" id="other_reason_detail" size="50" maxlength="255" value="'.GETPOST('other_reason_detail').'" placeholder="'.$langs->trans("SpecifyOtherReason").'">';
            print '</td></tr>';

            // Note publique
            print '<tr><td>'.$langs->trans("NotePublic").'</td><td>';
            print '<textarea name="note_public" wrap="soft" cols="60" rows="3">'.GETPOST('note_public').'</textarea>';
            print '</td></tr>';

            // Note privée
            print '<tr><td>'.$langs->trans("NotePrivate").'</td><td>';
            print '<textarea name="note_private" wrap="soft" cols="60" rows="3">'.GETPOST('note_private').'</textarea>';
            print '</td></tr>';

            print '</table>';

            dol_fiche_end();

            // Produits de la réception
            print '<br>';
            print '<div class="div-table-responsive">';
            print '<table class="noborder centpercent">';
            print '<tr class="liste_titre">';
            print '<td>'.$langs->trans("Product").'</td>';
            print '<td class="center">'.$langs->trans("QtyReceived").'</td>';
            print '<td class="center">'.$langs->trans("QtyAlreadyReturned").'</td>';
            print '<td class="center">'.$langs->trans("QtyAvailableForReturn").'</td>';
            print '<td class="center">'.$langs->trans("QtyToReturn").'</td>';
            print '<td>'.$langs->trans("Warehouse").'</td>';
            print '<td>'.$langs->trans("Batch").'</td>';
            print '</tr>';

            foreach ($reception_lines as $i => $line) {
                print '<tr class="oddeven">';
                
                // Produit
                print '<td>';
                if ($line->fk_product > 0) {
                    require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
                    $product = new Product($db);
                    $product->fetch($line->fk_product);
                    print $product->getNomUrl(1);
                } else {
                    print dol_htmlentitiesbr($line->description);
                }
                print '</td>';
                
                // Quantité reçue
                print '<td class="center">'.price($line->qty_received, 0, '', 1, -1, -1, '').'</td>';
                
                // Quantité déjà retournée
                print '<td class="center">'.price($line->qty_already_returned, 0, '', 1, -1, -1, '').'</td>';
                
                // Quantité disponible pour retour
                print '<td class="center">'.price($line->qty_available_for_return, 0, '', 1, -1, -1, '').'</td>';
                
                // Quantité à retourner
                print '<td class="center">';
                if ($line->qty_available_for_return > 0) {
                    print '<input type="number" name="return_qty['.$i.']" min="0" max="'.$line->qty_available_for_return.'" step="1" style="width: 80px;" value="0">';
                    // Add a hidden field to submit the batch number for this line
                    print '<input type="hidden" name="return_batch['.$i.']" value="'.dol_escape_htmltag($line->batch).'">';
                    print '<input type="hidden" name="return_fk_product_batch['.$i.']" value="'.$line->fk_product_batch.'">';
                } else {
                    print '<span class="opacitymedium">-</span>';
                }
                print '</td>';
                
                // Entrepôt
                print '<td>';
                print $line->warehouse_label ? $line->warehouse_label : '-';
                print '</td>';
                
                // Lot
                print '<td>'.$line->batch.'</td>';
                
                print '</tr>';
            }

            print '</table>';
            print '</div>';

            print '<div class="center" style="padding: 20px;">';
            print '<input type="submit" class="button" name="create" value="'.$langs->trans("CreateReturn").'">';
            print '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
            print '<input type="button" class="button button-cancel" value="'.$langs->trans("Cancel").'" onClick="javascript:history.go(-1)">';
            print '</div>';

            print '</form>';
        }
    } else {
        print '<div class="error">'.$langs->trans('ReceptionNotFound').'</div>';
    }
}

// JavaScript pour gestion dynamique du champ "Autre motif"
?>
<script type="text/javascript">
$(document).ready(function() {
    // Fonction pour afficher/masquer le champ "Autre motif"
    function toggleOtherReasonField() {
        var selectedReason = $('select[name="return_reason"]').val();
        if (selectedReason === 'other') {
            $('#other_reason_row').show();
            $('#other_reason_detail').prop('required', true);
        } else {
            $('#other_reason_row').hide();
            $('#other_reason_detail').prop('required', false);
            $('#other_reason_detail').val(''); // Vider le champ si caché
        }
    }
    
    // Vérifier l'état initial
    toggleOtherReasonField();
    
    // Écouter les changements de sélection
    $('select[name="return_reason"]').change(function() {
        toggleOtherReasonField();
    });
    
    // Validation du formulaire
    $('form').submit(function(e) {
        var selectedReason = $('select[name="return_reason"]').val();
        var otherDetail = $('#other_reason_detail').val().trim();
        
        if (selectedReason === 'other' && otherDetail === '') {
            alert('<?php echo addslashes($langs->trans("SpecifyOtherReason")); ?>');
            $('#other_reason_detail').focus();
            e.preventDefault();
            return false;
        }
    });
});
</script>
<?php

llxFooter();
$db->close();
?>
