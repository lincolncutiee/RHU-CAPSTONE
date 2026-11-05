-- Reports & Heat Map: Doctor and IT only (hide from Records, Pharmacy, Receptionist).
UPDATE role_permissions
SET allowed = 0
WHERE permission_key IN ('reports.view', 'heatmap.view')
  AND role_slug IN ('records_office', 'pharmacy', 'receptionist', 'system_administrator');

UPDATE role_permissions
SET allowed = 1
WHERE permission_key IN ('reports.view', 'heatmap.view')
  AND role_slug IN ('doctor', 'it_department');
