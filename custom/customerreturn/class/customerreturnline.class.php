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

class CustomerReturnLine extends CommonObjectLine
{
    public $table_element = 'customerreturndet';
    public $element = 'customerreturnline';

    public $fk_customerreturn;
    public $fk_product;
    public $fk_product_batch;
    public $fk_expeditiondet_line;
    public $description;
    public $qty;
    public $subprice;
    public $total_ht;
    public $total_ttc;
    public $rang;
    public $batch;
    public $fk_entrepot;
    public $product_ref;
    public $product_label;
    public $warehouse_label;
    public $fk_facture_det_source;
    public $original_subprice;
    public $original_tva_tx;
    public $original_localtax1_tx;
    public $original_localtax2_tx;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function insert($user)
    {
        $this->total_ht = $this->qty * $this->subprice;
        $this->total_ttc = $this->total_ht; // Simplified for now

        $sql = "INSERT INTO ".MAIN_DB_PREFIX."customerreturndet (";
        $sql .= "fk_customerreturn, fk_product, fk_product_batch, fk_expeditiondet_line, description, qty, subprice, total_ht, total_ttc, batch, fk_entrepot, rang, fk_facture_det_source, original_subprice, original_tva_tx, original_localtax1_tx, original_localtax2_tx";
        $sql .= ") VALUES (";
        $sql .= (int) $this->fk_customerreturn.", ";
        $sql .= ($this->fk_product > 0 ? (int) $this->fk_product : "null").", ";
        $sql .= ($this->fk_product_batch > 0 ? (int) $this->fk_product_batch : "null").", ";
        $sql .= ($this->fk_expeditiondet_line > 0 ? (int) $this->fk_expeditiondet_line : "null").", ";
        $sql .= "'".$this->db->escape($this->description)."', ";
        $sql .= (float) $this->qty.", ";
        $sql .= (float) $this->subprice.", ";
        $sql .= (float) $this->total_ht.", ";
        $sql .= (float) $this->total_ttc.", ";
        $sql .= ($this->batch ? "'".$this->db->escape($this->batch)."'" : "null").", ";
        $sql .= ($this->fk_entrepot > 0 ? (int) $this->fk_entrepot : "null").", ";
        $sql .= (int) $this->rang.", ";
        $sql .= ($this->fk_facture_det_source > 0 ? (int) $this->fk_facture_det_source : "null").", ";
        $sql .= (isset($this->original_subprice) ? (float) $this->original_subprice : "null").", ";
        $sql .= (isset($this->original_tva_tx) ? (float) $this->original_tva_tx : "null").", ";
        $sql .= (isset($this->original_localtax1_tx) ? (float) $this->original_localtax1_tx : "null").", ";
        $sql .= (isset($this->original_localtax2_tx) ? (float) $this->original_localtax2_tx : "null");
        $sql .= ")";

        if ($this->db->query($sql)) {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."customerreturndet");
            return $this->id;
        }
        $this->error = "SQL Error: ".$this->db->error();
        return -1;
    }

    public function fetch($id)
    {
        $sql = "SELECT * FROM ".MAIN_DB_PREFIX."customerreturndet WHERE rowid = ".(int) $id;
        $resql = $this->db->query($sql);
        if ($resql && $this->db->num_rows($resql)) {
            $obj = $this->db->fetch_object($resql);
            foreach ($obj as $key => $value) {
                $this->$key = $value;
            }
            return 1;
        }
        $this->error = $this->db->error();
        return -1;
    }

    public function update($user)
    {
        $this->total_ht = $this->qty * $this->subprice;
        $this->total_ttc = $this->total_ht; // Simplified for now

        $sql = "UPDATE ".MAIN_DB_PREFIX."customerreturndet SET";
        $sql .= " fk_product = ".($this->fk_product > 0 ? (int) $this->fk_product : "null");
        $sql .= ", description = '".$this->db->escape($this->description)."'";
        $sql .= ", qty = ".(float) $this->qty;
        $sql .= ", subprice = ".(float) $this->subprice;
        $sql .= ", total_ht = ".(float) $this->total_ht;
        $sql .= ", total_ttc = ".(float) $this->total_ttc;
        $sql .= ", batch = ".($this->batch ? "'".$this->db->escape($this->batch)."'" : "null");
        $sql .= ", fk_entrepot = ".($this->fk_entrepot > 0 ? (int) $this->fk_entrepot : "null");
        $sql .= " WHERE rowid = ".(int) $this->id;

        if ($this->db->query($sql)) {
            return 1;
        }
        $this->error = "SQL Error: ".$this->db->error();
        return -1;
    }

    public function delete($user)
    {
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."customerreturndet WHERE rowid = ".(int) $this->id;
        if ($this->db->query($sql)) {
            return 1;
        }
        $this->error = "SQL Error: ".$this->db->error();
        return -1;
    }
}