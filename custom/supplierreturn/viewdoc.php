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
 * \file    viewdoc.php
 * \ingroup supplierreturn
 * \brief   Document viewer/downloader for supplier returns
 */

// Load Dolibarr environment  
// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
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

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

// Get parameters
$modulepart = GETPOST('modulepart', 'aZ09');
$original_file = GETPOST('file', 'alpha');
$entity = GETPOST('entity', 'int');

// URL decode the file parameter
$original_file = urldecode($original_file);

dol_syslog("SupplierReturns viewdoc: modulepart=$modulepart, file=$original_file, entity=$entity", LOG_INFO);

// Security check
if (!$user->hasRight('supplierreturn', 'lire')) {
    accessforbidden();
}

// Validate modulepart - accept both 'supplierreturn' and 'supplierreturns'
if ($modulepart !== 'supplierreturn' && $modulepart !== 'supplierreturns') {
    print "Error: Invalid modulepart. Expected 'supplierreturn' or 'supplierreturns', got '$modulepart'";
    exit;
}

// Initialize module configuration if needed
if (empty($conf->supplierreturn)) {
    $conf->supplierreturn = new stdClass();
    $conf->supplierreturn->enabled = 1;
    $conf->supplierreturn->dir_output = DOL_DATA_ROOT.'/supplierreturn';
}

// Set up directories and file path
$upload_dir = $conf->supplierreturn->dir_output;

// Handle entity for multi-company
if (!empty($entity) && $entity != $conf->entity) {
    if ($user->admin && isModEnabled('multicompany')) {
        $upload_dir = DOL_DATA_ROOT.'/'.$entity.'/supplierreturn';
    } else {
        accessforbidden('Access to other entity not allowed');
    }
}

// Clean and validate file name - BUT preserve the directory separator!
// dol_sanitizeFileName converts '/' to '_', so we need to handle this differently
if (empty($original_file)) {
    print "Error: No file specified";
    exit;
}

// Keep the original path structure and only sanitize individual parts
$path_parts = explode('/', $original_file);
$sanitized_parts = array();
foreach ($path_parts as $part) {
    if (!empty($part)) {
        $sanitized_parts[] = dol_sanitizeFileName($part);
    }
}
$original_file = implode('/', $sanitized_parts);

dol_syslog("SupplierReturns viewdoc: After path-aware sanitization: $original_file", LOG_INFO);

// Handle malformed paths from showdocuments() - ONLY if it starts with underscore
$original_file_backup = $original_file; // Keep backup for logging
if (strpos($original_file, '_supplierreturn_') === 0) {
    // Extract the reference from malformed path like '_supplierreturn_RF2507-0001_RF2507-0001.pdf'
    $parts = explode('_', $original_file);
    if (count($parts) >= 4) {
        $ref = $parts[2]; // RF2507-0001
        $filename = end($parts); // RF2507-0001.pdf (last part)
        $original_file = $ref.'/'.$filename;
        dol_syslog("SupplierReturns viewdoc: Converted malformed path '$original_file_backup' to: $original_file", LOG_INFO);
    }
} else {
    dol_syslog("SupplierReturns viewdoc: Using normal path: $original_file", LOG_INFO);
}

// Remove leading slash if present
$original_file = ltrim($original_file, '/');

// Build full file path
if (strpos($original_file, '/') === false) {
    // File only, we need to find it in the correct subdirectory
    if ($original_file === 'SPECIMEN.pdf') {
        // SPECIMEN files are directly in the root directory
        $fullpath_original_file = $upload_dir.'/'.$original_file;
        dol_syslog("SupplierReturns viewdoc: SPECIMEN file, direct path: $fullpath_original_file", LOG_INFO);
    } else {
        // Try to extract reference from filename
        $filename_parts = explode('.', $original_file);
        if (count($filename_parts) >= 2) {
            $ref_guess = $filename_parts[0]; // RF2507-0001 from RF2507-0001.pdf
            $fullpath_original_file = $upload_dir.'/'.$ref_guess.'/'.$original_file;
            dol_syslog("SupplierReturns viewdoc: File only, guessed path: $fullpath_original_file", LOG_INFO);
        } else {
            $fullpath_original_file = $upload_dir.'/'.$original_file;
            dol_syslog("SupplierReturns viewdoc: File only, direct path: $fullpath_original_file", LOG_INFO);
        }
    }
} else {
    $fullpath_original_file = $upload_dir.'/'.$original_file;
    dol_syslog("SupplierReturns viewdoc: Path with directory: $fullpath_original_file", LOG_INFO);
}

// For specimen or direct files in root
if (!file_exists($fullpath_original_file)) {
    dol_syslog("SupplierReturns viewdoc: File not found at: $fullpath_original_file", LOG_WARNING);
    
    // Try to find the file in different locations for debugging
    $debug_paths = array();
    $upload_dir_real = realpath($upload_dir);
    if ($upload_dir_real && is_dir($upload_dir_real)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($upload_dir_real));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'pdf') {
                $debug_paths[] = $file->getPathname();
            }
        }
    }
    
    $error_msg = "Error: File not found: ".$fullpath_original_file;
    if (!empty($debug_paths)) {
        $error_msg .= "\nAvailable PDF files in directory:";
        foreach ($debug_paths as $path) {
            $error_msg .= "\n- " . $path;
        }
    }
    
    print $error_msg;
    exit;
}

// Security checks
if (!is_readable($fullpath_original_file)) {
    print "Error: File not readable";
    exit;
}

// Check if file is outside allowed directory (security)
$upload_dir_real = realpath($upload_dir);
$file_real = realpath($fullpath_original_file);

if ($upload_dir_real === false || $file_real === false || strpos($file_real, $upload_dir_real) !== 0) {
    print "Error: Security violation - file outside allowed directory";
    exit;
}

// Determine MIME type
$mime_type = dol_mimetype($fullpath_original_file);

// Set appropriate headers with cache control to ensure fresh content
header('Content-Description: File Transfer');
header('Content-Type: '.$mime_type);
header('Content-Disposition: inline; filename="'.basename($original_file).'"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($fullpath_original_file)).' GMT');
header('Content-Length: '.filesize($fullpath_original_file));

// Clean output buffer
if (ob_get_level()) {
    ob_end_clean();
}

// Output file
readfile($fullpath_original_file);

// Log access
dol_syslog("SupplierReturns: Document accessed: ".$original_file." by user ".$user->id, LOG_INFO);

exit;
?>