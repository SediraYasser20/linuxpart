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

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobjectline.class.php';

class SupplierReturnLine extends CommonObjectLine
{
    public $table_element = 'supplierreturndet';
    public $element = 'supplierreturnline';

    public $db;
    public $id;
    public $fk_supplierreturn;
    public $fk_product;
    public $fk_product_batch;
    public $fk_reception_line;
    public $description;
    public $qty;
    public $subprice;
    public $total_ht;
    public $total_ttc;
    public $rang;
    public $batch;                    // Numéro de lot/série
    public $fk_entrepot;             // Entrepôt
    public $combination_id;          // ID de la combinaison d'attributs
    public $combination_ref;         // Référence de la variante
    public $product_ref;
    public $product_label;
    public $warehouse_label;

    public $fields = array(
        'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => '1', 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => '1', 'index' => 1, 'comment' => "Id"),
        'fk_supplierreturn' => array('type' => 'integer', 'label' => 'SupplierReturn', 'enabled' => '1', 'position' => 10, 'notnull' => 1, 'visible' => 0),
        'fk_product' => array('type' => 'integer:Product:product/class/product.class.php', 'label' => 'Product', 'enabled' => '1', 'position' => 20, 'notnull' => 0, 'visible' => 1),
        'description' => array('type' => 'text', 'label' => 'Description', 'enabled' => '1', 'position' => 30, 'notnull' => 0, 'visible' => 1),
        'qty' => array('type' => 'real', 'label' => 'Qty', 'enabled' => '1', 'position' => 40, 'notnull' => 1, 'visible' => 1),
        'subprice' => array('type' => 'price', 'label' => 'UnitPrice', 'enabled' => '1', 'position' => 50, 'notnull' => 0, 'visible' => 1),
        'total_ht' => array('type' => 'price', 'label' => 'TotalHT', 'enabled' => '1', 'position' => 60, 'notnull' => 0, 'visible' => 1),
        'batch' => array('type' => 'varchar(128)', 'label' => 'Batch', 'enabled' => '1', 'position' => 70, 'notnull' => 0, 'visible' => 1),
        'fk_entrepot' => array('type' => 'integer:Entrepot:product/stock/class/entrepot.class.php', 'label' => 'Warehouse', 'enabled' => '1', 'position' => 80, 'notnull' => 0, 'visible' => 1),
    );
    
    // Original pricing data for credit note generation
    public $fk_facture_fourn_det_source = 0; // Link to original supplier invoice line
    public $original_subprice = 0;           // Original unit price from invoice
    public $original_tva_tx = 0;             // Original VAT rate from invoice
    public $original_localtax1_tx = 0;       // Original local tax 1 rate
    public $original_localtax2_tx = 0;       // Original local tax 2 rate

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function insert($user)
    {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."supplierreturndet(";
        $sql .= "fk_supplierreturn,";
        $sql .= "fk_product,";
        $sql .= "fk_product_batch,";
        $sql .= "fk_reception_line,";
        $sql .= "description,";
        $sql .= "qty,";
        $sql .= "subprice,";
        $sql .= "total_ht,";
        $sql .= "total_ttc,";
        $sql .= "batch,";
        $sql .= "fk_entrepot,";
        $sql .= "rang,";
        $sql .= "fk_facture_fourn_det_source,";
        $sql .= "original_subprice,";
        $sql .= "original_tva_tx,";
        $sql .= "original_localtax1_tx,";
        $sql .= "original_localtax2_tx";
        $sql .= ") VALUES (";
        $sql .= (int) $this->fk_supplierreturn.",";
        $sql .= ($this->fk_product > 0 ? (int) $this->fk_product : "null").",";
        $sql .= ($this->fk_product_batch > 0 ? (int) $this->fk_product_batch : "null").",";
        $sql .= ($this->fk_reception_line > 0 ? (int) $this->fk_reception_line : "null").",";
        $sql .= "'".$this->db->escape($this->description)."',";
        $sql .= (float) $this->qty.",";
        $sql .= (float) $this->subprice.",";
        $sql .= (float) $this->total_ht.",";
        $sql .= (float) $this->total_ttc.",";
        $sql .= ($this->batch ? "'".$this->db->escape($this->batch)."'" : "null").",";
        $sql .= ($this->fk_entrepot > 0 ? (int) $this->fk_entrepot : "null").",";
        $sql .= (int) $this->rang.",";
        $sql .= (!empty($this->fk_facture_fourn_det_source) ? (int) $this->fk_facture_fourn_det_source : "null").",";
        $sql .= (!empty($this->original_subprice) ? (float) $this->original_subprice : "null").",";
        $sql .= (!empty($this->original_tva_tx) ? (float) $this->original_tva_tx : "null").",";
        $sql .= (!empty($this->original_localtax1_tx) ? (float) $this->original_localtax1_tx : "null").",";
        $sql .= (!empty($this->original_localtax2_tx) ? (float) $this->original_localtax2_tx : "null");
        $sql .= ")";

        $resql = $this->db->query($sql);
        if ($resql) {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."supplierreturndet");
            return $this->id; // Return the ID, not 1
        } else {
            $this->error = "SQL Error: ".$this->db->error()." - SQL: ".$sql;
            dol_syslog("SupplierReturnLine::insert() ".$this->error, LOG_ERR);
            return -1;
        }
    }

    /**
     * Fetch a line
     *
     * @param int $id Id of line
     * @return int <0 if KO, >0 if OK
     */
    public function fetch($id)
    {
        $sql = "SELECT t.rowid, t.fk_supplierreturn, t.fk_product, t.fk_product_batch, t.fk_reception_line,";
        $sql .= " t.description, t.qty, t.subprice, t.total_ht, t.total_ttc, t.batch, t.fk_entrepot, t.rang,";
        $sql .= " t.fk_facture_fourn_det_source, t.original_subprice, t.original_tva_tx, t.original_localtax1_tx, t.original_localtax2_tx,";
        $sql .= " p.ref as product_ref, p.label as product_label";
        $sql .= " FROM ".MAIN_DB_PREFIX."supplierreturndet as t";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = t.fk_product";
        $sql .= " WHERE t.rowid = ".(int) $id;

        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            if ($obj) {
                $this->id = $obj->rowid;
                $this->fk_supplierreturn = $obj->fk_supplierreturn;
                $this->fk_product = $obj->fk_product;
                $this->fk_product_batch = $obj->fk_product_batch;
                $this->fk_reception_line = $obj->fk_reception_line;
                $this->description = $obj->description;
                $this->qty = $obj->qty;
                $this->subprice = $obj->subprice;
                $this->total_ht = $obj->total_ht;
                $this->total_ttc = $obj->total_ttc;
                $this->batch = $obj->batch;
                $this->fk_entrepot = $obj->fk_entrepot;
                $this->rang = $obj->rang;
                $this->product_ref = $obj->product_ref;
                $this->product_label = $obj->product_label;
                
                // Original pricing data for credit note generation
                $this->fk_facture_fourn_det_source = $obj->fk_facture_fourn_det_source;
                $this->original_subprice = $obj->original_subprice;
                $this->original_tva_tx = $obj->original_tva_tx;
                $this->original_localtax1_tx = $obj->original_localtax1_tx;
                $this->original_localtax2_tx = $obj->original_localtax2_tx;
                
                // Get warehouse label separately
                if ($this->fk_entrepot > 0) {
                    require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
                    $warehouse = new Entrepot($this->db);
                    if ($warehouse->fetch($this->fk_entrepot) > 0) {
                        $this->warehouse_label = $warehouse->label;
                    }
                }
                
                $this->db->free($resql);
                return 1;
            } else {
                $this->db->free($resql);
                return 0;
            }
        } else {
            return -1;
        }
    }

    /**
     * Update line
     *
     * @param User $user User object
     * @return int <0 if KO, >0 if OK
     */
    public function update($user)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX."supplierreturndet SET";
        $sql .= " fk_product = ".($this->fk_product > 0 ? (int) $this->fk_product : "null");
        $sql .= ", fk_product_batch = ".($this->fk_product_batch > 0 ? (int) $this->fk_product_batch : "null");
        $sql .= ", description = '".$this->db->escape($this->description)."'";
        $sql .= ", qty = ".(float) $this->qty;
        $sql .= ", subprice = ".(float) $this->subprice;
        $sql .= ", total_ht = ".(float) $this->total_ht;
        $sql .= ", total_ttc = ".(float) $this->total_ttc;
        $sql .= ", batch = ".($this->batch ? "'".$this->db->escape($this->batch)."'" : "null");
        $sql .= ", fk_entrepot = ".($this->fk_entrepot > 0 ? (int) $this->fk_entrepot : "null");
        $sql .= ", rang = ".(int) $this->rang;
        $sql .= " WHERE rowid = ".(int) $this->id;

        $resql = $this->db->query($sql);
        if ($resql) {
            return 1;
        } else {
            $this->error = "SQL Error: ".$this->db->error()." - SQL: ".$sql;
            dol_syslog("SupplierReturnLine::update() ".$this->error, LOG_ERR);
            return -1;
        }
    }

    /**
     * Delete line
     *
     * @param User $user User object
     * @return int <0 if KO, >0 if OK
     */
    public function delete($user)
    {
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."supplierreturndet";
        $sql .= " WHERE rowid = ".(int) $this->id;

        $resql = $this->db->query($sql);
        if ($resql) {
            return 1;
        } else {
            $this->error = "SQL Error: ".$this->db->error()." - SQL: ".$sql;
            dol_syslog("SupplierReturnLine::delete() ".$this->error, LOG_ERR);
            return -1;
        }
    }

    /**
     * Retourne le libellé complet du produit (avec variante si applicable)
     */
    public function getProductLabel()
    {
        $label = $this->product_ref;
        if ($this->product_label) {
            $label .= ' - '.$this->product_label;
        }
        if ($this->combination_ref) {
            $label .= ' ('.$this->combination_ref.')';
        }
        return $label;
    }

    /**
     * Retourne les informations d'entrepôt et lot
     */
    public function getStockInfo()
    {
        $info = array();
        if ($this->warehouse_label) {
            $info[] = 'Entrepôt: '.$this->warehouse_label;
        }
        if ($this->batch) {
            $info[] = 'Lot: '.$this->batch;
        }
        return implode(' - ', $info);
    }
}