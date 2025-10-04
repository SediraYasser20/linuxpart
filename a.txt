<?php
/* Copyright (C) 2025 SuperAdmin
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    mymodule/core/modules/mrp/doc/pdf_mo_serial_label.modules.php
 * \ingroup mymodule
 * \brief   PDF template for printing serial number labels of produced products in MO
 */

dol_include_once('/mrp/class/mo.class.php');
dol_include_once('/core/modules/mrp/modules_mo.php');
dol_include_once('/core/lib/pdf.lib.php'); // Required for pdf_getInstance()
dol_include_once('/core/lib/functions2.lib.php'); // Required for isValidForC128 or dol_validate_barcode_value if available

class pdf_mo_serial_label extends ModelePDFMo
{
    /**
     * @var array Page format in millimeters (width, height)
     */
    public $format = array(40, 20); // 40 mm wide × 20 mm high (landscape)

    public function __construct($db)
    {
        parent::__construct($db);
        $this->name = 'pdf_mo_serial_label';
        $this->description = 'PDF template for printing serial number labels of produced products in MO';
    }

    /**
     * Generates the PDF file with serial number labels for produced products
     *
     * @param Object $object The MO object
     * @param Object $outputlangs Language object
     * @param string $srctemplatepath Source template path
     * @param int $hidedetails Hide details flag
     * @param int $hidedesc Hide description flag
     * @param int $hideref Hide reference flag
     * @return int 1 if successful, 0 if error
     */
    public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
    {
        global $langs;

        if (!is_object($outputlangs)) {
            $outputlangs = $langs;
        }
        $outputlangs->load("mymodule");

        // Create PDF in landscape mode
        $pdf = pdf_getInstance($this->format, 'mm', 'L');

        // Margins: 1 mm left/right, 1 mm top/bottom (adjusted for barcode)
        $marginH = 1; // horizontal margins
        $marginV = 1; // vertical margins
        $pdf->SetMargins($marginH, $marginV, $marginH);
        $pdf->SetAutoPageBreak(false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->setPageUnit('mm'); // Ensure units are set to mm

        // Barcode style - adjusted for the small label size
        $fontSize = 6;
        $style = array(
            'align' => 'C',
            'stretch' => false,
            'fitwidth' => true,
            'fgcolor' => array(0, 0, 0),
            'bgcolor' => false,
            'text' => true, // Enable text under the barcode
            'font' => 'helvetica',
            'fontsize' => $fontSize,
            'stretchtext' => 4,
            'padding' => 0 // Minimal padding
        );
        
        $barcodeW = $this->format[0] - 2 * $marginH; // 40 - 2 = 38 mm
        $barcodeH = 14; // Reduced height to leave space for text below

        // Fetch serial numbers of produced products only
        $sql = "SELECT DISTINCT mp.batch 
                FROM " . MAIN_DB_PREFIX . "mrp_production mp
                WHERE mp.fk_mo = " . ((int) $object->id) . "
                AND mp.role = 'produced' 
                AND mp.batch IS NOT NULL 
                AND mp.batch <> ''";
        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            return 0;
        }

        // Check if there are any results
        if ($this->db->num_rows($resql) == 0) {
            $this->error = "No produced product serial numbers found for MO ID " . $object->id;
            $this->db->free($resql);
            return 0;
        }

        // Generate a page for each serial number
        while ($row = $this->db->fetch_object($resql)) {
            $serial_number = $row->batch;

            // Optional: Check if the serial number is valid for Code128B
            if (function_exists('dol_validate_barcode_value')) {
                if (!dol_validate_barcode_value($serial_number, 'C128B')) {
                    dol_syslog("Invalid serial number for barcode: $serial_number", LOG_ERR);
                    continue; // Skip invalid serial numbers
                }
            }

            $pdf->AddPage();

            // Set X and Y coordinates for the barcode, slightly higher to make space for text
            $pdf->SetXY($marginH, $marginV); 

            try {
                // Generate the Code128B barcode
                $pdf->write1DBarcode(
                    $serial_number, 
                    'C128B', 
                    '', // X position (use current X)
                    '', // Y position (use current Y)
                    $barcodeW, 
                    $barcodeH, 
                    0.4, // X resolution (width of the thinnest bar)
                    $style, 
                    'C' // Alignment of the barcode in the cell
                );
            } catch (Exception $e) {
                // Log error and skip to next serial number
                dol_syslog("Error generating barcode for serial $serial_number: " . $e->getMessage(), LOG_ERR);
                continue;
            }
        }
        $this->db->free($resql);

        // Save PDF
        $dir = DOL_DATA_ROOT . '/mrp/' . dol_sanitizeFileName($object->ref);
        if (!file_exists($dir)) {
            dol_mkdir($dir);
        }
        $file = $dir . '/' . dol_sanitizeFileName($object->ref) . '_serial_labels.pdf';
        $pdf->Output($file, 'F');
        $pdf->Close(); // Important to close the PDF object

        return 1;
    }
}
