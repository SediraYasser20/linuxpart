<?php
/* Copyright (C) 2025 Nicolas Testori
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \defgroup   customerreturn Module CustomerReturn
 * \brief      Customer Return Management Module
 */

/**
 * \file       core/modules/modCustomerreturn.class.php
 * \ingroup    customerreturn
 * \brief      Description and activation file for module CustomerReturn
 */

include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

class modCustomerreturn extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs, $conf;
        $this->db = $db;

        // Identification
        $this->numero = 540000;
        $this->rights_class = 'customerreturn';
        $this->family = "products";
        $this->module_position = '90';
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = "Customer Returns Management Module";
        $this->descriptionlong = "Manage customer product returns";
        $this->editor_name = 'Nicolas Testori';
        $this->editor_url = '';
        $this->version = '1.0';
        $this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
        $this->picto = 'customerreturn@customerreturn';

        // Language files
        $this->langfiles = array('customerreturn@customerreturn');

        // Module parts
        $this->module_parts = array(
            'triggers' => 1,
            'hooks' => array('ordercard', 'expeditioncard'),
            'models' => 1,
        );

        // Data directories to create when module is enabled
        $this->dirs = array('/customerreturn/temp');

        // Config pages
        $this->config_page_url = array("customerreturn.php@customerreturn");

        // Dependencies
        $this->hidden = false;
        $this->depends = array('modCommande', 'modExpedition', 'modFacture', 'modStock');
        $this->requiredby = array();
        $this->conflictwith = array();

        // Constants
        $this->const = array(
            1 => array(
                'CUSTOMERRETURN_ADDON',
                'chaine',
                'mod_customerreturn_standard',
                'Numbering module for customer returns',
                0
            ),
            2 => array(
                'CUSTOMERRETURN_ADDON_PDF',
                'chaine',
                'standard',
                'PDF template for customer returns',
                0
            ),
        );

        // Boxes
        $this->boxes = array();

        // Permissions
        $this->rights = array();
        $r = 0;

        $r++;
        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Read customer returns';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'lire';

        $r++;
        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Create/Update customer returns';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'creer';

        $r++;
        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Delete customer returns';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'supprimer';

        // Menus
        $this->menu = array();
        $r = 0;

        // ---- TOP MENU ----
        $r++;
        $this->menu[$r] = array(
            'fk_menu' => '',
            'type' => 'top',
            'titre' => 'CustomerReturns',
            'prefix' => img_picto('', $this->picto, 'class="paddingright pictofixedwidth"'),
            'mainmenu' => 'customerreturn',
            'leftmenu' => '',
            'url' => '/custom/customerreturn/dashboard.php',
            'langs' => 'customerreturn@customerreturn',
            'position' => 1000 + $r,
            'enabled' => 'isModEnabled("customerreturn")',
            'perms' => '$user->rights->customerreturn->lire',
            'target' => '',
            'user' => 2,
        );

        // ---- LEFT MENU: Dashboard ----
        $r++;
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=customerreturn',
            'type' => 'left',
            'titre' => 'Dashboard',
            'mainmenu' => 'customerreturn',
            'leftmenu' => 'dashboard',
            'url' => '/custom/customerreturn/dashboard.php',
            'langs' => 'customerreturn@customerreturn',
            'position' => 1000 + $r,
            'enabled' => 'isModEnabled("customerreturn")',
            'perms' => '$user->rights->customerreturn->lire',
            'target' => '',
            'user' => 2,
        );

        // ---- LEFT MENU: List ----
        $r++;
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=customerreturn',
            'type' => 'left',
            'titre' => 'List',
            'mainmenu' => 'customerreturn',
            'leftmenu' => 'list',
            'url' => '/custom/customerreturn/list.php',
            'langs' => 'customerreturn@customerreturn',
            'position' => 1000 + $r,
            'enabled' => 'isModEnabled("customerreturn")',
            'perms' => '$user->rights->customerreturn->lire',
            'target' => '',
            'user' => 2,
        );

        // ---- LEFT MENU: New Return ----
        $r++;
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=customerreturn',
            'type' => 'left',
            'titre' => 'NewCustomerReturn',
            'mainmenu' => 'customerreturn',
            'leftmenu' => 'new',
            'url' => '/custom/customerreturn/create_from_shipment.php',
            'langs' => 'customerreturn@customerreturn',
            'position' => 1000 + $r,
            'enabled' => 'isModEnabled("customerreturn")',
            'perms' => '$user->rights->customerreturn->creer',
            'target' => '',
            'user' => 2,
        );
    }

    public function init($options = '')
    {
        global $langs;
        $this->_load_tables('/customerreturn/sql/');
        $this->remove($options);
        $sql = array();
        return $this->_init($sql, $options);
    }

    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }
}

