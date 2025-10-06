<?php
/* Copyright (C) 2025 Nicolas Testori
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
dol_include_once('/custom/customerreturn/core/modules/customerreturn/modules_customerreturn.php');

/**
 * Class to generate customer return PDF using standard template
 */
class pdf_standard extends ModelePDFCustomerreturn
{
    public $db;
    public $name;
    public $description;
    public $update_main_doc_field;
    public $type;
    public $version = 'dolibarr';
    public $page_largeur;
    public $page_hauteur;
    public $format;
    public $marge_gauche;
    public $marge_droite;
    public $marge_haute;
    public $marge_basse;
    public $emetteur;
    public $tva = array();
    public $atleastoneratenotnull = 0;

    public function isEnabled()
    {
        return true;
    }

    public function __construct($db)
    {
        global $conf, $langs, $mysoc;

        $langs->loadLangs(array("main", "bills", "products", "customerreturn@customerreturn"));

        $this->db = $db;
        $this->name = "standard";
        $this->description = $langs->trans('CustomerReturnStandardPDF');
        $this->update_main_doc_field = 1;

        $this->type = 'pdf';
        $formatarray = pdf_getFormat();
        $this->page_largeur = $formatarray['width'];
        $this->page_hauteur = $formatarray['height'];
        $this->format = array($this->page_largeur, $this->page_hauteur);
        $this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
        $this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
        $this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
        $this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);

        $this->option_logo = 1;
        $this->option_tva = 1;
        $this->option_modereg = 1;
        $this->option_condreg = 1;
        $this->option_multilang = 1;
        $this->option_escompte = 0;
        $this->option_credit_note = 0;
        $this->option_freetext = 1;
        $this->option_draft_watermark = 1;

        $this->emetteur = $mysoc;
        if (empty($this->emetteur->country_code)) {
            $this->emetteur->country_code = getDolGlobalString('MAIN_INFO_SOCIETE_COUNTRY');
        }
    }

    public function write_file($object, $outputlangs = null, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
    {
        global $user, $langs, $conf, $mysoc, $nblines;

        dol_syslog("pdf_standard::write_file", LOG_DEBUG);

        if (!is_object($outputlangs)) {
            $outputlangs = $langs;
        }
        if (getDolGlobalString('MAIN_USE_FPDF')) {
            $outputlangs->charset_output = 'ISO-8859-1';
        }

        $outputlangs->loadLangs(array("main", "dict", "companies", "bills", "products", "orders", "customerreturn@customerreturn"));

        if (empty($conf->customerreturn)) {
            $conf->customerreturn = new stdClass();
            $conf->customerreturn->dir_output = DOL_DATA_ROOT.'/customerreturn';
        }

        if ($conf->customerreturn->dir_output) {
            // Fetch object data
            $object->fetch($object->id);
            
            // Load lines using getLines() method instead of deprecated fetch_lines()
            if (empty($object->lines)) {
                $object->lines = $object->getLines();
            }
            
            $nblines = count($object->lines);
            $object->updateTotal();

            // Load thirdparty
            if (empty($object->thirdparty) || !is_object($object->thirdparty)) {
                $result = $object->fetch_thirdparty();
                if ($result < 0) {
                    $this->error = "Failed to load thirdparty";
                    return 0;
                }
            }

            dol_syslog("pdf_standard::write_file - Data loaded for object ".$object->id, LOG_DEBUG);

            if ($object->specimen) {
                $dir = $conf->customerreturn->dir_output;
                $file = $dir."/SPECIMEN.pdf";
            } else {
                $objectref = dol_sanitizeFileName(str_replace(array('(', ')'), '', $object->ref));
                $dir = $conf->customerreturn->dir_output."/".$objectref;
                $file = $dir."/".$objectref.".pdf";
            }

            if (!file_exists($dir)) {
                if (dol_mkdir($dir) < 0) {
                    $this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
                    return 0;
                }
            }

            if (file_exists($dir)) {
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
                $pdf->SetSubject($outputlangs->transnoentities("CustomerReturn"));
                $pdf->SetCreator("Dolibarr ".DOL_VERSION);
                $pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
                $pdf->SetKeywords($outputlangs->convToOutputCharset($object->ref)." ".$outputlangs->transnoentities("CustomerReturn"));

                $pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);

                $pdf->AddPage();
                $pagenb++;
                $this->_pagehead($pdf, $object, 1, $outputlangs);
                $pdf->SetFont('', '', $default_font_size - 1);
                $pdf->MultiCell(0, 3, '');
                $pdf->SetTextColor(0, 0, 0);

                $tab_top = 90;
                $tab_height = $this->page_hauteur - $tab_top - 40;

                $this->_tableau($pdf, $tab_top, $tab_height, 0, $outputlangs, 0, 0, $object);

                $this->_tableau_tot($pdf, $object, $outputlangs);

                $this->_pagefoot($pdf, $object, $outputlangs);

                if ($object->statut == 0 && getDolGlobalString('CUSTOMERRETURN_DRAFT_WATERMARK')) {
                    pdf_watermark($pdf, $outputlangs, $this->page_hauteur, $this->page_largeur, 'mm', getDolGlobalString('CUSTOMERRETURN_DRAFT_WATERMARK'));
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

    protected function _pagehead(&$pdf, $object, $showaddress, $outputlangs)
    {
        global $conf;

        $outputlangs->loadLangs(array("main", "bills", "companies", "customers", "customerreturn@customerreturn"));

        $default_font_size = pdf_getPDFFontSize($outputlangs);

        pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);

        $pdf->SetTextColor(0, 0, 60);
        $pdf->SetFont('', 'B', $default_font_size + 3);

        $w = 110;
        $posy = $this->marge_haute;
        $posx = $this->page_largeur - $this->marge_droite - $w;

        $pdf->SetXY($this->marge_gauche, $posy);

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

        $pdf->SetFont('', 'B', $default_font_size + 3);
        $pdf->SetXY($posx, $posy);
        $pdf->SetTextColor(0, 0, 60);
        $title = $outputlangs->transnoentities("CustomerReturn");
        $pdf->MultiCell($w, 3, $title, '', 'R');

        $pdf->SetFont('', 'B', $default_font_size);

        $posy += 5;
        $pdf->SetXY($posx, $posy);
        $pdf->SetTextColor(0, 0, 60);
        $pdf->MultiCell($w, 4, $outputlangs->transnoentities("Ref")." : ".$outputlangs->convToOutputCharset($object->ref), '', 'R');

        $posy += 4;
        $pdf->SetXY($posx, $posy);
        $pdf->SetTextColor(0, 0, 60);
        $pdf->MultiCell($w, 3, $outputlangs->transnoentities("Date")." : ".dol_print_date($object->date_return, "day", false, $outputlangs, true), '', 'R');

        if (!empty($object->return_reason)) {
            $posy += 4;
            $pdf->SetXY($posx, $posy);
            $pdf->SetTextColor(0, 0, 60);
            $pdf->MultiCell($w, 3, $outputlangs->transnoentities("ReturnReason")." : ".$outputlangs->convToOutputCharset($object->return_reason), '', 'R');
        }

        $posy += 4;
        $pdf->SetXY($posx, $posy);
        $pdf->SetTextColor(0, 0, 60);
        $status_label = $object->getLibStatut(1);
        $pdf->MultiCell($w, 3, $outputlangs->transnoentities("Status")." : ".$status_label, '', 'R');

        if ($showaddress) {
            $carac_emetteur = pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, '', 0, 'source', $object);

            $posy = 42;
            $posx = $this->marge_gauche;
            $widthrecbox = 82;
            $hautcadre = 40;

            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('', '', $default_font_size - 2);
            $pdf->SetXY($posx, $posy - 5);
            $pdf->MultiCell($widthrecbox, 5, $outputlangs->transnoentities("BillFrom"), 0, 'L');
            $pdf->SetXY($posx, $posy);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->MultiCell($widthrecbox, $hautcadre, "", 0, 'R', 1);
            $pdf->SetTextColor(0, 0, 60);

            $pdf->SetXY($posx + 2, $posy + 3);
            $pdf->SetFont('', 'B', $default_font_size);
            $pdf->MultiCell($widthrecbox - 2, 4, $outputlangs->convToOutputCharset($this->emetteur->name), 0, 'L');
            $posy = $pdf->getY();

            $pdf->SetXY($posx + 2, $posy);
            $pdf->SetFont('', '', $default_font_size - 1);
            $pdf->MultiCell($widthrecbox - 2, 4, $carac_emetteur, 0, 'L');

            if (!empty($object->thirdparty) && is_object($object->thirdparty)) {
                $carac_client_name = pdfBuildThirdpartyName($object->thirdparty, $outputlangs);
                $carac_client = pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, '', 0, 'target', $object);
            } else {
                $carac_client_name = 'Unknown customer';
                $carac_client = 'Address not available';
            }

            $widthrecbox = 100;
            $posy = 42;
            $posx = $this->page_largeur - $this->marge_droite - $widthrecbox;

            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('', '', $default_font_size - 2);
            $pdf->SetXY($posx + 2, $posy - 5);
            $pdf->MultiCell($widthrecbox - 2, 5, $outputlangs->transnoentities("BillTo"), 0, 'L');
            $pdf->Rect($posx, $posy, $widthrecbox, $hautcadre);

            $pdf->SetXY($posx + 2, $posy + 3);
            $pdf->SetFont('', 'B', $default_font_size);
            $pdf->MultiCell($widthrecbox - 2, 2, $carac_client_name, 0, 'L');

            $posy = $pdf->getY();

            $pdf->SetFont('', '', $default_font_size - 1);
            $pdf->SetXY($posx + 2, $posy);
            $pdf->MultiCell($widthrecbox - 2, 4, $carac_client, 0, 'L');
        }

        $pdf->SetTextColor(0, 0, 0);

        return $posy;
    }

    protected function _tableau(&$pdf, $tab_top, $tab_height, $nexY, $outputlangs, $hidetop = 0, $hidebottom = 0, $object = null)
    {
        $default_font_size = pdf_getPDFFontSize($outputlangs);

        $this->posxdesc = $this->marge_gauche + 1;
        $this->posxqty = $this->page_largeur - $this->marge_droite - 50;
        $this->posxup = $this->page_largeur - $this->marge_droite - 36;
        $this->posxtotalht = $this->page_largeur - $this->marge_droite - 17;

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('', '', $default_font_size - 2);
        $pdf->SetDrawColor(128, 128, 128);
        $pdf->SetFont('', '', $default_font_size - 1);

        $pdf->Rect($this->marge_gauche, $tab_top, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $tab_height);

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

        if (!empty($object->lines)) {
            foreach ($object->lines as $i => $line) {
                $curY = $nexY;
                $pdf->SetXY($this->posxdesc, $curY);
                $product_desc = "";

                if ($line->fk_product > 0 && !empty($line->product_ref)) {
                    $product_desc = $line->product_ref;
                    if (!empty($line->product_label)) {
                        $product_desc .= " - " . $line->product_label;
                    }
                } elseif (!empty($line->product_label)) {
                    $product_desc = $line->product_label;
                } elseif (!empty($line->description)) {
                    $product_desc = $line->description;
                }

                if (!empty($line->description) && $line->description != $line->product_label) {
                    if ($product_desc) {
                        $product_desc .= "\n" . $line->description;
                    } else {
                        $product_desc = $line->description;
                    }
                }

                $pdf->MultiCell($this->posxqty - $this->posxdesc - 1, 3, $outputlangs->convToOutputCharset($product_desc), 0, 'L');

                $nexY = max($pdf->GetY(), $nexY);

                $pdf->SetXY($this->posxqty, $curY);
                $pdf->MultiCell($this->posxup - $this->posxqty - 1, 3, $line->qty, 0, 'C');

                if ($line->subprice) {
                    $pdf->SetXY($this->posxup, $curY);
                    $pdf->MultiCell($this->posxtotalht - $this->posxup - 1, 3, price($line->subprice, 0, $outputlangs), 0, 'R');
                }

                $total_line = $line->qty * $line->subprice;
                $pdf->SetXY($this->posxtotalht, $curY);
                $pdf->MultiCell($this->page_largeur - $this->marge_droite - $this->posxtotalht, 3, price($total_line, 0, $outputlangs), 0, 'R');

                $nexY = max($pdf->GetY(), $nexY);
                $nexY += 4;

                if ($i < (count($object->lines) - 1)) {
                    $pdf->SetDrawColor(220, 220, 220);
                    $pdf->line($this->marge_gauche + 1, $nexY - 1, $this->page_largeur - $this->marge_droite - 1, $nexY - 1);
                    $pdf->SetDrawColor(128, 128, 128);
                }
            }
        }

        return $nexY;
    }

    protected function _tableau_tot(&$pdf, $object, $outputlangs)
    {
        $default_font_size = pdf_getPDFFontSize($outputlangs);

        $tab2_top = $this->page_hauteur - 60;
        $tab2_hl = 4;

        $col1x = 120;
        $col2x = 170;
        if ($this->page_largeur < 210) {
            $col2x -= 20;
        }
        $largcol2 = ($this->page_largeur - $this->marge_droite - $col2x);

        $pdf->SetFont('', '', $default_font_size - 1);

        $total_ht = 0;
        $total_tva = 0;
        $this->tva = array();

        if (!empty($object->lines)) {
            foreach ($object->lines as $line) {
                $line_total_ht = $line->qty * $line->subprice;
                $total_ht += $line_total_ht;

                $vat_rate = 0;
                if (!empty($line->original_tva_tx)) {
                    $vat_rate = $line->original_tva_tx;
                } elseif (!empty($line->tva_tx)) {
                    $vat_rate = $line->tva_tx;
                }

                if ($vat_rate > 0) {
                    $line_vat = $line_total_ht * ($vat_rate / 100);
                    $total_tva += $line_vat;

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

        $index = 0;

        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetXY($col1x, $tab2_top);
        $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("TotalHT"), 0, 'L', 1);

        $pdf->SetXY($col2x, $tab2_top);
        $pdf->MultiCell($largcol2, $tab2_hl, price($total_ht, 0, $outputlangs), 0, 'R', 1);

        $pdf->SetFillColor(248, 248, 248);
        $this->atleastoneratenotnull = 0;

        foreach ($this->tva as $tvakey => $tvaval) {
            if ($tvakey > 0) {
                $this->atleastoneratenotnull++;
                $index++;
                $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
                $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("TotalVAT").' '.vatrate($tvakey, 1).'%', 0, 'L', 1);

                $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
                $pdf->MultiCell($largcol2, $tab2_hl, price($tvaval, 0, $outputlangs), 0, 'R', 1);
            }
        }

        if (!$this->atleastoneratenotnull) {
            $index++;
            $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
            $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("TotalVAT"), 0, 'L', 1);

            $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
            $pdf->MultiCell($largcol2, $tab2_hl, price(0, 0, $outputlangs), 0, 'R', 1);
        }

        $index++;
        $pdf->SetFont('', 'B', $default_font_size - 1);
        $pdf->SetFillColor(224, 224, 224);
        $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
        $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("TotalTTC"), 0, 'L', 1);

        $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
        $pdf->MultiCell($largcol2, $tab2_hl, price($total_ttc, 0, $outputlangs), 0, 'R', 1);

        return ($tab2_top + $tab2_hl * ($index + 1));
    }

    protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
    {
        $showdetails = getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS', 0);
        return pdf_pagefoot($pdf, $outputlangs, 'CUSTOMERRETURN_FREE_TEXT', $this->emetteur, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, $showdetails, $hidefreetext);
    }
}
