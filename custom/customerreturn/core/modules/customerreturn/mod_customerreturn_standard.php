<?php
/* Copyright (C) 2025 Nicolas Testori
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    mod_customerreturn_standard.php
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
     * Version of the numbering module
     * @var string
     */
    public $version = 'dolibarr';

    /**
     * @var string Error message
     */
    public $error = '';

    /**
     * @var string Name
     */
    public $name = 'standard';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->code_auto = 1;
    }

    /**
     * Return description of numbering module
     *
     * @param   Translate   $langs      Language object
     * @return  string                  Text with description
     */
    public function info($langs)
    {
        $langs->load("customerreturn@customerreturn");
        return $langs->trans("SimpleNumRefModelDesc", 'RT{YYYY}{0000}');
    }

    /**
     * Return an example of numbering
     *
     * @return string      Example
     */
    public function getExample()
    {
        return "RT0001";
    }

    /**
     * Checks if the numbers already in the database do not
     * cause conflicts that would prevent this numbering working.
     *
     * @param   object      $object     Object
     * @return  boolean                 false if conflict, true if ok
     */
    public function canBeActivated($object)
    {
        return true;
    }

    /**
     * Return next free value
     *
     * @param   Societe             $objsoc     Object thirdparty
     * @param   CustomerReturn      $object     Object we need next value for
     * @return  string                          Value if OK, 0 if KO
     */
    public function getNextValue($objsoc, $object)
    {
        global $db, $conf;

        dol_syslog(__METHOD__." Generating next reference for customer return", LOG_DEBUG);

        // Get current year
        $year = dol_print_date(dol_now(), '%Y');
        
        // Find the highest number for this year
        $sql = "SELECT MAX(CAST(SUBSTRING(ref, 7) AS SIGNED)) as max";
        $sql .= " FROM ".MAIN_DB_PREFIX."customerreturn";
        $sql .= " WHERE ref LIKE 'RT".$year."%'";
        $sql .= " AND entity = ".(int) $conf->entity;

        dol_syslog(__METHOD__." SQL: ".$sql, LOG_DEBUG);
        
        $resql = $db->query($sql);
        if ($resql) {
            $obj = $db->fetch_object($resql);
            $max = ($obj->max !== null) ? intval($obj->max) : 0;
            $next = $max + 1;
            
            $ref = 'RT'.$year.sprintf("%04d", $next);
            
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
     * @param   Societe     $objsoc     Object thirdparty
     * @param   Object      $object     Object
     * @return  string                  Next free value
     */
    public function getNumRef($objsoc, $object)
    {
        return $this->getNextValue($objsoc, $object);
    }
}
