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
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modCustomerreturn extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;
        $this->numero = 468271; // New ID to avoid conflicts
        $this->rights_class = 'customerreturn';
        $this->family = "customer";
        $this->module_position = '45';
        $this->name = 'customerreturn';
        $this->description = "Module for managing product returns from customers";
        $this->descriptionlong = "Module to manage customer product returns with automatic stock updates";
        $this->editor_name = 'Nicolas Testori';
        $this->editor_url = '';
        $this->version = '1.0.0';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->picto = 'customerreturn@customerreturn';

        $this->config_page_url = array("customerreturn.php@customerreturn");

        $this->module_parts = array(
            'hooks' => array(
                'expeditioncard', 'expeditionlist', 'expeditionindex',
                'ordercard', 'orderlist', 'orderindex',
                'invoicecard', 'invoicelist', 'invoiceindex',
                'globalcard', 'commonobject', 'document'
            ),
            'triggers' => 1,
            'models' => 1,
            'moduleforexternal' => array(
                'customerreturn' => array(
                    'dir' => 'customerreturn',
                    'type' => 'read',
                    'description' => 'Customer returns documents'
                )
            )
        );

        $this->dirs = array("/customerreturn");

        if (is_object($conf)) {
            if (empty($conf->customerreturn)) {
                $conf->customerreturn = new stdClass();
            }
            $conf->customerreturn->enabled = 1;
            $conf->customerreturn->dir_output = DOL_DATA_ROOT.'/customerreturn';
            if (empty($conf->customerreturn->multidir_output)) {
                $conf->customerreturn->multidir_output = array();
                $conf->customerreturn->multidir_output[$conf->entity] = DOL_DATA_ROOT.'/customerreturn';
            }
        }

        $this->depends = array('modStock', 'modExpedition', 'modCommande');
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->langfiles = array('customerreturn@customerreturn');

        $this->rights = array();
        $r = 0;

        $this->rights[$r][0] = $this->numero + 1;
        $this->rights[$r][1] = 'Read customer returns';
        $this->rights[$r][2] = 'r';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'lire';
        $r++;

        $this->rights[$r][0] = $this->numero + 2;
        $this->rights[$r][1] = 'Create/modify customer returns';
        $this->rights[$r][2] = 'w';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'creer';
        $r++;

        $this->rights[$r][0] = $this->numero + 3;
        $this->rights[$r][1] = 'Delete customer returns';
        $this->rights[$r][2] = 'd';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'supprimer';

        $this->menu = array();
        $r = 0;

        $this->menu[$r++] = array(
            'fk_menu'=>'fk_mainmenu=commercial',
            'type'=>'left',
            'titre'=>'CustomerReturns',
            'mainmenu'=>'commercial',
            'leftmenu'=>'customerreturn',
            'url'=>'/custom/customerreturn/list.php',
            'langs'=>'customerreturn@customerreturn',
            'position'=>50,
            'enabled'=>'isModEnabled("customerreturn")',
            'perms'=>'$user->hasRight("customerreturn", "lire")',
            'target'=>'',
            'user'=>2
        );
    }

    public function init($options = '')
    {
        global $conf, $db;

        $this->remove($options);

        $sql = array();

        $sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."customerreturn (
            rowid integer AUTO_INCREMENT PRIMARY KEY,
            ref varchar(30) NOT NULL,
            customer_ref varchar(100) DEFAULT NULL,
            entity integer DEFAULT 1 NOT NULL,
            fk_soc integer NOT NULL,
            fk_expedition integer,
            fk_commande integer,
            fk_facture integer,
            date_creation datetime NOT NULL,
            date_modification datetime,
            date_return date,
            date_valid datetime,
            date_process datetime,
            note_public text,
            note_private text,
            return_reason varchar(255),
            statut smallint DEFAULT 0 NOT NULL,
            fk_user_author integer,
            fk_user_modif integer,
            fk_user_valid integer,
            model_pdf varchar(255) DEFAULT 'standard',
            last_main_doc varchar(255),
            total_ht double(24,8) DEFAULT 0,
            total_ttc double(24,8) DEFAULT 0,
            tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_customerreturn_ref (ref, entity)
        ) ENGINE=InnoDB";

        $sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."customerreturndet (
            rowid integer AUTO_INCREMENT PRIMARY KEY,
            fk_customerreturn integer NOT NULL,
            fk_product integer,
            fk_product_batch integer,
            fk_expeditiondet_line integer,
            description text,
            qty double DEFAULT 0,
            subprice double(24,8) DEFAULT 0,
            total_ht double(24,8) DEFAULT 0,
            total_ttc double(24,8) DEFAULT 0,
            batch varchar(128),
            fk_entrepot integer,
            rang integer DEFAULT 0,
            fk_facture_det_source integer,
            original_subprice double(24,8),
            original_tva_tx double(8,4),
            original_localtax1_tx double(8,4) DEFAULT 0,
            original_localtax2_tx double(8,4) DEFAULT 0,
            tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB";

        $sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."c_customerreturn_reasons (
            rowid integer AUTO_INCREMENT PRIMARY KEY,
            code varchar(16) NOT NULL,
            label varchar(255) NOT NULL,
            active tinyint DEFAULT 1 NOT NULL
        ) ENGINE=InnoDB";

        $sql[] = "INSERT INTO ".MAIN_DB_PREFIX."c_customerreturn_reasons (code, label, active) VALUES ('defective', 'Defective product', 1), ('damaged', 'Damaged during shipping', 1), ('wrong_item', 'Wrong item shipped', 1), ('other', 'Other reason', 1)";

        return $this->_init($sql, $options);
    }

    public function remove($options = '')
    {
        $sql = array(
            "DROP TABLE IF EXISTS ".MAIN_DB_PREFIX."customerreturn",
            "DROP TABLE IF EXISTS ".MAIN_DB_PREFIX."customerreturndet",
            "DROP TABLE IF EXISTS ".MAIN_DB_PREFIX."c_customerreturn_reasons"
        );
        return $this->_remove($sql, $options);
    }
}