# Deployment Manifest — E-Tugas Safe Delete QA

| Field | Value |
|-------|--------|
| **Manifest ID** | `2026-05-17-etugas-safe-delete-qa` |
| **Type** | QA sign-off |
| **SQL import required** | **No** |
| **Verdict** | **GO** |

---

## 1. Purpose

QA sign-off for safe delete feature. Includes one post-QA hardening fix (atomic DELETE with NOT EXISTS).

---

## 2. Production files to deploy

| Path | Note |
|------|------|
| `admin/etugas_hapus.php` | New |
| `admin/etugas.php` | Updated list UI |
| `includes/etugas_helpers.php` | **Must include QA atomic delete fix** |

---

## 3. QA-only files (do not upload)

| Path |
|------|
| `tests/etugas_safe_delete_qa_harness.php` |

---

## 4. SQL import required

**No SQL import required.**

---

## 5. Database verification (automated)

| Check | Result |
|-------|--------|
| Empty task delete removes `etugas` row | PASS |
| `etugas_pengumpulan` unchanged on delete | PASS |
| Submitted task delete blocked | PASS |
| `etugas` + `etugas_pengumpulan` unchanged when blocked | PASS |

---

## 6. Validation results

| Check | Result |
|-------|--------|
| `php -l` (3 files) | PASS |
| Harness 28/28 | PASS |

---

## 7. Rollback

Restore three files from backup; delete `admin/etugas_hapus.php` if rolling back feature.

---

## 8. Go/No-Go

**GO** — Include in final E-Tugas hosting bundle with updated `etugas_helpers.php`.

---

## Related

- `2026-05-17-etugas-safe-delete.md` (feature manifest)
- `docs/ai-agent-reports/2026-05-17-etugas-safe-delete-qa.md`
