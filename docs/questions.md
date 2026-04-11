# questions.md

## Business Logic Questions Log

### 1. What does "offline-first" mean for this Laravel + Vue campus platform?
1. **What sounded ambiguous:** The prompt requires offline-first control on a local network, but it does not spell out whether any external identity provider, SaaS search, cloud storage, or remote queue service is allowed.
2. **How it was understood:** The product must run entirely from the repository with only local services and local network access.
3. **How it was solved:** Keep authentication, API, queueing, file storage, recommendations, imports, reports, and analytics fully local. Do not depend on cloud storage, external messaging, hosted search, or third-party scanning.

### 2. What counts as a "resource" in one system: equipment, venues, and entitlement packages?
1. **What sounded ambiguous:** The prompt covers physical inventory, reservable venues, and membership entitlement packages, but it does not define whether they share one domain model or three unrelated ones.
2. **How it was understood:** They should share a common resource vocabulary while keeping different availability rules.
3. **How it was solved:** Model a shared resource catalog with distinct subtypes: lendable inventory items, reservable venues, and membership-backed entitlements. Use subtype-specific policies for checkout, reservation, and consumption.

### 3. What does "real-time availability" mean in an offline local deployment?
1. **What sounded ambiguous:** The Vue interface must show real-time availability and available quantity, but the prompt does not require websockets or define an update interval.
2. **How it was understood:** The UI should feel immediate on a local network without introducing unnecessary infrastructure.
3. **How it was solved:** Recompute availability on every state-changing operation, refresh the affected views immediately after mutations, and use short local polling for dashboards where contention is likely.

### 4. Do the default per-user limits apply to both active loans and approved reservations?
1. **What sounded ambiguous:** The prompt states a default limit of 2 active items per student, a 7-day standard loan, and 1 renewal if no waitlist, but it does not say whether an approved future reservation consumes the same limit before checkout.
2. **How it was understood:** The system should prevent overcommitment, not just overcheckout.
3. **How it was solved:** Count confirmed checkouts and approved but not yet fulfilled inventory reservations toward the active-item limit. Venue reservations use separate entitlement and capacity rules.

### 5. Are venue reservations governed by the same rules as physical equipment loans?
1. **What sounded ambiguous:** The prompt combines equipment lending and venue entitlement packages, but it does not define whether venue reservations have the same loan duration, renewal, and waitlist behavior as inventory.
2. **How it was understood:** Venues need conflict and entitlement logic, not physical checkout rules.
3. **How it was solved:** Keep venue reservations in the same request workflow family, but enforce time-slot and entitlement consumption rules instead of physical return and renewal rules.

### 6. What exactly are the administrator-configured permission scopes by course, class, and assignment?
1. **What sounded ambiguous:** The prompt says administrators configure permission scopes by course, class, and assignment, but it does not define which actions are scope-bound.
2. **How it was understood:** Scope must constrain who can request, approve, check out, transfer, and reveal sensitive data.
3. **How it was solved:** Represent scope assignments explicitly and enforce them in policy checks for request creation, approval, checkout, check-in, transfer, membership intervention, and sensitive-field reveal.

### 7. What can Teachers and TAs approve or transfer?
1. **What sounded ambiguous:** Teachers and TAs can approve requests for their classes and assignments, perform checkout or check-in during lab sessions, and initiate transfers between departments, but the prompt does not define whether they can act outside their assigned academic scope.
2. **How it was understood:** Their authority must be bounded by both academic scope and departmental custody.
3. **How it was solved:** Teachers and TAs may act only when the request belongs to a class or assignment they are scoped to, and when the item or venue is currently held by a department they are allowed to manage.

### 8. How should inventory availability be calculated when loans, returns, approved reservations, and transfers overlap?
1. **What sounded ambiguous:** The prompt gives the formula in prose, but it does not define the source of truth when multiple transactions occur close together.
2. **How it was understood:** Availability must come from transactional inventory-lot state, not optimistic UI math.
3. **How it was solved:** Calculate available quantity from inventory lots under transaction control: total serviceable quantity minus active checkouts minus approved pending reservations plus completed returns, while excluding lots in active transfer custody.

### 9. How should row-level locking and idempotency interact on state-changing requests?
1. **What sounded ambiguous:** The prompt requires transactional writes, row-level locking, and idempotency keys, but it does not define how duplicate submissions should behave when a lock already exists or a retry occurs.
2. **How it was understood:** The system must give deterministic outcomes instead of racing or double-creating state.
3. **How it was solved:** Require an idempotency key on every state-changing endpoint. For the same key and same normalized payload, return the previously persisted outcome; for the same key with a different payload, reject the request as a conflict. Perform availability deductions inside one transaction with row locks on affected inventory lots or reservation rows.

### 10. How should local queued retries work without external infrastructure?
1. **What sounded ambiguous:** The prompt requires queued retries with exponential backoff capped at 3 attempts, but it does not say what queue backend is allowed offline.
2. **How it was understood:** Queueing must remain local and visible inside the repo.
3. **How it was solved:** Use Laravel's local database-backed queue with a dedicated worker container or process. Define deterministic retry backoff schedules and persist failure reasons for intervention review.

### 11. How should overdue reminders starting 48 hours before due time and repeating every 24 hours be generated?
1. **What sounded ambiguous:** The prompt says reminders are on-screen and repeat every 24 hours, but it does not define whether they are purely computed in the UI or recorded as system events.
2. **How it was understood:** The UI should show reminders reliably, and the system should keep an auditable reminder history.
3. **How it was solved:** Use a local scheduler to generate reminder events at the 48-hour threshold and then every 24 hours until return or closure. Surface active reminders in the UI as badges and dashboard notices.

### 12. How do memberships, stored value, points, and entitlement packages interact?
1. **What sounded ambiguous:** The prompt includes membership tiers, stored-value balances, entitlement packages, earned points, and redemptions, but it does not define whether these are one ledger or several related ledgers.
2. **How it was understood:** These are separate financial or entitlement concepts that should not be mixed.
3. **How it was solved:** Use separate ledgers for stored value, points, and entitlement consumption. Membership tier determines package eligibility and rule limits; stored value funds monetary redemptions; points are earned and spent separately; entitlement packages track time-based access rights and expiration.

### 13. What is the hold policy for suspicious high-frequency actions?
1. **What sounded ambiguous:** The prompt defines warning and temporary hold triggers, but it does not specify how long holds last or whether release is automatic or manual.
2. **How it was understood:** Holds must be predictable, reversible, and auditable.
3. **How it was solved:** Apply configurable automatic holds with a default timed duration and allow administrator release with a required reason. Record the trigger, threshold crossed, actor, and release event in intervention logs.

### 14. How should the "$200.00 equivalent stored value" threshold be represented?
1. **What sounded ambiguous:** The prompt mentions a single redemption over $200.00 equivalent stored value, but it does not define whether the system supports multiple currencies.
2. **How it was understood:** The safest baseline is one local currency representation with exact decimal handling.
3. **How it was solved:** Store all value balances in integer minor units using a single configured currency, defaulting to USD-style precision. Evaluate the high-value redemption rule against that normalized amount.

### 15. How should metadata normalization work for vendor/manufacturer naming in collection imports?
1. **What sounded ambiguous:** The prompt compares the problem to author or dynasty normalization, but it does not define whether normalization edits live source rows, canonical dictionaries, or both.
2. **How it was understood:** Staff need canonical naming without destroying the original import evidence.
3. **How it was solved:** Keep raw imported values, map them to canonical vendor and manufacturer records, and send unresolved aliases into a remediation queue for staff approval.

### 16. How should duplicate detection behave during bulk imports?
1. **What sounded ambiguous:** The prompt requires duplicate detection, but it does not say whether duplicates must be blocked, merged automatically, or merely flagged.
2. **How it was understood:** Exact duplicates should be blocked, while near duplicates should be reviewed.
3. **How it was solved:** Use normalized-title and attribute-fingerprint matching to hard-block exact duplicates and create review tasks for near matches.

### 17. What is the controlled taxonomy and prohibited-word validation boundary?
1. **What sounded ambiguous:** The prompt requires tag validation and prohibited-word detection, but it does not define whether checks apply only to imports or also to manual edits.
2. **How it was understood:** Data quality rules should be enforced consistently.
3. **How it was solved:** Apply taxonomy and prohibited-term validation to both bulk import pipelines and staff-facing create or edit forms. Failed records enter the same remediation queue and appear in downloadable validation reports.

### 18. How should "recommended for your class" explainability be stored?
1. **What sounded ambiguous:** The prompt requires top contributing factors and injected business filters to be traceable, but it does not define whether traces are transient or persisted.
2. **How it was understood:** Recommendation decisions must be reviewable after the fact.
3. **How it was solved:** Persist a rule-trace record for each recommendation batch, including the top factors, applied exclusions, ranking weights, and any manual override reason.

### 19. What does "sensitive fields are masked by default and revealed only within scope" mean operationally?
1. **What sounded ambiguous:** The prompt requires masking and scoped reveal, but it does not define which roles may reveal which fields or whether reveals require an audit reason.
2. **How it was understood:** Sensitive data should stay masked unless a scoped actor performs an explicit reveal action that can be reviewed later.
3. **How it was solved:** Return masked fields by default in API resources, gate reveal actions behind role and scope policies, require a reason on reveal where appropriate, and log the actor, target record, fields revealed, and timestamp.

### 20. How should HTTPS on the local network work if startup must require no manual environment preparation?
1. **What sounded ambiguous:** The prompt requires HTTPS on the local network, while the delivery constraints call for one-command startup with no manual environment input.
2. **How it was understood:** The repo must be able to generate and serve local certificates by itself.
3. **How it was solved:** Generate a local self-signed certificate on first boot, persist it in a runtime volume, and configure the reverse proxy to serve HTTPS by default. Browser trust confirmation is treated as a local client step, not an environment prerequisite.

### 21. How should bootstrap access work in a fully local first-run deployment?
1. **What sounded ambiguous:** The prompt requires local authentication but does not define how the first administrator, teacher, TA, and student accounts appear in a clean deployment.
2. **How it was understood:** A stand-alone repo must be usable immediately after `docker compose up --build`.
3. **How it was solved:** Seed one bootstrap account for each role on first boot, generate strong initial passwords locally, persist them in runtime storage, and force password rotation on first login.

### 22. Which supporting implementation pieces are necessary, and which would count as prompt drift?
1. **What sounded ambiguous:** The repo needs queue workers, scheduling, certificate bootstrapping, seed data, and test harnesses that are not spelled out in the business prompt, but overbuilding would dilute the delivery.
2. **How it was understood:** Only repo content that directly supports startup, security, offline operation, concurrency control, auditability, or required testing should be added.
3. **How it was solved:** Keep local queue and scheduler support, seed inventory and taxonomy data, runtime secret and certificate generation, storage volumes, and test tooling. Exclude cloud integrations, external identity, external scanners, unrelated analytics services, and repository automation not required by the prompt.