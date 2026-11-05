# RHUWEB ERD & workflow diagrams

## Files

| File | Use |
|------|-----|
| **`RHUWEB-ERD.drawio`** | Full diagrams for thesis — open in [diagrams.net](https://app.diagrams.net) or VS Code Draw.io extension |
| **`RHUWEB-ERD.mmd`** | Mermaid source (ERD + workflow + analytics) — preview in VS Code or [mermaid.live](https://mermaid.live) |

## Capstone scope (workflow)

The **primary workflow tab** is **04 - Workflow (Capstone — No Receptionist)**:

**Records** (Draft) → **IT** (`send_to_doctor` or `submit_to_it_review`) → **Doctor** → **Pharmacy** → **Dispensed** → **IT** (print queue) → **Printed** → **Archived**

Workflow state lives on **`patients.status`**, with history in **`workflow_logs`**. There is no separate workflow table.

### Out of scope (still in DB for future use)

- **`reception_queue`** and status **Pending Receptionist** — see tab **05 - Workflow (Optional Receptionist)** if you need the legacy path for documentation only.

### Active roles in capstone flow

`records_office`, `it_department`, `doctor`, `pharmacy`, plus optional **`system_administrator`** for admin module.

## Draw.io tabs

1. **01 - Core ERD** — main tables and relationships  
2. **02 - Admin & Reminders** — permissions, audit, follow-up SMS  
3. **03 - Analytics (logical)** — Reports / Heat Map (query-only, no analytics tables)  
4. **04 - Workflow (Capstone — No Receptionist)** — use this in manuscript  
5. **05 - Workflow (Optional Receptionist)** — alternate path, not used in demo  

## Export for manuscript

In Draw.io: **File → Export as → PNG** or **PDF** (300 DPI for print).
