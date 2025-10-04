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
 * \file    core/modules/supplierreturn/pdf/pdf_standard.php
 * \ingroup supplierreturns
 * \brief   Class to generate supplier return PDF from standard template
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
dol_include_once('/custom/supplierreturn/core/modules/supplierreturn/modules_supplierreturn.php');

/**
 * Class to generate supplier return PDF using standard template
 */
class pdf_standard extends ModelePDFSupplierreturn
{
    /**
     * @var DoliDb Database handler
     */
    public $db;

    /**
     * @var string model name
     */
    public $name;

    /**
     * @var string model description
     */
    public $description;

    /**
     * @var int Save the name of generated file as the main doc
     */
    public $update_main_doc_field;

    /**
     * @var string document type
     */
    public $type;

    /**
     * @var string version
     */
    public $version = 'dolibarr';

    /**
     * @var int page width in mm
     */
    public $page_largeur;

    /**
     * @var int page height in mm
     */
    public $page_hauteur;

    /**
     * @var array format
     */
    public $format;

    /**
     * @var int margin left
     */
    public $marge_gauche;

    /**
     * @var int margin right
     */
    public $marge_droite;

    /**
     * @var int margin top
     */
    public $marge_haute;

    /**
     * @var int margin bottom
     */
    public $marge_basse;

    /**
     * @var Societe Issuer
     */
    public $emetteur;

    /**
     * @var array VAT rates and amounts
     */
    public $tva = array();

    /**
     * @var int At least one VAT rate is not null
     */
    public $atleastoneratenotnull = 0;

    /**
     * Return if a module can be used or not
     *
     * @return boolean true if module can be used
     */
    public function isEnabled()
    {
        return true;
    }

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        global $conf, $langs, $mysoc;

        // Load translation files required by the page
        $langs->loadLangs(array("main", "bills", "products", "supplierreturn@supplierreturn"));

        $this->db = $db;
        $this->name = "standard";
        $this->description = $langs->trans('SupplierReturnStandardPDF');
        $this->update_main_doc_field = 1; // Save the name of generated file as the main doc when generating a doc with this template

        // Page size for A4 format
        $this->type = 'pdf';
        $formatarray = pdf_getFormat();
        $this->page_largeur = $formatarray['width'];
        $this->page_hauteur = $formatarray['height'];
        $this->format = array($this->page_largeur, $this->page_hauteur);
        $this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
        $this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
        $this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
        $this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);

        $this->option_logo = 1; // Display logo
        $this->option_tva = 1; // Manage the vat option
        $this->option_modereg = 1; // Display payment mode
        $this->option_condreg = 1; // Display payment terms
        $this->option_multilang = 1; // Available in several languages
        $this->option_escompte = 0; // Displays if there has been a discount
        $this->option_credit_note = 0; // Support credit notes
        $this->option_freetext = 1; // Support add of a free text
        $this->option_draft_watermark = 1; // Support add of a watermark on drafts

        $this->emetteur = $mysoc;
        if (empty($this->emetteur->country_code)) {
            $this->emetteur->country_code = getDolGlobalString('MAIN_INFO_SOCIETE_COUNTRY');
        }
    }

    /**
     * Function to build pdf onto disk
     *
     * @param object $object SupplierReturn object
     * @param Translate $outputlangs Lang output object
     * @param string $srctemplatepath Full path of source filename for generator using a template file
     * @param int $hidedetails Do not show line details
     * @param int $hidedesc Do not show desc
     * @param int $hideref Do not show ref
     * @return int 1=OK, 0=KO
     */
    public function write_file($object, $outputlangs = null, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
    {
        global $user, $langs, $conf, $hookmanager, $mysoc, $nblines;

        dol_syslog("pdf_standard::write_file", LOG_DEBUG);

        if (!is_object($outputlangs)) {
            $outputlangs = $langs;
        }
        // For backward compatibility with FPDF, force output charset to ISO, because FPDF expect text to be encoded in ISO
        if (getDolGlobalString('MAIN_USE_FPDF')) {
            $outputlangs->charset_output = 'ISO-8859-1';
        }

        // Load translation files required by the page
        $outputlangs->loadLangs(array("main", "dict", "companies", "bills", "products", "orders", "supplierreturn@supplierreturn"));

        $nblines = count($object->lines);

        // Initialize module configuration if needed
        if (empty($conf->supplierreturn)) {
            $conf->supplierreturn = new stdClass();
            $conf->supplierreturn->dir_output = DOL_DATA_ROOT.'/supplierreturn';
        }

        if ($conf->supplierreturn->dir_output) {
            // Force fresh data loading to ensure PDF reflects current state
            $object->fetch($object->id);
            $object->fetch_lines();

            // Update totals before generating PDF
            $object->updateTotal();

            // Force loading of thirdparty if not loaded
            if (empty($object->thirdparty) || !is_object($object->thirdparty)) {
                $result = $object->fetch_thirdparty();
                if ($result < 0) {
                    $this->error = "Failed to load thirdparty";
                    return 0;
                }
            }

            dol_syslog("pdf_standard::write_file - Fresh data loaded for object ".$object->id, LOG_DEBUG);

            // Definition of $dir and $file
            if ($object->specimen) {
                $dir = $conf->supplierreturn->dir_output;
                $file = $dir."/SPECIMEN.pdf";
            } else {
                $objectref = dol_sanitizeFileName(str_replace(array('(', ')'), '', $object->ref));
                $dir = $conf->supplierreturn->dir_output."/".$objectref;
                $file = $dir."/".$objectref.".pdf";
            }

            if (!file_exists($dir)) {
                if (dol_mkdir($dir) < 0) {
                    $this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
                    return 0;
                }
            }

            if (file_exists($dir)) {
                // Create pdf instance
                $pdf = pdf_getInstance($this->format);
                $default_font_size = pdf_getPDFFontSize($outputlangs);
                $pdf->SetAutoPageBreak(1, 0);

                if (class_exists('TCPDF')) {
                    $pdf->setPrintHeader(false);
                    $pdf->setPrintFooter(false);
                }
                $pdf->SetFont(pdf_getPDFFont($outputlangs));

                $pdf->Open();
                $pagenb = 0;
                $pdf->SetDrawColor(128, 128, 128);

                $pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
                $pdf->SetSubject($outputlangs->transnoentities("SupplierReturn"));
                $pdf->SetCreator("Dolibarr ".DOL_VERSION);
                $pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
                $pdf->SetKeywords($outputlangs->convToOutputCharset($object->ref)." ".$outputlangs->transnoentities("SupplierReturn"));

                $pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);

                // New page
                $pdf->AddPage();
                $pagenb++;
                $this->_pagehead($pdf, $object, 1, $outputlangs);
                $pdf->SetFont('', '', $default_font_size - 1);
                $pdf->MultiCell(0, 3, '');
                $pdf->SetTextColor(0, 0, 0);

                $tab_top = 90;
                $tab_height = $this->page_hauteur - $tab_top - 40;

                // Display lines
                $this->_tableau($pdf, $tab_top, $tab_height, 0, $outputlangs, 0, 0, $object);

                // Display totals
                $this->_tableau_tot($pdf, $object, $outputlangs);

                // Display footer
                $this->_pagefoot($pdf, $object, $outputlangs);

                // Add draft watermark if document is draft
                if ($object->statut == 0 && getDolGlobalString('SUPPLIERRETURN_DRAFT_WATERMARK')) {
                    pdf_watermark($pdf, $outputlangs, $this->page_hauteur, $this->page_largeur, 'mm', getDolGlobalString('SUPPLIERRETURN_DRAFT_WATERMARK'));
                }

                $pdf->Close();
                $pdf->Output($file, 'F');

                dolChmod($file);

                $this->result = array('fullpath'=>$file);

                return 1;
            } else {
                $this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
                return 0;
            }
        } else {
            $this->error = $langs->transnoentities("ErrorConstantNotDefined", "DOL_DATA_ROOT");
            return 0;
        }
    }

    /**
     * Show top header of page
     *
     * @param TCPDF $pdf Object PDF
     * @param Object $object Object to show
     * @param int $showaddress 0=hide, 1=show
     * @param Translate $outputlangs Object lang for output
     * @return int Return topshift value
     */
    protected function _pagehead(&$pdf, $object, $showaddress, $outputlangs)
    {
        global $conf, $langs;

        $outputlangs->loadLangs(array("main", "bills", "companies", "suppliers", "supplierreturn@supplierreturn"));

        $default_font_size = pdf_getPDFFontSize($outputlangs);

        pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);

        $pdf->SetTextColor(0, 0, 60);
        $pdf->SetFont('', 'B', $default_font_size + 3);

        $w = 110;
        $posy = $this->marge_haute;
        $posx = $this->page_largeur - $this->marge_droite - $w;

        $pdf->SetXY($this->marge_gauche, $posy);

        // Logo
        if (!empty($this->emetteur->logo)) {
            $logodir = $conf->mycompany->dir_output;
            if (!empty($conf->mycompany->multidir_output[$object->entity])) {
                $logodir = $conf->mycompany->multidir_output[$object->entity];
            }
            $logo = $logodir.'/logos/'.$this->emetteur->logo;
            if (is_readable($logo)) {
                $height = pdf_getHeightForLogo($logo);
                $pdf->Image($logo, $this->marge_gauche, $posy, 0, $height);
            } else {
                $pdf->SetTextColor(200, 0, 0);
                $pdf->SetFont('', 'B', $default_font_size - 2);
                $pdf->MultiCell($w, 3, $outputlangs->transnoentities("ErrorLogoFileNotFound", $logo), 0, 'L');
            }
        } else {
            $text = $this->emetteur->name;
            $pdf->MultiCell($w, 4, $outputlangs->convToOutputCharset($text), 0, 'L');
        }

        // Title
        $pdf->SetFont('', 'B', $default_font_size + 3);
        $pdf->SetXY($posx, $posy);
        $pdf->SetTextColor(0, 0, 60);
        $title = $outputlangs->transnoentities("SupplierReturn");
        $pdf->MultiCell($w, 3, $title, '', 'R');

        $pdf->SetFont('', 'B', $default_font_size);

        // Reference
        $posy += 5;
        $pdf->SetXY($posx, $posy);
        $pdf->SetTextColor(0, 0, 60);
        $pdf->MultiCell($w, 4, $outputlangs->transnoentities("Ref")." : ".$outputlangs->convToOutputCharset($object->ref), '', 'R');

        // Date
        $posy += 4;
        $pdf->SetXY($posx, $posy);
        $pdf->SetTextColor(0, 0, 60);
        $pdf->MultiCell($w, 3, $outputlangs->transnoentities("Date")." : ".dol_print_date($object->date_return, "day", false, $outputlangs, true), '', 'R');

        // Return reason
        if (!empty($object->return_reason)) {
            $posy += 4;
            $pdf->SetXY($posx, $posy);
            $pdf->SetTextColor(0, 0, 60);
            $pdf->MultiCell($w, 3, $outputlangs->transnoentities("ReturnReason")." : ".$outputlangs->convToOutputCharset($object->return_reason), '', 'R');
        }

        // Status
        $posy += 4;
        $pdf->SetXY($posx, $posy);
        $pdf->SetTextColor(0, 0, 60);
        $status_label = $object->getLibStatut(1);
        $pdf->MultiCell($w, 3, $outputlangs->transnoentities("Status")." : ".$status_label, '', 'R');

        // Show extrafields in header if any
        if (!empty($object->array_options)) {
            require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
            $extrafieldsclass = new ExtraFields($this->db);
            $extralabels = $extrafieldsclass->fetch_name_optionals_label('supplierreturn');

            foreach ($object->array_options as $key => $value) {
                if (!empty($value)) {
                    $tmpkey = preg_replace('/options_/', '', $key);
                    if (isset($extralabels[$tmpkey])) {
                        $posy += 4;
                        $pdf->SetXY($posx, $posy);
                        $pdf->SetTextColor(0, 0, 60);
                        $label = $outputlangs->transnoentities($extralabels[$tmpkey]);
                        if (empty($label)) $label = $extralabels[$tmpkey];
                        $pdf->MultiCell($w, 3, $label." : ".$outputlangs->convToOutputCharset($value), '', 'R');
                    }
                }
            }
        }

        if ($showaddress) {
            // Sender address
            $carac_emetteur = pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, '', 0, 'source', $object);

            $posy = 42;
            $posx = $this->marge_gauche;
            $widthrecbox = 82;
            $hautcadre = 40;

            // Show sender frame
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('', '', $default_font_size - 2);
            $pdf->SetXY($posx, $posy - 5);
            $pdf->MultiCell($widthrecbox, 5, $outputlangs->transnoentities("BillFrom"), 0, 'L');
            $pdf->SetXY($posx, $posy);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->MultiCell($widthrecbox, $hautcadre, "", 0, 'R', 1);
            $pdf->SetTextColor(0, 0, 60);

            // Show sender name
            $pdf->SetXY($posx + 2, $posy + 3);
            $pdf->SetFont('', 'B', $default_font_size);
            $pdf->MultiCell($widthrecbox - 2, 4, $outputlangs->convToOutputCharset($this->emetteur->name), 0, 'L');
            $posy = $pdf->getY();

            // Show sender information
            $pdf->SetXY($posx + 2, $posy);
            $pdf->SetFont('', '', $default_font_size - 1);
            $pdf->MultiCell($widthrecbox - 2, 4, $carac_emetteur, 0, 'L');

            // Recipient - check if thirdparty is loaded
            if (!empty($object->thirdparty) && is_object($object->thirdparty)) {
                $carac_client_name = pdfBuildThirdpartyName($object->thirdparty, $outputlangs);
                $carac_client = pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, '', 0, 'target', $object);
            } else {
                $carac_client_name = 'Unknown supplier';
                $carac_client = 'Address not available';
            }

            // Show recipient
            $widthrecbox = 100;
            $posy = 42;
            $posx = $this->page_largeur - $this->marge_droite - $widthrecbox;

            // Show recipient frame
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('', '', $default_font_size - 2);
            $pdf->SetXY($posx + 2, $posy - 5);
            $pdf->MultiCell($widthrecbox - 2, 5, $outputlangs->transnoentities("BillTo"), 0, 'L');
            $pdf->Rect($posx, $posy, $widthrecbox, $hautcadre);

            // Show recipient name
            $pdf->SetXY($posx + 2, $posy + 3);
            $pdf->SetFont('', 'B', $default_font_size);
            $pdf->MultiCell($widthrecbox - 2, 2, $carac_client_name, 0, 'L');

            $posy = $pdf->getY();

            // Show recipient information
            $pdf->SetFont('', '', $default_font_size - 1);
            $pdf->SetXY($posx + 2, $posy);
            $pdf->MultiCell($widthrecbox - 2, 4, $carac_client, 0, 'L');
        }

        $pdf->SetTextColor(0, 0, 0);

        return $posy;
    }

    /**
     * Show table for lines
     *
     * @param TCPDF $pdf Object PDF
     * @param string $tab_top Top position of table
     * @param string $tab_height Height of table
     * @param int $nexY Y position
     * @param Translate $outputlangs Langs object
     * @param int $hidetop Hide top bar
     * @param int $hidebottom Hide bottom bar
     * @param object $object Object
     * @return int Height of table
     */
    protected function _tableau(&$pdf, $tab_top, $tab_height, $nexY, $outputlangs, $hidetop = 0, $hidebottom = 0, $object = null)
    {
        global $conf;

        $default_font_size = pdf_getPDFFontSize($outputlangs);

        // Amount columns
        $this->posxdesc = $this->marge_gauche + 1;
        $this->posxqty = $this->page_largeur - $this->marge_droite - 50;
        $this->posxup = $this->page_largeur - $this->marge_droite - 36;
        $this->posxtotalht = $this->page_largeur - $this->marge_droite - 17;

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('', '', $default_font_size - 2);

        // Draw rect
        $pdf->SetDrawColor(128, 128, 128);
        $pdf->SetFont('', '', $default_font_size - 1);

        // Output Rect
        $pdf->Rect($this->marge_gauche, $tab_top, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $tab_height);

        // Show title line
        if (empty($hidetop)) {
            $pdf->line($this->marge_gauche, $tab_top + 5, $this->page_largeur - $this->marge_droite, $tab_top + 5);

            $pdf->SetXY($this->posxdesc - 1, $tab_top + 1);
            $pdf->MultiCell($this->posxqty - $this->posxdesc + 1, 3, $outputlangs->transnoentities("Description"), '', 'L');

            $pdf->SetXY($this->posxqty - 1, $tab_top + 1);
            $pdf->MultiCell($this->posxup - $this->posxqty + 1, 3, $outputlangs->transnoentities("Qty"), '', 'C');

            $pdf->SetXY($this->posxup - 1, $tab_top + 1);
            $pdf->MultiCell($this->posxtotalht - $this->posxup + 1, 3, $outputlangs->transnoentities("PriceUHT"), '', 'C');

            $pdf->SetXY($this->posxtotalht - 1, $tab_top + 1);
            $pdf->MultiCell($this->page_largeur - $this->marge_droite - $this->posxtotalht + 1, 3, $outputlangs->transnoentities("TotalHT"), '', 'C');
        }

        $pdf->SetFont('', '', $default_font_size - 1);
        $pdf->SetTextColor(0, 0, 0);

        $nexY = $tab_top + 8;

        // Loop on each lines
        if (!empty($object->lines)) {
            foreach ($object->lines as $i => $line) {
                $curY = $nexY;

                // Description with product reference
                $pdf->SetXY($this->posxdesc, $curY);
                $product_desc = "";

                // Add product reference if exists
                if ($line->fk_product > 0 && $line->product_ref) {
                    $product_desc = $line->product_ref;
                    if ($line->product_label) {
                        $product_desc .= " - " . $line->product_label;
                    }
                } elseif ($line->product_label) {
                    $product_desc = $line->product_label;
                } elseif ($line->description) {
                    $product_desc = $line->description;
                }

                // Add description on new line if different from label
                if ($line->description && $line->description != $line->product_label) {
                    if ($product_desc) {
                        $product_desc .= "\n" . $line->description;
                    } else {
                        $product_desc = $line->description;
                    }
                }

                $pdf->MultiCell($this->posxqty - $this->posxdesc - 1, 3, $outputlangs->convToOutputCharset($product_desc), 0, 'L');

                $nexY = max($pdf->GetY(), $nexY);

                // Quantity
                $pdf->SetXY($this->posxqty, $curY);
                $pdf->MultiCell($this->posxup - $this->posxqty - 1, 3, $line->qty, 0, 'C');

                // Unit price
                if ($line->subprice) {
                    $pdf->SetXY($this->posxup, $curY);
                    $pdf->MultiCell($this->posxtotalht - $this->posxup - 1, 3, price($line->subprice, 0, $outputlangs), 0, 'R');
                }

                // Total HT
                $total_line = $line->qty * $line->subprice;
                $pdf->SetXY($this->posxtotalht, $curY);
                $pdf->MultiCell($this->page_largeur - $this->marge_droite - $this->posxtotalht, 3, price($total_line, 0, $outputlangs), 0, 'R');

                $nexY = max($pdf->GetY(), $nexY);

                // Add extrafields for lines if any
                if (!empty($line->array_options)) {
                    $tmpextrafields = new ExtraFields($this->db);
                    $tmpextrafields->fetch_name_optionals_label('supplierreturndet');  // supplierreturndet is the line table

                    foreach ($line->array_options as $extrafieldkey => $extrafieldvalue) {
                        if (!empty($extrafieldvalue)) {
                            $tmpkey = preg_replace('/options_/', '', $extrafieldkey);
                            if (isset($tmpextrafields->attributes['supplierreturndet']['label'][$tmpkey])) {
                                $nexY += 2;
                                $pdf->SetFont('', '', $default_font_size - 2);
                                $pdf->SetTextColor(80, 80, 80);
                                $pdf->SetXY($this->posxdesc, $nexY);
                                $extralabel = $tmpextrafields->attributes['supplierreturndet']['label'][$tmpkey];
                                $pdf->MultiCell($this->posxqty - $this->posxdesc - 1, 2, $extralabel.": ".$extrafieldvalue, 0, 'L');
                                $nexY = max($pdf->GetY(), $nexY);
                                $pdf->SetFont('', '', $default_font_size - 1); // Reset font
                                $pdf->SetTextColor(0, 0, 0); // Reset color
                            }
                        }
                    }
                }

                $nexY += 4;

                // Add line
                if ($i < (count($object->lines) - 1)) {
                    $pdf->SetDrawColor(220, 220, 220);
                    $pdf->line($this->marge_gauche + 1, $nexY - 1, $this->page_largeur - $this->marge_droite - 1, $nexY - 1);
                    $pdf->SetDrawColor(128, 128, 128);
                }
            }
        }

        return $nexY;
    }

    /**
     * Show total zone
     *
     * @param TCPDF $pdf Object PDF
     * @param object $object Object
     * @param Translate $outputlangs Object lang for output
     * @return int Height
     */
    protected function _tableau_tot(&$pdf, $object, $outputlangs)
    {
        global $conf;

        $default_font_size = pdf_getPDFFontSize($outputlangs);

        $tab2_top = $this->page_hauteur - 60;
        $tab2_hl = 4;

        // Total table positioning like native modules
        $col1x = 120;
        $col2x = 170;
        if ($this->page_largeur < 210) { // To work with US executive format
            $col2x -= 20;
        }
        $largcol2 = ($this->page_largeur - $this->marge_droite - $col2x);

        $pdf->SetFont('', '', $default_font_size - 1);

        // Calculate actual totals from lines with proper VAT calculation
        $total_ht = 0;
        $total_tva = 0;
        $this->tva = array(); // Array to store VAT by rates

        if (!empty($object->lines)) {
            foreach ($object->lines as $line) {
                $line_total_ht = $line->qty * $line->subprice;
                $total_ht += $line_total_ht;

                // Calculate VAT if line has a VAT rate
                $vat_rate = 0;
                if (!empty($line->original_tva_tx)) {
                    $vat_rate = $line->original_tva_tx;
                } elseif (!empty($line->tva_tx)) {
                    $vat_rate = $line->tva_tx;
                }

                if ($vat_rate > 0) {
                    $line_vat = $line_total_ht * ($vat_rate / 100);
                    $total_tva += $line_vat;

                    // Store VAT by rate for detailed display
                    if (!isset($this->tva[$vat_rate])) {
                        $this->tva[$vat_rate] = 0;
                    }
                    $this->tva[$vat_rate] += $line_vat;
                }
            }
        } else {
            $total_ht = $object->total_ht ? $object->total_ht : 0;
            $total_tva = $object->total_tva ? $object->total_tva : 0;
        }
        $total_ttc = $total_ht + $total_tva;

        $useborder = 0;
        $index = 0;

        // Total HT
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetXY($col1x, $tab2_top);
        $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("TotalHT"), 0, 'L', 1);

        $pdf->SetXY($col2x, $tab2_top);
        $pdf->MultiCell($largcol2, $tab2_hl, price($total_ht, 0, $outputlangs), 0, 'R', 1);

        // Show VAT by rates like native modules
        $pdf->SetFillColor(248, 248, 248);
        $this->atleastoneratenotnull = 0;

        foreach ($this->tva as $tvakey => $tvaval) {
            if ($tvakey > 0) {    // We do not display rate 0
                $this->atleastoneratenotnull++;

                $index++;
                $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

                if (getDolGlobalString('MAIN_GENERATE_DOCUMENTS_WITH_PICTURE_PDF') && $this->atleastoneratenotnull == 1) {
                    $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("TotalVAT"), 0, 'L', 1);
                } else {
                    $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("TotalVAT").' '.vatrate($tvakey, 1).'%', 0, 'L', 1);
                }

                $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
                $pdf->MultiCell($largcol2, $tab2_hl, price($tvaval, 0, $outputlangs), 0, 'R', 1);
            }
        }

        if (!$this->atleastoneratenotnull) { // If no VAT, we may want to show VAT=0
            $index++;
            $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
            $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("TotalVAT"), 0, 'L', 1);

            $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
            $pdf->MultiCell($largcol2, $tab2_hl, price(0, 0, $outputlangs), 0, 'R', 1);
        }

        // Total TTC
        $index++;
        $pdf->SetFont('', 'B', $default_font_size - 1);
        $pdf->SetFillColor(224, 224, 224);
        $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
        $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("TotalTTC"), 0, 'L', 1);

        $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
        $pdf->MultiCell($largcol2, $tab2_hl, price($total_ttc, 0, $outputlangs), 0, 'R', 1);

        return ($tab2_top + $tab2_hl * ($index + 1));
    }

    /**
     * Show footer of page
     *
     * @param TCPDF $pdf PDF
     * @param Object $object Object to show
     * @param Translate $outputlangs Object lang for output
     * @param int $hidefreetext 1=Hide free text
     * @return int Return height of bottom margin including footer text
     */
    protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
    {
        global $conf;

        $showdetails = getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS', 0);

        return pdf_pagefoot($pdf, $outputlangs, 'SUPPLIERRETURN_FREE_TEXT', $this->emetteur, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, $showdetails, $hidefreetext);
    }
}
?>