<?php
/* Copyright (C) 2025 Nicolas Testori
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    modules_customerreturn.php
 * \ingroup customerreturn
 * \brief   Base class for customer return numbering modules
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonnumrefgenerator.class.php';

/**
 * Parent class for customer return numbering modules
 */
abstract class ModeleNumRefCustomerReturn extends CommonNumRefGenerator
{
    /**
     * @var string Error code (or message)
     */
    public $error = '';

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
     * Return default description of numbering model
     *
     * @return string      Text with description
     */
    public function info()
    {
        global $langs;
        $langs->load("customerreturn@customerreturn");
        return $langs->trans("NoDescription");
    }

    /**
     * Return an example of numbering
     *
     * @return string      Example
     */
    public function getExample()
    {
        return '';
    }

    /**
     * Checks if the numbers already in the database do not
     * cause conflicts that would prevent this numbering working.
     *
     * @return boolean     false if conflict, true if ok
     */
    public function canBeActivated()
    {
        return true;
    }

    /**
     * Return next value
     *
     * @param   Societe     $objsoc     Third party object
     * @param   Object      $object     Object
     * @return  string                  Value if OK, 0 if KO
     */
    abstract public function getNextValue($objsoc, $object);

    /**
     * Return version of module
     *
     * @return string
     */
    public function getVersion()
    {
        return $this->version ?? 'development';
    }
}
