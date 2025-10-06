<?php
/* Copyright (C) 2025 Nicolas Testori */

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
