<?php
/* Copyright (C) 2025 SupplierReturns Module
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

/**
 * \file    core/triggers/interface_99_modSupplierReturns_SupplierReturnsTriggers.class.php
 * \ingroup supplierreturns
 * \brief   Triggers for SupplierReturns module
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 *  Class of triggers for SupplierReturns module
 */
class InterfaceSupplierReturnsTriggers extends DolibarrTriggers
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->family = "stock";
		$this->description = "SupplierReturns triggers.";
		$this->version = '1.0.0';
		$this->picto = 'supplierreturn@supplierreturn';
	}

	/**
	 * Function called when a Dolibarr business event is done.
	 *
	 * @param string 		$action 	Event action code
	 * @param CommonObject 	$object 	Object
	 * @param User 			$user 		Object user
	 * @param Translate 	$langs 		Object langs
	 * @param Conf 			$conf 		Object conf
	 * @return int              		Return integer <0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('supplierreturns')) {
			return 0; // If module is not enabled, we do nothing
		}

		// Handle different actions
		switch ($action) {
			case 'SUPPLIERRETURN_CREATE':
				return $this->supplierReturnCreate($action, $object, $user, $langs, $conf);

			case 'SUPPLIERRETURN_MODIFY':
				return $this->supplierReturnModify($action, $object, $user, $langs, $conf);

			case 'SUPPLIERRETURN_VALIDATE':
				return $this->supplierReturnValidate($action, $object, $user, $langs, $conf);

			case 'SUPPLIERRETURN_CLOSE':
				return $this->supplierReturnClose($action, $object, $user, $langs, $conf);

			case 'SUPPLIERRETURN_DELETE':
				return $this->supplierReturnDelete($action, $object, $user, $langs, $conf);

			case 'SUPPLIERRETURNLINE_CREATE':
				return $this->supplierReturnLineCreate($action, $object, $user, $langs, $conf);

			case 'SUPPLIERRETURNLINE_MODIFY':
				return $this->supplierReturnLineModify($action, $object, $user, $langs, $conf);

			case 'SUPPLIERRETURNLINE_DELETE':
				return $this->supplierReturnLineDelete($action, $object, $user, $langs, $conf);

			default:
				// Action not handled by this trigger
				return 0;
		}
	}

	/**
	 * Action on supplier return creation
	 *
	 * @param string 		$action 	Event action code
	 * @param CommonObject 	$object 	Object
	 * @param User 			$user 		Object user
	 * @param Translate 	$langs 		Object langs
	 * @param Conf 			$conf 		Object conf
	 * @return int              		Return integer <0 if KO, 0 if no triggered ran, >0 if OK
	 */
	private function supplierReturnCreate($action, $object, User $user, Translate $langs, Conf $conf)
	{
		dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);

		// Log the creation in agenda using direct SQL to avoid nested transaction issues
		if (isModEnabled('agenda')) {
			// Use direct SQL insert to avoid nested transaction conflicts with ActionComm::create()
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."actioncomm (";
			$sql .= "datec, datep, type_code, code, label, note, fk_soc, fk_element, elementtype, userownerid, percentage, entity";
			$sql .= ") VALUES (";
			$sql .= "'".$this->db->idate(dol_now())."', ";
			$sql .= "'".$this->db->idate(dol_now())."', ";
			$sql .= "'AC_OTH_AUTO', ";
			$sql .= "'AC_OTH_AUTO', ";
			$sql .= "'".$this->db->escape($langs->trans('SupplierReturnCreated', $object->ref))."', ";
			$sql .= "'".$this->db->escape($langs->trans('SupplierReturnCreated', $object->ref))."', ";
			$sql .= (int) $object->fk_soc.", ";
			$sql .= (int) $object->id.", ";
			$sql .= "'".$this->db->escape($object->element.'@supplierreturn')."', ";
			$sql .= (int) $user->id.", ";
			$sql .= "-1, ";
			$sql .= "1";
			$sql .= ")";
			
			$result = $this->db->query($sql);
			if (!$result) {
				dol_syslog("SupplierReturnsTriggers: Failed to log creation in agenda - ".$this->db->error(), LOG_WARNING);
			}
		}

		return 1;
	}

	/**
	 * Action on supplier return modification
	 */
	private function supplierReturnModify($action, $object, User $user, Translate $langs, Conf $conf)
	{
		dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
		return 1;
	}

	/**
	 * Action on supplier return validation
	 */
	private function supplierReturnValidate($action, $object, User $user, Translate $langs, Conf $conf)
	{
		dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);

		// Log the validation in agenda using direct SQL to avoid nested transaction issues
		if (isModEnabled('agenda')) {
			// Use direct SQL insert to avoid nested transaction conflicts with ActionComm::create()
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."actioncomm (";
			$sql .= "datec, datep, type_code, code, label, note, fk_soc, fk_element, elementtype, userownerid, percentage, entity";
			$sql .= ") VALUES (";
			$sql .= "'".$this->db->idate(dol_now())."', ";
			$sql .= "'".$this->db->idate(dol_now())."', ";
			$sql .= "'AC_OTH_AUTO', ";
			$sql .= "'AC_OTH_AUTO', ";
			$sql .= "'".$this->db->escape($langs->trans('SupplierReturnValidated', $object->ref))."', ";
			$sql .= "'".$this->db->escape($langs->trans('SupplierReturnValidated', $object->ref))."', ";
			$sql .= (int) $object->fk_soc.", ";
			$sql .= (int) $object->id.", ";
			$sql .= "'".$this->db->escape($object->element.'@supplierreturn')."', ";
			$sql .= (int) $user->id.", ";
			$sql .= "-1, ";
			$sql .= "1";
			$sql .= ")";
			
			$result = $this->db->query($sql);
			if (!$result) {
				dol_syslog("SupplierReturnsTriggers: Failed to log validation in agenda - ".$this->db->error(), LOG_WARNING);
			}
		}

		return 1;
	}

	/**
	 * Action on supplier return closure
	 */
	private function supplierReturnClose($action, $object, User $user, Translate $langs, Conf $conf)
	{
		dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);

		// Log the closure in agenda using direct SQL to avoid nested transaction issues
		if (isModEnabled('agenda')) {
			// Use direct SQL insert to avoid nested transaction conflicts with ActionComm::create()
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."actioncomm (";
			$sql .= "datec, datep, type_code, code, label, note, fk_soc, fk_element, elementtype, userownerid, percentage, entity";
			$sql .= ") VALUES (";
			$sql .= "'".$this->db->idate(dol_now())."', ";
			$sql .= "'".$this->db->idate(dol_now())."', ";
			$sql .= "'AC_OTH_AUTO', ";
			$sql .= "'AC_OTH_AUTO', ";
			$sql .= "'".$this->db->escape($langs->trans('SupplierReturnClosed', $object->ref))."', ";
			$sql .= "'".$this->db->escape($langs->trans('SupplierReturnClosed', $object->ref))."', ";
			$sql .= (int) $object->fk_soc.", ";
			$sql .= (int) $object->id.", ";
			$sql .= "'".$this->db->escape($object->element.'@supplierreturn')."', ";
			$sql .= (int) $user->id.", ";
			$sql .= "-1, ";
			$sql .= "1";
			$sql .= ")";
			
			$result = $this->db->query($sql);
			if (!$result) {
				dol_syslog("SupplierReturnsTriggers: Failed to log closure in agenda - ".$this->db->error(), LOG_WARNING);
			}
		}

		return 1;
	}

	/**
	 * Action on supplier return deletion
	 */
	private function supplierReturnDelete($action, $object, User $user, Translate $langs, Conf $conf)
	{
		dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
		return 1;
	}

	/**
	 * Action on supplier return line creation
	 */
	private function supplierReturnLineCreate($action, $object, User $user, Translate $langs, Conf $conf)
	{
		dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
		return 1;
	}

	/**
	 * Action on supplier return line modification
	 */
	private function supplierReturnLineModify($action, $object, User $user, Translate $langs, Conf $conf)
	{
		dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
		return 1;
	}

	/**
	 * Action on supplier return line deletion
	 */
	private function supplierReturnLineDelete($action, $object, User $user, Translate $langs, Conf $conf)
	{
		dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
		return 1;
	}
}