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

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

class CustomerReturn extends CommonObject
{
    public $table_element = 'customerreturn';
    public $element = 'customerreturn';
    public $picto = 'customerreturn@customerreturn';

    public $id;
    public $ref;
    public $fk_soc;
    public $date_creation;
    public $date_modification;
    public $fk_user_author;
    public $fk_user_modif;
    public $note_public;
    public $note_private;
    public $statut;
    public $total_ht;
    public $total_ttc;
    public $model_pdf = 'standard';
    public $last_main_doc;
    public $lines = array();
    public $thirdparty;

    // Relations with other documents
    public $fk_expedition;
    public $fk_commande;
    public $fk_facture;
    public $return_reason;
    public $date_return;
    public $customer_ref;

    const STATUS_DRAFT = 0;
    const STATUS_VALIDATED = 1;
    const STATUS_CLOSED = 2;
    const STATUS_RETURNED_TO_SUPPLIER = 3;
    const STATUS_CHANGED_PRODUCT_FOR_CLIENT = 4;
    const STATUS_REIMBURSED_MONEY_TO_CLIENT = 5;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function create($user, $notrigger = false)
    {
        global $langs;
        $error = 0;
        $this->db->begin();
        $now = dol_now();

        if (empty($this->ref) || $this->ref == '(PROV)') {
            $this->ref = '(PROV)';
        }

        $sql = "INSERT INTO ".MAIN_DB_PREFIX."customerreturn (";
        $sql .= "ref, fk_soc, date_creation, fk_user_author, note_public, note_private, statut, fk_expedition, fk_commande, fk_facture, return_reason, date_return, customer_ref, total_ht, total_ttc";
        $sql .= ") VALUES (";
        $sql .= "'".$this->db->escape($this->ref)."', ";
        $sql .= (int) $this->fk_soc.", ";
        $sql .= "'".$this->db->idate($now)."', ";
        $sql .= (int) $user->id.", ";
        $sql .= (isset($this->note_public) ? "'".$this->db->escape($this->note_public)."'" : "null").", ";
        $sql .= (isset($this->note_private) ? "'".$this->db->escape($this->note_private)."'" : "null").", ";
        $sql .= self::STATUS_DRAFT.", ";
        $sql .= (isset($this->fk_expedition) ? (int) $this->fk_expedition : "null").", ";
        $sql .= (isset($this->fk_commande) ? (int) $this->fk_commande : "null").", ";
        $sql .= (isset($this->fk_facture) ? (int) $this->fk_facture : "null").", ";
        $sql .= (isset($this->return_reason) ? "'".$this->db->escape($this->return_reason)."'" : "null").", ";
        $sql .= (isset($this->date_return) ? "'".$this->db->idate($this->date_return)."'" : "null").", ";
        $sql .= (isset($this->customer_ref) ? "'".$this->db->escape($this->customer_ref)."'" : "null").", ";
        $sql .= (isset($this->total_ht) ? (float) $this->total_ht : 0).", ";
        $sql .= (isset($this->total_ttc) ? (float) $this->total_ttc : 0);
        $sql .= ")";

        $resql = $this->db->query($sql);
        if ($resql) {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."customerreturn");
            $this->date_creation = $now;
            $this->statut = self::STATUS_DRAFT;

            if ($this->ref == '(PROV)') {
                $this->ref = '(PROV'.$this->id.')';
                $this->db->query("UPDATE ".MAIN_DB_PREFIX."customerreturn SET ref = '".$this->db->escape($this->ref)."' WHERE rowid = ".(int) $this->id);
            }

            if (!$notrigger) {
                $result = $this->call_trigger('CUSTOMERRETURN_CREATE', $user);
                if ($result < 0) $error++;
            }

            if (!$error) {
                $this->db->commit();
                return $this->id;
            }
        }
        $this->error = $this->db->lasterror();
        dol_syslog(__METHOD__." Error: ".$this->error, LOG_ERR);
        $this->db->rollback();
        return -1;
    }

    public function fetch($id, $ref = null)
    {
        $sql = "SELECT t.rowid, t.ref, t.fk_soc, t.date_creation, t.date_modification, t.fk_user_author, t.fk_user_modif, t.note_public, t.note_private, t.statut, t.last_main_doc, t.fk_expedition, t.fk_commande, t.fk_facture, t.return_reason, t.date_return, t.customer_ref, t.total_ht, t.total_ttc";
        $sql .= " FROM ".MAIN_DB_PREFIX."customerreturn as t";
        $sql .= " WHERE t.entity IN (".getEntity('customerreturn').")";
        if ($id) $sql .= " AND t.rowid = ".(int) $id;
        else $sql .= " AND t.ref = '".$this->db->escape($ref)."'";

        $resql = $this->db->query($sql);
        if ($resql && $this->db->num_rows($resql)) {
            $obj = $this->db->fetch_object($resql);
            $this->id = $obj->rowid;
            $this->ref = $obj->ref;
            $this->fk_soc = $obj->fk_soc;
            $this->date_creation = $this->db->jdate($obj->date_creation);
            $this->date_modification = $this->db->jdate($obj->date_modification);
            $this->fk_user_author = $obj->fk_user_author;
            $this->fk_user_modif = $obj->fk_user_modif;
            $this->note_public = $obj->note_public;
            $this->note_private = $obj->note_private;
            $this->statut = $obj->statut;
            $this->last_main_doc = $obj->last_main_doc;
            $this->fk_expedition = $obj->fk_expedition;
            $this->fk_commande = $obj->fk_commande;
            $this->fk_facture = $obj->fk_facture;
            $this->return_reason = $obj->return_reason;
            $this->date_return = $this->db->jdate($obj->date_return);
            $this->customer_ref = $obj->customer_ref;
            $this->total_ht = $obj->total_ht;
            $this->total_ttc = $obj->total_ttc;

            // Initialize thirdparty object
            if ($this->fk_soc > 0) {
                $this->thirdparty = new Societe($this->db);
                if ($this->thirdparty->fetch($this->fk_soc) <= 0) {
                    $this->error = $this->thirdparty->error;
                    dol_syslog(__METHOD__." Failed to fetch thirdparty: ".$this->error, LOG_ERR);
                    return -1;
                }
            }

            $this->lines = $this->getLines();
            dol_syslog(__METHOD__." Fetched customer return id=".$this->id, LOG_DEBUG);
            return 1;
        }
        $this->error = $this->db->lasterror();
        dol_syslog(__METHOD__." Error: ".$this->error, LOG_ERR);
        return -1;
    }

    public function update($user, $notrigger = false)
    {
        $this->db->begin();
        $sql = "UPDATE ".MAIN_DB_PREFIX."customerreturn SET";
        $sql .= " fk_soc = ".(int) $this->fk_soc;
        $sql .= ", note_public = ".(isset($this->note_public) ? "'".$this->db->escape($this->note_public)."'" : "null");
        $sql .= ", note_private = ".(isset($this->note_private) ? "'".$this->db->escape($this->note_private)."'" : "null");
        $sql .= ", customer_ref = ".(isset($this->customer_ref) ? "'".$this->db->escape($this->customer_ref)."'" : "null");
        $sql .= ", return_reason = ".(isset($this->return_reason) ? "'".$this->db->escape($this->return_reason)."'" : "null");
        $sql .= ", date_modification = '".$this->db->idate(dol_now())."'";
        $sql .= ", fk_user_modif = ".(int) $user->id;
        $sql .= ", total_ht = ".(isset($this->total_ht) ? (float) $this->total_ht : 0);
        $sql .= ", total_ttc = ".(isset($this->total_ttc) ? (float) $this->total_ttc : 0);
        $sql .= " WHERE rowid = ".(int) $this->id;

        $resql = $this->db->query($sql);
        if ($resql) {
            if (!$notrigger) {
                if ($this->call_trigger('CUSTOMERRETURN_MODIFY', $user) < 0) {
                    $this->db->rollback();
                    return -1;
                }
            }
            $this->db->commit();
            return 1;
        }
        $this->error = $this->db->lasterror();
        dol_syslog(__METHOD__." Error: ".$this->error, LOG_ERR);
        $this->db->rollback();
        return -1;
    }

    public function delete($user, $notrigger = false)
    {
        $this->db->begin();
        if (!$notrigger) {
            if ($this->call_trigger('CUSTOMERRETURN_DELETE', $user) < 0) {
                $this->db->rollback();
                return -1;
            }
        }
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."customerreturn WHERE rowid = ".(int) $this->id;
        if ($this->db->query($sql)) {
            $this->db->commit();
            return 1;
        }
        $this->error = $this->db->lasterror();
        dol_syslog(__METHOD__." Error: ".$this->error, LOG_ERR);
        $this->db->rollback();
        return -1;
    }

    public function getNomUrl($withpicto = 0)
    {
        global $langs;
        $url = dol_buildpath('/custom/customerreturn/card.php', 1).'?id='.$this->id;
        $label = '<u>'.$langs->trans("CustomerReturn").'</u><br><b>'.$langs->trans('Ref').':</b> '.$this->ref;
        $link = '<a href="'.$url.'" title="'.dol_escape_htmltag($label, 1).'" class="classfortooltip">';
        if ($withpicto) $link .= img_object($label, 'customerreturn@customerreturn', 'class="paddingright"');
        $link .= $this->ref;
        $link .= '</a>';
        return $link;
    }

    public function getLines()
    {
        $lines = array();
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."customerreturndet WHERE fk_customerreturn = ".(int) $this->id." ORDER BY rang ASC";
        $resql = $this->db->query($sql);
        if ($resql) {
            dol_include_once('/custom/customerreturn/class/customerreturnline.class.php');
            while ($obj = $this->db->fetch_object($resql)) {
                $line = new CustomerReturnLine($this->db);
                if ($line->fetch($obj->rowid) > 0) {
                    $lines[] = $line;
                }
            }
        } else {
            $this->error = $this->db->lasterror();
            dol_syslog(__METHOD__." Error fetching lines: ".$this->error, LOG_ERR);
        }
        return $lines;
    }

    public function addLine($fk_product, $qty, $subprice, $description, $fk_entrepot, $batch, $fk_expeditiondet_line, $user, $fk_product_batch = 0)
    {
        if ($this->statut != self::STATUS_DRAFT) {
            $this->error = 'CustomerReturn is not in draft status';
            dol_syslog(__METHOD__." Error: ".$this->error, LOG_ERR);
            return -1;
        }

        // Always use default warehouse for returns
        $fk_entrepot = getDolGlobalInt('CUSTOMERRETURN_DEFAULT_WAREHOUSE');
        if (empty($fk_entrepot)) {
            $this->error = 'No default warehouse configured';
            dol_syslog(__METHOD__." Error: ".$this->error, LOG_ERR);
            return -1;
        }

        dol_include_once('/custom/customerreturn/class/customerreturnline.class.php');
        $line = new CustomerReturnLine($this->db);
        $line->fk_customerreturn = $this->id;
        $line->fk_product = $fk_product;
        $line->qty = $qty;
        $line->subprice = $subprice;
        $line->description = $description;
        $line->fk_entrepot = $fk_entrepot;
        $line->batch = $batch;
        $line->fk_expeditiondet_line = $fk_expeditiondet_line;
        $line->fk_product_batch = $fk_product_batch;

        $resql = $this->db->query("SELECT MAX(rang) as maxrang FROM ".MAIN_DB_PREFIX."customerreturndet WHERE fk_customerreturn = ".(int) $this->id);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            $line->rang = ($obj->maxrang ?? 0) + 1;
        } else {
            $this->error = $this->db->lasterror();
            dol_syslog(__METHOD__." Error fetching max rang: ".$this->error, LOG_ERR);
            return -1;
        }

        if ($line->insert($user) > 0) {
            $this->updateTotal();
            return $line->id;
        }
        $this->error = $line->error;
        dol_syslog(__METHOD__." Error adding line: ".$this->error, LOG_ERR);
        return -1;
    }

    public function updateTotal()
    {
        $sql = "SELECT SUM(total_ht) as total_ht, SUM(total_ttc) as total_ttc FROM ".MAIN_DB_PREFIX."customerreturndet WHERE fk_customerreturn = ".(int) $this->id;
        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            $this->total_ht = $obj->total_ht ?? 0;
            $this->total_ttc = $obj->total_ttc ?? 0;
            $sql = "UPDATE ".MAIN_DB_PREFIX."customerreturn SET total_ht = ".(float) $this->total_ht.", total_ttc = ".(float) $this->total_ttc." WHERE rowid = ".(int) $this->id;
            if ($this->db->query($sql)) {
                return 1;
            }
            $this->error = $this->db->lasterror();
            dol_syslog(__METHOD__." Error updating totals: ".$this->error, LOG_ERR);
        } else {
            $this->error = $this->db->lasterror();
            dol_syslog(__METHOD__." Error fetching totals: ".$this->error, LOG_ERR);
        }
        return -1;
    }

  public function validate($user, $notrigger = 0)
    {
        global $langs, $conf;

        dol_syslog(__METHOD__." Starting validation for id=".$this->id, LOG_DEBUG);

        if ($this->statut != self::STATUS_DRAFT) {
            $this->error = 'CustomerReturn is not in draft status (current status: '.$this->statut.')';
            dol_syslog(__METHOD__." Error: ".$this->error, LOG_ERR);
            return -1;
        }

        if (empty($this->lines)) {
            $this->error = $langs->trans('ErrorCustomerReturnNoLines');
            dol_syslog(__METHOD__." Error: ".$this->error, LOG_ERR);
            return -1;
        }

        $this->db->begin();

        // Ensure thirdparty is loaded
        if (empty($this->thirdparty) && $this->fk_soc > 0) {
            $this->thirdparty = new Societe($this->db);
            if ($this->thirdparty->fetch($this->fk_soc) <= 0) {
                $this->error = 'Failed to load thirdparty';
                dol_syslog(__METHOD__." Error: ".$this->error, LOG_ERR);
                $this->db->rollback();
                return -1;
            }
        }

        // Check default warehouse
        $default_warehouse = getDolGlobalInt('CUSTOMERRETURN_DEFAULT_WAREHOUSE');
        if (empty($default_warehouse)) {
            $this->error = 'No default warehouse configured for customer returns';
            dol_syslog(__METHOD__." Error: ".$this->error, LOG_ERR);
            $this->db->rollback();
            return -1;
        }

        // Load numbering module
        $numbering_module = getDolGlobalString('CUSTOMERRETURN_ADDON', 'mod_customerreturn_standard');
        dol_syslog(__METHOD__." Using numbering module: ".$numbering_module, LOG_DEBUG);
        
        dol_include_once('/custom/customerreturn/core/modules/customerreturn/'.$numbering_module.'.php');
        if (!class_exists($numbering_module)) {
            $this->error = 'Numbering module '.$numbering_module.' not found';
            dol_syslog(__METHOD__." Error: ".$this->error, LOG_ERR);
            $this->db->rollback();
            return -1;
        }

        $obj = new $numbering_module($this->db);
        $new_ref = $obj->getNextValue($this->thirdparty, $this);
        
        dol_syslog(__METHOD__." Generated new ref: ".$new_ref, LOG_DEBUG);
        
        if (empty($new_ref) || $new_ref <= 0) {
            $this->error = !empty($obj->error) ? $obj->error : 'Failed to generate new reference';
            dol_syslog(__METHOD__." Error: ".$this->error, LOG_ERR);
            $this->db->rollback();
            return -1;
        }

        foreach ($this->lines as $line) {
            // Override warehouse to default for all return lines
            $line->fk_entrepot = $default_warehouse;
            // Update the line in database
            $sql = "UPDATE ".MAIN_DB_PREFIX."customerreturndet SET fk_entrepot = ".(int) $default_warehouse." WHERE rowid = ".(int) $line->id;
            if (!$this->db->query($sql)) {
                $this->error = $this->db->lasterror();
                dol_syslog(__METHOD__." Error updating line warehouse: ".$this->error, LOG_ERR);
                $this->db->rollback();
                return -1;
            }
            if ($line->fk_product > 0 && $line->qty > 0) {
                if ($this->updateStock($line, $user) < 0) {
                    $this->db->rollback();
                    return -1;
                }
            }
        }

        $sql = "UPDATE ".MAIN_DB_PREFIX."customerreturn SET";
        $sql .= " statut = ".self::STATUS_VALIDATED;
        $sql .= ", ref = '".$this->db->escape($new_ref)."'";
        $sql .= ", date_valid = '".$this->db->idate(dol_now())."'";
        $sql .= ", fk_user_valid = ".(int) $user->id;
        $sql .= " WHERE rowid = ".(int) $this->id;

        dol_syslog(__METHOD__." SQL: ".$sql, LOG_DEBUG);
        
        $resql = $this->db->query($sql);
        if ($resql) {
            dol_syslog(__METHOD__." UPDATE successful, affected rows: ".$this->db->affected_rows($resql), LOG_DEBUG);
            
            $this->ref = $new_ref;
            $this->statut = self::STATUS_VALIDATED;
            
            if (!$notrigger) {
                $result = $this->call_trigger('CUSTOMERRETURN_VALIDATE', $user);
                if ($result < 0) {
                    $this->error = $this->errorsToString();
                    dol_syslog(__METHOD__." Trigger failed: ".$this->error, LOG_ERR);
                    $this->db->rollback();
                    return -1;
                }
            }
            
            $this->db->commit();
            dol_syslog(__METHOD__." Validation completed successfully for id=".$this->id.", new ref=".$new_ref, LOG_DEBUG);
            return 1;
        }

        $this->error = $this->db->lasterror();
        dol_syslog(__METHOD__." SQL Error: ".$this->error, LOG_ERR);
        $this->db->rollback();
        return -1;
    }

    public function setReturnedToSupplier($user, $notrigger = 0)
    {
        if ($this->statut != self::STATUS_VALIDATED) {
            $this->error = 'CustomerReturn is not in validated status';
            dol_syslog(__METHOD__." Error: ".$this->error, LOG_ERR);
            return -1;
        }

        $this->db->begin();

        $sql = "UPDATE ".MAIN_DB_PREFIX."customerreturn SET statut = ".self::STATUS_RETURNED_TO_SUPPLIER." WHERE rowid = ".(int) $this->id;

        if ($this->db->query($sql)) {
            if (!$notrigger) {
                if ($this->call_trigger('CUSTOMERRETURN_RETURNED_TO_SUPPLIER', $user) < 0) {
                    $this->db->rollback();
                    return -1;
                }
            }
            $this->db->commit();
            $this->statut = self::STATUS_RETURNED_TO_SUPPLIER;
            return 1;
        }

        $this->error = $this->db->lasterror();
        dol_syslog(__METHOD__." Error: ".$this->error, LOG_ERR);
        $this->db->rollback();
        return -1;
    }

    public function setChangedProductForClient($user, $notrigger = 0)
    {
        if ($this->statut != self::STATUS_VALIDATED) {
            $this->error = 'CustomerReturn is not in validated status';
            dol_syslog(__METHOD__." Error: ".$this->error, LOG_ERR);
            return -1;
        }

        $this->db->begin();

        $sql = "UPDATE ".MAIN_DB_PREFIX."customerreturn SET statut = ".self::STATUS_CHANGED_PRODUCT_FOR_CLIENT." WHERE rowid = ".(int) $this->id;

        if ($this->db->query($sql)) {
            if (!$notrigger) {
                if ($this->call_trigger('CUSTOMERRETURN_CHANGED_PRODUCT_FOR_CLIENT', $user) < 0) {
                    $this->db->rollback();
                    return -1;
                }
            }
            $this->db->commit();
            $this->statut = self::STATUS_CHANGED_PRODUCT_FOR_CLIENT;
            return 1;
        }

        $this->error = $this->db->lasterror();
        dol_syslog(__METHOD__." Error: ".$this->error, LOG_ERR);
        $this->db->rollback();
        return -1;
    }

    public function setReimbursedMoneyToClient($user, $notrigger = 0)
    {
        if ($this->statut != self::STATUS_VALIDATED) {
            $this->error = 'CustomerReturn is not in validated status';
            dol_syslog(__METHOD__." Error: ".$this->error, LOG_ERR);
            return -1;
        }

        $this->db->begin();

        $sql = "UPDATE ".MAIN_DB_PREFIX."customerreturn SET statut = ".self::STATUS_REIMBURSED_MONEY_TO_CLIENT." WHERE rowid = ".(int) $this->id;

        if ($this->db->query($sql)) {
            if (!$notrigger) {
                if ($this->call_trigger('CUSTOMERRETURN_REIMBURSED_MONEY_TO_CLIENT', $user) < 0) {
                    $this->db->rollback();
                    return -1;
                }
            }
            $this->db->commit();
            $this->statut = self::STATUS_REIMBURSED_MONEY_TO_CLIENT;
            return 1;
        }

        $this->error = $this->db->lasterror();
        dol_syslog(__METHOD__." Error: ".$this->error, LOG_ERR);
        $this->db->rollback();
        return -1;
    }

 public function updateStock($line, $user)
    {
        global $conf, $langs;
        if (empty($conf->stock->enabled)) return 1;
        require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
        
        // Load product to check if it uses batch tracking
        $product = new Product($this->db);
        if ($product->fetch($line->fk_product) <= 0) {
            $this->error = 'Failed to load product';
            dol_syslog(__METHOD__." Error: ".$this->error, LOG_ERR);
            return -1;
        }
        
        $mouvementstock = new MouvementStock($this->db);
        $label = $langs->trans("CustomerReturn").' '.$this->ref;
        
        // For products with batch tracking, we need to pass batch info correctly
        if ($product->hasbatch() && !empty($line->batch)) {
            // Use _create method directly for better control with batch products
            $result = $mouvementstock->_create($user, $line->fk_product, $line->fk_entrepot, $line->qty, 3, 0, $label, '', '', '', '', $line->batch);
        } else {
            // For regular products without batch tracking
            $result = $mouvementstock->reception($user, $line->fk_product, $line->fk_entrepot, $line->qty, 0, $label);
        }
        
        if ($result < 0) {
            $this->error = $mouvementstock->error;
            dol_syslog(__METHOD__." Error updating stock: ".$this->error, LOG_ERR);
        }
        return $result;
    }

    public function getLibStatut($mode = 0)
    {
        return $this->LibStatut($this->statut, $mode);
    }

    public function LibStatut($status, $mode = 0)
    {
        global $langs;

        if ($status == self::STATUS_DRAFT) {
            $statusType = 'status0';
        } elseif ($status == self::STATUS_VALIDATED) {
            $statusType = 'status4';
        } elseif ($status == self::STATUS_RETURNED_TO_SUPPLIER) {
            $statusType = 'status2';
        } elseif ($status == self::STATUS_CHANGED_PRODUCT_FOR_CLIENT) {
            $statusType = 'status3';
        } elseif ($status == self::STATUS_REIMBURSED_MONEY_TO_CLIENT) {
            $statusType = 'status5';
        } elseif ($status == self::STATUS_CLOSED) {
            $statusType = 'status6';
        } else {
            $statusType = 'status0';
        }

        $statusLabels = array(
            self::STATUS_DRAFT => $langs->trans('Draft'),
            self::STATUS_VALIDATED => $langs->trans('Validated'),
            self::STATUS_CLOSED => $langs->trans('Closed'),
            self::STATUS_RETURNED_TO_SUPPLIER => $langs->trans('CustomerReturnStatusReturnedToSupplier'),
            self::STATUS_CHANGED_PRODUCT_FOR_CLIENT => $langs->trans('CustomerReturnStatusChangedProductForClient'),
            self::STATUS_REIMBURSED_MONEY_TO_CLIENT => $langs->trans('CustomerReturnStatusReimbursedMoneyToClient')
        );

        $statusLabel = isset($statusLabels[$status]) ? $statusLabels[$status] : 'Unknown';

        if ($mode == 0) {
            return $statusLabel;
        } else {
            return dolGetStatus($statusLabel, '', '', $statusType, $mode);
        }
    }

    public function getShipmentLines($shipment_id)
    {
        $lines = array();
        $sql = "SELECT ed.rowid, cd.fk_product, ed.qty as qty_shipped, p.ref as product_ref, p.label as product_label, cd.description, ed.fk_entrepot, cd.subprice, eb.batch";
        $sql .= " FROM ".MAIN_DB_PREFIX."expeditiondet as ed";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."commandedet as cd ON cd.rowid = ed.fk_elementdet";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = cd.fk_product";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."expeditiondet_batch as eb ON eb.fk_expeditiondet = ed.rowid";
        $sql .= " WHERE ed.fk_expedition = ".(int) $shipment_id;

        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $line = new stdClass();
                $line->id = $obj->rowid;
                $line->fk_product = $obj->fk_product;
                $line->qty_shipped = $obj->qty_shipped;
                $line->product_ref = $obj->product_ref;
                $line->product_label = $obj->product_label;
                $line->description = $obj->description;
                $line->fk_entrepot = $obj->fk_entrepot;
                $line->subprice = $obj->subprice;
                $line->batch = $obj->batch;
                $line->qty_already_returned = $this->getQtyAlreadyReturned($line->id);
                $line->qty_available_for_return = $line->qty_shipped - $line->qty_already_returned;
                $lines[] = $line;
            }
        } else {
            $this->error = $this->db->lasterror();
            dol_syslog(__METHOD__." Error fetching shipment lines: ".$this->error, LOG_ERR);
        }
        return $lines;
    }

    public function getQtyAlreadyReturned($expeditiondet_line_id)
    {
        $sql = "SELECT SUM(crd.qty) as qty_returned FROM ".MAIN_DB_PREFIX."customerreturndet as crd";
        $sql .= " JOIN ".MAIN_DB_PREFIX."customerreturn as cr ON cr.rowid = crd.fk_customerreturn";
        $sql .= " WHERE crd.fk_expeditiondet_line = ".(int) $expeditiondet_line_id." AND cr.statut != ".self::STATUS_DRAFT;
        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            return $obj->qty_returned ?? 0;
        }
        $this->error = $this->db->lasterror();
        dol_syslog(__METHOD__." Error fetching qty returned: ".$this->error, LOG_ERR);
        return 0;
    }

    public function createCreditNote($user)
    {
        if ($this->statut != self::STATUS_CLOSED) {
            $this->error = 'CustomerReturn is not in closed status';
            dol_syslog(__METHOD__." Error: ".$this->error, LOG_ERR);
            return -1;
        }
        require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
        $creditnote = new Facture($this->db);
        $creditnote->socid = $this->fk_soc;
        $creditnote->type = Facture::TYPE_CREDIT_NOTE;
        $creditnote->ref_client = 'RETURN-'.$this->ref;
        $creditnote->note_public = 'Credit note for customer return '.$this->ref;
        $creditnote->date = dol_now();

        $result = $creditnote->create($user);
        if ($result > 0) {
            foreach ($this->lines as $line) {
                if ($line->qty > 0) {
                    $creditnote->addline($line->description, $line->subprice, $line->qty, 0);
                }
            }
            $this->db->query("UPDATE ".MAIN_DB_PREFIX."customerreturn SET fk_facture = ".$result." WHERE rowid = ".$this->id);
            $this->add_object_linked('facture', $result);
            return $result;
        }
        $this->error = $creditnote->error;
        dol_syslog(__METHOD__." Error creating credit note: ".$this->error, LOG_ERR);
        return -1;
    }

public function backToDraft($user, $notrigger = false)
{
    global $langs;

    if ($this->fk_facture > 0) {
        $this->error = $langs->trans('ErrorCreditNoteAlreadyCreated');
        dol_syslog(__METHOD__." Error: ".$this->error, LOG_ERR);
        return -1;
    }

    $this->db->begin();

    $stock_reversal_statuses = array(
        self::STATUS_VALIDATED,
        self::STATUS_RETURNED_TO_SUPPLIER,
        self::STATUS_CHANGED_PRODUCT_FOR_CLIENT,
        self::STATUS_REIMBURSED_MONEY_TO_CLIENT,
        self::STATUS_CLOSED
    );

    if (in_array($this->statut, $stock_reversal_statuses)) {
        foreach ($this->lines as $line) {
            if ($line->fk_product > 0 && $line->qty > 0) {
                if ($this->reverseStock($line, $user) < 0) {
                    $this->db->rollback();
                    return -1;
                }
            }
        }
    }

    $sql = "UPDATE ".MAIN_DB_PREFIX."customerreturn 
            SET statut = ".self::STATUS_DRAFT.",
                date_valid = NULL, 
                fk_user_valid = NULL, 
                date_process = NULL
            WHERE rowid = ".(int) $this->id;

    $resql = $this->db->query($sql);
    if ($resql) {
        if (!$notrigger) {
            if ($this->call_trigger('CUSTOMERRETURN_BACKTODRAFT', $user) < 0) {
                $this->db->rollback();
                return -1;
            }
        }
        $this->db->commit();
        return 1;
    }

    $this->error = $this->db->lasterror();
    dol_syslog(__METHOD__." Error: ".$this->error, LOG_ERR);
    $this->db->rollback();
    return -1;
}


 
public function reverseStock($line, $user)
    {
        global $conf, $langs;
        if (empty($conf->stock->enabled)) return 1;
        require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';

        $product = new Product($this->db);
        if ($product->fetch($line->fk_product) <= 0) {
            $this->error = 'Failed to load product';
            dol_syslog(__METHOD__." Error: ".$this->error, LOG_ERR);
            return -1;
        }

        $mouvementstock = new MouvementStock($this->db);
        $label = $langs->trans("CustomerReturnBackToDraft").' '.$this->ref;

        // To reverse a stock reception, we need to remove stock (negative quantity)
        // Movement type 3 = Reception, Type 2 = Shipment/Removal
        if ($product->hasbatch() && !empty($line->batch)) {
            // Use negative quantity to remove stock, type 2 for shipment/removal
            $result = $mouvementstock->_create($user, $line->fk_product, $line->fk_entrepot, -$line->qty, 2, 0, $label, '', '', '', '', $line->batch);
        } else {
            // For non-batch products, use livraison method which removes stock
            $result = $mouvementstock->livraison($user, $line->fk_product, $line->fk_entrepot, $line->qty, 0, $label);
        }

        if ($result < 0) {
            $this->error = $mouvementstock->error;
            dol_syslog(__METHOD__." Error reversing stock: ".$this->error, LOG_ERR);
        }
        return $result;
    }
/**
 * Generate document
 *
 * @param  string    $modele      Model to use
 * @param  Translate $outputlangs Language object
 * @param  int       $hidedetails Hide details
 * @param  int       $hidedesc    Hide description
 * @param  int       $hideref     Hide reference
 * @return int                    <0 if error, >0 if success
 */

/**
 * Generate document
 *
 * @param  string    $modele      Model to use
 * @param  Translate $outputlangs Language object
 * @param  int       $hidedetails Hide details
 * @param  int       $hidedesc    Hide description
 * @param  int       $hideref     Hide reference
 * @return int                    <0 if error, >0 if success
 */
public function generateDocument($modele, $outputlangs, $hidedetails = 0, $hidedesc = 0, $hideref = 0)
{
    global $conf, $langs;

    $langs->load("customerreturn@customerreturn");

    if (!dol_strlen($modele)) {
        $modele = 'standard';
        if (!empty($this->model_pdf)) {
            $modele = $this->model_pdf;
        } elseif (getDolGlobalString('CUSTOMERRETURN_ADDON_PDF')) {
            $modele = getDolGlobalString('CUSTOMERRETURN_ADDON_PDF');
        }
    }

    // Load the PDF generator class
    $classfile = 'pdf_'.$modele.'.php';
    $classname = 'pdf_'.$modele;
    $filepath = DOL_DOCUMENT_ROOT.'/custom/customerreturn/core/modules/customerreturn/pdf/'.$classfile;

    dol_syslog(__METHOD__." Looking for PDF class at: ".$filepath, LOG_DEBUG);

    if (!file_exists($filepath)) {
        $this->error = 'PDF generator file not found: '.$filepath;
        dol_syslog(__METHOD__." ".$this->error, LOG_ERR);
        return -1;
    }

    require_once $filepath;

    if (!class_exists($classname)) {
        $this->error = 'PDF generator class not found: '.$classname;
        dol_syslog(__METHOD__." ".$this->error, LOG_ERR);
        return -1;
    }

    $obj = new $classname($this->db);

    if (!is_object($obj)) {
        $this->error = 'Failed to instantiate PDF generator';
        dol_syslog(__METHOD__." ".$this->error, LOG_ERR);
        return -1;
    }

    // Set output directory
    $objectref = dol_sanitizeFileName($this->ref);
    if (empty($conf->customerreturn->multidir_output[$this->entity])) {
        $conf->customerreturn->multidir_output[$this->entity] = DOL_DATA_ROOT.'/customerreturn';
    }
    $dir = $conf->customerreturn->multidir_output[$this->entity].'/'.$objectref;

    if (!file_exists($dir)) {
        if (dol_mkdir($dir) < 0) {
            $this->error = $langs->trans("ErrorCanNotCreateDir", $dir);
            dol_syslog(__METHOD__." ".$this->error, LOG_ERR);
            return -1;
        }
    }

    // Generate the document
    $result = $obj->write_file($this, $outputlangs, '', $hidedetails, $hidedesc, $hideref);

    if ($result > 0) {
        // Update last_main_doc field
        $this->last_main_doc = $objectref.'/'.$objectref.'.pdf';
        
        $sql = "UPDATE ".MAIN_DB_PREFIX."customerreturn";
        $sql .= " SET last_main_doc = '".$this->db->escape($this->last_main_doc)."'";
        $sql .= " WHERE rowid = ".(int) $this->id;

        if (!$this->db->query($sql)) {
            dol_syslog(__METHOD__." Failed to update last_main_doc", LOG_WARNING);
        }

        return 1;
    } else {
        $this->error = $obj->error;
        $this->errors = $obj->errors;
        dol_syslog(__METHOD__." Error generating PDF: ".$this->error, LOG_ERR);
        return -1;
    }
}

 
}



