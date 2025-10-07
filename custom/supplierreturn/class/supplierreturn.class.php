
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

class SupplierReturn extends CommonObject
{
    public $table_element = 'supplierreturn';
    public $element = 'supplierreturn';
    public $picto = 'supplierreturn@supplierreturn';

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
    
    // Relations avec autres documents
    public $fk_reception;
    public $fk_commande_fournisseur;
    public $fk_facture_fourn;
    public $return_reason;
    public $date_return;
    public $supplier_ref;

    public $fields = array(
        'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => '1', 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => '1', 'index' => 1, 'comment' => "Id"),
        'ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => '1', 'position' => 20, 'notnull' => 1, 'visible' => 4, 'noteditable' => '1', 'default' => '(PROV)', 'index' => 1, 'searchall' => 1, 'showoncombobox' => '1', 'comment' => "Reference of object"),
        'fk_soc' => array('type' => 'integer:Societe:societe/class/societe.class.php', 'label' => 'ThirdParty', 'enabled' => '1', 'position' => 50, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'help' => "LinkToThirdparty"),
        'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => '1', 'position' => 500, 'notnull' => 1, 'visible' => -2),
        'date_modification' => array('type' => 'datetime', 'label' => 'DateModification', 'enabled' => '1', 'position' => 501, 'notnull' => 0, 'visible' => -2),
        'fk_user_author' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => '1', 'position' => 510, 'notnull' => 1, 'visible' => -2, 'foreignkey' => 'user.rowid'),
        'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => '1', 'position' => 511, 'notnull' => -1, 'visible' => -2),
        'note_public' => array('type' => 'html', 'label' => 'NotePublic', 'enabled' => '1', 'position' => 61, 'notnull' => 0, 'visible' => 0),
        'note_private' => array('type' => 'html', 'label' => 'NotePrivate', 'enabled' => '1', 'position' => 62, 'notnull' => 0, 'visible' => 0),
        'last_main_doc' => array('type' => 'varchar(255)', 'label' => 'LastMainDoc', 'enabled' => '1', 'position' => 63, 'notnull' => 0, 'visible' => 0),
        'statut' => array('type' => 'smallint', 'label' => 'Status', 'enabled' => '1', 'position' => 1000, 'notnull' => 1, 'visible' => 5, 'default' => '0', 'index' => 1, 'arrayofkeyval' => array('0' => 'Draft', '1' => 'Validated', '2' => 'Closed', '3' => 'ReturnedToVendor', '4' => 'ReimbursedFromVendor', '5' => 'ProductChangedFromVendor')),
    );

    const STATUS_DRAFT = 0;
    const STATUS_VALIDATED = 1;
    const STATUS_CLOSED = 2; // Kept for compatibility, but the new flow will use the statuses below
    const STATUS_RETURNED_TO_VENDOR = 3;
    const STATUS_REIMBURSED_FROM_VENDOR = 4;
    const STATUS_PRODUCT_CHANGED_FROM_VENDOR = 5;

    /**
     * Check if extended columns exist in the table
     * @return boolean
     */
    private function checkExtendedColumns()
    {
        static $checked = null;
        if ($checked !== null) {
            return $checked;
        }
        
        dol_syslog("SupplierReturn::checkExtendedColumns() - Checking if extended columns exist", LOG_DEBUG);
        $sql = "SHOW COLUMNS FROM ".MAIN_DB_PREFIX."supplierreturn LIKE 'fk_reception'";
        $result = $this->db->query($sql);
        $checked = ($result && $this->db->num_rows($result) > 0);
        dol_syslog("SupplierReturn::checkExtendedColumns() - Result: ".($checked ? 'true' : 'false'), LOG_DEBUG);
        
        return $checked;
    }

    private function autoFixMissingColumns()
    {
        static $fixed = false;
        if ($fixed) return;
        
        $columnsToCheck = array(
            'last_main_doc' => 'varchar(255) DEFAULT NULL',
            'model_pdf' => 'varchar(255) DEFAULT \'standard\'',
            'supplier_ref' => 'varchar(100) DEFAULT NULL'
        );
        
        foreach ($columnsToCheck as $column => $definition) {
            $sql = "SHOW COLUMNS FROM ".MAIN_DB_PREFIX."supplierreturn LIKE '".$column."'";
            $result = $this->db->query($sql);
            
            if ($result && $this->db->num_rows($result) == 0) {
                dol_syslog("SupplierReturn: Auto-fixing missing column ".$column, LOG_INFO);
                $sql_alter = "ALTER TABLE ".MAIN_DB_PREFIX."supplierreturn ADD COLUMN ".$column." ".$definition;
                $this->db->query($sql_alter);
                
                if ($column == 'model_pdf') {
                    $this->db->query("UPDATE ".MAIN_DB_PREFIX."supplierreturn SET model_pdf = 'standard' WHERE model_pdf IS NULL");
                }
            }
        }
        
        $fixed = true;
    }

    private function autoFixModuleConfig()
    {
        static $config_fixed = false;
        if ($config_fixed) return;
        
        global $conf;
        
        // 1. Fix module activation constant in database
        $sql = "UPDATE ".MAIN_DB_PREFIX."const SET name = 'MAIN_MODULE_SUPPLIERRETURN' WHERE name = 'MAIN_MODULE_SUPPLIERRETURNS'";
        $this->db->query($sql);
        
        // 2. Fix any remaining configuration constants
        $sql = "UPDATE ".MAIN_DB_PREFIX."const SET name = REPLACE(name, 'SUPPLIERRETURNS_', 'SUPPLIERRETURN_') WHERE name LIKE 'SUPPLIERRETURNS_%'";
        $this->db->query($sql);
        
        // 3. Clean old menus to avoid "Menu entry already exists" error
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."menu WHERE module = 'supplierreturns'";
        $this->db->query($sql);
        
        // 4. Also clean any menu with old URLs pointing to supplierreturns
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."menu WHERE url LIKE '%/custom/supplierreturns/%'";
        $this->db->query($sql);
        
        // 5. Delete old module constants completely
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."const WHERE name = 'MAIN_MODULE_SUPPLIERRETURNS'";
        $this->db->query($sql);
        
        // 6. Force update runtime configuration
        if (isset($conf->global->MAIN_MODULE_SUPPLIERRETURNS)) {
            $conf->global->MAIN_MODULE_SUPPLIERRETURN = $conf->global->MAIN_MODULE_SUPPLIERRETURNS;
            unset($conf->global->MAIN_MODULE_SUPPLIERRETURNS);
        }
        
        // 7. Update module hooks configuration in session
        if (isset($conf->hooks_modules) && is_array($conf->hooks_modules)) {
            foreach ($conf->hooks_modules as $key => $module) {
                if ($module === 'supplierreturns') {
                    $conf->hooks_modules[$key] = 'supplierreturn';
                }
            }
        }
        
        // 8. Force module to be enabled with correct name and proper configuration
        if (empty($conf->supplierreturn)) {
            $conf->supplierreturn = new stdClass();
        }
        $conf->supplierreturn->enabled = 1;
        $conf->supplierreturn->dir_output = DOL_DATA_ROOT.'/supplierreturn';
        $conf->supplierreturn->multidir_output = array();
        $conf->supplierreturn->multidir_output[$conf->entity] = DOL_DATA_ROOT.'/supplierreturn';
        
        // 9. Force recreation of module menus if needed
        $this->forceRecreateMenus();
        
        dol_syslog("SupplierReturn: Auto-fixed complete module configuration", LOG_INFO);
        $config_fixed = true;
    }

    private function forceRecreateMenus()
    {
        // Check if the main menu entry exists for our module
        $sql = "SELECT COUNT(*) as count FROM ".MAIN_DB_PREFIX."menu WHERE url = '/custom/supplierreturn/index.php' AND module = 'supplierreturn'";
        $resql = $this->db->query($sql);
        
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            if ($obj->count == 0) {
                // Menu doesn't exist, we need to force module reactivation
                // Set a flag that menus need to be recreated
                $sql = "DELETE FROM ".MAIN_DB_PREFIX."const WHERE name = 'SUPPLIERRETURN_MENUS_CREATED'";
                $this->db->query($sql);
                
                dol_syslog("SupplierReturn: Forced menu recreation flag", LOG_INFO);
            }
        }
    }

    public function __construct($db)
    {
        global $conf;
        
        $this->db = $db;
        
        // Auto-fix missing columns on first access
        $this->autoFixMissingColumns();
        
        // Auto-fix module configuration
        $this->autoFixModuleConfig();
        
        // Initialize module configuration if not set
        if (empty($conf->supplierreturns)) {
            $conf->supplierreturns = new stdClass();
            $conf->supplierreturns->dir_output = DOL_DATA_ROOT.'/invoice_supplier/supplierreturn';
            $conf->supplierreturns->dir_temp = DOL_DATA_ROOT.'/supplierreturn/temp';
        }
    }

    public function create($user, $notrigger = false)
    {
        global $conf, $langs;

        $error = 0;

        $this->db->begin();

        $now = dol_now();

        if (empty($this->ref) || $this->ref == '(PROV)') {
            $this->ref = '(PROV)';
        }

        // Check if extended columns exist
        $extended_columns_exist = $this->checkExtendedColumns();
        
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."supplierreturn (";
        $sql .= "ref, fk_soc, date_creation, fk_user_author, note_public, note_private, statut";
        if ($extended_columns_exist) {
            $sql .= ", fk_reception, fk_commande_fournisseur, fk_facture_fourn, return_reason, date_return, supplier_ref";
        }
        $sql .= ") VALUES (";
        $sql .= "'".$this->db->escape($this->ref)."'";
        $sql .= ", ".(int) $this->fk_soc;
        $sql .= ", '".$this->db->idate($now)."'";
        $sql .= ", ".(int) $user->id;
        $sql .= ", ".(isset($this->note_public) ? "'".$this->db->escape($this->note_public)."'" : "null");
        $sql .= ", ".(isset($this->note_private) ? "'".$this->db->escape($this->note_private)."'" : "null");
        $sql .= ", ".self::STATUS_DRAFT;
        if ($extended_columns_exist) {
            $sql .= ", ".(isset($this->fk_reception) ? (int) $this->fk_reception : "null");
            $sql .= ", ".(isset($this->fk_commande_fournisseur) ? (int) $this->fk_commande_fournisseur : "null");
            $sql .= ", ".(isset($this->fk_facture_fourn) ? (int) $this->fk_facture_fourn : "null");
            $sql .= ", ".(isset($this->return_reason) ? "'".$this->db->escape($this->return_reason)."'" : "null");
            $sql .= ", ".(isset($this->date_return) ? "'".$this->db->idate($this->date_return)."'" : "null");
            $sql .= ", ".(isset($this->supplier_ref) ? "'".$this->db->escape($this->supplier_ref)."'" : "null");
        }
        $sql .= ")";

        $resql = $this->db->query($sql);
        if ($resql) {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."supplierreturn");
            $this->date_creation = $now;
            $this->statut = self::STATUS_DRAFT;

            // Update reference with unique (PROV) number using ID
            if ($this->ref == '(PROV)') {
                $this->ref = '(PROV'.$this->id.')';
                $sql_update = "UPDATE ".MAIN_DB_PREFIX."supplierreturn SET ref = '".$this->db->escape($this->ref)."' WHERE rowid = ".(int) $this->id;
                $this->db->query($sql_update);
            }

            // Create object links to related documents
            if (!$error) {
                $this->createObjectLinks();
            }

            if (!$notrigger) {
                // Appel des triggers
                $result = $this->call_trigger('SUPPLIERRETURN_CREATE', $user);
                if ($result < 0) {
                    $error++;
                }
            }

            if (!$error) {
                $this->db->commit();
                return $this->id;
            } else {
                $this->db->rollback();
                return -1;
            }
        } else {
            $this->error = $this->db->error();
            $this->db->rollback();
            return -1;
        }
    }

    public function fetch($id, $ref = null)
    {
        $extended_columns_exist = $this->checkExtendedColumns();
        
        $sql = "SELECT t.rowid, t.ref, t.fk_soc, t.date_creation, t.date_modification";
        $sql .= ", t.fk_user_author, t.fk_user_modif, t.note_public, t.note_private, t.statut, t.last_main_doc";
        if ($extended_columns_exist) {
            $sql .= ", t.fk_reception, t.fk_commande_fournisseur, t.fk_facture_fourn, t.return_reason, t.date_return, t.supplier_ref";
        }
        $sql .= " FROM ".MAIN_DB_PREFIX."supplierreturn as t";
        $sql .= " WHERE t.entity IN (".getEntity('supplierreturn').")";
        if ($id) {
            $sql .= " AND t.rowid = ".(int) $id;
        } else {
            $sql .= " AND t.ref = '".$this->db->escape($ref)."'";
        }

        $resql = $this->db->query($sql);
        if ($resql) {
            if ($this->db->num_rows($resql)) {
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
                
                if ($extended_columns_exist) {
                    $this->fk_reception = $obj->fk_reception;
                    $this->fk_commande_fournisseur = $obj->fk_commande_fournisseur;
                    $this->fk_facture_fourn = $obj->fk_facture_fourn;
                    $this->return_reason = $obj->return_reason;
                    $this->date_return = $this->db->jdate($obj->date_return);
                    $this->supplier_ref = $obj->supplier_ref;
                }

                // Load lines
                $this->lines = $this->getLines();

                $this->db->free($resql);
                return 1;
            } else {
                $this->db->free($resql);
                return 0;
            }
        } else {
            $this->error = $this->db->error();
            return -1;
        }
    }

    public function update($user, $notrigger = false)
    {
        $error = 0;

        $this->db->begin();

        $sql = "UPDATE ".MAIN_DB_PREFIX."supplierreturn SET";
        $sql .= " fk_soc = ".(int) $this->fk_soc;
        $sql .= ", note_public = ".(isset($this->note_public) ? "'".$this->db->escape($this->note_public)."'" : "null");
        $sql .= ", note_private = ".(isset($this->note_private) ? "'".$this->db->escape($this->note_private)."'" : "null");
        $sql .= ", supplier_ref = ".(isset($this->supplier_ref) ? "'".$this->db->escape($this->supplier_ref)."'" : "null");
        $sql .= ", return_reason = ".(isset($this->return_reason) ? "'".$this->db->escape($this->return_reason)."'" : "null");
        $sql .= ", statut = ".(int) $this->statut;
        $sql .= ", date_modification = '".$this->db->idate(dol_now())."'";
        $sql .= ", fk_user_modif = ".(int) $user->id;
        $sql .= " WHERE rowid = ".(int) $this->id;

        $resql = $this->db->query($sql);
        if ($resql) {
            if (!$notrigger) {
                $result = $this->call_trigger('SUPPLIERRETURN_MODIFY', $user);
                if ($result < 0) {
                    $error++;
                }
            }

            if (!$error) {
                $this->db->commit();
                return 1;
            } else {
                $this->db->rollback();
                return -1;
            }
        } else {
            $this->error = $this->db->error();
            $this->db->rollback();
            return -1;
        }
    }


    public function delete($user, $notrigger = false)
    {
        $error = 0;

        $this->db->begin();

        if (!$notrigger) {
            $result = $this->call_trigger('SUPPLIERRETURN_DELETE', $user);
            if ($result < 0) {
                $error++;
            }
        }

        if (!$error) {
            $sql = "DELETE FROM ".MAIN_DB_PREFIX."supplierreturn";
            $sql .= " WHERE rowid = ".(int) $this->id;

            $resql = $this->db->query($sql);
            if (!$resql) {
                $error++;
                $this->error = $this->db->error();
            }
        }

        if (!$error) {
            $this->db->commit();
            return 1;
        } else {
            $this->db->rollback();
            return -1;
        }
    }

    public function getNextNumRef()
    {
        global $conf;
        
        dol_syslog("SupplierReturn::getNextNumRef() - Starting simplified reference generation", LOG_INFO);
        
        // Génération directe sans modules de numérotation pour éviter les problèmes de transaction
        $date = dol_now();
        $yymm = dol_print_date($date, '%y%m');
        
        $sql = "SELECT MAX(CAST(SUBSTRING(ref FROM 8) AS SIGNED)) as max";
        $sql .= " FROM ".MAIN_DB_PREFIX."supplierreturn";
        $sql .= " WHERE ref LIKE 'RF".$yymm."-%'";
        $sql .= " AND ref NOT LIKE '(PROV%)'";
        $sql .= " AND entity = ".$conf->entity;
        
        dol_syslog("SupplierReturn::getNextNumRef() - SQL: ".$sql, LOG_INFO);
        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            $max = $obj ? $obj->max : 0;
            $max = ($max) ? intval($max) : 0;
            $newref = 'RF'.$yymm.'-'.sprintf("%04d", $max + 1);
            dol_syslog("SupplierReturn::getNextNumRef() - Generated ref: ".$newref, LOG_INFO);
            return $newref;
        } else {
            dol_syslog("SupplierReturn::getNextNumRef() - SQL Error: ".$this->db->lasterror(), LOG_ERR);
        }
        
        // En cas d'erreur, utiliser une référence par défaut
        $defaultref = 'RF'.$yymm.'-0001';
        dol_syslog("SupplierReturn::getNextNumRef() - Using default ref: ".$defaultref, LOG_INFO);
        return $defaultref;
    }

    public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1)
    {
        global $conf, $langs, $hookmanager;

        if (!empty($conf->dol_no_mouse_hover)) $notooltip = 1;

        $result = '';

        $label = img_picto('', $this->picto).' <u>'.$langs->trans("SupplierReturn").'</u>';
        $label .= '<br>';
        $label .= '<b>'.$langs->trans('Ref').':</b> '.$this->ref;

        $url = dol_buildpath('/custom/supplierreturn/card.php', 1).'?id='.$this->id;

        if ($option != 'nolink') {
            $add_save_lastsearch_values = ($save_lastsearch_value == 1 ? 1 : 0);
            if ($save_lastsearch_value == -1 && preg_match('/list\.php/', $_SERVER["PHP_SELF"])) $add_save_lastsearch_values = 1;
            if ($add_save_lastsearch_values) $url .= '&save_lastsearch_values=1';
        }

        $linkclose = '';
        if (empty($notooltip)) {
            if (!empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER)) {
                $label = $langs->trans("ShowSupplierReturn");
                $linkclose .= ' alt="'.$label.'"';
            }
            $linkclose .= ' title="'.dol_escape_htmltag($label, 1).'"';
            $linkclose .= ' class="classfortooltip'.($morecss ? ' '.$morecss : '').'"';
        }

        if ($option == 'nolink') $linkstart = $linkend = '';
        else {
            $linkstart = '<a href="'.$url.'"';
            $linkstart .= $linkclose.'>';
            $linkend = '</a>';
        }

        $result .= $linkstart;

        if (empty($this->showphoto_on_popup)) {
            if ($withpicto) $result .= img_object(($notooltip ? '' : $label), ($this->picto ? $this->picto : 'generic'), ($notooltip ? (($withpicto != 2) ? 'class="paddingright"' : '') : 'class="'.(($withpicto != 2) ? 'paddingright ' : '').'classfortooltip"'), 0, 0, $notooltip ? 0 : 1);
        }

        if ($withpicto != 2) $result .= $this->ref;

        $result .= $linkend;

        global $action;
        $hookmanager->initHooks(array('supplierreturndao'));
        $parameters = array('id'=>$this->id, 'getnomurl'=>$result);
        $reshook = $hookmanager->executeHooks('getNomUrl', $parameters, $this, $action);
        if ($reshook > 0) $result = $hookmanager->resPrint;
        else $result .= $hookmanager->resPrint;

        return $result;
    }

    public function getLibStatut($mode = 0)
    {
        return $this->LibStatut($this->statut, $mode);
    }

    public function LibStatut($status, $mode = 0)
    {
        global $langs;

        // Define proper status colors following Dolibarr standards
        if ($status == self::STATUS_DRAFT) {
            $statusType = 'status0';  // Gray for draft
        } elseif ($status == self::STATUS_VALIDATED) {
            $statusType = 'status4';  // Green for validated/approved
        } elseif ($status == self::STATUS_RETURNED_TO_VENDOR || $status == self::STATUS_REIMBURSED_FROM_VENDOR || $status == self::STATUS_PRODUCT_CHANGED_FROM_VENDOR) {
            $statusType = 'status5'; // Orange/Yellow for processed
        } elseif ($status == self::STATUS_CLOSED) {
            $statusType = 'status6';  // Red for closed/cancelled
        } else {
            $statusType = 'status0';  // Default to gray
        }

        $statusLabels = array(
            self::STATUS_DRAFT => $langs->trans('Draft'),
            self::STATUS_VALIDATED => $langs->trans('Validated'),
            self::STATUS_CLOSED => $langs->trans('Closed'),
            self::STATUS_RETURNED_TO_VENDOR => $langs->trans('ReturnedToVendor'),
            self::STATUS_REIMBURSED_FROM_VENDOR => $langs->trans('ReimbursedFromVendor'),
            self::STATUS_PRODUCT_CHANGED_FROM_VENDOR => $langs->trans('ProductChangedFromVendor')
        );

        $statusLabel = isset($statusLabels[$status]) ? $statusLabels[$status] : 'Unknown';

        if ($mode == 0) {
            return $statusLabel;
        } else {
            return dolGetStatus($statusLabel, '', '', $statusType, $mode);
        }
    }

    /**
     * Set status (override to use correct field name)
     *
     * @param int $status New status
     * @param int $elementId Optional element ID
     * @param string $elementType Optional element type 
     * @param string $trigkey Optional trigger key
     * @param string $fieldstatus Field name for status (default: statut)
     * @return int <0 if KO, >0 if OK
     */
    public function setStatut($status, $elementId = null, $elementType = '', $trigkey = '', $fieldstatus = 'statut')
    {
        global $user;
        
        // Use appropriate method based on status
        if ($status == self::STATUS_VALIDATED && $this->statut == self::STATUS_DRAFT) {
            return $this->validate($user);
        } elseif ($status == self::STATUS_CLOSED && $this->statut == self::STATUS_VALIDATED) {
            return $this->process($user);
        } elseif ($status == self::STATUS_DRAFT) {
            return $this->cancel($user);
        } else {
            // Direct status change if needed
            $sql = "UPDATE ".MAIN_DB_PREFIX."supplierreturn SET statut = ".(int) $status." WHERE rowid = ".(int) $this->id;
            $result = $this->db->query($sql);
            if ($result) {
                $this->statut = $status;
                return 1;
            } else {
                $this->error = $this->db->lasterror();
                return -1;
            }
        }
    }

    /**
     * Add a line to supplier return
     *
     * @param int $fk_product Product ID
     * @param float $qty Quantity
     * @param float $subprice Unit price HT
     * @param string $description Description
     * @param int $fk_entrepot Warehouse ID
     * @param string $batch Batch/Serial number
     * @param int $fk_reception_line Reception line ID
     * @param User $user User object
     * @return int <0 if KO, line ID if OK
     */
    public function addLine($fk_product, $qty, $subprice, $description = '', $fk_entrepot = 0, $batch = '', $fk_reception_line = 0, $user = null, $fk_product_batch = 0)
    {
        if (empty($user)) {
            global $user;
        }

        if ($this->statut != self::STATUS_DRAFT) {
            $this->error = 'Cannot add line to a non-draft supplier return';
            return -1;
        }
        
        // Validation: il faut soit un produit, soit une description
        if (empty($fk_product) && empty($description)) {
            // Utiliser une description par défaut si rien n'est fourni
            $description = 'Ligne de retour fournisseur';
            dol_syslog("SupplierReturn::addLine() - Using default description", LOG_WARNING);
        }

        dol_include_once('/custom/supplierreturn/class/supplierreturnline.class.php');

        $line = new SupplierReturnLine($this->db);
        $line->fk_supplierreturn = $this->id;
        $line->fk_product = $fk_product;
        $line->qty = $qty;
        $line->subprice = $subprice;
        $line->description = $description;
        
        // Validate warehouse ID if provided
        if ($fk_entrepot > 0) {
            require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
            $warehouse = new Entrepot($this->db);
            $result = $warehouse->fetch($fk_entrepot);
            if ($result <= 0) {
                dol_syslog("SupplierReturn::addLine() Invalid warehouse ID $fk_entrepot, setting to null", LOG_WARNING);
                $fk_entrepot = 0; // Reset to 0 which will be stored as null
            }
        }
        
        $line->fk_entrepot = $fk_entrepot;
        $line->batch = $batch;
        $line->fk_reception_line = $fk_reception_line;
        $line->fk_product_batch = $fk_product_batch;
        
        // Try to get original pricing data for better credit note generation
        if ($fk_product > 0) {
            $original_pricing = $this->getOriginalProductPricing($fk_product);
            if ($original_pricing) {
                $line->original_subprice = $original_pricing['subprice'];
                $line->original_tva_tx = $original_pricing['tva_tx'];
                $line->original_localtax1_tx = $original_pricing['localtax1_tx'];
                $line->original_localtax2_tx = $original_pricing['localtax2_tx'];
            }
        }
        
        // Calcul du total
        $line->total_ht = $qty * $subprice;
        $line->total_ttc = $line->total_ht; // Pas de TVA sur les retours pour simplifier
        
        // Calcul du rang
        $sql = "SELECT MAX(rang) as maxrang FROM ".MAIN_DB_PREFIX."supplierreturndet WHERE fk_supplierreturn = ".(int) $this->id;
        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            $line->rang = $obj->maxrang + 1;
        } else {
            $line->rang = 1;
        }

        $result = $line->insert($user);
        if ($result > 0) {
            $this->updateTotal();
            // Refresh the object's lines array to include the new line
            $this->lines = $this->getLines();
            return $line->id;
        } else {
            $this->error = "addLine failed: ".$line->error;
            dol_syslog("SupplierReturn::addLine() ".$this->error, LOG_ERR);
            return -1;
        }
    }

    /**
     * Update a line
     *
     * @param int $lineid Line ID
     * @param float $qty Quantity
     * @param float $subprice Unit price HT
     * @param string $description Description
     * @param int $fk_entrepot Warehouse ID
     * @param string $batch Batch/Serial number
     * @param User $user User object
     * @return int <0 if KO, >0 if OK
     */
    public function updateLine($lineid, $qty, $subprice, $description = '', $fk_entrepot = 0, $batch = '', $user = null)
    {
        global $globaluser;
        if (empty($user)) $user = $globaluser;

        if ($this->statut != self::STATUS_DRAFT) {
            $this->error = 'Cannot update line in a non-draft supplier return';
            return -1;
        }

        dol_include_once('/custom/supplierreturn/class/supplierreturnline.class.php');

        $line = new SupplierReturnLine($this->db);
        $result = $line->fetch($lineid);
        if ($result > 0) {
            $line->qty = $qty;
            $line->subprice = $subprice;
            $line->description = $description;
            $line->fk_entrepot = $fk_entrepot;
            $line->batch = $batch;
            $line->total_ht = $qty * $subprice;
            $line->total_ttc = $line->total_ht;

            $result = $line->update($user);
            if ($result > 0) {
                $this->updateTotal();
                // Refresh the object's lines array to reflect the changes
                $this->lines = $this->getLines();
                return 1;
            } else {
                $this->error = $line->error;
                return -1;
            }
        }
        return -1;
    }

    /**
     * Delete a line
     *
     * @param int $lineid Line ID
     * @param User $user User object
     * @return int <0 if KO, >0 if OK
     */
    public function deleteLine($lineid, $user = null)
    {
        global $globaluser;
        if (empty($user)) $user = $globaluser;

        if ($this->statut != self::STATUS_DRAFT) {
            $this->error = 'Cannot delete line in a non-draft supplier return';
            return -1;
        }

        dol_include_once('/custom/supplierreturn/class/supplierreturnline.class.php');

        $line = new SupplierReturnLine($this->db);
        $result = $line->fetch($lineid);
        if ($result > 0) {
            $result = $line->delete($user);
            if ($result > 0) {
                $this->updateTotal();
                // Refresh the object's lines array to reflect the deletion
                $this->lines = $this->getLines();
                return 1;
            } else {
                $this->error = $line->error;
                return -1;
            }
        }
        return -1;
    }

    /**
     * Get lines of supplier return
     *
     * @return array Array of SupplierReturnLine objects
     */
    public function getLines()
    {
        $lines = array();
        
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."supplierreturndet";
        $sql .= " WHERE fk_supplierreturn = ".(int) $this->id;
        $sql .= " ORDER BY rang ASC";

        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                dol_include_once('/custom/supplierreturn/class/supplierreturnline.class.php');
                $line = new SupplierReturnLine($this->db);
                if ($line->fetch($obj->rowid) > 0) {
                    $lines[] = $line;
                }
            }
        }
        
        return $lines;
    }

    /**
     * Fetch lines (alias for getLines for compatibility)
     *
     * @return int Number of lines loaded, <0 if KO
     */
    public function fetch_lines()
    {
        $this->lines = $this->getLines();
        return count($this->lines);
    }

    /**
     * Update total amounts
     *
     * @return int <0 if KO, >0 if OK
     */
    public function updateTotal()
    {
        $sql = "SELECT SUM(total_ht) as total_ht, SUM(total_ttc) as total_ttc";
        $sql .= " FROM ".MAIN_DB_PREFIX."supplierreturndet";
        $sql .= " WHERE fk_supplierreturn = ".(int) $this->id;

        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            $this->total_ht = $obj->total_ht ? $obj->total_ht : 0;
            $this->total_ttc = $obj->total_ttc ? $obj->total_ttc : 0;

            $sql = "UPDATE ".MAIN_DB_PREFIX."supplierreturn SET";
            $sql .= " total_ht = ".(float) $this->total_ht;
            $sql .= ", total_ttc = ".(float) $this->total_ttc;
            $sql .= " WHERE rowid = ".(int) $this->id;

            $resql = $this->db->query($sql);
            if ($resql) {
                return 1;
            }
        }
        return -1;
    }

    /**
     * Validate supplier return
     *
     * @param User $user User object
     * @param int $notrigger 1=Does not execute triggers, 0= execute triggers
     * @return int <0 if KO, >0 if OK
     */
    public function validate($user, $notrigger = 0)
    {
        global $conf, $langs;

        dol_syslog("SupplierReturn::validate() - Starting validation for ID ".$this->id, LOG_INFO);
        
        $error = 0;

        if ($this->statut != self::STATUS_DRAFT) {
            $this->error = 'Supplier return must be in draft status to be validated';
            dol_syslog("SupplierReturn::validate() - Error: not in draft status", LOG_ERR);
            return -1;
        }

        // Check if there are lines
        $lines = $this->getLines();
        if (empty($lines)) {
            $this->error = 'Cannot validate a supplier return without lines';
            dol_syslog("SupplierReturn::validate() - Error: no lines", LOG_ERR);
            return -1;
        }

        dol_syslog("SupplierReturn::validate() - Starting transaction", LOG_INFO);
        // Start transaction
        $this->db->begin();

        // Generate definitive reference using numbering module
        if (preg_match('/^\(PROV\d*\)$/', $this->ref)) {
            dol_syslog("SupplierReturn::validate() - Generating new reference", LOG_INFO);
            
            // Use numbering module system like commande module
            $numbering_module = getDolGlobalString('SUPPLIERRETURN_ADDON', 'mod_supplierreturn_standard');
            
            // Load the numbering module
            $dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);
            $found = false;
            foreach ($dirmodels as $reldir) {
                $file = dol_buildpath($reldir."core/modules/supplierreturn/".$numbering_module.".php", 0);
                if (file_exists($file)) {
                    require_once $file;
                    $found = true;
                    break;
                }
            }
            
            if ($found) {
                $obj = new $numbering_module($this->db);
                if ($obj) {
                    $newref = $obj->getNextValue($mysoc, $this);
                    if ($newref && $newref != -1) {
                        $this->ref = $newref;
                        dol_syslog("SupplierReturn::validate() - New reference from module: ".$this->ref, LOG_INFO);
                    } else {
                        $this->error = 'Failed to generate reference number from module '.$numbering_module;
                        dol_syslog("SupplierReturn::validate() - Error: ".$this->error, LOG_ERR);
                        $this->db->rollback();
                        return -1;
                    }
                } else {
                    $this->error = 'Cannot instantiate numbering module '.$numbering_module;
                    dol_syslog("SupplierReturn::validate() - Error: ".$this->error, LOG_ERR);
                    $this->db->rollback();
                    return -1;
                }
            } else {
                // Fallback to old method if module not found
                dol_syslog("SupplierReturn::validate() - Module not found, using fallback", LOG_WARNING);
                $newref = $this->getNextNumRef();
                if ($newref) {
                    $this->ref = $newref;
                    dol_syslog("SupplierReturn::validate() - New reference (fallback): ".$this->ref, LOG_INFO);
                } else {
                    $this->error = 'Failed to generate reference number';
                    dol_syslog("SupplierReturn::validate() - Error: failed to generate reference", LOG_ERR);
                    $this->db->rollback();
                    return -1;
                }
            }
        }

        dol_syslog("SupplierReturn::validate() - Preparing update query", LOG_INFO);
        // Process each line (update stock)
        foreach ($lines as $line) {
            if ($line->fk_product > 0 && $line->qty > 0) {
                $result = $this->updateStock($line, $user);
                if ($result < 0) {
                    $error++;
                    break;
                }
            }
        }

        if ($error) {
            $this->db->rollback();
            return -1;
        }

        // Update status and reference
        $sql = "UPDATE ".MAIN_DB_PREFIX."supplierreturn SET";
        $sql .= " statut = ".self::STATUS_VALIDATED;
        $sql .= ", ref = '".$this->db->escape($this->ref)."'";
        // Only update date_valid and fk_user_valid if columns exist
        $extended_columns_exist = $this->checkExtendedColumns();
        dol_syslog("SupplierReturn::validate() - Extended columns exist: ".($extended_columns_exist ? 'yes' : 'no'), LOG_INFO);
        if ($extended_columns_exist) {
            $sql .= ", date_valid = '".$this->db->idate(dol_now())."'";
            $sql .= ", fk_user_valid = ".(int) $user->id;
        }
        $sql .= " WHERE rowid = ".(int) $this->id;
        dol_syslog("SupplierReturn::validate() - SQL: ".$sql, LOG_INFO);

        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->error();
            dol_syslog("SupplierReturn::validate() - SQL Error: ".$this->error, LOG_ERR);
            $this->db->rollback();
            return -1;
        }

        dol_syslog("SupplierReturn::validate() - SQL update successful", LOG_INFO);
        $this->statut = self::STATUS_VALIDATED;
        $this->date_valid = dol_now();
        $this->fk_user_valid = $user->id;

        // Call triggers if not disabled
        if (!$notrigger) {
            dol_syslog("SupplierReturn::validate() - Calling triggers", LOG_INFO);
            $result = $this->call_trigger('SUPPLIERRETURN_VALIDATE', $user);
            if ($result < 0) {
                $error++;
                $this->error = 'Trigger failed: '.($this->error ? $this->error : 'Unknown trigger error');
                dol_syslog("SupplierReturn::validate() - Trigger error: ".$this->error, LOG_ERR);
            } else {
                dol_syslog("SupplierReturn::validate() - Triggers executed successfully", LOG_INFO);
            }
        }

        // Commit or rollback based on errors
        if (!$error) {
            dol_syslog("SupplierReturn::validate() - Committing transaction", LOG_INFO);
            $this->db->commit();
            dol_syslog("SupplierReturn::validate() - Validation completed successfully", LOG_INFO);
            return 1;
        } else {
            dol_syslog("SupplierReturn::validate() - Rolling back transaction due to errors", LOG_ERR);
            $this->db->rollback();
            return -1;
        }
    }

    /**
     * Alias for validate method (used by Dolibarr standard actions)
     *
     * @param User $user User object
     * @param int $notrigger 1=Does not execute triggers, 0= execute triggers
     * @return int <0 if KO, >0 if OK
     */
    public function setValidated($user, $notrigger = 0)
    {
        return $this->validate($user, $notrigger);
    }

    /**
     * Return if object can be deleted by user
     *
     * @param User $user User object
     * @return int <0 if KO, >0 if OK
     */
    public function isErasable($user = null)
    {
        if ($this->statut == self::STATUS_DRAFT) {
            return 1;
        }
        return 0;
    }

    /**
     * Process supplier return (update stock)
     *
     * @param User $user User object
     * @param int $notrigger 1=Does not execute triggers, 0= execute triggers
     * @return int <0 if KO, >0 if OK
     */
    public function process($user, $notrigger = 0)
    {
        global $conf, $langs;

        $error = 0;

        if ($this->statut != self::STATUS_VALIDATED) {
            $this->error = 'Supplier return must be validated before processing';
            return -1;
        }

        $this->db->begin();

        if (!$error) {
            // Update status
            $sql = "UPDATE ".MAIN_DB_PREFIX."supplierreturn SET";
            $sql .= " statut = ".self::STATUS_CLOSED;
            $sql .= ", date_process = '".$this->db->idate(dol_now())."'";
            $sql .= " WHERE rowid = ".(int) $this->id;

            $resql = $this->db->query($sql);
            if ($resql) {
                $this->statut = self::STATUS_CLOSED;
                $this->date_process = dol_now();

                if (!$notrigger) {
                    $result = $this->call_trigger('SUPPLIERRETURN_PROCESS', $user);
                    if ($result < 0) {
                        $error++;
                    }
                }

                if (!$error) {
                    $this->db->commit();
                    return 1;
                } else {
                    $this->db->rollback();
                    return -1;
                }
            } else {
                $this->error = $this->db->error();
                $this->db->rollback();
                return -1;
            }
        } else {
            $this->db->rollback();
            return -1;
        }
    }

    /**
     * Cancel supplier return
     *
     * @param User $user User object
     * @param int $notrigger 1=Does not execute triggers, 0= execute triggers
     * @return int <0 if KO, >0 if OK
     */
    public function cancel($user, $notrigger = 0)
    {
        global $conf, $langs;

        // Allow cancellation from any status (draft, validated, or closed)
        $this->db->begin();

        // If the return was validated or processed, we need to reverse stock movements
        if ($this->statut == self::STATUS_VALIDATED || $this->statut == self::STATUS_CLOSED) {
            $lines = $this->getLines();
            foreach ($lines as $line) {
                if ($line->fk_product > 0 && $line->qty > 0) {
                    // Reverse stock movement
                    $result = $this->reverseStock($line, $user);
                    if ($result < 0) {
                        $this->db->rollback();
                        return -1;
                    }
                }
            }
        }

        // Update status to draft
        $sql = "UPDATE ".MAIN_DB_PREFIX."supplierreturn SET";
        $sql .= " statut = ".self::STATUS_DRAFT;
        $sql .= ", date_valid = NULL";
        $sql .= ", date_process = NULL";
        $sql .= ", fk_user_valid = NULL";
        $sql .= " WHERE rowid = ".(int) $this->id;

        $resql = $this->db->query($sql);
        if ($resql) {
            $this->statut = self::STATUS_DRAFT;
            $this->date_valid = null;
            $this->date_process = null;
            $this->fk_user_valid = null;

            if (!$notrigger) {
                $result = $this->call_trigger('SUPPLIERRETURN_CANCEL', $user);
                if ($result < 0) {
                    $this->db->rollback();
                    return -1;
                }
            }

            $this->db->commit();
            return 1;
        } else {
            $this->error = $this->db->error();
            $this->db->rollback();
            return -1;
        }
    }




/**
     * Update stock when processing a supplier return line
     *
     * @param SupplierReturnLine $line Line to process
     * @param User $user User object
     * @return int <0 if KO, >0 if OK
     */
    public function updateStock($line, $user)
    {
        global $conf, $langs;

        if (!$conf->stock->enabled) {
            return 1;
        }

        require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
        require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

        if ($line->fk_product <= 0 || $line->qty <= 0) {
            return 1;
        }

        $product = new Product($this->db);
        if ($product->fetch($line->fk_product) <= 0) {
            $this->error = 'Product not found';
            return -1;
        }

        if ($product->type != Product::TYPE_PRODUCT) {
            return 1;
        }

        $fk_product_batch_to_use = $line->fk_product_batch;
        if (empty($fk_product_batch_to_use) && !empty($line->batch)) {
            // --- CORRECTED SQL QUERY START ---
            $sql = "SELECT pb.rowid";
            $sql .= " FROM ".MAIN_DB_PREFIX."product_batch as pb";
            $sql .= " INNER JOIN ".MAIN_DB_PREFIX."product_stock as ps ON ps.rowid = pb.fk_product_stock";
            $sql .= " WHERE ps.fk_product = " . (int) $line->fk_product;
            $sql .= " AND ps.fk_entrepot = " . (int) $line->fk_entrepot;
            $sql .= " AND pb.batch = '" . $this->db->escape($line->batch) . "'";
            // The line "AND pb.entity = ..." has been removed as it was incorrect.
            $sql .= " LIMIT 1";
            // --- CORRECTED SQL QUERY END ---

            dol_syslog("SupplierReturn FINAL DEBUG: Executing SQL: " . preg_replace('/\s+/', ' ', $sql), LOG_INFO);
            $resql = $this->db->query($sql);

            if ($resql && $this->db->num_rows($resql) > 0) {
                $obj = $this->db->fetch_object($resql);
                $fk_product_batch_to_use = $obj->rowid;
                dol_syslog("SupplierReturn FINAL DEBUG: Found Batch ID: " . $fk_product_batch_to_use, LOG_INFO);
            } else {
                dol_syslog("SupplierReturn FINAL DEBUG: Batch ID NOT FOUND.", LOG_WARNING);
            }
        }

        $eatby_date = null;
        $sellby_date = null;
        if (!empty($line->batch)) {
            // The product_batch table does not contain eatby/sellby, product_lot does.
            // Let's get the dates from the correct table using the batch number.
            require_once DOL_DOCUMENT_ROOT.'/product/stock/class/productlot.class.php';
            $productlot = new Productlot($this->db);
            // fetch by product and batch number
            if ($productlot->fetch(0, $line->fk_product, $line->batch) > 0) {
                if ($this->db->jdate($productlot->eatby) > 0) $eatby_date = $productlot->eatby;
                if ($this->db->jdate($productlot->sellby) > 0) $sellby_date = $productlot->sellby;
            }
        }

        $mouvementstock = new MouvementStock($this->db);
        $mouvementstock->origin_type = 'supplierreturn';
        $mouvementstock->origin_id = $this->id;

        // The price is 0 because a return does not impact the PMP/AWP.
        // The label should be translated for clarity.
        $label = $langs->trans("SupplierReturn").' '.$this->ref;

        // Use the public API 'livraison' for stock decrease.
        // It correctly handles all parameters, including the id_product_batch for serialized products.
        $result = $mouvementstock->livraison(
            $user,
            $line->fk_product,
            $line->fk_entrepot,
            $line->qty,
            $line->subprice,
            $label,
            '', // datem
            $eatby_date,
            $sellby_date,
            $line->batch,
            $fk_product_batch_to_use
        );

        if ($result < 0) {
            $this->error = $mouvementstock->error;
            return -1;
        }
        return 1;
    }

    /**
     * Reverse stock movement when cancelling a supplier return
     *
     * @param SupplierReturnLine $line Line to reverse
     * @param User $user User object
     * @return int <0 if KO, >0 if OK
     */
    public function reverseStock($line, $user)
    {
        global $conf, $langs;

        if (!$conf->stock->enabled) return 1;

        require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
        require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

        if ($line->fk_product <= 0 || $line->qty <= 0) return 1;

        $product = new Product($this->db);
        if ($product->fetch($line->fk_product) <= 0) {
            $this->error = 'Product not found';
            return -1;
        }

        if ($product->type != Product::TYPE_PRODUCT) return 1;

        $fk_product_batch_to_use = $line->fk_product_batch;
        if (empty($fk_product_batch_to_use) && !empty($line->batch)) {
            // --- CORRECTED SQL QUERY START ---
            $sql = "SELECT pb.rowid";
            $sql .= " FROM ".MAIN_DB_PREFIX."product_batch as pb";
            $sql .= " INNER JOIN ".MAIN_DB_PREFIX."product_stock as ps ON ps.rowid = pb.fk_product_stock";
            $sql .= " WHERE ps.fk_product = " . (int) $line->fk_product;
            $sql .= " AND ps.fk_entrepot = " . (int) $line->fk_entrepot;
            $sql .= " AND pb.batch = '" . $this->db->escape($line->batch) . "'";
            // The line "AND pb.entity = ..." has been removed as it was incorrect.
            $sql .= " LIMIT 1";
            // --- CORRECTED SQL QUERY END ---

            $resql = $this->db->query($sql);
            if ($resql && $this->db->num_rows($resql) > 0) {
                $obj = $this->db->fetch_object($resql);
                $fk_product_batch_to_use = $obj->rowid;
            }
        }

        $eatby_date = null;
        $sellby_date = null;
        if (!empty($line->batch)) {
            // The product_batch table does not contain eatby/sellby, product_lot does.
            // Let's get the dates from the correct table using the batch number.
            require_once DOL_DOCUMENT_ROOT.'/product/stock/class/productlot.class.php';
            $productlot = new Productlot($this->db);
            // fetch by product and batch number
            if ($productlot->fetch(0, $line->fk_product, $line->batch) > 0) {
                if ($this->db->jdate($productlot->eatby) > 0) $eatby_date = $productlot->eatby;
                if ($this->db->jdate($productlot->sellby) > 0) $sellby_date = $productlot->sellby;
            }
        }

        $mouvementstock = new MouvementStock($this->db);
        $mouvementstock->origin_type = 'supplierreturn';
        $mouvementstock->origin_id = $this->id;

        // The price is 0 because a return does not impact the PMP/AWP.
        // The label should be translated for clarity.
        $label = $langs->trans("CancellationSupplierReturn").' '.$this->ref;

        // Use the public API 'reception' for stock increase.
        // It correctly handles all parameters, including the id_product_batch for serialized products.
        $result = $mouvementstock->reception(
            $user,
            $line->fk_product,
            $line->fk_entrepot,
            $line->qty,
            $line->subprice,
            $label,
            $eatby_date,
            $sellby_date,
            $line->batch,
            '', // datem
            $fk_product_batch_to_use
        );

        if ($result < 0) {
            $this->error = $mouvementstock->error;
            return -1;
        }
        return 1;
    }

	
	
    /**
     * Get stock movements for this supplier return
     *
     * @return array Array of stock movements
     */
    public function getStockMovements()
    {
        $movements = array();
        
        if (!isset($conf->stock) || !$conf->stock->enabled) {
            return $movements;
        }

        require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';

        $sql = "SELECT m.rowid";
        $sql .= " FROM ".MAIN_DB_PREFIX."stock_mouvement as m";
        $sql .= " WHERE m.fk_origin = ".(int) $this->id;
        $sql .= " AND m.origintype = 'supplierreturn'";
        $sql .= " ORDER BY m.datem DESC";

        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $movement = new MouvementStock($this->db);
                if ($movement->fetch($obj->rowid) > 0) {
                    $movements[] = $movement;
                }
            }
        }

        return $movements;
    }

    /**
     * Check if there is enough stock for a return
     *
     * @param int $fk_product Product ID
     * @param int $fk_entrepot Warehouse ID
     * @param float $qty Quantity to return
     * @param string $batch Batch number
     * @return bool True if enough stock, false otherwise
     */
    public function checkStockAvailability($fk_product, $fk_entrepot, $qty, $batch = '')
    {
        global $conf;

        if (!$conf->stock->enabled) {
            return true; // Stock module disabled, always OK
        }

        require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

        $product = new Product($this->db);
        $result = $product->fetch($fk_product);
        if ($result <= 0) {
            return false;
        }

        // Check if product is a stock product
        if ($product->type != Product::TYPE_PRODUCT) {
            return true; // Not a stock product, always OK
        }

        // Get current stock
        $stock_qty = $product->stock_reel;
        
        if ($fk_entrepot > 0) {
            // Get stock for specific warehouse
            $sql = "SELECT SUM(reel) as stock_qty";
            $sql .= " FROM ".MAIN_DB_PREFIX."product_stock";
            $sql .= " WHERE fk_product = ".(int) $fk_product;
            $sql .= " AND fk_entrepot = ".(int) $fk_entrepot;
            
            if ($batch) {
                $sql .= " AND batch = '".$this->db->escape($batch)."'";
            }

            $resql = $this->db->query($sql);
            if ($resql) {
                $obj = $this->db->fetch_object($resql);
                $stock_qty = $obj->stock_qty ? $obj->stock_qty : 0;
            }
        }

        return ($stock_qty >= $qty);
    }

    /**
     * Get reception lines available for return
     *
     * @param int $reception_id Reception ID
     * @return array Array of reception lines with return info
     */
   
   /**
     * Get reception lines available for return
     *
     * @param int $reception_id Reception ID
     * @return array Array of reception lines with return info
     */
   /**
     * Get reception lines available for return
     *
     * @param int $reception_id Reception ID
     * @return array Array of reception lines with return info
     */
    public function getReceptionLines($reception_id)
    {
        require_once DOL_DOCUMENT_ROOT.'/reception/class/reception.class.php';
        require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
        
        $lines = array();
        
        // Query receptiondet_batch directly - columns based on actual table structure
        $sql = "SELECT rdb.rowid, rdb.fk_product, rdb.qty, rdb.batch, rdb.fk_entrepot,";
        $sql .= " p.ref as product_ref, p.label as product_label";
        $sql .= " FROM ".MAIN_DB_PREFIX."receptiondet_batch as rdb";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = rdb.fk_product";
        $sql .= " WHERE rdb.fk_reception = ".(int) $reception_id;
        $sql .= " ORDER BY rdb.rowid";

        dol_syslog("SupplierReturn::getReceptionLines SQL: ".$sql, LOG_DEBUG);

        $resql = $this->db->query($sql);
        if ($resql) {
            $num = $this->db->num_rows($resql);
            dol_syslog("SupplierReturn::getReceptionLines found ".$num." lines for reception ID ".$reception_id, LOG_INFO);
            
            while ($obj = $this->db->fetch_object($resql)) {
                $line = new stdClass();
                $line->id = $obj->rowid;
                $line->fk_product = $obj->fk_product;
                $line->fk_product_batch = 0; // Not available in this table
                $line->qty_received = $obj->qty;
                $line->batch = $obj->batch;
                $line->fk_entrepot = $obj->fk_entrepot;
                $line->product_ref = $obj->product_ref;
                $line->product_label = $obj->product_label;

                // Get pricing from product or supplier order
                if ($obj->fk_product > 0) {
                    $product = new Product($this->db);
                    if ($product->fetch($obj->fk_product) > 0) {
                        $line->subprice = $product->pmp; // Weighted average price
                        if (empty($line->subprice)) $line->subprice = $product->cost_price;
                        $line->tva_tx = $product->tva_tx;
                        $line->description = $product->description;
                    } else {
                        $line->subprice = 0;
                        $line->tva_tx = 0;
                        $line->description = '';
                    }
                } else {
                    $line->subprice = 0;
                    $line->tva_tx = 0;
                    $line->description = '';
                }

                // Get warehouse label
                if ($obj->fk_entrepot > 0) {
                    require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
                    $warehouse = new Entrepot($this->db);
                    if ($warehouse->fetch($obj->fk_entrepot) > 0) {
                        $line->warehouse_label = $warehouse->label;
                    } else {
                        $line->warehouse_label = '';
                    }
                } else {
                    $line->warehouse_label = '';
                }
                
                // Calculate available quantity for return
                $line->qty_already_returned = $this->getQtyAlreadyReturned($obj->rowid);
                $line->qty_available_for_return = $line->qty_received - $line->qty_already_returned;
                
                $lines[] = $line;
            }
        } else {
            dol_syslog("SupplierReturn::getReceptionLines SQL Error: ".$this->db->lasterror(), LOG_ERR);
        }
        
        return $lines;
    }
   
   
    /**
     * Get quantity already returned for a reception line
     *
     * @param int $reception_line_id Reception line ID
     * @return float Quantity already returned
     */
    public function getQtyAlreadyReturned($reception_line_id)
    {
        $sql = "SELECT SUM(srd.qty) as qty_returned";
        $sql .= " FROM ".MAIN_DB_PREFIX."supplierreturndet as srd";
        $sql .= " INNER JOIN ".MAIN_DB_PREFIX."supplierreturn as sr ON sr.rowid = srd.fk_supplierreturn";
        $sql .= " WHERE srd.fk_reception_line = ".(int) $reception_line_id;
        $sql .= " AND sr.statut != ".self::STATUS_DRAFT; // Only count validated/processed returns
        
        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            return $obj->qty_returned ? $obj->qty_returned : 0;
        }
        
        return 0;
    }

    /**
     * Create supplier credit note from return with intelligent price recovery
     *
     * @param User $user User object
     * @return int <0 if KO, credit note ID if OK
     */
    public function createCreditNote($user)
    {
        global $conf, $langs, $hookmanager;
        
        if ($this->statut != self::STATUS_CLOSED) {
            $this->error = 'Supplier return must be processed to create credit note';
            return -1;
        }
        
        // Include all necessary classes for credit note creation
        require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
        require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
        require_once DOL_DOCUMENT_ROOT.'/core/class/discount.class.php';
        require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
        
        // Check if DiscountAbsolute class exists, if not try to include it
        if (!class_exists('DiscountAbsolute')) {
            if (file_exists(DOL_DOCUMENT_ROOT.'/core/class/discountabsolute.class.php')) {
                require_once DOL_DOCUMENT_ROOT.'/core/class/discountabsolute.class.php';
            }
        }
        
        // Initialize hooks for credit note creation
        if (!is_object($hookmanager)) {
            include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
            $hookmanager = new HookManager($this->db);
        }
        $hookmanager->initHooks(array('supplierreturncreditnote'));
        
        $creditnote = new FactureFournisseur($this->db);
        $creditnote->socid = $this->fk_soc;
        $creditnote->type = FactureFournisseur::TYPE_CREDIT_NOTE;
        $creditnote->ref_supplier = 'RETURN-'.$this->ref;
        $creditnote->note_public = 'Avoir pour retour fournisseur '.$this->ref;
        $creditnote->note_private = $this->note_private;
        $creditnote->date = dol_now();
        
        // Execute before create hook
        $parameters = array('supplierreturn' => $this);
        $action = 'create_creditnote';
        $hookmanager->executeHooks('beforeCreateCreditNote', $parameters, $this, $action);
        
        $result = $creditnote->create($user);
        if ($result > 0) {
            // Get original pricing from supplier invoice or order
            $original_prices = $this->getOriginalPricesFromInvoices();
            
            // Add lines from return with original prices and proper VAT
            $lines = $this->getLines();
            foreach ($lines as $line) {
                // Validate quantities first
                if ($line->qty <= 0) {
                    $this->error = "Invalid quantity for line: " . ($line->description ? $line->description : 'Line ' . $line->rowid);
                    return -1;
                }
                
                // Process all lines (with or without product)
                // SIMPLIFIED LOGIC: Use the prices and VAT as entered in the supplier return
                $subprice = $line->subprice;
                $vat_rate = 0;
                $localtax1_rate = 0;
                $localtax2_rate = 0;
                $fk_product = $line->fk_product > 0 ? $line->fk_product : 0;
                
                // Priority order for VAT rate (simplified):
                // 1. Original stored in line (from when line was created)
                // 2. Product default VAT rate
                // 3. Supplier default VAT rate
                
                if (!empty($line->original_tva_tx) && $line->original_tva_tx >= 0) {
                    // Use stored original VAT rate (most reliable)
                    $vat_rate = $line->original_tva_tx;
                    $localtax1_rate = !empty($line->original_localtax1_tx) ? $line->original_localtax1_tx : 0;
                    $localtax2_rate = !empty($line->original_localtax2_tx) ? $line->original_localtax2_tx : 0;
                    // Always use the subprice from the return line (what user entered)
                    $subprice = $line->subprice;
                } elseif ($fk_product > 0) {
                    // Get VAT from product, but keep the entered price
                    $product_vat = $this->getProductVATRate($fk_product);
                    $vat_rate = $product_vat;
                    $subprice = $line->subprice; // Use price from return line
                } else {
                    // For lines without product, try to get default VAT rate for supplier
                    $vat_rate = $this->getSupplierDefaultVAT($this->fk_soc);
                    $subprice = $line->subprice; // Use price from return line
                }
                
                // Ensure prices are numeric and positive
                $subprice = max(0, (float) $subprice);
                $vat_rate = max(0, (float) $vat_rate);
                $localtax1_rate = max(0, (float) $localtax1_rate);
                $localtax2_rate = max(0, (float) $localtax2_rate);
                
                // Ensure description is set
                $description = !empty($line->description) ? $line->description : 'Ligne de retour fournisseur';
                
                // DEBUG: Log all values before addline
                dol_syslog("DEBUG createCreditNote: Line ID={$line->rowid}, qty=[{$line->qty}] subprice=[{$subprice}] (from line: {$line->subprice}) vat_rate=[{$vat_rate}] original_tva=[".($line->original_tva_tx ?? 'NULL')."] fk_product=[{$fk_product}]", LOG_DEBUG);
                
                // Force quantity to ensure it's not 0
                $actual_qty = $line->qty;
                if (empty($actual_qty) || $actual_qty <= 0) {
                    dol_syslog("ERROR createCreditNote: Invalid quantity detected for line {$line->rowid}: qty=[{$line->qty}], forcing to 1", LOG_ERR);
                    $actual_qty = 1; // Force to 1 if invalid
                }
                
                // Add line to credit note with CORRECT parameter order
                $result_line = $creditnote->addline(
                    $description,                // $desc - Description
                    $subprice,                   // $pu - Unit price HT
                    $vat_rate,                   // $txtva - VAT rate  
                    $localtax1_rate,             // $txlocaltax1 - Local tax 1
                    $localtax2_rate,             // $txlocaltax2 - Local tax 2
                    $actual_qty,                 // $qty - Quantity (corrected)
                    $fk_product,                 // $fk_product - Product ID (0 if no product)
                    0,                           // $remise_percent - Discount percent
                    '', '',                      // $date_start, $date_end
                    0, 0,                        // $fk_code_ventilation, $info_bits
                    'HT'                         // $price_base_type
                );
                
                if ($result_line <= 0) {
                    $this->error = "Error adding line to credit note: ".$creditnote->error." (Line: ".$description.")";
                    dol_syslog("SupplierReturn::createCreditNote() Error adding line: ".$creditnote->error, LOG_ERR);
                    return -1;
                }
            }
            
            // Validate the credit note automatically
            if ($conf->global->SUPPLIERRETURN_AUTO_VALIDATE_CREDITNOTE) {
                $creditnote->validate($user);
            }
            
            // Link credit note to supplier return (basic database link)
            $sql = "UPDATE ".MAIN_DB_PREFIX."supplierreturn SET";
            $sql .= " fk_facture_fourn = ".(int) $result;
            $sql .= " WHERE rowid = ".(int) $this->id;
            $this->db->query($sql);
            
            // Create proper object-to-object linking in element_element table
            $this->add_object_linked('invoice_supplier', $result);
            
            // Link credit note back to supplier return
            if (method_exists($creditnote, 'add_object_linked')) {
                $creditnote->add_object_linked('supplierreturn', $this->id);
            }
            
            // HOOK IMPROVEMENT: Propagate all linked documents from supplier return to credit note
            $this->propagateLinkedDocumentsToCreditNote($creditnote);
            
            // Generate PDF document if auto-generation is enabled
            if (!empty($conf->global->SUPPLIERRETURN_AUTO_GENERATE_PDF)) {
                $outputlangs = $langs;
                if (!empty($conf->global->MAIN_MULTILANGS)) {
                    require_once DOL_DOCUMENT_ROOT.'/core/class/translate.class.php';
                    $outputlangs = new Translate("", $conf);
                    
                    // Load thirdparty to get default language
                    if (empty($creditnote->thirdparty)) {
                        $creditnote->fetch_thirdparty();
                    }
                    
                    if (!empty($creditnote->thirdparty->default_lang)) {
                        $outputlangs->setDefaultLang($creditnote->thirdparty->default_lang);
                    }
                }
                
                // Get default model for supplier invoices
                $model_pdf = empty($creditnote->model_pdf) ? 
                    (!empty($conf->global->INVOICE_SUPPLIER_ADDON_PDF) ? $conf->global->INVOICE_SUPPLIER_ADDON_PDF : 'soleil') : 
                    $creditnote->model_pdf;
                    
                $ret = $creditnote->generateDocument($model_pdf, $outputlangs);
                if ($ret < 0) {
                    dol_syslog("Warning: Failed to generate PDF for credit note ".$creditnote->ref, LOG_WARNING);
                }
            }
            
            // Execute after create hooks
            $parameters = array(
                'creditnote' => $creditnote, 
                'supplierreturn' => $this,
                'creditnote_id' => $result
            );
            $hookmanager->executeHooks('afterCreateCreditNote', $parameters, $this, $action);
            
            // Send notification if enabled
            if (!empty($conf->global->SUPPLIERRETURN_SEND_CREDITNOTE_NOTIFICATION)) {
                $this->sendCreditNoteNotification($creditnote, $user);
            }
            
            // Update the supplier return with credit note reference
            $this->fk_facture_fourn = $result;
            
            return $result;
        } else {
            $this->error = $creditnote->error;
            return -1;
        }
    }
    
    /**
     * Get original prices from supplier invoices related to the reception
     * Priority: 1) Direct supplier invoice, 2) Via supplier order, 3) Default return prices
     *
     * @return array Array of prices indexed by product ID
     */
    private function getOriginalPricesFromInvoices()
    {
        $prices = array();
        
        if (!$this->fk_reception) {
            return $prices;
        }
        
        // Get reception info
        require_once DOL_DOCUMENT_ROOT.'/reception/class/reception.class.php';
        $reception = new Reception($this->db);
        if ($reception->fetch($this->fk_reception) <= 0) {
            return $prices;
        }
        
        // Method 1: Try to find supplier invoice directly linked to reception
        $sql = "SELECT ffd.fk_product, ffd.pu_ht as subprice, ffd.tva_tx, ffd.localtax1_tx, ffd.localtax2_tx";
        $sql .= " FROM ".MAIN_DB_PREFIX."facture_fourn_det as ffd";
        $sql .= " INNER JOIN ".MAIN_DB_PREFIX."facture_fourn as ff ON ff.rowid = ffd.fk_facture_fourn";
        $sql .= " WHERE ff.fk_soc = ".(int) $this->fk_soc;
        $sql .= " AND ff.entity IN (".getEntity('invoice').")";
        $sql .= " AND ffd.fk_product IN (";
        
        // Get product IDs from return lines
        $lines = $this->getLines();
        $product_ids = array();
        foreach ($lines as $line) {
            if ($line->fk_product > 0) {
                $product_ids[] = (int) $line->fk_product;
            }
        }
        
        if (empty($product_ids)) {
            return $prices;
        }
        
        $sql .= implode(',', $product_ids).")";
        $sql .= " ORDER BY ff.datef DESC"; // Most recent invoice first
        
        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                if (!isset($prices[$obj->fk_product])) { // Keep first (most recent)
                    $prices[$obj->fk_product] = array(
                        'subprice' => $obj->subprice,
                        'tva_tx' => $obj->tva_tx,
                        'localtax1_tx' => $obj->localtax1_tx,
                        'localtax2_tx' => $obj->localtax2_tx
                    );
                }
            }
        }
        
        // Method 2: If no direct invoice found, try via supplier order
        if (empty($prices) && $reception->origin == 'order_supplier' && $reception->origin_id > 0) {
            $sql = "SELECT cod.fk_product, cod.subprice, cod.tva_tx, cod.localtax1_tx, cod.localtax2_tx";
            $sql .= " FROM ".MAIN_DB_PREFIX."commande_fournisseurdet as cod";
            $sql .= " WHERE cod.fk_commande = ".(int) $reception->origin_id;
            $sql .= " AND cod.fk_product IN (".implode(',', $product_ids).")";
            
            $resql = $this->db->query($sql);
            if ($resql) {
                while ($obj = $this->db->fetch_object($resql)) {
                    if (!isset($prices[$obj->fk_product])) {
                        $prices[$obj->fk_product] = array(
                            'subprice' => $obj->subprice,
                            'tva_tx' => $obj->tva_tx,
                            'localtax1_tx' => $obj->localtax1_tx,
                            'localtax2_tx' => $obj->localtax2_tx
                        );
                    }
                }
            }
        }
        
        return $prices;
    }
    
    /**
     * Get VAT rate for a product
     *
     * @param int $fk_product Product ID
     * @return float VAT rate
     */
    private function getProductVATRate($fk_product)
    {
        if ($fk_product <= 0) return 0;
        
        require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
        $product = new Product($this->db);
        if ($product->fetch($fk_product) > 0) {
            return $product->tva_tx;
        }
        
        return 0;
    }
    
    /**
     * Get supplier pricing for a specific product
     *
     * @param int $fk_product Product ID
     * @param int $fk_soc Supplier ID
     * @return array|null Supplier pricing data or null if not found
     */
    private function getSupplierProductPricing($fk_product, $fk_soc)
    {
        if ($fk_product <= 0 || $fk_soc <= 0) return null;
        
        // Get the most recent supplier price for this product
        $sql = "SELECT pfp.price, pfp.tva_tx, pfp.localtax1_tx, pfp.localtax2_tx";
        $sql .= " FROM ".MAIN_DB_PREFIX."product_fournisseur_price pfp";
        $sql .= " WHERE pfp.fk_product = ".(int) $fk_product;
        $sql .= " AND pfp.fk_soc = ".(int) $fk_soc;
        $sql .= " AND pfp.entity IN (".getEntity('product').")";
        $sql .= " ORDER BY pfp.date_price DESC, pfp.rowid DESC";
        $sql .= " LIMIT 1";
        
        $resql = $this->db->query($sql);
        if ($resql && $this->db->num_rows($resql) > 0) {
            $obj = $this->db->fetch_object($resql);
            return array(
                'price' => $obj->price,
                'tva_tx' => $obj->tva_tx,
                'localtax1_tx' => $obj->localtax1_tx,
                'localtax2_tx' => $obj->localtax2_tx
            );
        }
        
        return null;
    }
    
    /**
     * Get default VAT rate for a supplier (from their last invoices)
     *
     * @param int $fk_soc Supplier ID
     * @return float Default VAT rate
     */
    private function getSupplierDefaultVAT($fk_soc)
    {
        if ($fk_soc <= 0) return 0;
        
        // Get the most commonly used VAT rate for this supplier
        $sql = "SELECT ffd.tva_tx, COUNT(*) as usage_count";
        $sql .= " FROM ".MAIN_DB_PREFIX."facture_fourn_det ffd";
        $sql .= " INNER JOIN ".MAIN_DB_PREFIX."facture_fourn ff ON ff.rowid = ffd.fk_facture_fourn";
        $sql .= " WHERE ff.fk_soc = ".(int) $fk_soc;
        $sql .= " AND ff.entity IN (".getEntity('invoice').")";
        $sql .= " AND ff.fk_statut >= 1"; // Validated invoices only
        $sql .= " GROUP BY ffd.tva_tx";
        $sql .= " ORDER BY usage_count DESC, ff.datef DESC";
        $sql .= " LIMIT 1";
        
        $resql = $this->db->query($sql);
        if ($resql && $this->db->num_rows($resql) > 0) {
            $obj = $this->db->fetch_object($resql);
            return $obj->tva_tx;
        }
        
        // Fallback: try to get default VAT rate from configuration
        global $conf;
        if (!empty($conf->global->MAIN_VAT_DEFAULT_IF_AUTODETECT_FAILS)) {
            return $conf->global->MAIN_VAT_DEFAULT_IF_AUTODETECT_FAILS;
        }
        
        return 0;
    }
    
    /**
     * Get original pricing data for a product from recent supplier invoices
     *
     * @param int $fk_product Product ID
     * @return array|null Original pricing data or null if not found
     */
    private function getOriginalProductPricing($fk_product)
    {
        if ($fk_product <= 0 || !$this->fk_soc) return null;
        
        // Look for recent pricing in supplier invoices for this product and supplier
        $sql = "SELECT ffd.pu_ht as subprice, ffd.tva_tx, ffd.localtax1_tx, ffd.localtax2_tx";
        $sql .= " FROM ".MAIN_DB_PREFIX."facture_fourn_det as ffd";
        $sql .= " INNER JOIN ".MAIN_DB_PREFIX."facture_fourn as ff ON ff.rowid = ffd.fk_facture_fourn";
        $sql .= " WHERE ff.fk_soc = ".(int) $this->fk_soc;
        $sql .= " AND ffd.fk_product = ".(int) $fk_product;
        $sql .= " AND ff.entity IN (".getEntity('invoice').")";
        $sql .= " AND ff.fk_statut >= 1"; // Validated invoices only
        $sql .= " ORDER BY ff.datef DESC, ff.rowid DESC";
        $sql .= " LIMIT 1";
        
        $resql = $this->db->query($sql);
        if ($resql && $this->db->num_rows($resql) > 0) {
            $obj = $this->db->fetch_object($resql);
            return array(
                'subprice' => $obj->subprice,
                'tva_tx' => $obj->tva_tx,
                'localtax1_tx' => $obj->localtax1_tx,
                'localtax2_tx' => $obj->localtax2_tx
            );
        }
        
        // Fallback: look in supplier orders
        $sql = "SELECT cffd.subprice, cffd.tva_tx, cffd.localtax1_tx, cffd.localtax2_tx";
        $sql .= " FROM ".MAIN_DB_PREFIX."commande_fournisseurdet as cffd";
        $sql .= " INNER JOIN ".MAIN_DB_PREFIX."commande_fournisseur as cff ON cff.rowid = cffd.fk_commande";
        $sql .= " WHERE cff.fk_soc = ".(int) $this->fk_soc;
        $sql .= " AND cffd.fk_product = ".(int) $fk_product;
        $sql .= " AND cff.entity IN (".getEntity('supplier_order').")";
        $sql .= " AND cff.fk_statut >= 3"; // Approved orders only
        $sql .= " ORDER BY cff.date_creation DESC, cff.rowid DESC";
        $sql .= " LIMIT 1";
        
        $resql = $this->db->query($sql);
        if ($resql && $this->db->num_rows($resql) > 0) {
            $obj = $this->db->fetch_object($resql);
            return array(
                'subprice' => $obj->subprice,
                'tva_tx' => $obj->tva_tx,
                'localtax1_tx' => $obj->localtax1_tx,
                'localtax2_tx' => $obj->localtax2_tx
            );
        }
        
        return null;
    }
    
    /**
     * Get unified product display with variations and lot info
     * Format: "REF-PRODUIT - Libellé (Couleur: Rouge, Taille: L) - Lot: LOT-001"
     *
     * @param object $line Line object with product info
     * @return string Formatted product display
     */
    public function getUnifiedProductDisplay($line)
    {
        $display = '';
        
        // Base product reference and label
        if ($line->product_ref) {
            $display = $line->product_ref;
            if ($line->product_label) {
                $display .= ' - ' . $line->product_label;
            }
        } else {
            $display = $line->description;
        }
        
        // Add combination/variant info if exists
        if ($line->combination_id && $line->combination_ref) {
            $display .= ' (' . $line->combination_ref . ')';
        } elseif ($line->fk_product > 0) {
            // Try to get attribute combinations for this product
            $attributes = $this->getProductAttributes($line->fk_product);
            if (!empty($attributes)) {
                $attr_strings = array();
                foreach ($attributes as $attr_name => $attr_value) {
                    $attr_strings[] = $attr_name . ': ' . $attr_value;
                }
                if (!empty($attr_strings)) {
                    $display .= ' (' . implode(', ', $attr_strings) . ')';
                }
            }
        }
        
        // Add batch/lot info if exists
        if ($line->batch) {
            $display .= ' - Lot: ' . $line->batch;
        }
        
        // Add warehouse info
        if ($line->warehouse_label) {
            $display .= ' [' . $line->warehouse_label . ']';
        }
        
        return $display;
    }
    
    /**
     * Get product attributes for a product
     *
     * @param int $product_id Product ID
     * @return array Array of attributes
     */
    private function getProductAttributes($product_id)
    {
        $attributes = array();
        
        $sql = "SELECT pa.label as attr_name, pav.value as attr_value";
        $sql .= " FROM ".MAIN_DB_PREFIX."product_attribute_combination_price_level as pacpl";
        $sql .= " INNER JOIN ".MAIN_DB_PREFIX."product_attribute_combination as pac ON pac.rowid = pacpl.fk_combination";
        $sql .= " INNER JOIN ".MAIN_DB_PREFIX."product_attribute_value as pav ON pav.fk_combination = pac.rowid";
        $sql .= " INNER JOIN ".MAIN_DB_PREFIX."product_attribute as pa ON pa.rowid = pav.fk_attribute";
        $sql .= " WHERE pac.fk_product_child = ".(int) $product_id;
        $sql .= " ORDER BY pa.position ASC";
        
        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $attributes[$obj->attr_name] = $obj->attr_value;
            }
        }
        
        return $attributes;
    }

    /**
     * Print object lines
     *
     * @param string $action Action
     * @param object $seller Seller
     * @param object $buyer Buyer
     * @param int $selected Selected
     * @param int $dateSelector Date selector
     * @param string $defaulttpldir Default template directory
     * @return int Status
     */
    public function printObjectLines($action, $seller, $buyer, $selected = 0, $dateSelector = 0, $defaulttpldir = '/core/tpl')
    {
        global $conf, $langs, $user, $hookmanager, $form;

        // Keep compatibility with old parameters
        $mysoc = $seller;
        $soc = $buyer;
        $lineid = $selected;

        $num = 0;

        print "<!-- BEGIN PHP TEMPLATE supplierreturn/printObjectLines -->\n";
        print '<thead>';
        print '<tr class="liste_titre">';
        print '<td>'.$langs->trans('ProductOrService').'</td>';
        print '<td class="center">'.$langs->trans('Qty').'</td>';
        print '<td class="right">'.$langs->trans('PriceUHT').'</td>';
        if (isModEnabled('stock')) {
            print '<td class="center">'.$langs->trans('Warehouse').'</td>';
        }
        print '<td class="right">'.$langs->trans('TotalHT').'</td>';
        if ($this->statut == self::STATUS_DRAFT) {
            print '<td class="center">'.$langs->trans('Action').'</td>';
        }
        print '</tr>';
        print '</thead>';

        $var = true;
        foreach ($this->lines as $line) {
            $var = !$var;

            if ($action == 'editline' && $line->id == $lineid) {
                print '<tr class="oddeven">';
                print '<td>';
                print '<input type="hidden" name="lineid" value="'.$line->id.'">';
                if ($line->fk_product > 0) {
                    print '<strong>'.$line->product_ref.' - '.$line->product_label.'</strong><br>';
                }
                print '<textarea name="product_desc" rows="2" style="width:100%">'.$line->description.'</textarea>';
                print '</td>';
                print '<td class="center">';
                print '<input type="text" name="qty" value="'.$line->qty.'" size="4" class="right">';
                print '</td>';
                print '<td class="right">';
                print '<input type="text" name="price_ht" value="'.price($line->subprice).'" size="8" class="right">';
                print '</td>';
                if (isModEnabled('stock')) {
                    print '<td class="center">';
                    require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
                    $formproduct = new FormProduct($this->db);

                    $default_warehouse_id = getDolGlobalString('SUPPLIERRETURN_DEFAULT_WAREHOUSE_ID');

                    if ($default_warehouse_id > 0) {
                        // If a default warehouse is set, show it as plain text
                        require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
                        $warehouse = new Entrepot($this->db);
                        $warehouse->fetch($default_warehouse_id);
                        print $warehouse->getNomUrl(1);
                        print '<input type="hidden" name="entrepot_id" value="'.$default_warehouse_id.'">';
                    } else {
                        // Otherwise, show the standard warehouse selector
                        print $formproduct->selectWarehouses($line->fk_entrepot, 'entrepot_id', '', 1, 0, 0, '', 0, 0, array(), 'minwidth100');
                    }
                    print '</td>';
                }
                print '<td class="right">';
                print price($line->qty * $line->subprice);
                print '</td>';
                print '<td class="center">';
                print '<input type="submit" class="button" name="save" value="'.$langs->trans('Save').'">';
                print ' <input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans('Cancel').'">';
                print '</td>';
                print '</tr>';
            } else {
                print '<tr class="oddeven">';
                print '<td>';
                if ($line->fk_product > 0) {
                    require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
                    $product = new Product($this->db);
                    if ($product->fetch($line->fk_product) > 0) {
                        print $product->getNomUrl(1);
                    }
                } else {
                    print $langs->trans('None');
                }
                if ($line->description) {
                    print '<br>'.dol_nl2br($line->description);
                }
                if (!empty($line->batch)) {
                    print '<br>'.$langs->trans('Batch').'/'.$langs->trans('SerialNumber').': '.dol_escape_htmltag($line->batch);
                }
                print '</td>';
                print '<td class="center">'.$line->qty.'</td>';
                print '<td class="right">'.price($line->subprice).'</td>';
                if (isModEnabled('stock')) {
                    print '<td class="center">';
                    if ($line->fk_entrepot > 0) {
                        require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
                        $warehouse = new Entrepot($this->db);
                        if ($warehouse->fetch($line->fk_entrepot) > 0) {
                            print $warehouse->getNomUrl(1);
                        }
                    } else {
                        print '-';
                    }
                    print '</td>';
                }
                print '<td class="right">'.price($line->total_ht).'</td>';
                if ($this->statut == self::STATUS_DRAFT) {
                    print '<td class="center">';
                    print '<a class="editfielda" href="'.$_SERVER["PHP_SELF"].'?id='.$this->id.'&action=editline&lineid='.$line->id.'#line_'.$line->id.'">';
                    print img_edit();
                    print '</a>';
                    print ' ';
                    print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$this->id.'&action=deleteline&lineid='.$line->id.'&token='.newToken().'" onclick="return confirm(\''.dol_escape_js($langs->trans('ConfirmDeleteLine')).'\');">';
                    print img_delete();
                    print '</a>';
                    print '</td>';
                }
                print '</tr>';
            }
            $num++;
        }

        print "<!-- END PHP TEMPLATE -->\n";
        return $num;
    }

    /**
     * Form to add an object line
     *
     * @param int $dateSelector Date selector
     * @param object $seller Seller
     * @param object $buyer Buyer
     * @param string $defaulttpldir Default template directory
     * @return void
     */
    public function formAddObjectLine($dateSelector, $seller, $buyer, $defaulttpldir = '/core/tpl')
    {
        global $conf, $user, $langs, $object, $hookmanager, $extrafields, $form, $formproduct;

        // Line to add products/services form
        print '<tr class="liste_titre nodrag nodrop">';
        print '<td>'.$langs->trans('ProductOrService').'</td>';
        print '<td class="center">'.$langs->trans('Qty').'</td>';
        print '<td class="right">'.$langs->trans('PriceUHT').'</td>';
        if (isModEnabled('stock')) {
            print '<td class="center">'.$langs->trans('Warehouse').'</td>';
        }
        if (isModEnabled('productbatch')) {
            print '<td class="center">'.$langs->trans("Batch").'</td>';
        }
        print '<td class="center">'.$langs->trans('Action').'</td>';
        print '</tr>';

        print '<tr class="pair nodrag nodrop">';

        // Product selection
        print '<td>';
        
        // Product combo
        require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
        require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.class.php';
        
        $form = new Form($this->db);
        
        // Replace custom product selection with standard Dolibarr product selector.
        // This is more robust and user-friendly.
        $filtertype = '';
        if (empty($conf->global->STOCK_SUPPORTS_SERVICES)) {
            $filtertype = '0'; // Only products, not services
        }
        
        // Use the standard product/service selector
        $options = array();
        $default_warehouse_id = getDolGlobalString('SUPPLIERRETURN_DEFAULT_WAREHOUSE_ID');
        if ($default_warehouse_id > 0) {
            $options['warehouse_id'] = $default_warehouse_id;
        }
        print $form->select_produits('', 'productid', $filtertype, 0, 0, 1, 2, '', 1, $options, 0, '1', 0, 'minwidth300 maxwidth500');

        print '<br><textarea id="product_desc" name="product_desc" rows="2" style="width:100%" placeholder="'.$langs->trans('Description').'"></textarea>';
        print '</td>';

        // Qty
        print '<td class="center">';
        print '<input type="text" name="qty" value="1" size="4" class="right">';
        print '</td>';

        // Price
        print '<td class="right">';
        print '<input type="text" id="price_ht" name="price_ht" value="" size="8" placeholder="0.00" class="right">';
        print '</td>';

        // Warehouse
        if (isModEnabled('stock')) {
            print '<td class="center">';

            require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
            if (!is_object($formproduct)) {
                $formproduct = new FormProduct($this->db);
            }

            $default_warehouse_id = getDolGlobalString('SUPPLIERRETURN_DEFAULT_WAREHOUSE_ID');

            if ($default_warehouse_id > 0) {
                // If a default warehouse is set, show it as plain text
                require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
                $warehouse = new Entrepot($this->db);
                $warehouse->fetch($default_warehouse_id);
                print $warehouse->getNomUrl(1);
                print '<input type="hidden" name="entrepot_id" value="'.$default_warehouse_id.'">';
            } else {
                // Otherwise, show the standard warehouse selector
                print $formproduct->selectWarehouses('', 'entrepot_id', '', 1, 0, 0, '', 0, 0, array(), 'minwidth100');
            }

            print '</td>';
        }

        // Batch
        if (isModEnabled('productbatch')) {
            print '<td class="center">';
            print '<input type="text" name="batch" size="10" placeholder="'.$langs->trans('Batch').'">';
            print '</td>';
        }

        // Add button
        print '<td class="center">';
        print '<input type="submit" class="button" name="addline" value="'.$langs->trans('Add').'">';
        print '</td>';

        print '</tr>';
    }

    /**
     * Create from clone
     *
     * @param User $user User
     * @param int $socid Thirdparty ID
     * @return int New ID if OK, <0 if KO
     */
    public function createFromClone(User $user, $socid = 0)
    {
        global $conf, $hookmanager, $langs;

        $error = 0;

        $this->db->begin();

        // Clone main object
        $clone = clone $this;

        $clone->id = 0;
        $clone->ref = '';
        $clone->statut = self::STATUS_DRAFT;
        $clone->date_creation = dol_now();
        $clone->date_modification = null;
        $clone->fk_user_author = $user->id;
        $clone->fk_user_modif = null;

        if ($socid > 0) {
            $clone->fk_soc = $socid;
        }

        // Create clone
        $result = $clone->create($user);
        if ($result > 0) {
            $clone->ref = $clone->getNextNumRef();
            $clone->update($user);

            // Clone lines
            foreach ($this->lines as $line) {
                $result = $clone->addLine(
                    $line->fk_product,
                    $line->qty,
                    $line->subprice,
                    $line->description,
                    $line->fk_entrepot,
                    $line->batch,
                    0,
                    $user
                );
                if ($result < 0) {
                    $error++;
                    break;
                }
            }
        } else {
            $error++;
        }

        if (!$error) {
            $this->db->commit();
            return $clone->id;
        } else {
            $this->error = $clone->error;
            $this->errors = $clone->errors;
            $this->db->rollback();
            return -1;
        }
    }

    /**
     * Fetch optional attributes
     *
     * @param int $rowid Row ID
     * @param array $optionsArray Options array
     * @return int <0 if KO, >0 if OK
     */
    public function fetch_optionals($rowid = null, $optionsArray = null)
    {
        // For now, return OK (no extrafields implemented yet)
        return 1;
    }

    /**
     * Fetch object linked to the current object
     *
     * @param int $sourceid Object source id
     * @param string $sourcetype Object source type
     * @param int $targetid Object target id
     * @param string $targettype Object target type
     * @param string $clause SQL filter for search
     * @param int $alsosametype Fetch also same type objects
     * @param string $orderby Order by field
     * @param int $loadalsoobjects Load also objects
     * @return int Number of links
     */
    public function fetchObjectLinked($sourceid = null, $sourcetype = '', $targetid = null, $targettype = '', $clause = 'OR', $alsosametype = 1, $orderby = 'sourcetype', $loadalsoobjects = 1)
    {
        global $conf;

        $this->linkedObjectsIds = array();
        $this->linkedObjects = array();
        
        // Optimization: Skip linked object loading during mass operations to improve performance
        static $skip_linked_objects = false;
        if (defined('SKIP_LINKED_OBJECTS_LOADING') && SKIP_LINKED_OBJECTS_LOADING) {
            return 1;
        }

        $now = dol_now();

        if (empty($sourceid)) $sourceid = $this->id;
        if (empty($sourcetype)) $sourcetype = $this->element;
        if (empty($targettype)) $targettype = '';

        /*
         * Add linked objects from manual linkings in element_element table
         */
        $sql = 'SELECT t.rowid, t.fk_target as rowid_target, t.targettype, t.fk_source as rowid_source, t.sourcetype';
        $sql .= ' FROM '.MAIN_DB_PREFIX.'element_element as t';
        $sql .= " WHERE (t.fk_source = ".((int) $sourceid)." AND t.sourcetype = '".$this->db->escape($sourcetype)."')";
        if ($targettype) $sql .= " AND t.targettype = '".$this->db->escape($targettype)."'";
        $sql .= ' ORDER BY t.targettype';

        dol_syslog(get_class($this)."::fetchObjectLinked", LOG_DEBUG);
        $resql = $this->db->query($sql);
        if ($resql) {
            $num = $this->db->num_rows($resql);
            $i = 0;
            while ($i < $num) {
                $obj = $this->db->fetch_object($resql);
                if ($obj->targettype && $obj->rowid_target) {
                    $this->linkedObjectsIds[$obj->targettype][] = $obj->rowid_target;
                }
                $i++;
            }
            $this->db->free($resql);
        } else {
            dol_print_error($this->db);
            return -1;
        }

        /*
         * Add linked objects specific to supplier returns (reception, order, invoice)
         */
        if (!empty($this->fk_reception)) {
            $this->linkedObjectsIds['reception'][] = $this->fk_reception;
        }
        if (!empty($this->fk_commande_fournisseur)) {
            $this->linkedObjectsIds['order_supplier'][] = $this->fk_commande_fournisseur;
        }
        if (!empty($this->fk_facture_fourn)) {
            $this->linkedObjectsIds['invoice_supplier'][] = $this->fk_facture_fourn;
        }

        // Get linked credit notes - optimized to reduce repetitive queries
        if (class_exists('FactureFournisseur') && !empty($this->ref) && $this->ref !== '(PROV)') {
            // Use a more efficient query with better pattern matching
            $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."facture_fourn";
            $sql .= " WHERE note_private LIKE 'CreatedFromSupplierReturns: %".$this->db->escape($this->ref)."%'";
            $sql .= " AND type = 2"; // TYPE_CREDIT_NOTE
            $sql .= " AND entity IN (".getEntity('invoice').")";
            $sql .= " LIMIT 10"; // Limit results to prevent performance issues
            
            $resql = $this->db->query($sql);
            if ($resql) {
                while ($obj = $this->db->fetch_object($resql)) {
                    if (!in_array($obj->rowid, $this->linkedObjectsIds['invoice_supplier'] ?? [])) {
                        $this->linkedObjectsIds['invoice_supplier'][] = $obj->rowid;
                    }
                }
            }
        }

        /*
         * Load objects found
         */
        foreach ($this->linkedObjectsIds as $objecttype => $objectids) {
            $this->linkedObjects[$objecttype] = array();
            foreach ($objectids as $objectid) {
                if ($objecttype == 'reception' && isModEnabled('reception')) {
                    include_once DOL_DOCUMENT_ROOT.'/reception/class/reception.class.php';
                    $object = new Reception($this->db);
                } elseif ($objecttype == 'order_supplier' && isModEnabled('supplier_order')) {
                    include_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
                    $object = new CommandeFournisseur($this->db);
                } elseif ($objecttype == 'invoice_supplier' && isModEnabled('supplier_invoice')) {
                    if (!class_exists('FactureFournisseur')) {
                        require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
                    }
                    if (class_exists('FactureFournisseur')) {
                        $object = new FactureFournisseur($this->db);
                    } else {
                        dol_syslog("SupplierReturn::fetchObjectLinked() Warning: FactureFournisseur class not found", LOG_WARNING);
                        continue;
                    }
                } else {
                    continue;
                }

                if ($object->fetch($objectid) > 0) {
                    $this->linkedObjects[$objecttype][$objectid] = $object;
                }
            }
        }

        return count($this->linkedObjectsIds);
    }

    /**
     * Add object linked to the current object
     *
     * @param string $origin Origin of source object
     * @param int $origin_id Id of source object
     * @param User $f_user User that create
     * @param string $notrigger Disable triggers
     * @return int <0 if KO, >0 if OK
     */
    public function add_object_linked($origin = null, $origin_id = null, $f_user = null, $notrigger = 0)
    {
        // Use parent method instead of custom implementation
        return parent::add_object_linked($origin, $origin_id, $f_user, $notrigger);
    }

    /**
     * Load the third party of the supplier return
     *
     * @param  int $force_thirdparty_id Force thirdparty id
     * @return int <0 if KO, >0 if OK
     */
    public function fetch_thirdparty($force_thirdparty_id = 0)
    {
        $thirdparty_id = $force_thirdparty_id ? $force_thirdparty_id : $this->fk_soc;
        
        if (empty($thirdparty_id)) {
            return -1;
        }

        require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
        
        $this->thirdparty = new Societe($this->db);
        $result = $this->thirdparty->fetch($thirdparty_id);
        
        return $result;
    }

    /**
     * Update object linked to the current object
     *
     * @param int $sourceid Source object id
     * @param string $sourcetype Source object type
     * @param int $targetid Target object id
     * @param string $targettype Target object type
     * @param User $f_user User that update
     * @param string $notrigger Disable triggers
     * @return int <0 if KO, >0 if OK
     */
    public function updateObjectLinked($sourceid = null, $sourcetype = '', $targetid = null, $targettype = '', $f_user = null, $notrigger = 0)
    {
        // This method can be implemented if needed for specific update operations
        return 1;
    }

    /**
     * Get table element line
     *
     * @return string Table element line
     */
    public function getTableElementLine()
    {
        return 'supplierreturndet';
    }

    /**
     * Generate document
     *
     * @param string $modele Model to use
     * @param Translate $outputlangs Lang object to use for output
     * @param int $hidedetails Hide details
     * @param int $hidedesc Hide description
     * @param int $hideref Hide ref
     * @return int <0 if KO, >0 if OK
     */
    public function generateDocument($modele, $outputlangs, $hidedetails = 0, $hidedesc = 0, $hideref = 0)
    {
        global $conf, $langs;

        // Temporarily disable PDF generation to avoid errors during validation
        if (!empty($conf->global->SUPPLIERRETURN_DISABLE_PDF_AUTOGEN)) {
            return 1; // Return success without generating PDF
        }

        $langs->load("supplierreturn@supplierreturn");

        if (!dol_strlen($modele)) {
            $modele = 'standard';

            if (!empty($this->model_pdf)) {
                $modele = $this->model_pdf;
            } elseif (!empty($conf->global->SUPPLIERRETURN_ADDON_PDF)) {
                $modele = $conf->global->SUPPLIERRETURN_ADDON_PDF;
            }
        }

        $modelpath = "custom/supplierreturn/core/modules/supplierreturn/pdf/";

        // Custom implementation to bypass commonGenerateDocument issues
        try {
            // Load parent class first
            dol_include_once('/custom/supplierreturn/core/modules/supplierreturn/modules_supplierreturn.php');
            
            // Load PDF class
            $classname = 'pdf_'.$modele;
            $pdf_file = DOL_DOCUMENT_ROOT.'/custom/supplierreturn/core/modules/supplierreturn/pdf/'.$classname.'.php';
            
            if (!file_exists($pdf_file)) {
                $this->error = "PDF template file not found: $pdf_file";
                dol_syslog("SUPPLIERRETURN: " . $this->error, LOG_ERR);
                return -1;
            }
            
            require_once $pdf_file;
            
            if (!class_exists($classname)) {
                $this->error = "PDF class $classname not found";
                dol_syslog("SUPPLIERRETURN: " . $this->error, LOG_ERR);
                return -1;
            }
            
            // Create PDF instance
            $pdf = new $classname($this->db);
            
            // Set output directory
            if (empty($conf->supplierreturn)) {
                $conf->supplierreturn = new stdClass();
                $conf->supplierreturn->dir_output = DOL_DATA_ROOT.'/invoice_supplier/supplierreturn';
            }
            
            if (!is_dir($conf->supplierreturn->dir_output)) {
                dol_mkdir($conf->supplierreturn->dir_output);
            }
            
            // Generate PDF
            $result = $pdf->write_file($this, $outputlangs, '', $hidedetails, $hidedesc, $hideref);
            
            if ($result > 0) {
                dol_syslog("SUPPLIERRETURN: PDF generated successfully for " . $this->ref, LOG_INFO);
                return 1;
            } else {
                $this->error = "PDF generation failed: " . $pdf->error;
                dol_syslog("SUPPLIERRETURN: " . $this->error, LOG_ERR);
                return -1;
            }
            
        } catch (Exception $e) {
            $this->error = "Exception during PDF generation: " . $e->getMessage();
            dol_syslog("SUPPLIERRETURN: " . $this->error, LOG_ERR);
            return -1;
        }
    }

    /**
     * Get original pricing from reception lines
     *
     * @param object $line Supplier return line
     * @return array|null Original pricing data or null if not found
     */
    private function getOriginalReceptionPricing($line)
    {
        if (!$this->fk_reception || $line->fk_product <= 0) return null;
        
        // Look for the product in the original reception
        $sql = "SELECT rd.subprice, rd.tva_tx, rd.localtax1_tx, rd.localtax2_tx";
        $sql .= " FROM ".MAIN_DB_PREFIX."reception_det as rd";
        $sql .= " WHERE rd.fk_reception = ".(int) $this->fk_reception;
        $sql .= " AND rd.fk_product = ".(int) $line->fk_product;
        $sql .= " LIMIT 1";
        
        $resql = $this->db->query($sql);
        if ($resql && $this->db->num_rows($resql) > 0) {
            $obj = $this->db->fetch_object($resql);
            return array(
                'subprice' => $obj->subprice,
                'tva_tx' => $obj->tva_tx,
                'localtax1_tx' => $obj->localtax1_tx,
                'localtax2_tx' => $obj->localtax2_tx
            );
        }
        
        return null;
    }
    
    /**
     * Send notification for credit note creation
     *
     * @param FactureFournisseur $creditnote Credit note object
     * @param User $user User object
     * @return int >0 if OK, <0 if KO
     */
    private function sendCreditNoteNotification($creditnote, $user)
    {
        global $conf, $langs;
        
        // Basic notification via agenda
        if (!empty($conf->agenda->enabled)) {
            require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
            
            $actioncomm = new ActionComm($this->db);
            $actioncomm->type_code = 'AC_SUP_RETURN_CREDITNOTE';
            $actioncomm->code = 'AC_SUP_RETURN_CREDITNOTE';
            $actioncomm->label = 'Création avoir pour retour fournisseur '.$this->ref;
            $actioncomm->note_private = 'Avoir '.$creditnote->ref.' créé automatiquement pour le retour fournisseur '.$this->ref;
            $actioncomm->datep = dol_now();
            $actioncomm->datef = dol_now();
            $actioncomm->percentage = 100;
            $actioncomm->socid = $this->fk_soc;
            $actioncomm->authorid = $user->id;
            $actioncomm->userownerid = $user->id;
            $actioncomm->fk_element = $this->id;
            $actioncomm->elementtype = 'supplierreturn';
            
            $result = $actioncomm->create($user);
            if ($result < 0) {
                dol_syslog("Warning: Failed to create agenda event for credit note notification", LOG_WARNING);
            }
            
            return $result;
        }
        
        return 1;
    }
    
    /**
     * Propagate all linked documents from supplier return to credit note
     * This ensures that reception, order, original invoice links are also visible in credit note
     *
     * @param FactureFournisseur $creditnote Credit note object
     * @return int >0 if OK, <0 if KO
     */
    private function propagateLinkedDocumentsToCreditNote($creditnote)
    {
        dol_syslog("SupplierReturn::propagateLinkedDocumentsToCreditNote() Starting propagation for supplier return ID ".$this->id." to credit note ID ".$creditnote->id, LOG_DEBUG);
        
        if (!method_exists($creditnote, 'add_object_linked')) {
            dol_syslog("SupplierReturn::propagateLinkedDocumentsToCreditNote() Credit note doesn't support add_object_linked", LOG_WARNING);
            return 1; // Not an error, just not supported
        }
        
        // Get all documents linked to this supplier return
        dol_syslog("SupplierReturn::propagateLinkedDocumentsToCreditNote() Calling fetchObjectLinked for supplier return ID ".$this->id, LOG_DEBUG);
        $fetch_result = $this->fetchObjectLinked($this->id, 'supplierreturn', null, '', 'OR', 1);
        
        dol_syslog("SupplierReturn::propagateLinkedDocumentsToCreditNote() fetchObjectLinked returned: $fetch_result types, linkedObjects count: " . count($this->linkedObjects), LOG_DEBUG);
        
        // Use $this->linkedObjects which contains the actual objects, not $linked_objects which is just a count
        if (empty($this->linkedObjects)) {
            dol_syslog("SupplierReturn::propagateLinkedDocumentsToCreditNote() No linked objects found for supplier return ID ".$this->id, LOG_DEBUG);
            return 1;
        }
        
        $linked_objects = $this->linkedObjects;
        
        $linked_count = 0;
        $document_types_to_link = array(
            'order_supplier' => 'Commande fournisseur',
            'reception' => 'Réception', 
            'invoice_supplier' => 'Facture fournisseur',
            'shipping' => 'Expédition',
            'stock_mouvement' => 'Mouvement de stock'
        );
        
        // Debug: Show what we found
        dol_syslog("SupplierReturn::propagateLinkedDocumentsToCreditNote() Analyzing found objects: " . print_r(array_keys($linked_objects), true), LOG_DEBUG);
        
        foreach ($linked_objects as $objecttype => $objects) {
            dol_syslog("SupplierReturn::propagateLinkedDocumentsToCreditNote() Processing objecttype: $objecttype", LOG_DEBUG);
            
            if (!isset($document_types_to_link[$objecttype])) {
                dol_syslog("SupplierReturn::propagateLinkedDocumentsToCreditNote() Skipping unsupported type: $objecttype", LOG_DEBUG);
                continue; // Skip unsupported document types
            }
            
            dol_syslog("SupplierReturn::propagateLinkedDocumentsToCreditNote() Processing supported type: $objecttype with " . (is_array($objects) ? count($objects) : 1) . " objects", LOG_DEBUG);
            
            if (is_array($objects)) {
                foreach ($objects as $object) {
                    $object_id = isset($object->id) ? $object->id : (isset($object->rowid) ? $object->rowid : null);
                    dol_syslog("SupplierReturn::propagateLinkedDocumentsToCreditNote() Object ID detected: $object_id for type $objecttype", LOG_DEBUG);
                    
                    if ($object_id && $object_id > 0) {
                        $link_result = $creditnote->add_object_linked($objecttype, $object_id);
                        if ($link_result > 0) {
                            $linked_count++;
                            dol_syslog("SupplierReturn::propagateLinkedDocumentsToCreditNote() Successfully linked {$document_types_to_link[$objecttype]} ID $object_id to credit note", LOG_INFO);
                        } else {
                            dol_syslog("SupplierReturn::propagateLinkedDocumentsToCreditNote() Failed to link {$document_types_to_link[$objecttype]} ID $object_id to credit note: ".$creditnote->error, LOG_ERR);
                        }
                    } else {
                        dol_syslog("SupplierReturn::propagateLinkedDocumentsToCreditNote() Invalid object ID for $objecttype: " . print_r($object, true), LOG_WARNING);
                    }
                }
            } else {
                dol_syslog("SupplierReturn::propagateLinkedDocumentsToCreditNote() Objects is not an array for type $objecttype: " . print_r($objects, true), LOG_WARNING);
            }
        }
        
        if ($linked_count > 0) {
            dol_syslog("SupplierReturn::propagateLinkedDocumentsToCreditNote() Successfully linked $linked_count documents to credit note ID ".$creditnote->id, LOG_INFO);
        }
        
        return $linked_count;
    }

    /**
     * Create object links to related documents (reception, supplier order, supplier invoice)
     * This makes the supplier return appear in "Related Objects" sections
     */
    public function createObjectLinks()
    {
        global $user;
        
        $links_created = 0;
        
        // Link to reception if exists
        if (!empty($this->fk_reception)) {
            $result = $this->add_object_linked('reception', $this->fk_reception);
            if ($result > 0) {
                $links_created++;
                dol_syslog("SupplierReturn::createObjectLinks() - Linked to reception ID ".$this->fk_reception, LOG_INFO);
            } else {
                dol_syslog("SupplierReturn::createObjectLinks() - Failed to link to reception ID ".$this->fk_reception, LOG_WARNING);
            }
        }
        
        // Link to supplier order if exists
        if (!empty($this->fk_commande_fournisseur)) {
            $result = $this->add_object_linked('order_supplier', $this->fk_commande_fournisseur);
            if ($result > 0) {
                $links_created++;
                dol_syslog("SupplierReturn::createObjectLinks() - Linked to supplier order ID ".$this->fk_commande_fournisseur, LOG_INFO);
            } else {
                dol_syslog("SupplierReturn::createObjectLinks() - Failed to link to supplier order ID ".$this->fk_commande_fournisseur, LOG_WARNING);
            }
        }
        
        // Link to supplier invoice if exists
        if (!empty($this->fk_facture_fourn)) {
            $result = $this->add_object_linked('invoice_supplier', $this->fk_facture_fourn);
            if ($result > 0) {
                $links_created++;
                dol_syslog("SupplierReturn::createObjectLinks() - Linked to supplier invoice ID ".$this->fk_facture_fourn, LOG_INFO);
            } else {
                dol_syslog("SupplierReturn::createObjectLinks() - Failed to link to supplier invoice ID ".$this->fk_facture_fourn, LOG_WARNING);
            }
        }
        
        dol_syslog("SupplierReturn::createObjectLinks() - Created $links_created object links for supplier return ID ".$this->id, LOG_INFO);
        
        return $links_created;
    }

    /**
     * Initialize object as a specimen for PDF generation
     *
     * @return int 1 if OK, < 0 if KO
     */
    public function initAsSpecimen()
    {
        global $conf, $user, $langs;
        
        // Basic specimen data
        $this->id = 0;  // Specimen has no real ID
        $this->ref = 'SPECIMEN';
        $this->specimen = 1;  // Mark as specimen for PDF template
        $this->statut = self::STATUS_VALIDATED;
        $this->date_creation = dol_now();
        $this->date_return = dol_now();
        $this->return_reason = $langs->trans('defective');
        $this->supplier_ref = 'SUPPLIER-REF-001';
        
        // Set a dummy third party
        $this->fk_soc = 1;
        $this->socid = 1;
        
        // Set totals
        $this->total_ht = 150.00;
        $this->total_ttc = 180.00;
        
        // Notes
        $this->note_public = $langs->trans('SpecimenNote');
        $this->note_private = $langs->trans('SpecimenPrivateNote');
        
        // User
        $this->fk_user_author = $user->id;
        
        // Add some specimen lines
        $this->lines = array();
        
        // Line 1
        $line1 = new stdClass();
        $line1->id = 1;
        $line1->product_ref = 'PROD001';
        $line1->product_label = $langs->trans('SpecimenProduct1');
        $line1->description = $langs->trans('SpecimenProductDesc1');
        $line1->qty = 2;
        $line1->price = 50.00;
        $line1->total_ht = 100.00;
        $line1->total_ttc = 120.00;
        $this->lines[] = $line1;
        
        // Line 2
        $line2 = new stdClass();
        $line2->id = 2;
        $line2->product_ref = 'PROD002';
        $line2->product_label = $langs->trans('SpecimenProduct2');
        $line2->description = $langs->trans('SpecimenProductDesc2');
        $line2->qty = 1;
        $line2->price = 50.00;
        $line2->total_ht = 50.00;
        $line2->total_ttc = 60.00;
        $this->lines[] = $line2;
        
        return 1;
    }

    public function setReturnedToVendor($user)
    {
        return $this->setFinalStatus(self::STATUS_RETURNED_TO_VENDOR, $user);
    }

    public function setReimbursedFromVendor($user)
    {
        return $this->setFinalStatus(self::STATUS_REIMBURSED_FROM_VENDOR, $user);
    }

    public function setProductChangedFromVendor($user)
    {
        return $this->setFinalStatus(self::STATUS_PRODUCT_CHANGED_FROM_VENDOR, $user);
    }

    private function setFinalStatus($status, $user)
    {
        if ($this->statut != self::STATUS_VALIDATED) {
            $this->error = 'Supplier return must be in validated status to be processed.';
            return -1;
        }

        $this->db->begin();

        $sql = "UPDATE ".MAIN_DB_PREFIX."supplierreturn SET statut = ".(int)$status." WHERE rowid = ".(int)$this->id;
        $resql = $this->db->query($sql);

        if ($resql) {
            $this->statut = $status;
            $this->db->commit();
            return 1;
        } else {
            $this->error = $this->db->lasterror();
            $this->db->rollback();
            return -1;
        }
    }

    // Properties for compatibility
    public $table_element_line = 'supplierreturndet';
    public $fk_element = 'fk_supplierreturn';
    // Note: $module property removed to avoid 'supplierreturns_' prefix in element_element table
}
