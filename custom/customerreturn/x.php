<?php
/*
 * Test script to check PDF model path
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) $res = @include __DIR__.'/../../main.inc.php';
if (!$res) die("Include of main fails");

require_once __DIR__.'/class/customerreturn.class.php';

$langs->loadLangs(array("customerreturn@customerreturn", "other"));

// --- Test Setup ---
$return_id = 1; // Change this to a valid customer return ID
$model = 'standard';

// --- Execution ---
$object = new CustomerReturn($db);
$result = $object->fetch($return_id);

if ($result <= 0) {
    echo "Error: Could not fetch customer return with ID: $return_id\n";
    exit(1);
}

echo "Successfully fetched customer return: " . $object->ref . "\n";
echo "Attempting to generate PDF with model: $model\n";

// --- Path to test ---
// The user mentioned that the original instructions pointed to a core module path.
// Let's try to replicate the structure of a core module path, but for a custom module.
// The core path is 'core/modules/propale/doc/'. Let's try a similar structure.
$modelpath = 'customerreturn/core/modules/customerreturn/pdf';

echo "Testing model path: " . $modelpath . "\n";

$outputlangs = $langs;
$result = $object->commonGenerateDocument($modelpath, $model, $outputlangs, 0, 0, 0);

// --- Verification ---
if ($result <= 0) {
    echo "Error: PDF generation failed.\n";
    if (!empty($object->error)) {
        echo "Error details: " . $object->error . "\n";
    }
    if (!empty($object->errors)) {
        echo "Errors array:\n";
        print_r($object->errors);
    }
} else {
    echo "Success: PDF generated successfully.\n";
    echo "Generated file: " . $result . "\n";
}

$db->close();
