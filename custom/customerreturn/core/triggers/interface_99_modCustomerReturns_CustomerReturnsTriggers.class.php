<?php
/* Copyright (C) 2025 CustomerReturns Module
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
 * \file    core/triggers/interface_99_modCustomerReturns_CustomerReturnsTriggers.class.php
 * \ingroup customerreturns
 * \brief   Triggers for CustomerReturns module
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

class InterfaceCustomerReturnsTriggers extends DolibarrTriggers
{
	public function __construct($db)
	{
		parent::__construct($db);
		$this->family = "stock";
		$this->description = "CustomerReturns triggers.";
		$this->version = '1.0.0';
		$this->picto = 'customerreturn@customerreturn';
	}

	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('customerreturn')) {
			return 0;
		}

		switch ($action) {
			case 'CUSTOMERRETURN_CREATE':
				return $this->customerReturnCreate($action, $object, $user, $langs, $conf);
			case 'CUSTOMERRETURN_VALIDATE':
				return $this->customerReturnValidate($action, $object, $user, $langs, $conf);
			case 'CUSTOMERRETURN_CLOSE':
				return $this->customerReturnClose($action, $object, $user, $langs, $conf);
			case 'CUSTOMERRETURN_CREDIT_NOTE_CREATED':
				return $this->customerReturnCreditNoteCreated($action, $object, $user, $langs, $conf);
			default:
				return 0;
		}
	}

	private function customerReturnCreate($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (isModEnabled('agenda')) {
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."actioncomm (datec, datep, type_code, code, label, fk_soc, fk_element, elementtype, userownerid, entity) VALUES (";
			$sql .= "'".$this->db->idate(dol_now())."', '".$this->db->idate(dol_now())."', 'AC_OTH_AUTO', 'AC_OTH_AUTO', ";
			$sql .= "'".$this->db->escape($langs->trans('CustomerReturnCreated', $object->ref))."', ";
			$sql .= (int) $object->fk_soc.", ".(int) $object->id.", '".$this->db->escape($object->element.'@customerreturn')."', ".(int) $user->id.", 1)";
			$this->db->query($sql);
		}
		return 1;
	}

	private function customerReturnValidate($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (isModEnabled('agenda')) {
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."actioncomm (datec, datep, type_code, code, label, fk_soc, fk_element, elementtype, userownerid, entity) VALUES (";
			$sql .= "'".$this->db->idate(dol_now())."', '".$this->db->idate(dol_now())."', 'AC_OTH_AUTO', 'AC_OTH_AUTO', ";
			$sql .= "'".$this->db->escape($langs->trans('CustomerReturnValidated', $object->ref))."', ";
			$sql .= (int) $object->fk_soc.", ".(int) $object->id.", '".$this->db->escape($object->element.'@customerreturn')."', ".(int) $user->id.", 1)";
			$this->db->query($sql);
		}
		return 1;
	}

	private function customerReturnClose($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (isModEnabled('agenda')) {
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."actioncomm (datec, datep, type_code, code, label, fk_soc, fk_element, elementtype, userownerid, entity) VALUES (";
			$sql .= "'".$this->db->idate(dol_now())."', '".$this->db->idate(dol_now())."', 'AC_OTH_AUTO', 'AC_OTH_AUTO', ";
			$sql .= "'".$this->db->escape($langs->trans('CustomerReturnClosed', $object->ref))."', ";
			$sql .= (int) $object->fk_soc.", ".(int) $object->id.", '".$this->db->escape($object->element.'@customerreturn')."', ".(int) $user->id.", 1)";
			$this->db->query($sql);
		}
		return 1;
	}

	private function customerReturnCreditNoteCreated($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (isModEnabled('agenda')) {
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."actioncomm (datec, datep, type_code, code, label, fk_soc, fk_element, elementtype, userownerid, entity) VALUES (";
			$sql .= "'".$this->db->idate(dol_now())."', '".$this->db->idate(dol_now())."', 'AC_OTH_AUTO', 'AC_OTH_AUTO', ";
			$sql .= "'".$this->db->escape($langs->trans('CustomerReturnCreditNoteCreated', $object->ref))."', ";
			$sql .= (int) $object->fk_soc.", ".(int) $object->id.", '".$this->db->escape($object->element.'@customerreturn')."', ".(int) $user->id.", 1)";
			$this->db->query($sql);
		}
		return 1;
	}
}