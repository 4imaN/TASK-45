## Business Logic Questions Log

### 1. What does "offline-first" mean for this Laravel + Vue campus platform?
- **Problem:** The prompt requires offline-first control on a local network, but it does not spell out whether any external identity provider, SaaS search, cloud storage, or remote queue service is allowed.
- **Interpretation:** The product must run entirely from the repository with only local services and local network access.
- **Solution:** Keep authentication, API, queueing, file storage, recommendations, imports, reports, and analytics fully local. Do not depend on cloud storage, external messaging, hosted search, or third-party scanning. :contentReference[oaicite:0]{index=0}

---

### 2. What counts as a "resource" in one system: equipment, venues, and entitlement packages?
- **Problem:** The prompt covers physical inventory, reservable venues, and membership entitlement packages, but it does not define whether they share one domain model or three unrelated ones.
- **Interpretation:** They should share a common resource vocabulary while keeping different availability rules.
- **Solution:** Model a shared resource catalog with distinct subtypes: lendable inventory items, reservable venues, and membership-backed entitlements. Use subtype-specific policies for checkout, reservation, and consumption.

---

### 3. What does "real-time availability" mean in an offline local deployment?
- **Problem:** The Vue interface must show real-time availability and available quantity, but the prompt does not require websockets or define an update interval.
- **Interpretation:** The UI should feel immediate on a local network without introducing unnecessary infrastructure.
- **Solution:** Recompute availability on every state-changing operation, refresh the affected views immediately after mutations, and use short local polling for dashboards where contention is likely.

---

### 4. Do the default per-user limits apply to both active loans and approved reservations?
- **Problem:** The prompt states a default limit of 2 active items per student, a 7-day standard loan, and 1 renewal if no waitlist, but it does not say whether an approved future reservation consumes the same limit before checkout.
- **Interpretation:** The system should prevent overcommitment, not just overcheckout.
- **Solution:** Count confirmed checkouts and approved but not yet fulfilled inventory reservations toward the active-item limit. Venue reservations use separate entitlement and capacity rules.

---

### 5. Are venue reservations governed by the same rules as physical equipment loans?
- **Problem:** The prompt combines equipment lending and venue entitlement packages, but it does not define whether venue reservations have the same loan duration, renewal, and waitlist behavior as inventory.
- **Interpretation:** Venues need conflict and entitlement logic, not physical checkout rules.
- **Solution:** Keep venue reservations in the same request workflow family, but enforce time-slot and entitlement consumption rules instead of physical return and renewal rules.

---

### 6. What exactly are the administrator-configured permission scopes by course, class, and assignment?
- **Problem:** The prompt says administrators configure permission scopes by course, class, and assignment, but it does not define which actions are scope-bound.
- **Interpretation:** Scope must constrain who can request, approve, check out, transfer, and reveal sensitive data.
- **Solution:** Represent scope assignments explicitly and enforce them in policy checks for request creation, approval, checkout, check-in, transfer, membership intervention, and sensitive-field reveal.

---

### 7. What can Teachers and TAs approve or transfer?
- **Problem:** Teachers and TAs can approve requests for their classes and assignments, perform checkout or check-in during lab sessions, and initiate transfers between departments, but the prompt does not define whether they can act outside their assigned academic scope.
- **Interpretation:** Their authority must be bounded by both academic scope and departmental custody.
- **Solution:** Teachers and TAs may act only when the request belongs to a class or assignment they are scoped to, and when the item or venue is currently held by a department they are allowed to manage.

---

### 8. How should inventory availability be calculated when loans, returns, approved reservations, and transfers overlap?
- **Problem:** The prompt gives the formula in prose, but it does not define the source of truth when multiple transactions occur close together.
- **Interpretation:** Availability must come from transactional inventory-lot state, not optimistic UI math.
- **Solution:** Calculate available quantity from inventory lots under transaction control: total serviceable quantity minus active checkouts minus approved pending reservations plus completed returns, while excluding lots in active transfer custody.

---

### 9. How should row-level locking and idempotency interact on state-changing requests?
- **Problem:** The prompt requires transactional writes, row-level locking, and idempotency keys, but it does not define how duplicate submissions should behave when a lock already exists or a retry occurs.
- **Interpretation:** The system must give deterministic outcomes instead of racing or double-creating state.
- **Solution:** Require an idempotency key on every state-changing endpoint. For the same key and same normalized payload, return the previously persisted outcome; for the same key with a different payload, reject the request as a conflict. Perform availability deductions inside one transaction with row locks on affected inventory lots or reservation rows.

---

### 10. How should local queued retries work without external infrastructure?
- **Problem:** The prompt requires queued retries with exponential backoff capped at 3 attempts, but it does not say what queue backend is allowed offline.
- **Interpretation:** Queueing must remain local and visible inside the repo.
- **Solution:** Use Laravel's local database-backed queue with a dedicated worker container or process. Define deterministic retry backoff schedules and persist failure reasons for intervention review.

---

### 11. How should overdue reminders starting 48 hours before due time and repeating every 24 hours be generated?
- **Problem:** The prompt says reminders are on-screen and repeat every 24 hours, but it does not define whether they are purely computed in the UI or recorded as system events.
- **Interpretation:** The UI should show reminders reliably, and the system should keep an auditable reminder history.
- **Solution:** Use a local scheduler to generate reminder events at the 48-hour threshold and then every 24 hours until return or closure. Surface active reminders in the UI as badges and dashboard notices.

---

### 12. How do memberships, stored value, points, and entitlement packages interact?
- **Problem:** The prompt includes membership tiers, stored-value balances, entitlement packages, earned points, and redemptions, but it does not define whether these are one ledger or several related ledgers.
- **Interpretation:** These are separate financial or entitlement concepts that should not be mixed.
- **Solution:** Use separate ledgers for stored value, points, and entitlement consumption. Membership tier determines package eligibility and rule limits; stored value funds monetary redemptions; points are earned and spent separately; entitlement packages track time-based access rights and expiration.

---

### 13. What is the hold policy for suspicious high-frequency actions?
- **Problem:** The prompt defines warning and temporary hold triggers, but it does not specify how long holds last or whether release is automatic or manual.
- **Interpretation:** Holds must be predictable, reversible, and auditable.
- **Solution:** Apply configurable automatic holds with a default timed duration and allow administrator release with a required reason. Record the trigger, threshold crossed, actor, and release event in intervention logs.

---

### 14. How should the "$200.00 equivalent stored value" threshold be represented?
- **Problem:** The prompt mentions a single redemption over $200.00 equivalent stored value, but it does not define whether the system supports multiple currencies.
- **Interpretation:** The safest baseline is one local currency representation with exact decimal handling.
- **Solution:** Store all value balances in integer minor units using a single configured currency, defaulting to USD-style precision. Evaluate the high-value redemption rule against that normalized amount.

---

### 15. How should metadata normalization work for vendor/manufacturer naming in collection imports?
- **Problem:** The prompt compares the problem to author or dynasty normalization, but it does not define whether normalization edits live source rows, canonical dictionaries, or both.
- **Interpretation:** Staff need canonical naming without destroying the original import evidence.
- **Solution:** Keep raw imported values, map them to canonical vendor and manufacturer records, and send unresolved aliases into a remediation queue for staff approval.

---

### 16. How should duplicate detection behave during bulk imports?
- **Problem:** The prompt requires duplicate detection, but it does not say whether duplicates must be blocked, merged automatically, or merely flagged.
- **Interpretation:** Exact duplicates should be blocked, while near duplicates should be reviewed.
- **Solution:** Use normalized-title and attribute-fingerprint matching to hard-block exact duplicates and create review tasks for near matches.

---

### 17. What is the controlled taxonomy and prohibited-word validation boundary?
- **Problem:** The prompt requires tag validation and prohibited-word detection, but it does not define whether checks apply only to imports or also to manual edits.
- **Interpretation:** Data quality rules should be enforced consistently.
- **Solution:** Apply taxonomy and prohibited-term validation to both bulk import pipelines and staff-facing create or edit forms. Failed records enter the same remediation queue and appear in downloadable validation reports.

---

### 18. How should "recommended for your class" explainability be stored?
- **Problem:** The prompt requires top contributing factors and injected business filters to be traceable, but it does not define whether traces are transient or persisted.
- **Interpretation:** Recommendation decisions must be reviewable after the fact.
- **Solution:** Persist a rule-trace record for each recommendation batch, including the top factors, applied exclusions, ranking weights, and any manual override reason.

---

### 19. What does "sensitive fields are masked by default and revealed only within scope" mean operationally?
- **Problem:** The prompt requires masking and scoped reveal, but it does not define which roles may reveal which fields or whether reveals require an audit reason.
- **Interpretation:** Sensitive data should stay masked unless a scoped actor performs an explicit reveal action that can be reviewed later.
- **Solution:** Return masked fields by default in API resources, gate reveal actions behind role and scope policies, require a reason on reveal where appropriate, and log the actor, target record, fields revealed, and timestamp.

---

### 20. How should HTTPS on the local network work if startup must require no manual environment preparation?
- **Problem:** The prompt requires HTTPS on the local network, while the delivery constraints call for one-command startup with no manual environment input.
- **Interpretation:** The repo must be able to generate and serve local certificates by itself.
- **Solution:** Generate a local self-signed certificate on first boot, persist it in a runtime volume, and configure the reverse proxy to serve HTTPS by default. Browser trust confirmation is treated as a local client step, not an environment prerequisite.

---

### 21. How should bootstrap access work in a fully local first-run deployment?
- **Problem:** The prompt requires local authentication but does not define how the first administrator, teacher, TA, and student accounts appear in a clean deployment.
- **Interpretation:** A stand-alone repo must be usable immediately after deployment.
- **Solution:** Seed one bootstrap account for each role on first boot, generate strong initial passwords locally, persist them in runtime storage, and force password rotation on first login.

---

### 22. Which supporting implementation pieces are necessary, and which would count as prompt drift?
- **Problem:** The repo needs queue workers, scheduling, certificate bootstrapping, seed data, and test harnesses that are not spelled out in the business prompt, but overbuilding would dilute the delivery.
- **Interpretation:** Only repo content that directly supports startup, security, offline operation, concurrency control, auditability, or required testing should be added.
- **Solution:** Keep local queue and scheduler support, seed inventory and taxonomy data, runtime secret and certificate generation, storage volumes, and test tooling. Exclude cloud integrations, external identity, external scanners, unrelated analytics services, and repository automation not required by the prompt.
