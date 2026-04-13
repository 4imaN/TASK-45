## Business Logic Questions Log (Simplified Format)

### 1. What does "offline-first" mean for this Laravel + Vue campus platform?
- **Problem:** It is unclear whether external services (cloud, SaaS, identity providers) are allowed.
- **Interpretation:** The system must run fully locally using only repository-contained services.
- **Solution:** Keep all services local (auth, API, queue, storage, analytics); avoid any external dependencies. :contentReference[oaicite:0]{index=0}

---

### 2. What counts as a "resource" in one system?
- **Problem:** Equipment, venues, and entitlement packages are not clearly unified.
- **Interpretation:** They should share a common model but have different rules.
- **Solution:** Use a shared resource catalog with subtypes and subtype-specific policies.

---

### 3. What does "real-time availability" mean offline?
- **Problem:** No definition of real-time updates or required tech (e.g., websockets).
- **Interpretation:** Must feel responsive within a local network.
- **Solution:** Recompute availability on each change and use light polling where needed.

---

### 4. Do limits apply to both loans and reservations?
- **Problem:** Not clear if future reservations count toward limits.
- **Interpretation:** Prevent overcommitment.
- **Solution:** Count both active loans and approved reservations toward limits.

---

### 5. Are venue reservations the same as equipment loans?
- **Problem:** Rules for venues vs equipment not distinguished.
- **Interpretation:** They require different logic.
- **Solution:** Use separate rules for time-slot reservations vs physical loans.

---

### 6. What are permission scopes by course, class, and assignment?
- **Problem:** Actions controlled by scope are not specified.
- **Interpretation:** Scope should control all key operations.
- **Solution:** Enforce scope in request, approval, checkout, transfer, and data access policies.

---

### 7. What can Teachers and TAs approve or transfer?
- **Problem:** Scope boundaries are unclear.
- **Interpretation:** Actions must respect both academic and departmental scope.
- **Solution:** Restrict actions to assigned scope and managed departments.

---

### 8. How is inventory availability calculated?
- **Problem:** Conflicts between overlapping transactions unclear.
- **Interpretation:** Must rely on transactional data.
- **Solution:** Compute from inventory lots using transactional control and exclusions.

---

### 9. How do locking and idempotency interact?
- **Problem:** Behavior on duplicate or concurrent requests unclear.
- **Interpretation:** Must ensure deterministic results.
- **Solution:** Use idempotency keys, reject conflicts, and process in transactions with locks.

---

### 10. How do local queued retries work?
- **Problem:** No queue system defined for offline use.
- **Interpretation:** Queue must be local and visible.
- **Solution:** Use a database-backed queue with retry logic and failure logging.

---

### 11. How are overdue reminders generated?
- **Problem:** Not clear if reminders are UI-based or system events.
- **Interpretation:** Must be reliable and auditable.
- **Solution:** Use scheduler to create reminder events and display in UI.

---

### 12. How do memberships and financial systems interact?
- **Problem:** Stored value, points, and entitlements are unclear.
- **Interpretation:** They are separate systems.
- **Solution:** Use separate ledgers for value, points, and entitlements.

---

### 13. What is the hold policy for suspicious actions?
- **Problem:** Duration and release conditions unclear.
- **Interpretation:** Must be configurable and auditable.
- **Solution:** Apply timed holds with admin override and full logging.

---

### 14. How is the $200 threshold handled?
- **Problem:** Currency handling not defined.
- **Interpretation:** Use a single normalized currency.
- **Solution:** Store values in minor units and evaluate against threshold.

---

### 15. How does metadata normalization work?
- **Problem:** Not clear if raw or canonical data is modified.
- **Interpretation:** Preserve original data while normalizing.
- **Solution:** Map raw values to canonical records and queue unresolved cases.

---

### 16. How does duplicate detection work in imports?
- **Problem:** Blocking vs flagging unclear.
- **Interpretation:** Exact duplicates blocked, near duplicates reviewed.
- **Solution:** Use normalization and fingerprinting with review queue.

---

### 17. What is the validation boundary for taxonomy and prohibited words?
- **Problem:** Not clear if rules apply only to imports.
- **Interpretation:** Must apply everywhere.
- **Solution:** Enforce validation in both imports and manual edits.

---

### 18. How is recommendation explainability stored?
- **Problem:** Not clear if explanations are temporary or persistent.
- **Interpretation:** Must be auditable.
- **Solution:** Store rule-trace records with factors, weights, and overrides.

---

### 19. How are sensitive fields handled?
- **Problem:** Masking and reveal rules unclear.
- **Interpretation:** Default masked, explicit reveal required.
- **Solution:** Enforce role-based reveal with logging and audit trails.

---

### 20. How does HTTPS work locally?
- **Problem:** HTTPS required but no setup allowed.
- **Interpretation:** Must auto-configure.
- **Solution:** Generate self-signed certs on startup and serve via proxy.

---

### 21. How does bootstrap access work?
- **Problem:** Initial accounts not defined.
- **Interpretation:** System must be usable immediately.
- **Solution:** Seed default accounts and require password reset.

---

### 22. What implementation details are necessary?
- **Problem:** Risk of overbuilding beyond requirements.
- **Interpretation:** Only essential components should be included.
- **Solution:** Keep core systems (queue, scheduler, storage, security) and exclude unnecessary integrations.
