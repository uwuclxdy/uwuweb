### 1. Golden rules
| # | Rule | Rationale |
|---|------|-----------|
| 1 | **Start at `dashboard.php`** and follow the include chain to locate issues. | Mirrors one‑dashboard architecture. |
| 2 | **Use temporary `echo`/`var_dump`/`console.log` probes**, then delete them. | Remember that inline comments are forbidden. |

### 2. Database (MySQL) steps
* Verify connection errors via `$pdo->errorInfo()` or `mysqli_connect_error()`.  
* Compare schema against the inline DB‑schema block in the architecture canvas.  
* Reload seed data quickly: `mysql < seed.sql` (Copilot will offer the command but you must run it). citeturn0search9  


### 6. Role‑switch smoke test
| Role     | Quick toggle (`$_SESSION['role']=…`) | Expected panes in `dashboard.php` |
|----------|--------------------------------------|-----------------------------------|
| admin    | `'admin'`    | All menus + user management |
| teacher  | `'teacher'`  | Own classes + grade/attendance forms |
| student  | `'student'`  | Read‑only grades + *submit absence* |
| parent   | `'parent'`   | Child selector + read‑only |

### 7. Docs ↔ code sync
1. When you **start** a bug‑fix: mark it “🔧 In‑progress” in `checklist.md`.  
2. When you **finish**: describe root cause & fix in `progress.md`, then remove from checklist.  
3. If you add/rename files: update the file‑tree block in the **Copilot Workflow Prompt** so future agent runs stay accurate.

### 8. Last‑resort tips
* `composer dump‑autoload` if you introduced Composer namespaces (otherwise skip).  
* Clear browser cache or enable *Disable cache* in DevTools.  
* Restart Apache & MySQL in XAMPP after changing PHP‑INI or DB user rights.  
* Always backup (`mysqldump`) before destructive tests.
