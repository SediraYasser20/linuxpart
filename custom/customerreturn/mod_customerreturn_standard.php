<?php
/* Copyright (C) 2025 Nicolas Testori
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    core/modules/customerreturn/mod_customerreturn_standard.php
 * \ingroup customerreturn
 * \brief   Standard numbering module for customer returns
 */

dol_include_once('/custom/customerreturn/core/modules/customerreturn/modules_customerreturn.php');

/**
 * Class to manage customer return numbering rules standard
 */
class mod_customerreturn_standard extends ModeleNumRefCustomerReturn
{
    /**
     * @var string Numbering module ref
     */
    public $name = 'standard';

    /**
     * @var string Version
     */
    public $version = 'dolibarr';

    /**
     * @var string Error message
     */
    public $error = '';

    /**
     * @var string Prefix
     */
    public $prefix = 'RT';

    /**
     * Return description of numbering module
     *
     * @param Translate $langs Language object for translation
     * @return string Description
     */
    public function info($langs)
    {
        $langs->load("customerreturn@customerreturn");
        return $langs->trans("SimpleNumRefModelDesc", $this->prefix.'{yyyy}{0000}');
    }

    /**
     * Return an example of numbering
     *
     * @return string Example
     */
    public function getExample()
    {
        return $this->prefix.date('Y').'0001';
    }

    /**
     * Checks if the numbers already in the database do not
     * cause conflicts that would prevent this numbering working.
     *
     * @param Object $object Object we need next value for
     * @return boolean false if conflict, true if ok
     */
    public function canBeActivated($object)
    {
        global $conf, $langs, $db;

        $year = date('Y');
        $sql = "SELECT MAX(CAST(SUBSTRING(ref, 7) AS SIGNED)) as max";
        $sql .= " FROM ".MAIN_DB_PREFIX."customerreturn";
        $sql .= " WHERE ref LIKE '".$db->escape($this->prefix.$year)."%'";
        $sql .= " AND entity = ".(int) $conf->entity;

        $resql = $db->query($sql);
        if ($resql) {
            return true;
        } else {
            $this->error = $db->lasterror();
            return false;
        }
    }

    /**
     * Return next free value
     *
     * @param Societe $objsoc Object thirdparty
     * @param CustomerReturn $object Object we need next value for
     * @return string Value if OK, 0 if KO
     */
    public function getNextValue($objsoc, $object)
    {
        global $db, $conf;

        dol_syslog(__METHOD__." Generating next reference for customer return", LOG_DEBUG);

        // Get current year
        $year = date('Y');
        
        // Find the highest number for this year
        $sql = "SELECT MAX(CAST(SUBSTRING(ref, 7) AS SIGNED)) as max";
        $sql .= " FROM ".MAIN_DB_PREFIX."customerreturn";
        $sql .= " WHERE ref LIKE '".$db->escape($this->prefix.$year)."%'";
        $sql .= " AND entity = ".(int) $conf->entity;

        dol_syslog(__METHOD__." SQL: ".$sql, LOG_DEBUG);
        
        $resql = $db->query($sql);
        if ($resql) {
            $obj = $db->fetch_object($resql);
            $max = ($obj->max !== null) ? intval($obj->max) : 0;
            $next = $max + 1;
            
            $ref = $this->prefix.$year.sprintf("%04d", $next);
            
            dol_syslog(__METHOD__." Generated reference: ".$ref, LOG_DEBUG);
            return $ref;
        } else {
            $this->error = $db->lasterror();
            dol_syslog(__METHOD__." Error: ".$this->error, LOG_ERR);
            return 0;
        }
    }

    /**
     * Return next free value (alias for compatibility)
     *
     * @param Societe $objsoc Object thirdparty
     * @param Object $object Object
     * @return string Next free value
     */
    public function getNumRef($objsoc, $object)
    {
        return $this->getNextValue($objsoc, $object);
    }
}
