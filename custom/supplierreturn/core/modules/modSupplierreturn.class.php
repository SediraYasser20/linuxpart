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

class modSupplierreturn extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;
        $this->numero = 468270; // Numéro DoliStore officiel
        $this->rights_class = 'supplierreturn';
        $this->family = "products";
        $this->module_position = '43';
        $this->name = 'supplierreturn';
        $this->description = "Module de gestion des retours de produits aux fournisseurs";
        $this->descriptionlong = "Module permettant de gérer les retours de produits aux fournisseurs avec mise à jour automatique du stock";
        $this->descriptionlong_en = "Module for managing product returns to suppliers with automatic stock updates";
        $this->editor_name = 'Nicolas Testori';
        $this->editor_url = '';
        $this->version = '1.1.1';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->picto = 'supplierreturn@supplierreturn';
        
        // Configuration page URL (gear icon in module list)
        $this->config_page_url = array("supplierreturn.php@supplierreturn");

        // Parts of module
        $this->module_parts = array(
            'hooks' => array(
                'receptioncard', 'receptionlist', 'receptionindex',
                'ordersuppliercard', 'ordersupplierlist', 'ordersupplierindex',
                'invoicesuppliercard', 'invoicesupplierlist', 'invoicesupplierindex',
                'globalcard', 'commonobject', 'document'
            ),
            'triggers' => 1,
            'models' => 1,
            // Déclaration du modulepart pour document.php
            'moduleforexternal' => array(
                'supplierreturn' => array(
                    'dir' => 'supplierreturn',
                    'type' => 'read',
                    'description' => 'Supplier returns documents'
                )
            )
        );

        // Configuration des répertoires
        $this->dirs = array("/supplierreturn");
        
        // Set module directories (needed for document management)
        // Note: Don't check isModEnabled here as it prevents module from being listed in interface
        if (is_object($conf)) {
            // Configure supplierreturn modulepart for showdocuments()
            if (empty($conf->supplierreturn)) {
                $conf->supplierreturn = new stdClass();
            }
            $conf->supplierreturn->enabled = 1;
            $conf->supplierreturn->dir_output = DOL_DATA_ROOT.'/supplierreturn';
            // Set multidir_output for multi-entity support (required by showdocuments)
            if (empty($conf->supplierreturn->multidir_output)) {
                $conf->supplierreturn->multidir_output = array();
                $conf->supplierreturn->multidir_output[$conf->entity] = DOL_DATA_ROOT.'/supplierreturn';
            }
        }

        // Dépendances requises
        $this->depends = array('modStock', 'modFournisseur');
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->langfiles = array('supplierreturn@supplierreturn');

        // Configuration des droits/permissions
        $this->rights = array();
        $r = 0;
        
        // Permission de lecture (basée sur le format du module Stock)
        $this->rights[$r][0] = $this->numero + 1; // 468271 
        $this->rights[$r][1] = 'Lire les retours fournisseurs';
        $this->rights[$r][2] = 'r';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'lire';
        $r++;
        
        // Permission d'écriture (création/modification)
        $this->rights[$r][0] = $this->numero + 2; // 468272
        $this->rights[$r][1] = 'Créer/modifier les retours fournisseurs';
        $this->rights[$r][2] = 'w';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'creer';
        $r++;
        
        // Permission de suppression
        $this->rights[$r][0] = $this->numero + 3; // 468273
        $this->rights[$r][1] = 'Supprimer les retours fournisseurs';
        $this->rights[$r][2] = 'd';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'supprimer';

        // Configuration des menus
        $this->menu = array();
        $r = 0;

        // Menu principal (top)
        $this->menu[$r++] = array(
            'fk_menu'=>'',
            'type'=>'top',
            'titre'=>'TopMenuSupplierReturns',
            'prefix' => img_picto('', $this->picto, 'class="paddingright pictofixedwidth"'),
            'mainmenu'=>'supplierreturn',
            'leftmenu'=>'',
            'url'=>'/custom/supplierreturn/index.php',
            'langs'=>'supplierreturn@supplierreturn',
            'position'=>1000,
            'enabled'=>'isModEnabled("supplierreturn")',
            'perms'=>'$user->hasRight("supplierreturn", "lire")',
            'target'=>'',
            'user'=>2
        );

        // Titre du menu latéral gauche
        $this->menu[$r++] = array(
            'fk_menu'=>'fk_mainmenu=supplierreturn',
            'type'=>'left',
            'titre'=>'TopMenuSupplierReturns',
            'prefix' => img_picto('', $this->picto, 'class="paddingright pictofixedwidth"'),
            'mainmenu'=>'supplierreturn',
            'leftmenu'=>'supplierreturn_main',
            'url'=>'',
            'langs'=>'supplierreturn@supplierreturn',
            'position'=>10,
            'enabled'=>'isModEnabled("supplierreturn")',
            'perms'=>'$user->hasRight("supplierreturn", "lire")',
            'target'=>'',
            'user'=>2
        );

        // Sous-menu: Nouveau retour
        $this->menu[$r++] = array(
            'fk_menu'=>'fk_mainmenu=supplierreturn',
            'type'=>'left',
            'titre'=>'Nouveau retour',
            'mainmenu'=>'supplierreturn',
            'leftmenu'=>'supplierreturn_new',
            'url'=>'/custom/supplierreturn/card.php?action=create',
            'langs'=>'supplierreturn@supplierreturn',
            'position'=>100,
            'enabled'=>'isModEnabled("supplierreturn")',
            'perms'=>'$user->hasRight("supplierreturn", "creer")',
            'target'=>'',
            'user'=>2
        );

        // Sous-menu: Créer depuis réception
        $this->menu[$r++] = array(
            'fk_menu'=>'fk_mainmenu=supplierreturn',
            'type'=>'left',
            'titre'=>'Créer depuis réception',
            'mainmenu'=>'supplierreturn',
            'leftmenu'=>'supplierreturn_from_reception',
            'url'=>'/custom/supplierreturn/create_from_reception.php',
            'langs'=>'supplierreturn@supplierreturn',
            'position'=>101,
            'enabled'=>'isModEnabled("supplierreturn") && isModEnabled("reception")',
            'perms'=>'$user->hasRight("supplierreturn", "creer")',
            'target'=>'',
            'user'=>2
        );

        // Sous-menu: Liste des retours
        $this->menu[$r++] = array(
            'fk_menu'=>'fk_mainmenu=supplierreturn',
            'type'=>'left',
            'titre'=>'Liste des retours',
            'mainmenu'=>'supplierreturn',
            'leftmenu'=>'supplierreturn_list',
            'url'=>'/custom/supplierreturn/list.php',
            'langs'=>'supplierreturn@supplierreturn',
            'position'=>102,
            'enabled'=>'isModEnabled("supplierreturn")',
            'perms'=>'$user->hasRight("supplierreturn", "lire")',
            'target'=>'',
            'user'=>2
        );

        // Sous-menu: Retours brouillon (enfant de Liste)
        $this->menu[$r++] = array(
            'fk_menu'=>'fk_mainmenu=supplierreturn,fk_leftmenu=supplierreturn_list',
            'type'=>'left',
            'titre'=>'SupplierReturnsDraft',
            'mainmenu'=>'supplierreturn',
            'leftmenu'=>'supplierreturn_list_draft',
            'url'=>'/custom/supplierreturn/list.php?search_status=0',
            'langs'=>'supplierreturn@supplierreturn',
            'position'=>103,
            'enabled'=>'isModEnabled("supplierreturn")',
            'perms'=>'$user->hasRight("supplierreturn", "lire")',
            'target'=>'',
            'user'=>2
        );

        // Sous-menu: Retours validés (enfant de Liste)
        $this->menu[$r++] = array(
            'fk_menu'=>'fk_mainmenu=supplierreturn,fk_leftmenu=supplierreturn_list',
            'type'=>'left',
            'titre'=>'SupplierReturnsValidated',
            'mainmenu'=>'supplierreturn',
            'leftmenu'=>'supplierreturn_list_validated',
            'url'=>'/custom/supplierreturn/list.php?search_status=1',
            'langs'=>'supplierreturn@supplierreturn',
            'position'=>104,
            'enabled'=>'isModEnabled("supplierreturn")',
            'perms'=>'$user->hasRight("supplierreturn", "lire")',
            'target'=>'',
            'user'=>2
        );

        // Sous-menu: Retours traités (enfant de Liste)
        $this->menu[$r++] = array(
            'fk_menu'=>'fk_mainmenu=supplierreturn,fk_leftmenu=supplierreturn_list',
            'type'=>'left',
            'titre'=>'SupplierReturnsProcessed',
            'mainmenu'=>'supplierreturn',
            'leftmenu'=>'supplierreturn_list_processed',
            'url'=>'/custom/supplierreturn/list.php?search_status=2',
            'langs'=>'supplierreturn@supplierreturn',
            'position'=>105,
            'enabled'=>'isModEnabled("supplierreturn")',
            'perms'=>'$user->hasRight("supplierreturn", "lire")',
            'target'=>'',
            'user'=>2
        );

        // Sous-menu: Retours annulés (enfant de Liste)
        $this->menu[$r++] = array(
            'fk_menu'=>'fk_mainmenu=supplierreturn,fk_leftmenu=supplierreturn_list',
            'type'=>'left',
            'titre'=>'SupplierReturnsCanceled',
            'mainmenu'=>'supplierreturn',
            'leftmenu'=>'supplierreturn_list_canceled',
            'url'=>'/custom/supplierreturn/list.php?search_status=9',
            'langs'=>'supplierreturn@supplierreturn',
            'position'=>106,
            'enabled'=>'isModEnabled("supplierreturn")',
            'perms'=>'$user->hasRight("supplierreturn", "lire")',
            'target'=>'',
            'user'=>2
        );

        // Sous-menu: Configuration (admin only)
        $this->menu[$r++] = array(
            'fk_menu'=>'fk_mainmenu=supplierreturn',
            'type'=>'left',
            'titre'=>'Setup',
            'mainmenu'=>'supplierreturn',
            'leftmenu'=>'supplierreturn_setup',
            'url'=>'/custom/supplierreturn/admin/supplierreturn.php',
            'langs'=>'supplierreturn@supplierreturn',
            'position'=>900,
            'enabled'=>'isModEnabled("supplierreturn")',
            'perms'=>'$user->admin',
            'target'=>'',
            'user'=>2
        );

        // Boxes
        $this->boxes = array();

        // Cronjobs
        $this->cronjobs = array();
        
        // Document models and module parts
        $this->module_parts = array_merge($this->module_parts, array(
            'models' => 1,  // Enable model management
            'pdf' => array(
                'supplierreturn' => array(
                    'dir' => '/custom/supplierreturn/core/modules/supplierreturn/pdf/',
                    'models' => array('standard')
                )
            )
        ));

        // Constants
        $this->const = array();
        $r = 0;
        
        $this->const[$r][0] = "SUPPLIERRETURN_ADDON";
        $this->const[$r][1] = "chaine";
        $this->const[$r][2] = "mod_supplierreturn_standard";
        $this->const[$r][3] = 'Active numbering module for supplier returns';
        $this->const[$r][4] = 0;
        $r++;
        
        $this->const[$r][0] = "SUPPLIERRETURN_ADDON_NUMBER";
        $this->const[$r][1] = "chaine";
        $this->const[$r][2] = "SR{yy}{mm}-{####}";
        $this->const[$r][3] = 'Numbering mask for supplier returns';
        $this->const[$r][4] = 0;
        $r++;
        
        $this->const[$r][0] = "SUPPLIERRETURN_AUTO_VALIDATE_CREDITNOTE";
        $this->const[$r][1] = "chaine";
        $this->const[$r][2] = "0";
        $this->const[$r][3] = 'Auto validate credit notes created from returns';
        $this->const[$r][4] = 0;
        $r++;
        
        $this->const[$r][0] = "SUPPLIERRETURN_ADDON_PDF";
        $this->const[$r][1] = "chaine";
        $this->const[$r][2] = "standard";
        $this->const[$r][3] = 'Default PDF template for supplier returns';
        $this->const[$r][4] = 0;
    }

    /**
     * Function called when module is enabled.
     * The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
     * It also creates data directories
     *
     * @param  string $options Options when enabling module ('', 'noboxes')
     * @return int             1 if OK, 0 if KO
     */
    public function init($options = '')
    {
        global $conf, $langs, $db;

        // Clean old module configuration before activation
        $this->cleanOldModuleConfig($db);

        // Create database tables
        $sql = array();
        
        // Table principale des retours fournisseurs
        $sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."supplierreturn (
            rowid integer AUTO_INCREMENT PRIMARY KEY,
            ref varchar(30) NOT NULL,
            supplier_ref varchar(100) DEFAULT NULL,
            entity integer DEFAULT 1 NOT NULL,
            fk_soc integer NOT NULL,
            fk_reception integer,
            fk_commande_fournisseur integer,
            fk_facture_fourn integer,
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
            UNIQUE KEY uk_supplierreturn_ref (ref, entity),
            KEY idx_supplierreturn_fk_soc (fk_soc),
            KEY idx_supplierreturn_fk_reception (fk_reception),
            KEY idx_supplierreturn_statut (statut),
            KEY idx_supplierreturn_supplier_ref (supplier_ref),
            KEY idx_supplierreturn_model_pdf (model_pdf),
            KEY idx_supplierreturn_last_main_doc (last_main_doc)
        ) ENGINE=InnoDB";

        // Table des lignes de retour
        $sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."supplierreturndet (
            rowid integer AUTO_INCREMENT PRIMARY KEY,
            fk_supplierreturn integer NOT NULL,
            fk_product integer,
            fk_product_batch integer,
            fk_reception_line integer,
            description text,
            qty double DEFAULT 0,
            subprice double(24,8) DEFAULT 0,
            total_ht double(24,8) DEFAULT 0,
            total_ttc double(24,8) DEFAULT 0,
            batch varchar(128),
            fk_entrepot integer,
            rang integer DEFAULT 0,
            fk_facture_fourn_det_source integer,
            original_subprice double(24,8),
            original_tva_tx double(8,4),
            original_localtax1_tx double(8,4) DEFAULT 0,
            original_localtax2_tx double(8,4) DEFAULT 0,
            tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_supplierreturndet_fk_supplierreturn (fk_supplierreturn),
            KEY idx_supplierreturndet_fk_product (fk_product),
            KEY idx_supplierreturndet_fk_reception_line (fk_reception_line)
        ) ENGINE=InnoDB";

        $result = $this->_init($sql, $options);
        
        // Force update database structure even if _init had issues
        // This ensures module works even if tables already exist
        try {
            $this->updateDatabaseStructure($db);
            dol_syslog("SupplierReturns: Database structure update completed", LOG_INFO);
        } catch (Exception $e) {
            dol_syslog("SupplierReturns: Database structure update failed: " . $e->getMessage(), LOG_WARNING);
        }
        
        // Set default configuration to disable PDF auto-generation initially
        if ($result && empty($conf->global->SUPPLIERRETURN_DISABLE_PDF_AUTOGEN)) {
            require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
            dolibarr_set_const($db, 'SUPPLIERRETURN_DISABLE_PDF_AUTOGEN', '1', 'chaine', 0, 'Disable automatic PDF generation during validation to avoid errors', $conf->entity);
        }
        
        // Register modulepart for document security system
        if ($result) {
            global $dolibarr_main_data_root;
            
            // Register supplierreturn modulepart for document security
            if (empty($conf->supplierreturn)) {
                $conf->supplierreturn = new stdClass();
            }
            $conf->supplierreturn->enabled = 1;
            $conf->supplierreturn->dir_output = DOL_DATA_ROOT.'/supplierreturn';
            
            dol_syslog("SupplierReturns: Modulepart 'supplierreturn' registered for document access", LOG_INFO);
            
            // Register PDF model in database
            dol_syslog("SupplierReturns: About to register PDF model", LOG_INFO);
            $pdf_result = $this->registerPDFModel($db);
            dol_syslog("SupplierReturns: PDF model registration result: " . ($pdf_result ? "SUCCESS" : "FAILED"), LOG_INFO);
        }
        
        return $result;
    }

    /**
     * Clean old module configuration that might conflict with activation
     *
     * @param  DoliDB $db Database object
     * @return void
     */
    private function cleanOldModuleConfig($db)
    {
        // 1. Remove ALL module constants (old and new) to avoid duplicates
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."const WHERE name = 'MAIN_MODULE_SUPPLIERRETURNS'";
        $db->query($sql);
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."const WHERE name = 'MAIN_MODULE_SUPPLIERRETURN'";
        $db->query($sql);
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'MAIN_MODULE_SUPPLIERRETURN_%'";
        $db->query($sql);
        
        // 2. Remove ALL conflicting menus
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."menu WHERE module = 'supplierreturns'";
        $db->query($sql);
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."menu WHERE module = 'supplierreturn'";
        $db->query($sql);
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."menu WHERE url LIKE '%/custom/supplierreturn%'";
        $db->query($sql);
        
        // 3. Remove specific problematic menu entry
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."menu WHERE url = '/custom/supplierreturn/index.php' AND mainmenu = 'all'";
        $db->query($sql);
        
        // 4. Remove any other supplierreturn-related constants
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."const WHERE name LIKE '%SUPPLIERRETURN%'";
        $db->query($sql);
        
        dol_syslog("SupplierReturns: Completely cleaned all module configuration and duplicates", LOG_INFO);
    }

    /**
     * Update database structure to add missing columns for existing installations
     *
     * @param  DoliDB $db Database object
     * @return bool       True if successful
     */
    private function updateDatabaseStructure($db)
    {
        // Define columns to add to supplierreturn table
        $columnsToAddSupplierReturn = array(
            'supplier_ref' => 'varchar(100) DEFAULT NULL',
            'model_pdf' => 'varchar(255) DEFAULT NULL',
            'last_main_doc' => 'varchar(255) DEFAULT NULL'
        );
        
        // Add missing columns to supplierreturn table
        foreach ($columnsToAddSupplierReturn as $column => $type) {
            $sql = "SHOW COLUMNS FROM ".MAIN_DB_PREFIX."supplierreturn LIKE '".$column."'";
            $resql = $db->query($sql);
            
            if ($resql && $db->num_rows($resql) == 0) {
                $sql = "ALTER TABLE ".MAIN_DB_PREFIX."supplierreturn ADD COLUMN ".$column." ".$type;
                $resql_alter = $db->query($sql);
                if (!$resql_alter) {
                    dol_syslog("SupplierReturns: Error adding column ".$column.": ".$db->error(), LOG_WARNING);
                    continue; // Continue with next column even if this one fails
                } else {
                    dol_syslog("SupplierReturns: Successfully added column ".$column, LOG_INFO);
                }
                
                // Add indexes for specific columns
                if ($column == 'supplier_ref') {
                    $sql = "CREATE INDEX idx_supplierreturn_supplier_ref ON ".MAIN_DB_PREFIX."supplierreturn(supplier_ref)";
                    $db->query($sql);
                }
                if ($column == 'model_pdf') {
                    // Set default value for existing records
                    $sql = "UPDATE ".MAIN_DB_PREFIX."supplierreturn SET model_pdf = 'standard' WHERE model_pdf IS NULL";
                    $db->query($sql);
                    // Add index
                    $sql = "CREATE INDEX idx_supplierreturn_model_pdf ON ".MAIN_DB_PREFIX."supplierreturn(model_pdf)";
                    $db->query($sql);
                    dol_syslog("SupplierReturns: Set default model_pdf to 'standard' for existing records", LOG_INFO);
                }
                if ($column == 'last_main_doc') {
                    // Add index for last_main_doc
                    $sql = "CREATE INDEX idx_supplierreturn_last_main_doc ON ".MAIN_DB_PREFIX."supplierreturn(last_main_doc)";
                    $db->query($sql);
                    dol_syslog("SupplierReturns: Added index for last_main_doc column", LOG_INFO);
                }
            }
        }
        
        // Define columns to add to supplierreturndet table
        $columnsToAdd = array(
            'fk_facture_fourn_det_source' => 'integer',
            'original_subprice' => 'double(24,8)',
            'original_tva_tx' => 'double(8,4)',
            'original_localtax1_tx' => 'double(8,4) DEFAULT 0',
            'original_localtax2_tx' => 'double(8,4) DEFAULT 0'
        );

        foreach ($columnsToAdd as $column => $type) {
            // Check if column exists
            $sql = "SHOW COLUMNS FROM ".MAIN_DB_PREFIX."supplierreturndet LIKE '".$column."'";
            $resql = $db->query($sql);
            
            if ($resql && $db->num_rows($resql) == 0) {
                // Column doesn't exist, add it
                $sql = "ALTER TABLE ".MAIN_DB_PREFIX."supplierreturndet ADD COLUMN ".$column." ".$type;
                $resql = $db->query($sql);
                if (!$resql) {
                    dol_syslog("SupplierReturns: Error adding column ".$column.": ".$db->error(), LOG_ERR);
                } else {
                    dol_syslog("SupplierReturns: Added column ".$column." to supplierreturndet table", LOG_INFO);
                }
            }
        }
        
        // Create directories for documents
        $this->createDirectories();
        
        return true;
    }
    
    /**
     * Create directories for module documents
     *
     * @return bool True if successful
     */
    private function createDirectories()
    {
        global $conf;
        
        // Create main supplierreturn directory
        $dir = DOL_DATA_ROOT.'/supplierreturn';
        if (!is_dir($dir)) {
            if (dol_mkdir($dir) < 0) {
                dol_syslog("SupplierReturns: Error creating directory ".$dir, LOG_ERR);
                return false;
            } else {
                dol_syslog("SupplierReturns: Created directory ".$dir, LOG_INFO);
            }
        }
        
        return true;
    }
    
    /**
     * Register PDF model 'standard' in llx_document_model table
     *
     * @param  DoliDB $db Database object  
     * @return bool       True if successful
     */
    private function registerPDFModel($db)
    {
        // Check if model already exists
        $sql = "SELECT nom FROM ".MAIN_DB_PREFIX."document_model WHERE type = 'supplierreturn' AND nom = 'standard'";
        $resql = $db->query($sql);
        
        if ($resql && $db->num_rows($resql) > 0) {
            dol_syslog("SupplierReturns: PDF model 'standard' already exists in database", LOG_INFO);
            return true;
        }
        
        // Insert PDF model
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."document_model (
            nom,
            type,
            libelle,
            description
        ) VALUES (
            'standard',
            'supplierreturn',
            'Standard supplier return template',
            'Default PDF template for supplier returns with company header, product lines and totals'
        )";
        
        $resql = $db->query($sql);
        
        if ($resql) {
            dol_syslog("SupplierReturns: PDF model 'standard' registered successfully in llx_document_model", LOG_INFO);
            return true;
        } else {
            dol_syslog("SupplierReturns: Error registering PDF model: " . $db->lasterror(), LOG_ERR);
            return false;
        }
    }

    /**
     * Function called when module is disabled.
     * Remove from database constants, boxes and permissions from Dolibarr database.
     * Data directories are not deleted
     *
     * @param  string $options Options when enabling module ('', 'noboxes')
     * @return int             1 if OK, 0 if KO
     */
    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }
}
?>