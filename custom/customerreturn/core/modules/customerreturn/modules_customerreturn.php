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
 * \file    core/modules/customerreturn/modules_customerreturn.php
 * \ingroup customerreturns
 * \brief   File that contains parent class for customer return document generators
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commondocgenerator.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/commonnumrefgenerator.class.php';

/**
 * Parent class for customer return document generators
 */
abstract class ModelePDFCustomerreturn extends CommonDocGenerator
{
    public $error = '';
    public $page_orientation = '';

    public static function liste_modeles($db, $maxfilenamelength = 0)
    {
        $type = 'customerreturn';
        $list = array();

        dol_include_once('/custom/customerreturn/core/modules/customerreturn/pdf/pdf_standard.php');

        if (class_exists('pdf_standard')) {
            $obj = new pdf_standard($db);
            if ($obj->isEnabled()) {
                $list[$obj->name] = $obj->description;
            }
        }

        include_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
        $additional_list = getListOfModels($db, $type, $maxfilenamelength);
        if (is_array($additional_list)) {
            $list = array_merge($list, $additional_list);
        }

        return $list;
    }

    abstract public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0);
}

/**
 * Parent class for customer return numbering models
 */
abstract class ModeleNumRefCustomerReturn extends CommonNumRefGenerator
{
    abstract public function getExample();

    public function canBeActivated($object)
    {
        return true;
    }

    public function isEnabled()
    {
        return true;
    }

    abstract public function getNextValue($objsoc, $object);
}