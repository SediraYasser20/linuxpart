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
/**
 * Class to manage supplier return numbering standard rule
 */

dol_include_once('/custom/supplierreturn/core/modules/supplierreturn/modules_supplierreturn.php');

class mod_supplierreturn_standard extends ModeleNumRefSupplierReturn
{
    public $version = 'dolibarr';
    public $prefix = 'RF';
    public $error = '';
    public $nom = 'Standard';
    public $name = 'Standard';

    /**
     * Return description of numbering model
     *
     * @return string Text with description
     */
    public function info($langs = null)
    {
        global $langs;
        return $langs->trans("SimpleNumRefModelDesc", $this->prefix);
    }

    /**
     * Return version of module
     *
     * @return string Version
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Return an example of numbering
     *
     * @return string Example
     */
    public function getExample()
    {
        return $this->prefix."2501-0001";
    }

    /**
     * Return if a module can be used or not
     *
     * @return boolean true if module can be used
     */
    public function isEnabled()
    {
        return true;
    }

    /**
     * Checks if the numbers already in the database do not
     * cause conflicts that would prevent this numbering working.
     *
     * @param object $object Object we need next value for
     * @return boolean false if conflict, true if ok
     */
    public function canBeActivated($object)
    {
        global $conf, $langs, $db;

        // For this simple implementation, we always allow activation
        // A more complex implementation would check for existing numbering conflicts
        return true;
    }

    /**
     * Return next free value
     *
     * @param Societe $objsoc Object thirdparty
     * @param Object $object Object we need next value for
     * @return string Value if KO, <0 if KO
     */
    public function getNextValue($objsoc, $object)
    {
        global $conf;

        dol_syslog("mod_supplierreturn_standard::getNextValue - Starting", LOG_INFO);

        // Get database connection from object
        if (!isset($object->db) || !$object->db) {
            dol_syslog("mod_supplierreturn_standard::getNextValue - No database connection", LOG_ERR);
            return -1;
        }
        $db = $object->db;
        
        dol_syslog("mod_supplierreturn_standard::getNextValue - Database connection OK", LOG_INFO);

        dol_syslog("mod_supplierreturn_standard::getNextValue - About to construct SQL query", LOG_INFO);
        
        try {
            $posindice = strlen($this->prefix) + 6;
            $sql = "SELECT MAX(CAST(SUBSTRING(ref FROM ".$posindice.") AS SIGNED)) as max";
            $sql .= " FROM ".MAIN_DB_PREFIX."supplierreturn";
            $sql .= " WHERE ref LIKE '".$db->escape($this->prefix).date('ym')."-____'";
            $sql .= " AND entity = ".$conf->entity;

            dol_syslog("mod_supplierreturn_standard::getNextValue - SQL: ".$sql, LOG_INFO);
            $resql = $db->query($sql);
        } catch (Exception $e) {
            dol_syslog("mod_supplierreturn_standard::getNextValue - Exception during SQL construction: ".$e->getMessage(), LOG_ERR);
            return -1;
        }
        if ($resql) {
            dol_syslog("mod_supplierreturn_standard::getNextValue - Query executed successfully", LOG_INFO);
            $obj = $db->fetch_object($resql);
            if ($obj) $max = intval($obj->max);
            else $max = 0;
            dol_syslog("mod_supplierreturn_standard::getNextValue - Current max: ".$max, LOG_INFO);
        } else {
            dol_syslog("mod_supplierreturn_standard::getNextValue - SQL Error: ".$db->error(), LOG_ERR);
            return -1;
        }

        dol_syslog("mod_supplierreturn_standard::getNextValue - About to generate reference", LOG_INFO);
        
        try {
            $date = time();
            $yymm = date("ym", $date);  // Remplacement de strftime par date

            if ($max >= (pow(10, 4) - 1)) $max = 0;

            $num = sprintf("%04s", $max + 1);
            $newref = $this->prefix.$yymm."-".$num;

            dol_syslog("mod_supplierreturn_standard::getNextValue - Generated: ".$newref, LOG_INFO);
            return $newref;
        } catch (Exception $e) {
            dol_syslog("mod_supplierreturn_standard::getNextValue - Exception during reference generation: ".$e->getMessage(), LOG_ERR);
            return -1;
        }
    }
}