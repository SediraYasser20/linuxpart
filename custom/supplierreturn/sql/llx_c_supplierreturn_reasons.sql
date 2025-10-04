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

--
-- Table structure for supplier return reasons dictionary
--

CREATE TABLE llx_c_supplierreturn_reasons(
  rowid         integer AUTO_INCREMENT PRIMARY KEY,
  code          varchar(16) NOT NULL,
  label         varchar(255) NOT NULL,
  active        tinyint DEFAULT 1 NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Default data for supplier return reasons
--

INSERT INTO llx_c_supplierreturn_reasons (code, label, active) VALUES 
('defective', 'Defective product', 1),
('damaged', 'Damaged during shipping', 1),
('wrong_item', 'Wrong item received', 1),
('excess_quantity', 'Excess quantity', 1),
('quality_issue', 'Quality issue', 1),
('not_as_described', 'Not as described', 1),
('other', 'Other reason', 1);