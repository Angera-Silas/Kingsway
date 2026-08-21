# Normalization mapping — Communications & Content
Part of `10_NORMALIZATION_MAPPING/`. Covers 34 tables. Base evidence: `08_PER_TABLE_BREAKDOWN/09_communications_content.md`, `/tmp/opencode/domains/domain_09.txt`.
Target authority: `09_NORMALIZED_TARGET_ARCHITECTURE.md` §4.14 (communications) + §4.15 (system/audit/workflow). Tags: `[V]` verified · `[I]` inference · `[U]` owner decision.

### 1. `albums`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — pure MASTER (`id`, `name`, `description`, `cover_image`); `created_by` present but not FK-constrained `[V]`; `name` not UNIQUE in dump `[V]`
- **Target home(s):** `albums` (§4.14)
- **Composite / relation key:** `albums.id` — year-agnostic persistent gallery; no year/term context
- **Migration rule:** re-home rows as-is, no re-ID; declare `created_by → users.id` FK `[I]`; optional `UNIQUE(name)` `[I]`; remain the FK target of `media_files.album_id`

### 2. `announcements_bulletin`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `view_count` is a stored derived counter duplicating `announcement_views` rows (redundancy, §1.7); no academic_year context for term-scoped bulletins `[V]`
- **Target home(s):** `announcements_bulletin` (§4.14)
- **Composite / relation key:** `announcements_bulletin.id` + `published_at`; `audience_json` snapshots the target list as-of publish
- **Migration rule:** re-home rows; drop `view_count` (derive as a view from `announcement_views`); add nullable `academic_year_id` + `academic_year_term_id` for term-scoped bulletins per §4.14 (NULL + flag where the term cannot be determined); keep `published_by → staff.id`, status/scheduled/expires lifecycle

### 3. `announcement_views`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none — immutable impression log `[V]`
- **Target home(s):** `announcement_views` (§4.14 read-fact)
- **Composite / relation key:** `(announcement_id, viewer_id, viewed_at)` — one impression event per viewer
- **Migration rule:** re-home rows as-is; keep `announcement_id → announcements_bulletin.id` and `viewer_id → users.id` FKs; become the sole source for bulletin `view_count`; apply `system_retention_policies`-based pruning

### 4. `communication_attachments`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `file_name`/`file_path` duplicate media facts that belong on the `media_files` master (redundancy — same file content re-stored per communication) `[V]`
- **Target home(s):** `communication_attachments` (§4.14) → reshaped to `(communication_id, media_file_id)`
- **Composite / relation key:** `(communication_id, media_file_id)`; `UNIQUE` pair
- **Migration rule:** for each row, upsert a `media_files` row keyed by path/content (or match an existing one); re-home `file_name`/`file_path` into `media_files`, keep the relation `communication_id → communications.id` + `media_file_id → media_files.id`; undeterminable media reference = NULL + flag

### 5. `communication_groups`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — MASTER recipient-group (`name`, `type` enum, `description`); `created_by` FK present `[V]`; `name` not UNIQUE `[V]`
- **Target home(s):** `communication_groups` (§4.14) — **kept as the audience master, NOT merged into `communication_recipients`**
- **Composite / relation key:** `communication_groups.id`; membership lives in `group_members`
- **Migration rule:** re-home rows as-is; optional `UNIQUE(name)` `[I]`; remains the FK target of `group_members.group_id` and the polymorphic `recipient_type='group'` reference from `communication_recipients`

### 6. `communication_logs`
- **Disposition:** MERGE
- **Normalization fault(s):** parallel delivery-timeline log duplicating the single history mechanism; per-recipient delivery state (`status`, `delivered_at`) already lives on `communication_recipients` `[V]`
- **Target home(s):** `audit_logs` (§4.15 — THE history mechanism)
- **Composite / relation key:** `audit_logs(entity_type='communication_recipient', entity_id=communication_recipients.id, action=event_type, acted_at=created_at)`
- **Migration rule:** re-home each row as an append-only `audit_logs` entry typed `entity_type='communication_recipient'`, `action=event_type` (queued/sent/delivered/failed/opened/clicked), `details` carries the legacy `details` text; actor = `NULL` + flag (no sender recorded in source); delivery facts stay on `communication_recipients`

### 7. `communication_recipients`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `recipient_id → users.id` binds a single principal, but sends target staff/students/parents/groups — mixed/under-specified context; `status` enum lacks `queued`; `UNIQUE` missing `[V]`
- **Target home(s):** `communication_recipients` (§4.14) — relation keyed on the normalized polymorphic target reference
- **Composite / relation key:** `(communication_id, recipient_type, recipient_id)` — `(recipient_type, recipient_id)` is the normalized target reference (`user` → `users.id`, `group` → `communication_groups.id`)
- **Migration rule:** re-home rows; add `recipient_type` (resolve from the source audience/group context; where undeterminable default `user` + data-quality flag) and `channel`; keep `communication_id`, `status`, `delivered_at`, and the per-recipient delivery counters (`delivery_attempts`, `last_attempt_at`, `error_message`); `opened_at`/`clicked_at`/`device_info` impressions re-home to `audit_logs` (typed); add `UNIQUE(communication_id, recipient_type, recipient_id)`

### 8. `communications`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** no academic_year context for term-scoped sends; `type` enum + `category` string overlap (duplicate classification) `[V]`
- **Target home(s):** `communications` (§4.14 master)
- **Composite / relation key:** `communications.id`; `sender_id`, `template_id` kept as FKs
- **Migration rule:** re-home rows, re-home `content → body`; keep `(id, type, subject, body, sender_id, sent_at, status)`; add nullable `academic_year_id` + `academic_year_term_id` for academic communications per §4.14 (NULL + flag when not determinable); drop/derive `category` from `type` (or retire the string); keep `template_id → message_templates.id` and `sender_id → users.id`

### 9. `communication_templates`
- **Disposition:** MERGE
- **Normalization fault(s):** two parallel template stores — `communication_templates` and `message_templates` carry the same subject+body+category semantics (redundancy) `[V]`
- **Target home(s):** `message_templates` + `template_categories` (§4.14 canonical template store)
- **Composite / relation key:** `message_templates.id`; category re-homed via `template_categories.id`
- **Migration rule:** re-home rows into `message_templates` (map `template_type` internal_message/sms/email/announcement → `type` email/sms/notification; keep subject/body/variables/status/`created_by`); map `category` string → `template_categories` (match or insert; undeterminable = NULL + flag); merge on identity — never overwrite existing `message_templates` rows; usage_count derived/audited, not stored twice

### 10. `communication_workflow_instances`
- **Disposition:** MERGE
- **Normalization fault(s):** parallel workflow-run store with its own `workflow_code`/`stage_code` strings, duplicating the `workflow_definitions`/`workflow_stages`/`workflow_instances` engine pattern (redundancy + denormalized stage names) `[V]`
- **Target home(s):** `workflow_instances` (§4.15 workflow engine) + `workflow_definitions`/`workflow_stages`
- **Composite / relation key:** `workflow_instances(reference_type='communication', reference_id=communications.id)` — one run per communication
- **Migration rule:** re-home each row to `workflow_instances` with `reference_type='communication'`, `reference_id=communication_id`; resolve `workflow_code` → `workflow_definitions` (by `code`) and `current_stage`/`stage_code` → `workflow_stages` (by `(workflow_id, code)`); unresolvable codes = NULL + flag; `initiated_by` → `started_by`, `initiated_at` → `started_at`, status mapped; stage transitions → `workflow_stage_history`; rows preserved read-only in the migration snapshot

### 11. `contact_directory`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** polymorphic `(contact_type, linked_id)` cannot be FK-enforced (no constraint possible across staff/student/parent/department/external) `[V]`
- **Target home(s):** `contact_directory` (§4.14)
- **Composite / relation key:** `contact_directory.id` + `(contact_type, linked_id)` — documented polymorphic mapping, persistent, not year-bound
- **Migration rule:** re-home rows as-is; document the `contact_type` → table mapping (staff/student/parent/department/external) `[I]`; no invented resolution; no year context

### 12. `contact_inquiries`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — public web lead fact with `ip_address` abuse-audit retention `[V]`
- **Target home(s):** `contact_inquiries` (§4.14)
- **Composite / relation key:** `contact_inquiries.id` + `created_at`
- **Migration rule:** re-home rows as-is; retain `ip_address` for abuse audit; no year context

### 13. `conversation_participants`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `participant_id → staff.id` while `internal_messages.sender_id` is unconstrained — split principal domain (mixed context); `UNIQUE` missing `[V]`
- **Target home(s):** `conversation_participants` (§4.14 messaging domain)
- **Composite / relation key:** `(conversation_id, participant_id)` — membership timeline with `left_at`
- **Migration rule:** re-home rows; add `UNIQUE(conversation_id, participant_id)`; realign `participant_id` onto `users.id` (the canonical messaging principal); `conversation_id → internal_conversations.id` kept

### 14. `external_emails`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** outbound email store not linked to the `communications` master — a parallel send fact (duplicate channel accounting) `[V]`
- **Target home(s):** `external_emails` (§4.14 channel-outbound fact referencing `communications`)
- **Composite / relation key:** `external_emails.id` + `sent_at`; `external_reference_id` correlates with the mail provider
- **Migration rule:** re-home rows; keep `institution_id → external_institutions.id`, `sent_by → staff.id`, provider correlation; add nullable `communication_id → communications.id` and nullable `academic_year_id` + `academic_year_term_id` for term runs (NULL + flag); align status machine (draft/queued/sent/delivered/failed/bounced) with `outbound_messages`

### 15. `external_inbound_messages`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** polymorphic `linked_user_id`/`linked_parent_id`/`linked_student_id` triple — three soft refs where one `(link_type, link_id)` pair suffices (redundant columns) `[V]`
- **Target home(s):** `external_inbound_messages` (§4.14)
- **Composite / relation key:** `external_inbound_messages.id` + `received_at`; `(link_type, link_id)` resolution pair
- **Migration rule:** re-home rows; collapse the triple into `link_type` + `link_id` (user→user, parent→parent, student→student; undeterminable = NULL + flag); keep `source_type`/`source_address`/`status`/`processing_notes`

### 16. `forum_posts`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `thread_id`/`author_id`/`reply_to_id` indexed but not FK-constrained; `author_type` + `author_id` polymorphic `[V]`
- **Target home(s):** `forum_posts` (§4.14)
- **Composite / relation key:** `forum_posts.id` + `created_at`; `author_type` preserves the author's identity class
- **Migration rule:** re-home rows; declare `thread_id → forum_threads.id`, `reply_to_id → forum_posts.id` (self); normalize the author to `users.id` where the polymorphic `(author_type, author_id)` resolves, else NULL + flag; no year context

### 17. `forum_threads`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** class-scoped threads carry no academic_year/class context — year/term attribution impossible (mixed context) `[V]`
- **Target home(s):** `forum_threads` (§4.14)
- **Composite / relation key:** `forum_threads.id` + `created_at`; `forum_type` scopes the thread
- **Migration rule:** re-home rows; declare `created_by → users.id`; when `forum_type='class'`, add nullable `academic_year_id` + `class_id` wired to the academic spine (NULL + flag when the class-year cannot be determined) `[I]`

### 18. `gallery_items`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** parallel display store (`image_url`) overlapping the `media_files`/`albums` media library (redundancy) `[V]`
- **Target home(s):** `gallery_items` (§4.14) — kept as gallery display config
- **Composite / relation key:** `gallery_items.id`; `display_order` + `is_active`
- **Migration rule:** re-home rows as-is; optionally add nullable `media_file_id → media_files.id` and derive `image_url` from the media master (undeterminable = NULL + flag); no year context

### 19. `group_members`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** pure junction lacking `UNIQUE(group_id, user_id)` `[V]`
- **Target home(s):** `group_members` (§4.14) — **kept as the group↔user membership junction, NOT merged into `communication_recipients`** (membership is an audience definition; resolved per-send recipients belong on `communication_recipients`)
- **Composite / relation key:** `(group_id, user_id)`
- **Migration rule:** re-home rows; add `UNIQUE(group_id, user_id)`; keep `group_id → communication_groups.id`, `user_id → users.id`, `added_by → users.id`; no year context

### 20. `internal_conversations`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `participant_count` is a stored derived value (countable from `conversation_participants`, §1.7); `last_message_by → staff.id` while the message principal domain is `users` (mixed context) `[V]`
- **Target home(s):** `internal_conversations` (§4.14 messaging domain)
- **Composite / relation key:** `internal_conversations.id` + `created_at`; `conversation_type` scopes the conversation
- **Migration rule:** re-home rows; drop `participant_count` (derive as a view over `conversation_participants`); realign `last_message_by` onto `users.id`; declare FK from `internal_messages.conversation_id`

### 21. `internal_messages`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `conversation_id`/`sender_id` indexed but not FK-constrained; sender principal unconstrained `[V]`
- **Target home(s):** `internal_messages` (§4.14 messaging domain)
- **Composite / relation key:** `internal_messages.id` + `created_at`; `is_edited`/`last_edited_at` preserve the edit trail
- **Migration rule:** re-home rows; declare `conversation_id → internal_conversations.id` and `sender_id → users.id`; apply retention via `system_retention_policies` for archived/deleted rows; read tracking derives from `message_read_status`

### 22. `media_files`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** polymorphic `context` + `entity_id` cannot be FK-enforced (documented); `uploader_id` not FK-constrained `[V]`
- **Target home(s):** `media_files` (§4.14 media master)
- **Composite / relation key:** `media_files.id`; `album_id → albums.id` FK kept
- **Migration rule:** re-home rows as-is; document the `context`+`entity_id` mapping; declare `uploader_id → users.id`; becomes the FK target of `communication_attachments.media_file_id` and optional `gallery_items.media_file_id`; no year context

### 23. `message_read_status`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `recipient_id → staff.id` while the message principal domain is `users` (mixed context); `UNIQUE` missing `[V]`
- **Target home(s):** `message_read_status` (§4.14 messaging domain)
- **Composite / relation key:** `(message_id, recipient_id)` — one read fact
- **Migration rule:** re-home rows; add `UNIQUE(message_id, recipient_id)`; realign `recipient_id` onto `users.id`; keep `message_id → internal_messages.id`

### 24. `message_templates`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `category` string AND `category_id` FK coexist (duplicate classification); overlaps `communication_templates` (see seq 9) `[V]`
- **Target home(s):** `message_templates` (§4.14 canonical template store) + `template_categories`
- **Composite / relation key:** `message_templates.id`; `category_id → template_categories.id`
- **Migration rule:** re-home rows as the canonical template master; drop the `category` string column (keep `category_id`); absorb `communication_templates` rows (seq 9); keep `created_by → users.id`; remains the FK target of `communications.template_id`

### 25. `news_articles`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `category` string duplicates the `news_categories` taxonomy (denormalized reference); `views` is a stored counter (read facts not captured) `[V]`
- **Target home(s):** `news_articles` (§4.14) + `news_categories`
- **Composite / relation key:** `news_articles.id` + `slug` (UNIQUE); `deleted_at` preserves soft-deleted rows
- **Migration rule:** re-home rows; re-home `category` string → `news_categories.id` FK (match or insert by `slug`; undeterminable = NULL + flag); keep `slug` UNIQUE and soft-delete semantics; honor `deleted_at` in queries `[I]`

### 26. `news_categories`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — display taxonomy master (`name` + `slug` UNIQUE) `[V]`
- **Target home(s):** `news_categories` (§4.14)
- **Composite / relation key:** `news_categories.id` + `slug` (UNIQUE)
- **Migration rule:** re-home rows as-is; becomes the FK master of `news_articles.category` (after seq 25 re-home); no year context

### 27. `newsletter_subscribers`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — subscription fact keyed on `email` UNIQUE; consent-source audit fields absent (traceability gap) `[V]`
- **Target home(s):** `newsletter_subscribers` (§4.14)
- **Composite / relation key:** `email` (UNIQUE); `subscribed_at`/`unsubscribed_at` timeline
- **Migration rule:** re-home rows as-is; optionally add consent-source audit fields `[I]`; no year context

### 28. `notifications`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none structural — per-user notification fact; no academic_year context for term-scoped alerts `[V]`
- **Target home(s):** `notifications` (§4.14 user notification facts)
- **Composite / relation key:** `notifications.id` + `created_at`; `read_status` transition
- **Migration rule:** re-home rows; keep `user_id → users.id`; add nullable `academic_year_id` + `academic_year_term_id` for term-scoped alerts (NULL + flag) `[I]`; prune read rows per `system_retention_policies`

### 29. `outbound_messages`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** parallel send-queue store not linked to the `communications` master (duplicate send fact); `user_id` not FK-constrained `[V]`
- **Target home(s):** `outbound_messages` (§4.14 channel-outbound queue) referencing `communications`
- **Composite / relation key:** `outbound_messages.id` + `created_at`; `template_key` + `payload_json` snapshot the send intent
- **Migration rule:** re-home rows; declare `user_id → users.id`; add nullable `communication_id → communications.id` (NULL + flag where no master send exists); align status machine with `sms_communications`/`external_emails`; driven by `system_background_jobs`

### 30. `page_downloads`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** duplicate storage columns — `storage_filename`/`file_url` and `file_size_bytes`/`file_size` both present (redundancy) `[V]`
- **Target home(s):** `page_downloads` (§4.14)
- **Composite / relation key:** `page_downloads.id` + `public_token` (UNIQUE) with `token_created_at`/`token_revoked_at` lifecycle
- **Migration rule:** re-home rows; resolve `storage_filename` under the configured uploads base path (AGENTS.md convention), not a hard-coded root; collapse `file_url`/`file_size` into `storage_filename`/`file_size_bytes` (single source); keep token revocation lifecycle; no year context

### 31. `school_content`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none — key→value content store keyed on `content_key` UNIQUE `[V]`
- **Target home(s):** `school_content` (§4.14 content management)
- **Composite / relation key:** `content_key` (UNIQUE)
- **Migration rule:** re-home rows as-is; ensure `updated_at` is maintained; no year context

### 32. `sms_communications`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** parallel SMS send store not linked to the `communications` master (duplicate send fact); no academic_year context for term-scoped sends; `template_id` not FK-constrained `[V]`
- **Target home(s):** `sms_communications` (§4.14 channel-outbound fact referencing `communications`)
- **Composite / relation key:** `sms_communications.id` + `sent_at`; `recipient_phone` + `external_reference_id` snapshots
- **Migration rule:** re-home rows; keep `parent_id`/`student_id`/`sent_by` FKs; add nullable `communication_id → communications.id` and nullable `academic_year_id` + `academic_year_term_id` (NULL + flag); declare `template_id → message_templates.id`; keep `character_count`/`sms_parts`/`cost`; align status with `outbound_messages`

### 33. `template_categories`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none — lookup list keyed on `name` UNIQUE `[V]`
- **Target home(s):** `template_categories` (§4.14)
- **Composite / relation key:** `template_categories.id` + `name` (UNIQUE)
- **Migration rule:** re-home rows as-is; remains the FK master of `message_templates.category_id`

### 34. `vw_user_recent_communications`
- **Disposition:** RETIRE
- **Normalization fault(s):** real table misnamed as a view (one of the 8 `vw_*` real tables); denormalized recent-communications digest duplicating `communications` + `communication_recipients` (redundancy); no PK declared `[V]`; name misleads `schema_discovery_cache`
- **Target home(s):** RETIRE — data re-homed into `communications` + `communication_recipients`; genuine `VIEW` re-implemented over those two target tables (§4.14)
- **Composite / relation key:** none (no PK) — digest key is `(recipient_id, created_at)`
- **Migration rule:** archive contents to the one-time migration snapshot (never delete without archive); re-home the underlying facts (they already exist in `communications`/`communication_recipients` — no duplicates inserted); drop the table and create the read-only `vw_user_recent_communications` VIEW over the target relations `[I]`

## Retired in this file
- `vw_user_recent_communications` (→ re-implemented as a genuine view over `communications` + `communication_recipients`)
