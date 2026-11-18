# Architecture

React renders locale-scoped public and administrator routes. TanStack Query manages server data and invalidation. Booking progress remains in React state; changing locale preserves the mounted booking component. No customer identity or reservation is persisted in browser localStorage. Prices are integer minor units and formatted using the configured currency’s default minor-unit precision.

Laravel owns availability, all writes, policies, authentication, and media validation. Public catalog queries only publish approved records. Form Requests validate booking input; explicit per-resource validation allowlists protect administrative writes. The AdminPolicy checks every dashboard mutation; only the sole owner (owner_slot=1) can access any administrative API; no account-creation endpoint exists. Guests use an expiring session scoped to one appointment or registration after exchanging a random single-use hashed token. Public references are not authorization credentials. Management tokens use URL fragments, are removed before exchange, and never need to enter server access logs.

## Transactions and locks

Every occupancy-changing appointment or schedule mutation begins an InnoDB transaction and locks the schedule platform_mutexes row, then legacy capacity/staff rows in ascending ID order. This is conservative and serializes salon scheduling writes. It avoids the unsafe empty-range case of locking only appointments that already exist. Availability is recomputed after the staff locks are held. Durations and buffers use half-open occupied intervals; overlap is `existing.start < requested.end AND existing.end > requested.start`.

The implementation deliberately uses one shared appointment capacity across all legacy resource IDs. The explicit mutex also protects an initially empty schedule. This is suitable for a salon; higher-throughput deployments can narrow the set only if all writers preserve deterministic lock order and shared-resource safety. Do not bypass domain services with direct database writes.

Idempotency writes lock a unique scope/key record before executing domain logic; fingerprints reject different payloads under the same key. Result bodies are encrypted at rest because they contain guest tokens and customer snapshots. Appointment scopes include action plus normalized contact identity. Reschedule scopes include the specific appointment. Retry retention is operator-configured, with a documented 30-day default prune example.

Course registration locks the offering row before any settings/plain reads and before counting pending/confirmed registrations. Administrative capacity edits and cancellation lock the same offering. Instructor session edits additionally acquire staff locks and reject appointment/course conflicts. Registrations have a separate capacity model from appointments.

Reschedules lock before reloading the original record, enforce its policy snapshot, use original duration/buffers, and update atomically. A conflict rolls back the request, including the idempotency record. Occupancy never temporarily disappears. Original price and policy remain intact.

## Time and schedules

Database timestamps use UTC. Weekly opening intervals use the salon IANA timezone. Split shifts express breaks. Explicit overrides and blocks use UTC, including salon-wide closures with null staff IDs. Slot generation iterates real UTC instants through the salon-local day, so DST repeated instants remain distinguishable and nonexistent times are not offered. Browser display always uses the salon timezone for booking selection. Server validation is authoritative; polling is only an availability hint.

## Notifications

For guests providing email, an encrypted outbox record is created in the booking transaction. Phone-only guests receive an on-screen confirmation and reference. The scheduler enqueues delivery jobs; workers recheck and lock each outbox row. Delivery failure does not roll back confirmed bookings. Development log transport stores a redacted local status without logging email bodies or guest tokens. SMTP/provider confirmation is recorded as sent; local transports are recorded as logged. Email is at least once, not exactly once, across provider/database failure boundaries.

## Content and SEO

Bilingual JSON fields hold editable content. Required bilingual fields must exist before a record can be saved/published. Laravel renders metadata into the compiled Vite HTML shell on each public page request; content changes are reflected without rebuilding JavaScript. Robots and sitemap routes exclude private flows. No fabricated reviews, prices, or structured business facts are emitted.

Production runs on a PHP-capable host with MySQL. Sites/Cloudflare Workers cannot directly host this Laravel runtime; no backend substitution or external publication was performed.
