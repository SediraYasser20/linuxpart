<?php

require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/productlot.class.php';

class pdf_lotbarcode
{
    public $name = 'lotbarcode';
    public $description = 'PDF avec uniquement le code-barres du numéro de lot';

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function write_file($object, $outputlangs)
    {
        global $conf;

        if (!is_object($object)) {
            return ['error' => 'Objet lot invalide'];
        }

        if (empty($object->batch)) {
            return ['error' => 'Numéro de lot vide ou non défini'];
        }

        if (!$this->isValidForC128($object->batch)) {
            return ['error' => 'Caractères non valides pour Code128B'];
        }

        $pdf = pdf_getInstance();
        if (!is_object($pdf)) {
            return ['error' => 'Impossible d\'initialiser le PDF'];
        }

        // Utiliser millimètres pour plus de clarté
        $pdf->SetAutoPageBreak(false);
        $pdf->setPageUnit('mm');
        $pdf->SetMargins(1, 1, 1); // Petits marges
        $pdf->AddPage('L', array(40, 20)); // 40mm x 20mm paysage

        // Style du code-barres
        $style = array(
            'align' => 'C',
            'stretch' => false,
            'fitwidth' => true,
            'fgcolor' => array(0, 0, 0),
            'bgcolor' => false,
            'text' => true, // afficher texte sous الباركود
            'font' => 'helvetica',
            'fontsize' => 6,
            'stretchtext' => 4
        );

        try {
            // Générer le code-barres (centré)
            $pdf->write1DBarcode($object->batch, 'C128B', '', '', 38, 16, 0.4, $style, 'C');
        } catch (Exception $e) {
            return ['error' => 'Erreur lors de la génération du code-barres : ' . $e->getMessage()];
        }

        // Répertoire et nom du fichier
        $filedir = dol_osencode($conf->product->dir_output . "/lot");
        dol_mkdir($filedir);

        if (!is_writable($filedir)) {
            return ['error' => 'Le répertoire n\'est pas accessible en écriture : ' . $filedir];
        }

        $filename = "lot_current.pdf";
        $filepath = $filedir . "/" . $filename;

        $pdf->Output($filepath, 'F');

        if (!file_exists($filepath) || filesize($filepath) == 0) {
            return ['error' => 'Le fichier PDF n\'a pas été créé ou est vide'];
        }

        $pdf->Close();

        return ['filename' => $filename, 'filepath' => $filepath];
    }

    private function isValidForC128($batch)
    {
        // Vérifie que tous les caractères sont valides pour le Code128B
        return preg_match('/^[\x20-\x7E]+$/', $batch);
    }
}

