CREATE TABLE "migrations"(
  "id" integer primary key autoincrement not null,
  "migration" varchar not null,
  "batch" integer not null
);
CREATE TABLE "password_reset_tokens"(
  "email" varchar not null,
  "token" varchar not null,
  "created_at" datetime,
  primary key("email")
);

CREATE TABLE "failed_jobs"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "connection" text not null,
  "queue" text not null,
  "payload" text not null,
  "exception" text not null,
  "failed_at" datetime not null default CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs"("uuid");
CREATE TABLE "personal_access_tokens"(
  "id" integer primary key autoincrement not null,
  "tokenable_type" varchar not null,
  "tokenable_id" integer not null,
  "name" varchar not null,
  "token" varchar not null,
  "abilities" text,
  "last_used_at" datetime,
  "expires_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "client_id" integer
);
CREATE INDEX "personal_access_tokens_tokenable_type_tokenable_id_index" on "personal_access_tokens"(
  "tokenable_type",
  "tokenable_id"
);
CREATE UNIQUE INDEX "personal_access_tokens_token_unique" on "personal_access_tokens"(
  "token"
);
CREATE TABLE "messages"(
  "id" integer primary key autoincrement not null,
  "client_id" integer not null,
  "contact_id" integer,
  "status_id" integer not null,
  "content" text not null,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE TABLE "clients"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "name" varchar not null,
  "slug" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  "logo" varchar,
  "publishable_key" varchar,
  "identity_secret" text,
  "require_identity_verification" tinyint(1) not null default '0'
);
CREATE UNIQUE INDEX "clients_uuid_unique" on "clients"("uuid");
CREATE UNIQUE INDEX "clients_slug_unique" on "clients"("slug");
CREATE TABLE "stages"(
  "id" integer primary key autoincrement not null,
  "client_id" integer not null,
  "name" varchar not null,
  "position" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  "slug" varchar,
  "color" varchar not null default '#94a3b8',
  "type" varchar not null default 'open',
  "show_on_roadmap" tinyint(1) not null default '0'
);
CREATE TABLE "posts"(
  "id" integer primary key autoincrement not null,
  "client_id" integer not null,
  "stage_id" integer,
  "contact_id" integer,
  "title" varchar not null,
  "content" text,
  "notes" text,
  "status" varchar,
  "stage_position" integer,
  "published_at" datetime,
  "archived_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "board_id" integer
);
CREATE INDEX "posts_status_index" on "posts"("status");
CREATE TABLE "tags"(
  "id" integer primary key autoincrement not null,
  "client_id" integer not null,
  "title" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE TABLE "taggables"(
  "tag_id" integer not null,
  "taggable_id" integer not null,
  "taggable_type" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE TABLE "votes"(
  "id" integer primary key autoincrement not null,
  "contact_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  "votable_type" varchar not null,
  "votable_id" integer not null
);
CREATE TABLE "comments"(
  "id" integer primary key autoincrement not null,
  "contact_id" integer not null,
  "commentable_id" integer not null,
  "commentable_type" varchar not null,
  "content" text not null,
  "hidden_at" datetime,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE TABLE "activity_log"(
  "id" integer primary key autoincrement not null,
  "client_id" integer not null,
  "log_name" varchar,
  "description" text not null,
  "subject_type" varchar,
  "subject_id" integer,
  "causer_type" varchar,
  "causer_id" integer,
  "properties" text,
  "created_at" datetime,
  "updated_at" datetime,
  "event" varchar,
  "batch_uuid" varchar
);
CREATE INDEX "subject" on "activity_log"("subject_type", "subject_id");
CREATE INDEX "causer" on "activity_log"("causer_type", "causer_id");
CREATE INDEX "activity_log_log_name_index" on "activity_log"("log_name");
CREATE TABLE "release_notes"(
  "id" integer primary key autoincrement not null,
  "client_id" integer not null,
  "user_id" integer not null,
  "title" varchar not null,
  "content" text not null,
  "slug" varchar not null,
  "published_at" datetime,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "release_notes_slug_unique" on "release_notes"("slug");
CREATE TABLE "client_user"(
  "id" integer primary key autoincrement not null,
  "client_id" integer not null,
  "user_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE TABLE "weekly_reports"(
  "id" integer primary key autoincrement not null,
  "client_id" integer not null,
  "week_start" date not null,
  "week_end" date not null,
  "metrics" text not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("client_id") references "clients"("id") on delete cascade
);
CREATE UNIQUE INDEX "weekly_reports_client_id_week_start_unique" on "weekly_reports"(
  "client_id",
  "week_start"
);
CREATE INDEX "activity_client_created" on "activity_log"(
  "client_id",
  "created_at"
);
CREATE INDEX "activity_client_causer_created" on "activity_log"(
  "client_id",
  "causer_id",
  "created_at"
);
CREATE INDEX "posts_client_created" on "posts"("client_id", "created_at");
CREATE INDEX "posts_client_published" on "posts"("client_id", "published_at");
CREATE INDEX "posts_contact_created" on "posts"("contact_id", "created_at");
CREATE INDEX "posts_contact_published" on "posts"(
  "contact_id",
  "published_at"
);
CREATE INDEX "comments_contact_created" on "comments"(
  "contact_id",
  "created_at"
);
CREATE INDEX "votes_contact_created" on "votes"("contact_id", "created_at");
CREATE INDEX "votes_votable_type_votable_id_index" on "votes"(
  "votable_type",
  "votable_id"
);
CREATE TABLE "testimonials"(
  "id" integer primary key autoincrement not null,
  "client_id" integer not null,
  "contact_id" integer,
  "title" varchar,
  "description" text not null,
  "rating" integer,
  "avatar_image" varchar,
  "username" varchar not null,
  "subtitle" varchar,
  "approved_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("contact_id") references "contacts"("id") on delete set null on update no action,
  foreign key("client_id") references "clients"("id") on delete cascade on update no action
);
CREATE TABLE "contact_attributes"(
  "id" integer primary key autoincrement not null,
  "client_id" integer not null,
  "name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("client_id") references "clients"("id") on delete cascade
);
CREATE UNIQUE INDEX "contact_attributes_client_id_name_unique" on "contact_attributes"(
  "client_id",
  "name"
);
CREATE TABLE "client_settings"(
  "id" integer primary key autoincrement not null,
  "client_id" integer not null,
  "name" varchar not null,
  "value" text not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "client_settings_name_index" on "client_settings"("name");
CREATE INDEX "stages_type_index" on "stages"("type");
CREATE TABLE "boards"(
  "id" integer primary key autoincrement not null,
  "client_id" integer not null,
  "name" varchar not null,
  "slug" varchar not null,
  "position" integer not null default '0',
  "is_public" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "boards_client_id_slug_unique" on "boards"(
  "client_id",
  "slug"
);
CREATE INDEX "boards_client_id_index" on "boards"("client_id");
CREATE INDEX "posts_board_id_index" on "posts"("board_id");
CREATE TABLE "webhooks"(
  "id" integer primary key autoincrement not null,
  "client_id" integer not null,
  "url" varchar not null,
  "events" text not null,
  "secret" varchar not null,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "webhooks_client_id_index" on "webhooks"("client_id");
CREATE TABLE "webhook_deliveries"(
  "id" integer primary key autoincrement not null,
  "webhook_id" integer not null,
  "event" varchar not null,
  "status_code" integer,
  "success" tinyint(1) not null default '0',
  "response_body" text,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "webhook_deliveries_webhook_id_index" on "webhook_deliveries"(
  "webhook_id"
);
CREATE TABLE "media"(
  "id" integer primary key autoincrement not null,
  "model_type" varchar not null,
  "model_id" integer not null,
  "uuid" varchar,
  "collection_name" varchar not null,
  "name" varchar not null,
  "file_name" varchar not null,
  "mime_type" varchar,
  "disk" varchar not null,
  "conversions_disk" varchar,
  "size" integer not null,
  "manipulations" text not null,
  "custom_properties" text not null,
  "generated_conversions" text not null,
  "responsive_images" text not null,
  "order_column" integer,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "media_model_type_model_id_index" on "media"(
  "model_type",
  "model_id"
);
CREATE UNIQUE INDEX "media_uuid_unique" on "media"("uuid");
CREATE INDEX "media_order_column_index" on "media"("order_column");
CREATE TABLE "sending_domains"(
  "id" integer primary key autoincrement not null,
  "client_id" integer not null,
  "domain" varchar not null,
  "sender_local_part" varchar not null,
  "mail_from_subdomain" varchar not null,
  "status" varchar not null default 'pending',
  "dkim_tokens" text not null,
  "dkim_status" varchar,
  "mail_from_status" varchar,
  "last_checked_at" datetime,
  "verified_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("client_id") references "clients"("id") on delete cascade
);
CREATE INDEX "sending_domains_status_last_checked_at_index" on "sending_domains"(
  "status",
  "last_checked_at"
);
CREATE UNIQUE INDEX "sending_domains_client_id_unique" on "sending_domains"(
  "client_id"
);
CREATE UNIQUE INDEX "sending_domains_domain_unique" on "sending_domains"(
  "domain"
);
CREATE UNIQUE INDEX "clients_publishable_key_unique" on "clients"(
  "publishable_key"
);
CREATE TABLE "instance_settings"(
  "key" varchar not null,
  "value" text,
  "created_at" datetime,
  "updated_at" datetime,
  primary key("key")
);

CREATE UNIQUE INDEX "votes_votable_contact_unique" on "votes"(
  "votable_type",
  "votable_id",
  "contact_id"
);
CREATE TABLE "cache"(
  "key" varchar not null,
  "value" text not null,
  "expiration" integer not null,
  primary key("key")
);

CREATE INDEX "cache_expiration_index" on "cache"("expiration");
CREATE TABLE "cache_locks"(
  "key" varchar not null,
  "owner" varchar not null,
  "expiration" integer not null,
  primary key("key")
);

CREATE INDEX "cache_locks_expiration_index" on "cache_locks"("expiration");
CREATE TABLE "sessions"(
  "id" varchar not null,
  "user_id" integer,
  "ip_address" varchar,
  "user_agent" text,
  "payload" text not null,
  "last_activity" integer not null,
  primary key("id")
);

CREATE INDEX "sessions_user_id_index" on "sessions"("user_id");
CREATE INDEX "sessions_last_activity_index" on "sessions"("last_activity");
CREATE TABLE "jobs"(
  "id" integer primary key autoincrement not null,
  "queue" varchar not null,
  "payload" text not null,
  "attempts" integer not null,
  "reserved_at" integer,
  "available_at" integer not null,
  "created_at" integer not null
);
CREATE INDEX "jobs_queue_index" on "jobs"("queue");
CREATE TABLE "job_batches"(
  "id" varchar not null,
  "name" varchar not null,
  "total_jobs" integer not null,
  "pending_jobs" integer not null,
  "failed_jobs" integer not null,
  "failed_job_ids" text not null,
  "options" text,
  "cancelled_at" integer,
  "created_at" integer not null,
  "finished_at" integer,
  primary key("id")
);

CREATE TABLE "users"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "email" varchar not null collate 'NOCASE',
  "email_verified_at" datetime,
  "password" varchar not null,
  "onboarding" varchar not null,
  "remember_token" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "is_owner" tinyint(1) not null default('0'),
  "deactivated_at" datetime
);
CREATE UNIQUE INDEX "users_email_unique" on "users"("email");
CREATE INDEX "users_onboarding_index" on "users"("onboarding");
CREATE TABLE "contacts"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "user_id" integer,
  "client_id" integer not null,
  "name" varchar,
  "email" varchar collate 'NOCASE',
  "metadata" text,
  "notes" text,
  "avatar" varchar,
  "remember_token" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "ltv" float,
  "mrr" float,
  "top_customer" tinyint(1) not null default('0')
);
CREATE INDEX "contacts_client_created" on "contacts"(
  "client_id",
  "created_at"
);
CREATE UNIQUE INDEX "contacts_uuid_unique" on "contacts"("uuid");

INSERT INTO migrations VALUES(1,'2014_10_12_000000_create_users_table',1);
INSERT INTO migrations VALUES(2,'2014_10_12_100000_create_password_reset_tokens_table',1);
INSERT INTO migrations VALUES(3,'2019_08_19_000000_create_failed_jobs_table',1);
INSERT INTO migrations VALUES(4,'2019_12_14_000001_create_personal_access_tokens_table',1);
INSERT INTO migrations VALUES(5,'2024_02_06_015116_create_messages_table',1);
INSERT INTO migrations VALUES(6,'2024_02_06_015135_create_clients_table',1);
INSERT INTO migrations VALUES(7,'2024_02_06_015230_create_contacts_table',1);
INSERT INTO migrations VALUES(8,'2024_02_06_015318_create_stages_table',1);
INSERT INTO migrations VALUES(9,'2024_02_13_211125_create_form_settings_table',1);
INSERT INTO migrations VALUES(10,'2024_02_14_150105_create_posts_table',1);
INSERT INTO migrations VALUES(11,'2024_02_22_195251_create_tags_table',1);
INSERT INTO migrations VALUES(12,'2024_02_22_195452_create_taggables_table',1);
INSERT INTO migrations VALUES(13,'2024_02_27_201311_create_votes_table',1);
INSERT INTO migrations VALUES(14,'2024_02_27_205348_create_comments_table',1);
INSERT INTO migrations VALUES(15,'2024_03_07_155622_create_activity_log_table',1);
INSERT INTO migrations VALUES(16,'2024_03_07_155623_add_event_column_to_activity_log_table',1);
INSERT INTO migrations VALUES(17,'2024_03_07_155624_add_batch_uuid_column_to_activity_log_table',1);
INSERT INTO migrations VALUES(18,'2024_03_22_143440_add_logo_column_to_clients_table',1);
INSERT INTO migrations VALUES(19,'2024_04_13_003946_create_release_notes_table',1);
INSERT INTO migrations VALUES(20,'2024_05_07_152744_add_revenue_fields_to_contacts_table',1);
INSERT INTO migrations VALUES(21,'2024_05_07_155632_add_top_customer_column_to_contacts_table',1);
INSERT INTO migrations VALUES(22,'2024_06_07_172649_add_status_column_to_release_notes_table',1);
INSERT INTO migrations VALUES(23,'2025_03_19_195526_create_client_users_table',1);
INSERT INTO migrations VALUES(24,'2025_03_19_195612_move_client_id_from_users_table_to_client_user_table',1);
INSERT INTO migrations VALUES(25,'2025_03_19_214937_add_client_id_column_to_personal_access_tokens_table',1);
INSERT INTO migrations VALUES(26,'2025_07_26_181918_create_weekly_reports_table',1);
INSERT INTO migrations VALUES(27,'2025_07_26_221012_add_dashboard_metrics_indexes',1);
INSERT INTO migrations VALUES(28,'2025_08_05_231845_create_testimonials_table',1);
INSERT INTO migrations VALUES(29,'2025_08_05_234516_make_votes_polymorphic',1);
INSERT INTO migrations VALUES(30,'2025_08_06_220132_make_testimonials_title_nullable',1);
INSERT INTO migrations VALUES(31,'2026_02_11_165411_create_contact_attributes_table',1);
INSERT INTO migrations VALUES(32,'2026_02_11_172750_change_value_column_size_on_client_settings_table',1);
INSERT INTO migrations VALUES(33,'2026_06_22_160855_add_status_fields_to_stages_table',1);
INSERT INTO migrations VALUES(34,'2026_06_22_160940_migrate_post_status_to_stages',1);
INSERT INTO migrations VALUES(35,'2026_06_22_162634_create_boards_table',1);
INSERT INTO migrations VALUES(36,'2026_06_22_162635_add_board_id_to_posts_table',1);
INSERT INTO migrations VALUES(37,'2026_06_22_163651_create_webhooks_table',1);
INSERT INTO migrations VALUES(38,'2026_06_22_172416_create_webhook_deliveries_table',1);
INSERT INTO migrations VALUES(39,'2026_06_23_144550_create_media_table',1);
INSERT INTO migrations VALUES(40,'2026_09_25_153000_create_sending_domains_table',1);
INSERT INTO migrations VALUES(41,'2026_11_15_000001_add_widget_keys_to_clients_table',1);
INSERT INTO migrations VALUES(42,'2026_11_15_000002_revoke_public_widget_tokens',1);
INSERT INTO migrations VALUES(43,'2026_11_16_000001_complete_pricing_onboarding',1);
INSERT INTO migrations VALUES(44,'2026_11_17_000100_add_is_owner_to_users_table',1);
INSERT INTO migrations VALUES(45,'2026_11_17_000200_create_instance_settings_table',1);
INSERT INTO migrations VALUES(46,'2026_11_17_000210_add_deactivated_at_to_users_table',1);
INSERT INTO migrations VALUES(47,'2026_11_17_000300_add_unique_index_to_votes_table',1);
INSERT INTO migrations VALUES(48,'2026_11_18_000100_drop_legacy_widget_key_hash_from_clients_table',1);
INSERT INTO migrations VALUES(49,'2026_11_18_000100_make_published_at_the_release_note_switch',1);
INSERT INTO migrations VALUES(50,'2026_11_25_000100_create_database_driver_tables',1);
INSERT INTO migrations VALUES(51,'2026_11_25_000200_make_emails_case_insensitive_on_sqlite',1);
