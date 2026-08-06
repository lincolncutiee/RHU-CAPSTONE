# Import official ICD-10 into `diagnosis_master`

Source dump (already extracted):

- `database/imports/icd10/icd-10-master/master_icd_x.sql`
- Staging table: `icds (code, name_en, name_id)`

## Mapping

| Source (`icds`) | Target (`diagnosis_master`) |
|-----------------|-----------------------------|
| `code` | `icd_code` |
| `name_en` | `diagnosis_name` (exact) |
| `name_id` | **ignored** |
| — | `status = 'active'` |
| — | `created_at` / `updated_at = NOW()` |

## phpMyAdmin steps (XAMPP)

1. Open `http://localhost/phpmyadmin`
2. Select database **`rhu_management`**
3. **Import** → choose  
   `database/imports/icd10/icd-10-master/master_icd_x.sql` → Go  
   (creates table `icds` with ~10,469 official ICD-10 rows)
4. **Import** → choose  
   `database/migrations/007_import_icd10_to_diagnosis_master.sql` → Go
5. Confirm the verification `SELECT` counts at the end of the script
6. Optional: run `DROP TABLE IF EXISTS icds;`

## Notes

- Official English names are not modified.
- No fake diagnoses are generated.
- Upsert is by `icd_code`, so existing `consultation_diagnoses` links remain valid when the same code already exists.
- Autocomplete keeps working with no PHP/JS changes.
