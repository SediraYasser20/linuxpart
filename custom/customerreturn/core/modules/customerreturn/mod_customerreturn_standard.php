<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/customerreturn/modules_customerreturn.php';
class mod_customerreturn_standard extends ModeleNumRefCustomerReturn
{
    public function getNextValue($object, $type)
    {
        global $db, $conf;
        $sql = "SELECT MAX(CAST(SUBSTRING(ref, 3) AS SIGNED)) as max FROM ".MAIN_DB_PREFIX."customerreturn WHERE ref LIKE 'RT%'";
        $resql = $db->query($sql);
        if ($resql) {
            $obj = $db->fetch_object($resql);
            $max = $obj->max ? $obj->max + 1 : 1;
            return 'RT'.sprintf("%04d", $max);
        }
        return -1;
    }
    public function info()
    {
        global $langs;
        return $langs->trans("SimpleNumRefModelDesc", 'RT');
    }
}
?>
