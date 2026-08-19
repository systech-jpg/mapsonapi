SELECT k.tabel, k.kolom AS kolom_yang_hilang
FROM (
  SELECT 'llxjp_stocktake' AS tabel, 'rowid' AS kolom
  UNION ALL
  SELECT 'llxjp_stocktake' AS tabel, 'ref' AS kolom
  UNION ALL
  SELECT 'llxjp_stocktake' AS tabel, 'label' AS kolom
  UNION ALL
  SELECT 'llxjp_stocktake' AS tabel, 'fk_warehouse' AS kolom
  UNION ALL
  SELECT 'llxjp_stocktake' AS tabel, 'type' AS kolom
  UNION ALL
  SELECT 'llxjp_stocktake' AS tabel, 'period_month' AS kolom
  UNION ALL
  SELECT 'llxjp_stocktake' AS tabel, 'period_year' AS kolom
  UNION ALL
  SELECT 'llxjp_stocktake' AS tabel, 'stocktake_date' AS kolom
  UNION ALL
  SELECT 'llxjp_stocktake' AS tabel, 'status' AS kolom
  UNION ALL
  SELECT 'llxjp_stocktake' AS tabel, 'note' AS kolom
  UNION ALL
  SELECT 'llxjp_stocktake' AS tabel, 'entity' AS kolom
  UNION ALL
  SELECT 'llxjp_stocktake_det' AS tabel, 'rowid' AS kolom
  UNION ALL
  SELECT 'llxjp_stocktake_det' AS tabel, 'fk_stocktake' AS kolom
  UNION ALL
  SELECT 'llxjp_stocktake_det' AS tabel, 'fk_product' AS kolom
  UNION ALL
  SELECT 'llxjp_stocktake_det' AS tabel, 'qty_theoretical' AS kolom
  UNION ALL
  SELECT 'llxjp_stocktake_det' AS tabel, 'qty_physical' AS kolom
  UNION ALL
  SELECT 'llxjp_stocktake_det' AS tabel, 'qty_rak' AS kolom
  UNION ALL
  SELECT 'llxjp_stocktake_det' AS tabel, 'qty_tray' AS kolom
  UNION ALL
  SELECT 'llxjp_stocktake_det' AS tabel, 'qty_container' AS kolom
  UNION ALL
  SELECT 'llxjp_stocktake_det' AS tabel, 'note' AS kolom
  UNION ALL
  SELECT 'llxjp_product' AS tabel, 'rowid' AS kolom
  UNION ALL
  SELECT 'llxjp_product' AS tabel, 'ref' AS kolom
  UNION ALL
  SELECT 'llxjp_product' AS tabel, 'label' AS kolom
  UNION ALL
  SELECT 'llxjp_product' AS tabel, 'barcode' AS kolom
  UNION ALL
  SELECT 'llxjp_product_extrafields' AS tabel, 'fk_object' AS kolom
  UNION ALL
  SELECT 'llxjp_product_extrafields' AS tabel, 'principal' AS kolom
  UNION ALL
  SELECT 'llxjp_societe' AS tabel, 'rowid' AS kolom
  UNION ALL
  SELECT 'llxjp_societe' AS tabel, 'nom' AS kolom
  UNION ALL
  SELECT 'llxjp_entrepot' AS tabel, 'rowid' AS kolom
  UNION ALL
  SELECT 'llxjp_entrepot' AS tabel, 'ref' AS kolom
) k
LEFT JOIN information_schema.COLUMNS c
  ON c.TABLE_SCHEMA = DATABASE() AND c.TABLE_NAME = k.tabel AND c.COLUMN_NAME = k.kolom
WHERE c.COLUMN_NAME IS NULL;
