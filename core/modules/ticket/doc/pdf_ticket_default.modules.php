<?php
/* Custom PDF Ticket Template - SAV Informatics DZ */

require_once DOL_DOCUMENT_ROOT.'/core/modules/ticket/modules_ticket.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php'; // For linked orders

class pdf_ticket_default extends ModelePDFTicket
{
    public $db;
    public $name;
    public $description;
    public $type;
    public $update_main_doc_field;
    private $headerImageHeight = 0; // Initialize to handle undefined variable

    public function __construct(DoliDB $db)
    {
        global $langs, $mysoc;

        $langs->loadLangs(array("main", "bills", "products", "ticket"));

        $this->db = $db;
        $this->name = "ticket_default";
        $this->description = $langs->trans('PDFTicketDefaultDescription');
        $this->update_main_doc_field = 1;

        $formatarray = pdf_getFormat();
        $this->page_largeur = $formatarray['width'];
        $this->page_hauteur = $formatarray['height'];
        $this->format = array($this->page_largeur, $this->page_hauteur);
        $this->marge_gauche = 12;
        $this->marge_droite = 12;
        $this->marge_haute = 35;
        $this->marge_basse = 20;

        $this->emetteur = $mysoc;
    }

    public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
    {
        global $user, $conf, $langs;

        if (!is_object($outputlangs)) $outputlangs = $langs;
        $outputlangs->loadLangs(array("main", "companies", "ticket"));

        // Directory for PDF
        $dir = $conf->ticket->dir_output;
        if (!empty($conf->ticket->multidir_output[$object->entity])) $dir = $conf->ticket->multidir_output[$object->entity];

        $objectref = dol_sanitizeFileName($object->ref);
        $dir .= "/".$objectref;
        $file = $dir."/".$objectref.".pdf";

        if (!file_exists($dir)) dol_mkdir($dir);

        // Load linked sales order if available
        $commande = null;
        if (!empty($object->commande)) {
            $commande = new Commande($this->db);
            if ($commande->fetch($object->commande) <= 0) {
                $commande = null;
            }
        }

        // Load linked objects
        $object->fetchObjectLinked();

        // PDF Init
        $pdf = pdf_getInstance($this->format);
        $pdf->SetAutoPageBreak(true, $this->marge_basse);
        if (class_exists('TCPDF')) {
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            if (method_exists($pdf, 'setFontSubsetting')) {
                $pdf->setFontSubsetting(true);
            }
        }

        // Font handling
        $fontPathRegular = DOL_DOCUMENT_ROOT . '/public/ticket/fonts/NotoKufiArabic-Regular.ttf';
        $fontRegular = 'dejavusans'; // Fallback
        if (file_exists($fontPathRegular) && is_readable($fontPathRegular)) {
            try {
                $fontRegular = TCPDF_FONTS::addTTFfont($fontPathRegular, 'TrueTypeUnicode', '', 32);
                if ($fontRegular === false) {
                    error_log("Failed to add NotoKufiArabic-Regular font");
                    $fontRegular = 'dejavusans';
                }
            } catch (Exception $e) {
                error_log("Font loading error: " . $e->getMessage());
                $fontRegular = 'dejavusans';
            }
        }

        $fontPathBold = DOL_DOCUMENT_ROOT . '/public/ticket/fonts/NotoKufiArabic-Bold.ttf';
        $fontBold = 'dejavusans'; // Fallback
        if (file_exists($fontPathBold) && is_readable($fontPathBold)) {
            try {
                $fontBold = TCPDF_FONTS::addTTFfont($fontPathBold, 'TrueTypeUnicode', '', 32);
                if ($fontBold === false) {
                    error_log("Failed to add NotoKufiArabic-Bold font");
                    $fontBold = 'dejavusans';
                }
            } catch (Exception $e) {
                error_log("Bold font loading error: " . $e->getMessage());
                $fontBold = 'dejavusans';
            }
        }

        $pdf->SetFont($fontRegular, '', 9);
        $pdf->Open();
        $pdf->AddPage();

        $this->headerImageHeight = $this->_pagehead($pdf, $object, $outputlangs); // Set header height from _pagehead

        $posy = 8 + $this->headerImageHeight + 8; // Use class property for positioning

        if (method_exists($pdf, 'setRTL')) {
            $pdf->setRTL(true);
        }

        // ==============================
        // TABLEAU INFOS CLIENT
        // ==============================
        $html = '<div style="margin-bottom: 3px;">
            <h2 style="font-family: ' . $fontBold . '; font-size: 12px; color: #0051A4; margin: 0; padding: 0; font-weight: bold; letter-spacing: 0.3px;">INFORMATIONS CLIENT</h2>
        </div>
        <table border="1" cellpadding="5" cellspacing="0" width="100%" style="border-collapse: collapse; font-family: ' . $fontRegular . '; font-size: 8.5px; margin-bottom: 5px;">
            <tr style="background-color: #f8f9fa;">
                <td width="28%" style="font-weight: bold; color: #2c3e50; border: 1px solid #dee2e6;">Nom du client</td>
                <td width="72%" style="color: #495057; border: 1px solid #dee2e6;">'.dol_escape_htmltag($object->thirdparty->name).'</td>
            </tr>
            <tr>
                <td style="font-weight: bold; color: #2c3e50; border: 1px solid #dee2e6;">Téléphone</td>
                <td style="color: #495057; border: 1px solid #dee2e6;">'.dol_escape_htmltag($object->thirdparty->phone).'</td>
            </tr>
            <tr style="background-color: #f8f9fa;">
                <td style="font-weight: bold; color: #2c3e50; border: 1px solid #dee2e6;">Email</td>
                <td style="color: #495057; border: 1px solid #dee2e6;">'.dol_escape_htmltag($object->thirdparty->email).'</td>
            </tr>
            <tr>
                <td style="font-weight: bold; color: #2c3e50; border: 1px solid #dee2e6;">Date & Heure</td>
                <td style="color: #495057; border: 1px solid #dee2e6;">'.dol_print_date($object->datec, "dayhour", false, $outputlangs, true).'</td>
            </tr>';

        // Add linked order if exists
        if ($commande) {
            $html .= '
            <tr style="background-color: #f8f9fa;">
                <td style="font-weight: bold; color: #2c3e50; border: 1px solid #dee2e6;">Commande liée</td>
                <td style="color: #495057; border: 1px solid #dee2e6;">'.dol_escape_htmltag($commande->ref).'</td>
            </tr>';
        }

        $html .= '</table>';

        // ==============================
        // TABLEAU SUJET + DESCRIPTION
        // ==============================
        $html .= '<div style="margin-bottom: 3px; margin-top: 6px;">
            <h2 style="font-family: ' . $fontBold . '; font-size: 12px; color: #0051A4; margin: 0; padding: 0; font-weight: bold; letter-spacing: 0.3px;">RÉCLAMATION CLIENT</h2>
        </div>
        <table border="1" cellpadding="5" cellspacing="0" width="100%" style="border-collapse: collapse; font-family: ' . $fontRegular . '; font-size: 8.5px; margin-bottom: 6px;">
            <tr style="background-color: #f8f9fa;">
                <td width="28%" style="font-weight: bold; color: #2c3e50; border: 1px solid #dee2e6;">Sujet</td>
                <td width="72%" style="color: #495057; border: 1px solid #dee2e6;">'.dol_escape_htmltag($object->subject).'</td>
            </tr>
            <tr>
                <td style="font-weight: bold; color: #2c3e50; border: 1px solid #dee2e6; vertical-align: top;">Description</td>
                <td style="color: #495057; border: 1px solid #dee2e6; line-height: 1.3;">'.dol_escape_htmltag($object->message).'</td>
            </tr>
        </table>';

        // ==============================
        // OBJETS LIÉS
        // ==============================
        if (!empty($object->linkedObjects)) {
            $html .= '<div style="margin-bottom: 3px; margin-top: 6px;">
                <h2 style="font-family: ' . $fontBold . '; font-size: 12px; color: #0051A4; margin: 0; padding: 0; font-weight: bold; letter-spacing: 0.3px;">OBJETS LIÉS</h2>
            </div>
            <table border="1" cellpadding="5" cellspacing="0" width="100%" style="border-collapse: collapse; font-family: ' . $fontRegular . '; font-size: 8.5px; margin-bottom: 6px;">
                <tr style="background-color: #f8f9fa;">
                    <td width="40%" style="font-weight: bold; color: #2c3e50; border: 1px solid #dee2e6;">Type</td>
                    <td width="60%" style="font-weight: bold; color: #2c3e50; border: 1px solid #dee2e6;">Référence</td>
                </tr>';

            foreach ($object->linkedObjects as $element => $list) {
                foreach ($list as $linked) {
                    $html .= '
                    <tr>
                        <td style="color: #495057; border: 1px solid #dee2e6;">'.ucfirst($element).'</td>
                        <td style="color: #495057; border: 1px solid #dee2e6;">'.dol_escape_htmltag($linked->ref).'</td>
                    </tr>';
                }
            }

            $html .= '</table>';
        }

        // ==============================
        // CONDITIONS SAV
        // ==============================
        $html .= '<div style="margin-top: 12px; padding: 5px; border-right: 3px solid #0051A4; background-color: #f8f9fa; direction: rtl; text-align: right;">
            <h3 style="font-family: ' . $fontBold . '; font-size: 10px; color: #0051A4; margin: 0 0 4px 0; font-weight: bold;">معلومات اضافية</h3>
            <div style="font-family: ' . $fontRegular . '; font-size: 8.5px; color: #2c3e50; line-height: 1.3;">
                <ul style="list-style-type: none; padding-right: 10px; margin: 0;">
                    <li style="margin: 3px 0; font-weight: bold;">
                        <span style="color: #0051A4; margin-left: 5px;">•</span>خدمة ما بعد البيع: <span style="color: #d63384;">0550 362 002</span>
                    </li>
                    <li style="margin: 3px 0;">
                        <span style="color: #0051A4; margin-left: 5px;">•</span>لن يتم تسليم أي منتج بدون هذا الإيصال.
                    </li>
                    <li style="margin: 3px 0;">
                        <span style="color: #0051A4; margin-left: 5px;">•</span>الشركة غير مسؤولة عن أي منتج يُترك لمدة شهر أو أكثر.
                    </li>
                    <li style="margin: 3px 0;">
                        <span style="color: #0051A4; margin-left: 5px;">•</span>ساعات العمل من <span style="font-weight: bold;">09:30 صباحًا إلى 5:30 مساءً</span>.
                    </li>
                </ul>
            </div>
        </div>';

        $pdf->writeHTMLCell(0, 0, $this->marge_gauche, $posy, $html, 0, 1, false, true, 'R');

        $pdf->Close();
        $pdf->Output($file, 'F');
        dolChmod($file);
        $this->result = array('fullpath' => $file);

        return 1;
    }

    protected function _pagehead(&$pdf, $object, $outputlangs)
    {
        // Header image handling - using remote URL
        $remote_image_url = 'https://support.ipn-dz.com/public/ticket/images/header.png';
        $pageWidth = $pdf->getPageWidth();
        $leftMargin = 12;
        $printableWidth = $pageWidth - ($leftMargin * 2);

        // Full-bleed image across the page
        $imageX = 0;
        $imageWidth = $pageWidth;

        $maxHeightMm = 80;
        $headerImageHeight = 0;

        try {
            $imgInfo = @getimagesize($remote_image_url);
            if ($imgInfo !== false) {
                list($origW, $origH) = $imgInfo;
                if ($origW > 0) {
                    $ratio = $origH / $origW;
                    $computedHeight = $imageWidth * $ratio;
                    if ($computedHeight > $maxHeightMm) {
                        $pdf->Image($remote_image_url, $imageX, 8, 0, $maxHeightMm, '', '', 'C', true, 300, '', false, false, 0, false, false, false);
                        $headerImageHeight = $maxHeightMm;
                    } else {
                        $pdf->Image($remote_image_url, $imageX, 8, $imageWidth, 0, '', '', 'C', true, 300, '', false, false, 0, false, false, false);
                        $headerImageHeight = $computedHeight;
                    }
                } else {
                    $pdf->Image($remote_image_url, $imageX, 8, $imageWidth, 0, '', '', 'C', true, 300, '', false, false, 0, false, false, false);
                    $headerImageHeight = $maxHeightMm; // Default to max if dimensions unavailable
                }
            } else {
                $pdf->Image($remote_image_url, $imageX, 8, $imageWidth, 0, '', '', 'C', true, 300, '', false, false, 0, false, false, false);
                $headerImageHeight = $maxHeightMm; // Fallback to max height
            }
        } catch (Exception $e) {
            error_log("Remote image loading error: " . $e->getMessage());
            $pdf->Image($remote_image_url, $imageX, 8, $imageWidth, 0, '', '', 'C', true, 300, '', false, false, 0, false, false, false);
            $headerImageHeight = $maxHeightMm; // Fallback on error
        }

        // Line under header
        $pdf->SetDrawColor(0, 81, 164);
        $pdf->SetLineWidth(0.3);
        $pdf->Line($this->marge_gauche, 8 + $headerImageHeight, $this->page_largeur - $this->marge_droite, 8 + $headerImageHeight);

        return $headerImageHeight; // Return height to be used in write_file
    }
}
?>
