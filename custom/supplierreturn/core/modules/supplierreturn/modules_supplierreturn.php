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
 * \file    core/modules/supplierreturn/modules_supplierreturn.php
 * \ingroup supplierreturns
 * \brief   File that contains parent class for supplier return document generators
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commondocgenerator.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/commonnumrefgenerator.class.php';

/**
 * Parent class for supplier return document generators
 */
abstract class ModelePDFSupplierreturn extends CommonDocGenerator
{
    /**
     * @var string Error code (or message)
     */
    public $error = '';

    /**
     * @var int Page orientation (P=portrait, L=landscape)
     */
    public $page_orientation = '';

    /**
     * Return list of active generation modules
     *
     * @param DoliDB $db                Database handler
     * @param integer $maxfilenamelength Max length of value to show
     * @return array                    List of templates
     */
    public static function liste_modeles($db, $maxfilenamelength = 0)
    {
        $type = 'supplierreturn';
        $list = array();

        // Force include our PDF model for proper detection
        dol_include_once('/custom/supplierreturn/core/modules/supplierreturn/pdf/pdf_standard.php');
        
        if (class_exists('pdf_standard')) {
            $obj = new pdf_standard($db);
            if ($obj->isEnabled()) {
                $list[$obj->name] = $obj->description;
            }
        }
        
        // Complement with native search
        include_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
        $additional_list = getListOfModels($db, $type, $maxfilenamelength);
        if (is_array($additional_list)) {
            $list = array_merge($list, $additional_list);
        }

        return $list;
    }

    /**
     * Function to build pdf onto disk
     *
     * @param  SupplierReturn  $object        Object to generate
     * @param  Translate       $outputlangs   Lang output object
     * @param  string          $srctemplatepath Full path of source filename for generator using a template file
     * @param  int             $hidedetails   Do not show line details
     * @param  int             $hidedesc      Do not show desc
     * @param  int             $hideref       Do not show ref
     * @return int                            1=OK, 0=KO
     */
    abstract public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0);
}

/**
 * Parent class for supplier return numbering models
 */
abstract class ModeleNumRefSupplierReturn extends CommonNumRefGenerator
{
    /**
     * Return an example of numbering
     *
     * @return string Example
     */
    abstract public function getExample();

    /**
     * Checks if the numbers already in the database do not
     * cause conflicts that would prevent this numbering working.
     *
     * @param object $object Object we need next value for
     * @return boolean false if conflict, true if ok
     */
    public function canBeActivated($object)
    {
        return true;
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
     * Return next free value
     *
     * @param Societe $objsoc Object thirdparty
     * @param Object $object Object we need next value for
     * @return string Value if KO, <0 if KO
     */
    abstract public function getNextValue($objsoc, $object);
}