<?php
/* Copyright (C) 2025 Nicolas Testori
 * Trigger file for CustomerReturn module
 * 
 * CRITICAL: This file MUST be named exactly:
 * interface_50_modCustomerreturn_Customerreturntriggers.class.php
 * 
 * And placed in:
 * htdocs/custom/customerreturn/core/triggers/
 */

// Test if file can be parsed
if (!defined('NOTRIGGER_TEST')) {
    error_log("[TRIGGER_FILE] CustomerReturn trigger file is being loaded/parsed");
}

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 * Trigger class for CustomerReturn module
 */
class InterfaceCustomerreturntriggers extends DolibarrTriggers
{
    /**
     * Constructor
     */
    public function __construct($db)
    {
        parent::__construct($db);
        
        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = "customerreturn";
        $this->description = "Customer return triggers";
        $this->version = '1.0';
        $this->picto = 'customerreturn@customerreturn';
        
        // Log that constructor was called
        error_log("[CUSTOMERRETURN_TRIGGER] *** CONSTRUCTOR CALLED *** Class: ".get_class($this).", Name: ".$this->name);
        dol_syslog("CustomerReturn Trigger constructor called - Name: ".$this->name, LOG_DEBUG);
    }

    /**
     * Trigger function
     */
    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
    {
        // Log EVERY call to runTrigger
        error_log("[CUSTOMERRETURN_TRIGGER] runTrigger called: action=$action, object_class=".(is_object($object) ? get_class($object) : 'NULL'));
        
        // Only process customerreturn actions
        if (strpos($action, 'CUSTOMERRETURN_') !== 0) {
            return 0;
        }
        
        error_log("[CUSTOMERRETURN_TRIGGER] Processing CustomerReturn action: $action");
        
        if (!isModEnabled('customerreturn')) {
            error_log("[CUSTOMERRETURN_TRIGGER] Module not enabled");
            return 0;
        }

        if (!isModEnabled('agenda')) {
            error_log("[CUSTOMERRETURN_TRIGGER] Agenda module not enabled - cannot create events");
            return 0;
        }

        require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
        
        $langs->load("customerreturn@customerreturn");
        $now = dol_now();

        // Get the status label based on object's current status
        $status_label = '';
        if (isset($object->statut)) {
            // Load the CustomerReturn class to use its LibStatut method
            dol_include_once('/custom/customerreturn/class/customerreturn.class.php');
            if (class_exists('CustomerReturn')) {
                $temp_obj = new CustomerReturn($this->db);
                $status_label = $temp_obj->LibStatut($object->statut, 0);
            }
        }
        
        // Map actions to codes and labels
        $action_map = array(
            'CUSTOMERRETURN_CREATE' => array(
                'code' => 'AC_CUSTOMERRETURN_CREATE',
                'label' => 'Customer Return Created'
            ),
            'CUSTOMERRETURN_VALIDATE' => array(
                'code' => 'AC_CUSTOMERRETURN_VALIDATE',
                'label' => 'Customer Return Validated'
            ),
            'CUSTOMERRETURN_MODIFY' => array(
                'code' => 'AC_CUSTOMERRETURN_MODIFY',
                'label' => 'Customer Return Modified'
            ),
            'CUSTOMERRETURN_BACKTODRAFT' => array(
                'code' => 'AC_CUSTOMERRETURN_BACKTODRAFT',
                'label' => 'Customer Return Back to Draft'
            ),
            'CUSTOMERRETURN_DELETE' => array(
                'code' => 'AC_CUSTOMERRETURN_DELETE',
                'label' => 'Customer Return Deleted'
            ),
            'CUSTOMERRETURN_RETURNED_TO_SUPPLIER' => array(
                'code' => 'AC_CUSTOMERRETURN_RETURNED',
                'label' => 'Customer Return - Status changed to: Returned to Supplier'
            ),
            'CUSTOMERRETURN_CHANGED_PRODUCT_FOR_CLIENT' => array(
                'code' => 'AC_CUSTOMERRETURN_CHANGED',
                'label' => 'Customer Return - Status changed to: Product Changed for Client'
            ),
            'CUSTOMERRETURN_REIMBURSED_MONEY_TO_CLIENT' => array(
                'code' => 'AC_CUSTOMERRETURN_REIMBURSED',
                'label' => 'Customer Return - Status changed to: Money Reimbursed to Client'
            )
        );

        if (!isset($action_map[$action])) {
            error_log("[CUSTOMERRETURN_TRIGGER] Unknown action: $action");
            return 0;
        }

        $map = $action_map[$action];
        
        // Build the label with status if available
        $event_label = $map['label'];
        if (!empty($status_label) && in_array($action, array('CUSTOMERRETURN_RETURNED_TO_SUPPLIER', 'CUSTOMERRETURN_CHANGED_PRODUCT_FOR_CLIENT', 'CUSTOMERRETURN_REIMBURSED_MONEY_TO_CLIENT', 'CUSTOMERRETURN_VALIDATE', 'CUSTOMERRETURN_BACKTODRAFT'))) {
            $event_label .= ' ('.$status_label.')';
        }
        $event_label .= ' - '.$object->ref;
        
        error_log("[CUSTOMERRETURN_TRIGGER] Creating agenda event - Code: ".$map['code'].", Label: ".$event_label);
        
        $actioncomm = new ActionComm($this->db);
        $actioncomm->type_code = 'AC_OTH_AUTO';
        $actioncomm->code = $map['code'];
        $actioncomm->label = $event_label;
        $actioncomm->datep = $now;
        $actioncomm->datef = $now;
        $actioncomm->durationp = 0;
        $actioncomm->percentage = -1;
        $actioncomm->socid = $object->fk_soc;
        $actioncomm->authorid = $user->id;
        $actioncomm->userownerid = $user->id;
        $actioncomm->fk_element = $object->id;
        $actioncomm->elementtype = 'customerreturn@customerreturn';
        
        error_log("[CUSTOMERRETURN_TRIGGER] ActionComm prepared - socid: ".$object->fk_soc.", user: ".$user->id.", element: ".$object->id);
        
        $result = $actioncomm->create($user);
        
        if ($result > 0) {
            error_log("[CUSTOMERRETURN_TRIGGER] SUCCESS - Event created with ID: $result");
            dol_syslog("CustomerReturn Trigger: Successfully created event ID $result for action $action", LOG_DEBUG);
            return 1;
        } else {
            error_log("[CUSTOMERRETURN_TRIGGER] ERROR creating event: ".$actioncomm->error);
            dol_syslog("CustomerReturn Trigger ERROR: ".$actioncomm->error, LOG_ERR);
            $this->errors[] = $actioncomm->error;
            return -1;
        }
    }
}

// Log that file finished loading
error_log("[TRIGGER_FILE] CustomerReturn trigger file loaded successfully - Class defined: ".(class_exists('InterfaceCustomerreturntriggers') ? 'YES' : 'NO'));
