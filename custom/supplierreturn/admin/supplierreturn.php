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
 * \file    admin/supplierreturn.php
 * \ingroup supplierreturns
 * \brief   SupplierReturn setup page
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; 
$tmp2 = realpath(__FILE__); 
$i = strlen($tmp) - 1; 
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once '../lib/supplierreturn.lib.php';
require_once '../class/supplierreturn.class.php';

// Translations
$langs->loadLangs(array("admin", "errors", "supplierreturn@supplierreturn", "other"));

// Parameters
$action = GETPOST('action', 'aZ09');
$value = GETPOST('value', 'alpha');
$modulepart = GETPOST('modulepart', 'aZ09');
$tab = GETPOST('tab', 'aZ09') ? GETPOST('tab', 'aZ09') : 'general';

$label = GETPOST('label', 'alpha');
$scandir = GETPOST('scan_dir', 'alpha');
$type = 'supplierreturn';

$error = 0;

// Security check
if (!$user->admin) {
    accessforbidden();
}

// Initialize both supplierreturn and supplierreturns configurations for document access
if (empty($conf->supplierreturn)) {
    $conf->supplierreturn = new stdClass();
    $conf->supplierreturn->enabled = 1;
    $conf->supplierreturn->dir_output = DOL_DATA_ROOT.'/supplierreturn';
}

if (empty($conf->supplierreturns)) {
    $conf->supplierreturns = new stdClass();
    $conf->supplierreturns->enabled = 1;
    $conf->supplierreturns->dir_output = DOL_DATA_ROOT.'/supplierreturn';
}

/*
 * Actions
 */

// Standard module options processing
include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';

// Set default warehouse
if ($action == 'set_default_warehouse') {
    $default_warehouse_id = GETPOSTINT('default_warehouse_id');

    $res = dolibarr_set_const($db, 'SUPPLIERRETURN_DEFAULT_WAREHOUSE_ID', $default_warehouse_id, 'chaine', 0, '', $conf->entity);

    if ($res > 0) {
        setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
    } else {
        setEventMessages($langs->trans("Error"), null, 'errors');
    }
}

// Update numbering mask
if ($action == 'updateMask') {
    $maskconstorder = GETPOST('maskconstorder', 'aZ09');
    $maskorder = GETPOST('maskorder', 'alpha');

    if ($maskconstorder) {
        $res = dolibarr_set_const($db, $maskconstorder, $maskorder, 'chaine', 0, '', $conf->entity);
        
        if ($res > 0) {
            setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
        } else {
            setEventMessages($langs->trans("Error"), null, 'errors');
        }
    } else {
        setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Mask")), null, 'errors');
    }
}

// Set numbering module
if ($action == 'setmod') {
    // Check if numbering module chosen can be activated
    $found = false;
    
    // Check custom module path first
    $file = dirname(__FILE__).'/../core/modules/supplierreturn/'.$value.'.php';
    if (file_exists($file)) {
        $found = true;
    } else {
        // Check standard Dolibarr paths
        if (isset($conf->modules_parts['models']) && is_array($conf->modules_parts['models'])) {
            foreach ($conf->modules_parts['models'] as $reldir) {
                $file = dol_buildpath($reldir."core/modules/supplierreturn/".$value.".php", 0);
                if (file_exists($file)) {
                    $found = true;
                    break;
                }
            }
        }
    }
    
    if ($found) {
        $res = dolibarr_set_const($db, "SUPPLIERRETURN_ADDON", $value, 'chaine', 0, '', $conf->entity);
        if ($res > 0) {
            setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
        } else {
            setEventMessages($langs->trans("Error"), null, 'errors');
        }
    } else {
        setEventMessages($langs->trans("ErrorModuleNotFound"), null, 'errors');
    }
}

// Set document model
if ($action == 'setdoc') {
    // Check if model can be activated
    $found = false;
    
    // Check custom module PDF path first
    $file = dirname(__FILE__).'/../core/modules/supplierreturn/pdf/pdf_'.$value.'.php';
    if (file_exists($file)) {
        $found = true;
    } else {
        // Check standard Dolibarr paths
        if (isset($conf->modules_parts['models']) && is_array($conf->modules_parts['models'])) {
            foreach ($conf->modules_parts['models'] as $reldir) {
                $file = dol_buildpath($reldir."core/modules/supplierreturn/pdf/pdf_".$value.".php", 0);
                if (file_exists($file)) {
                    $found = true;
                    break;
                }
            }
        }
    }
    
    if ($found) {
        dolibarr_set_const($db, "SUPPLIERRETURN_ADDON_PDF", $value, 'chaine', 0, '', $conf->entity);
        setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
    } else {
        setEventMessages($langs->trans("ErrorModuleNotFound"), null, 'errors');
    }
}

// Generate specimen document
if ($action == 'specimen') {
    $modele = GETPOST('module', 'alpha');
    $tmpobject = new SupplierReturn($db);
    $tmpobject->initAsSpecimen();

    // Define output language
    $outputlangs = $langs;
    if ($conf->global->MAIN_MULTILANGS) {
        $outputlangs = new Translate("", $conf);
        $outputlangs->setDefaultLang($conf->global->MAIN_LANG_DEFAULT);
    }

    // Call the modules - check custom path first
    $file = dirname(__FILE__).'/../core/modules/supplierreturn/pdf/pdf_'.$modele.'.php';
    if (file_exists($file)) {
        $classname = "pdf_".$modele;
        require_once $file;

        $module = new $classname($db);
        '@phan-var-force pdf_standard $module';

        if ($module->write_file($tmpobject, $outputlangs) > 0) {
            // Direct link to the generated PDF file
            $filepath = $conf->supplierreturn->dir_output.'/SPECIMEN.pdf';
            if (file_exists($filepath)) {
                header("Location: ".dol_buildpath('/custom/supplierreturn/viewdoc.php', 1)."?modulepart=supplierreturn&file=SPECIMEN.pdf");
            } else {
                setEventMessages($langs->trans("ErrorFileGenerated")." : ".$filepath, null, 'errors');
            }
            return;
        }
    } else {
        // Check standard Dolibarr paths
        if (isset($conf->modules_parts['models']) && is_array($conf->modules_parts['models'])) {
            foreach ($conf->modules_parts['models'] as $reldir) {
                $file = dol_buildpath($reldir."core/modules/supplierreturn/pdf/pdf_".$modele.".php", 0);
                if (file_exists($file)) {
                    $classname = "pdf_".$modele;
                    require_once $file;

                    $module = new $classname($db);
                    '@phan-var-force pdf_standard $module';

                    if ($module->write_file($tmpobject, $outputlangs) > 0) {
                        // Direct link to the generated PDF file
                        $filepath = $conf->supplierreturn->dir_output.'/SPECIMEN.pdf';
                        if (file_exists($filepath)) {
                            header("Location: ".dol_buildpath('/custom/supplierreturn/viewdoc.php', 1)."?modulepart=supplierreturn&file=SPECIMEN.pdf");
                        } else {
                            setEventMessages($langs->trans("ErrorFileGenerated")." : ".$filepath, null, 'errors');
                        }
                        return;
                    }
                    break;
                }
            }
        }
    }

    setEventMessages($langs->trans("ErrorModuleNotFound"), null, 'errors');
}

/*
 * View
 */

$title = $langs->trans('SupplierReturnsSetup');

llxHeader('', $title);

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($title, $linkback, 'title_setup');

$head = supplierreturnAdminPrepareHead();
print dol_get_fiche_head($head, $tab, $title, -1, 'supplierreturn@supplierreturn');

$form = new Form($db);

/*
 * Tab content (all combined in general tab like commande module)
 */

if ($tab == 'general') {
    /*
     * Numbering models
     */
    print load_fiche_titre($langs->trans("SupplierReturnsNumberingModules"), '', '');

    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<td>'.$langs->trans("Name").'</td>';
    print '<td>'.$langs->trans("Description").'</td>';
    print '<td class="nowrap">'.$langs->trans("Example").'</td>';
    print '<td class="center" width="60">'.$langs->trans("Status").'</td>';
    print '<td class="center" width="16">'.$langs->trans("ShortInfo").'</td>';
    print '</tr>'."\n";

    clearstatcache();

    // DEBUG: Show what we're looking for
    // Try direct path first
    $modules_found = 0;
    $dirs_to_check = array();
    $processed_modules = array(); // Track processed modules to avoid duplicates
    
    // Add custom module path
    $dirs_to_check[] = dirname(__FILE__).'/../core/modules/supplierreturn/';
    
    // Add standard Dolibarr paths if they exist
    if (isset($conf->modules_parts['models']) && is_array($conf->modules_parts['models'])) {
        foreach ($conf->modules_parts['models'] as $reldir) {
            $dirs_to_check[] = dol_buildpath($reldir."core/modules/supplierreturn/", 0);
        }
    }
    
    foreach ($dirs_to_check as $dir) {
        
        if (is_dir($dir)) {
            $handle = opendir($dir);
            if (is_resource($handle)) {
                while (($file = readdir($handle)) !== false) {
                    // Only numbering modules: mod_***.php, exclude PDF and helper files
                    if (substr($file, 0, 19) == 'mod_supplierreturn_' && 
                        substr($file, dol_strlen($file) - 3, 3) == 'php' &&
                        strpos($file, 'pdf_') === false &&
                        strpos($file, '.modules.') === false) {
                        $file = substr($file, 0, dol_strlen($file) - 4);
                        
                        // Check if already processed to avoid duplicates
                        if (isset($processed_modules[$file])) {
                            continue;
                        }
                        $processed_modules[$file] = true;
                        
                        try {
                            require_once $dir.$file.'.php';
                            
                            if (class_exists($file)) {
                                $module = new $file($db);
                                
                                if (method_exists($module, 'isEnabled') && $module->isEnabled()) {
                                    $modules_found++;
                                    $module_name = $module->nom ?? $module->name ?? $file;
                                    print '<tr class="oddeven"><td>'.$module_name."</td><td>\n";
                                    print $module->info($langs);
                                    print '</td>';

                                    // Show example
                                    print '<td class="nowrap">';
                                    $tmp = $module->getExample();
                                    if (preg_match('/^Error/', $tmp)) {
                                        print '<div class="error">'.$langs->trans($tmp).'</div>';
                                    } else {
                                        print $tmp;
                                    }
                                    print '</td>'."\n";

                                    print '<td class="center">';
                                    $active_module = getDolGlobalString('SUPPLIERRETURN_ADDON', 'mod_supplierreturn_standard');
                                    if ($active_module == $file) {
                                        print img_picto($langs->trans("Activated"), 'switch_on');
                                    } else {
                                        print '<a href="'.$_SERVER["PHP_SELF"].'?action=setmod&token='.newToken().'&value='.urlencode($file).'&tab='.$tab.'">';
                                        print img_picto($langs->trans("Disabled"), 'switch_off');
                                        print '</a>';
                                    }
                                    print '</td>';

                                    // Info tooltip
                                    print '<td class="center">';
                                    $htmltooltip = $langs->trans("Version").': '.$module->getVersion();
                                    print $form->textwithpicto('', $htmltooltip, 1, 0);
                                    print '</td>';
                                    print "</tr>\n";
                                }
                            }
                        } catch (Exception $e) {
                            // Skip modules that can't be loaded
                            dol_syslog("Error loading module $file: ".$e->getMessage(), LOG_WARNING);
                        }
                    }
                }
                closedir($handle);
            }
        }
    }

    if ($modules_found == 0) {
        print '<tr><td colspan="5" class="center opacitymedium">';
        print $langs->trans('NoNumberingModuleFound');
        // Debug info for admin
        if (getDolGlobalString('MAIN_FEATURES_LEVEL') >= 2) {
            print '<br><small>Debug: Searched in '.implode(', ', $dirmodels).'</small>';
        }
        print '</td></tr>';
    }

    print '</table>';
    print '</div>';
    print '<br>';
    
    // Show current mask configuration if active module supports it
    $active_module = getDolGlobalString('SUPPLIERRETURN_ADDON', 'mod_supplierreturn_standard');
    if ($active_module && file_exists(dol_buildpath("custom/supplierreturn/core/modules/supplierreturn/".$active_module.".php", 0))) {
        $mask = getDolGlobalString('SUPPLIERRETURN_ADDON_NUMBER', 'RF{yy}{mm}-{####}');
        
        print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
        print '<input type="hidden" name="token" value="'.newToken().'">';
        print '<input type="hidden" name="action" value="updateMask">';
        print '<input type="hidden" name="tab" value="'.$tab.'">';
        
        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre">';
        print '<td>'.$langs->trans("Parameter").'</td>';
        print '<td>'.$langs->trans("Value").'</td>';
        print '<td class="center">'.$langs->trans("Action").'</td>';
        print '</tr>';
        
        print '<tr class="oddeven">';
        print '<td>'.$langs->trans("NumberingMask").'</td>';
        print '<td>';
        print '<input type="hidden" name="maskconstorder" value="SUPPLIERRETURN_ADDON_NUMBER">';
        print '<input type="text" class="flat minwidth200" name="maskorder" value="'.dol_escape_htmltag($mask).'">';
        print '</td>';
        print '<td class="center">';
        print '<input type="submit" class="button button-edit small" value="'.$langs->trans("Modify").'">';
        print '</td>';
        print '</tr>';
        
        print '</table>';
        print '</form>';
        print '<br>';
    }

    /*
     * Document templates generators
     */
    print load_fiche_titre($langs->trans("PDFModels"), '', '');
    
    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">'."\n";

    print '<tr class="liste_titre">'."\n";
    print '<td>'.$langs->trans("Name").'</td>'."\n";
    print '<td>'.$langs->trans("Description").'</td>'."\n";
    print '<td class="center" width="60">'.$langs->trans("Status").'</td>';
    print '<td class="center" width="60">'.$langs->trans("Default").'</td>';
    print '<td class="center" width="38">'.$langs->trans("ShortInfo").'</td>';
    print '<td class="center" width="38">'.$langs->trans("Preview").'</td>';
    print "</tr>\n";

    
    $pdf_found = 0;
    $pdf_dirs_to_check = array();
    $processed_pdf_modules = array(); // Track processed PDF modules to avoid duplicates
    
    // Add custom module PDF path
    $pdf_dirs_to_check[] = dirname(__FILE__).'/../core/modules/supplierreturn/pdf/';
    
    // Add standard Dolibarr PDF paths if they exist
    if (isset($conf->modules_parts['models']) && is_array($conf->modules_parts['models'])) {
        foreach ($conf->modules_parts['models'] as $reldir) {
            $pdf_dirs_to_check[] = dol_buildpath($reldir."core/modules/supplierreturn/pdf/", 0);
        }
    }
    
    foreach ($pdf_dirs_to_check as $dir) {
        if (is_dir($dir)) {
            $handle = opendir($dir);
            if (is_resource($handle)) {
                while (($file = readdir($handle)) !== false) {
                    // Only PDF templates: pdf_***.php, exclude helper/parent classes
                    if (substr($file, 0, 4) == 'pdf_' && 
                        substr($file, dol_strlen($file) - 3, 3) == 'php' &&
                        strpos($file, '.modules.') === false &&
                        strpos($file, 'modules_') === false) {
                        $file = substr($file, 0, dol_strlen($file) - 4);
                        
                        // Check if already processed to avoid duplicates
                        if (isset($processed_pdf_modules[$file])) {
                            continue;
                        }
                        $processed_pdf_modules[$file] = true;
                        
                        
                        try {
                            require_once $dir.$file.'.php';
                            
                            if (class_exists($file)) {
                                $module = new $file($db);
                            } else {
                                continue;
                            }
                        } catch (Exception $e) {
                            dol_syslog("Error requiring PDF file $file: " . $e->getMessage(), LOG_ERR);
                            dol_print_error(null, "Error loading PDF template $file: " . $e->getMessage());
                            continue;
                        }
                        
                        if (!method_exists($module, 'isEnabled')) {
                            continue;
                        }
                        
                        if (!$module->isEnabled()) {
                            continue;
                        }
                        
                        
                        $pdf_found++;
                        $selected_model = getDolGlobalString('SUPPLIERRETURN_ADDON_PDF', 'standard');
                        $isSelected = ($module->name == $selected_model);
                        
                        
                        print '<tr class="oddeven"><td>';
                        print dol_escape_htmltag($module->name);
                        print "</td><td>";
                        print dol_escape_htmltag($module->description);
                        print '</td>';
                        print '<td class="center">';
                        if ($isSelected) {
                            print img_picto($langs->trans("Enabled"), 'switch_on');
                        } else {
                            print '<a href="'.$_SERVER["PHP_SELF"].'?action=setdoc&token='.newToken().'&value='.urlencode($module->name).'&tab='.$tab.'">';
                            print img_picto($langs->trans("Disabled"), 'switch_off');
                            print '</a>';
                        }
                        print '</td>';
                        print '<td class="center">';
                        if ($isSelected) {
                            print img_picto($langs->trans("Default"), 'on');
                        } else {
                            print '-';
                        }
                        print '</td>';
                        print '<td class="center">';
                        $htmltooltip = $langs->trans("Name").': '.dol_escape_htmltag($module->name).'<br>';
                        $htmltooltip .= $langs->trans("Type").': PDF';
                        print $form->textwithpicto('', $htmltooltip, 1, 0);
                        print '</td>';
                        print '<td class="center">';
                        print '<a href="'.$_SERVER["PHP_SELF"].'?action=specimen&module='.urlencode($module->name).'&tab='.$tab.'">'.img_object($langs->trans("PreviewDoc"), 'pdf').'</a>';
                        print '</td>';
                        print '</tr>';
                    }
                }
                closedir($handle);
            }
        }
    }
    
    if ($pdf_found == 0) {
        print '<tr><td colspan="6" class="center opacitymedium">';
        print $langs->trans('NoPDFTemplateFound');
        print '</td></tr>';
    }

    print '</table>';
    print '</div>';
    print '<br>';


    /*
     * Warehouse settings
     */
    print load_fiche_titre($langs->trans("WarehouseSettings"), '', '');

    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="set_default_warehouse">';
    print '<input type="hidden" name="tab" value="'.$tab.'">';

    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<td>'.$langs->trans("Parameter").'</td>';
    print '<td>'.$langs->trans("Value").'</td>';
    print '<td class="center">'.$langs->trans("Action").'</td>';
    print '</tr>';

    print '<tr class="oddeven">';
    print '<td>'.$langs->trans("DefaultWarehouse").'</td>';
    print '<td>';

    require_once DOL_DOCUMENT_ROOT . '/product/class/html.formproduct.class.php';
    $formproduct = new FormProduct($db);
    $selected_warehouse = getDolGlobalString('SUPPLIERRETURN_DEFAULT_WAREHOUSE_ID');

    print $formproduct->selectWarehouses($selected_warehouse, 'default_warehouse_id', '', 1, 0, 0, null, 0, 0, null, 'minwidth200');

    print '</td>';
    print '<td class="center">';
    print '<input type="submit" class="button button-edit small" value="'.$langs->trans("Modify").'">';
    print '</td>';
    print '</tr>';

    print '</table>';
    print '</form>';
    print '<br>';


} elseif ($tab == 'extrafields') {
    // Extrafields tab
    print '<div class="center">';
    print '<a href="../admin/supplierreturn_extrafields.php">'.$langs->trans("ConfigureExtraFields").'</a>';
    print '</div>';
    
} elseif ($tab == 'extrafields_lines') {
    // Extrafields lines tab  
    print '<div class="center">';
    print '<a href="../admin/supplierreturnline_extrafields.php">'.$langs->trans("ConfigureExtraFieldsLines").'</a>';
    print '</div>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();