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

    /**
     * Return list of active generation models
     *
     * @param  DoliDB $db                Database handler
     * @param  int    $maxfilenamelength Max length of value to show
     * @return array                     List of models
     */
    public static function liste_modeles($db, $maxfilenamelength = 0)
    {
        $type = 'customerreturn';
        $list = array();

        // Include the standard PDF model
        $pdfpath = DOL_DOCUMENT_ROOT.'/custom/customerreturn/core/modules/customerreturn/pdf/pdf_standard.php';
        if (file_exists($pdfpath)) {
            require_once $pdfpath;
            if (class_exists('pdf_standard')) {
                $obj = new pdf_standard($db);
                if ($obj->isEnabled()) {
                    $list[$obj->name] = $obj->description;
                }
            }
        }

        // Get additional models from custom directory
        include_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
        $additional_list = getListOfModels($db, $type, $maxfilenamelength);
        if (is_array($additional_list)) {
            $list = array_merge($list, $additional_list);
        }

        return $list;
    }

    /**
     * Write PDF file
     *
     * @param  CommonObject $object       Object to generate
     * @param  Translate    $outputlangs  Language object
     * @param  string       $srctemplatepath Template path
     * @param  int          $hidedetails  Hide details
     * @param  int          $hidedesc     Hide description
     * @param  int          $hideref      Hide reference
     * @return int                        <0 if error, >0 if success
     */
    abstract public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0);
}

/**
 * Parent class for customer return numbering models
 */
abstract class ModeleNumRefCustomerReturn extends CommonNumRefGenerator
{
    /**
     * Return an example of numbering
     *
     * @return string Example
     */
    abstract public function getExample();

    /**
     * Checks if the numbers already in the database do not
     * cause conflicts that would prevent this numbering working
     *
     * @param  Object $object Object we need next value for
     * @return boolean        false if conflict, true if ok
     */
    public function canBeActivated($object)
    {
        return true;
    }

    /**
     * Return if a model is enabled
     *
     * @return boolean True if enabled
     */
    public function isEnabled()
    {
        return true;
    }

    /**
     * Return next free value
     *
     * @param  Societe $objsoc Object thirdparty
     * @param  Object  $object Object we need next value for
     * @return string          Next free value
     */
    abstract public function getNextValue($objsoc, $object);
}
