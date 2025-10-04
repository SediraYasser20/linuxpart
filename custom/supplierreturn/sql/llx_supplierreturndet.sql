-- ===================================================================
-- Copyright (C) 2025 Nicolas Testori
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
-- ===================================================================

-- Table des lignes de retour fournisseur
CREATE TABLE IF NOT EXISTS llx_supplierreturndet (
    rowid integer AUTO_INCREMENT PRIMARY KEY,
    fk_supplierreturn integer NOT NULL,
    fk_product integer,
    fk_product_batch integer,
    fk_reception_line integer,
    description text,
    qty double DEFAULT 0,
    subprice double(24,8) DEFAULT 0,
    total_ht double(24,8) DEFAULT 0,
    total_ttc double(24,8) DEFAULT 0,
    batch varchar(128),
    fk_entrepot integer,
    rang integer DEFAULT 0,
    fk_facture_fourn_det_source integer,
    original_subprice double(24,8),
    original_tva_tx double(8,4),
    original_localtax1_tx double(8,4) DEFAULT 0,
    original_localtax2_tx double(8,4) DEFAULT 0,
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_supplierreturndet_fk_supplierreturn (fk_supplierreturn),
    KEY idx_supplierreturndet_fk_product (fk_product),
    KEY idx_supplierreturndet_fk_reception_line (fk_reception_line)
) ENGINE=InnoDB;

-- Contraintes de clés étrangères
ALTER TABLE llx_supplierreturndet ADD CONSTRAINT fk_supplierreturndet_fk_supplierreturn FOREIGN KEY (fk_supplierreturn) REFERENCES llx_supplierreturn(rowid);
ALTER TABLE llx_supplierreturndet ADD CONSTRAINT fk_supplierreturndet_fk_product FOREIGN KEY (fk_product) REFERENCES llx_product(rowid);
ALTER TABLE llx_supplierreturndet ADD CONSTRAINT fk_supplierreturndet_fk_entrepot FOREIGN KEY (fk_entrepot) REFERENCES llx_entrepot(rowid);