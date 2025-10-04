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

-- Table principale des retours fournisseurs
CREATE TABLE IF NOT EXISTS llx_supplierreturn (
    rowid integer AUTO_INCREMENT PRIMARY KEY,
    ref varchar(30) NOT NULL,
    entity integer DEFAULT 1 NOT NULL,
    fk_soc integer NOT NULL,
    fk_reception integer,
    fk_commande_fournisseur integer,
    fk_facture_fourn integer,
    fk_facture_avoir integer,
    date_creation datetime NOT NULL,
    date_modification datetime,
    date_return date,
    date_valid datetime,
    date_process datetime,
    note_public text,
    note_private text,
    return_reason varchar(255),
    statut smallint DEFAULT 0 NOT NULL,
    fk_user_author integer,
    fk_user_modif integer,
    fk_user_valid integer,
    total_ht double(24,8) DEFAULT 0,
    total_ttc double(24,8) DEFAULT 0,
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_supplierreturn_ref (ref, entity),
    KEY idx_supplierreturn_fk_soc (fk_soc),
    KEY idx_supplierreturn_fk_reception (fk_reception),
    KEY idx_supplierreturn_statut (statut)
) ENGINE=InnoDB;

-- Table des lignes de retour
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
ALTER TABLE llx_supplierreturn ADD CONSTRAINT fk_supplierreturn_fk_soc FOREIGN KEY (fk_soc) REFERENCES llx_societe(rowid);
ALTER TABLE llx_supplierreturn ADD CONSTRAINT fk_supplierreturn_fk_reception FOREIGN KEY (fk_reception) REFERENCES llx_reception(rowid);
ALTER TABLE llx_supplierreturn ADD CONSTRAINT fk_supplierreturn_fk_user_author FOREIGN KEY (fk_user_author) REFERENCES llx_user(rowid);
ALTER TABLE llx_supplierreturndet ADD CONSTRAINT fk_supplierreturndet_fk_supplierreturn FOREIGN KEY (fk_supplierreturn) REFERENCES llx_supplierreturn(rowid);
ALTER TABLE llx_supplierreturndet ADD CONSTRAINT fk_supplierreturndet_fk_product FOREIGN KEY (fk_product) REFERENCES llx_product(rowid);
ALTER TABLE llx_supplierreturndet ADD CONSTRAINT fk_supplierreturndet_fk_entrepot FOREIGN KEY (fk_entrepot) REFERENCES llx_entrepot(rowid);