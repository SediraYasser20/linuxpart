<?php
// Check if PDF model is registered in database

// Recherche main.inc.php en remontant dans l'arborescence
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
    $i--;
    $j--;
}
$reldir = substr($tmp, 0, ($i + 1));
$subdir = substr($tmp2, 0, ($j + 1));
$reldir = substr($reldir, strlen($subdir));
$reldir = preg_replace('/[\\/]/', '/', $reldir);

// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $tmp = $_SERVER["CONTEXT_DOCUMENT_ROOT"];
    $tmp2 = realpath($_SERVER["CONTEXT_DOCUMENT_ROOT"]);
    if (file_exists($tmp."/main.inc.php")) {
        $res = include $tmp."/main.inc.php";
    } elseif (file_exists($tmp2."/main.inc.php")) {
        $res = include $tmp2."/main.inc.php";
    }
}
if (!$res && file_exists("../main.inc.php")) {
    $res = include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
    $res = include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
    $res = include "../../../../main.inc.php";
}

echo "Checking PDF model registration...\n";

$sql = "SELECT nom, type, libelle, description FROM ".MAIN_DB_PREFIX."document_model WHERE type = 'supplierreturn'";
$resql = $db->query($sql);

if ($resql) {
    $num = $db->num_rows($resql);
    echo "Found $num PDF models for supplierreturn:\n";
    
    if ($num > 0) {
        while ($obj = $db->fetch_object($resql)) {
            echo "- Name: {$obj->nom}, Label: {$obj->libelle}\n";
        }
    } else {
        echo "No PDF models found. Need to register standard model.\n";
        
        // Register the model
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."document_model (
            nom,
            type,
            libelle,
            description
        ) VALUES (
            'standard',
            'supplierreturn',
            'Standard supplier return template',
            'Default PDF template for supplier returns with company header, product lines and totals'
        )";
        
        $result = $db->query($sql);
        if ($result) {
            echo "PDF model registered successfully!\n";
        } else {
            echo "Error registering PDF model: " . $db->lasterror() . "\n";
        }
    }
} else {
    echo "Error querying database: " . $db->lasterror() . "\n";
}
?>