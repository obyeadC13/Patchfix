<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260603083123 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Patch Fix core schema, workflow tables, location tables, and seed initial categories/divisions.';
    }

    public function up(Schema $schema): void
    {
        // =========================
        // Users / Auth
        // =========================

        $this->addSql("
            CREATE TABLE users (
                id BIGSERIAL PRIMARY KEY,
                email VARCHAR(180) NOT NULL UNIQUE,
                roles JSONB NOT NULL DEFAULT '[]'::jsonb,
                password VARCHAR(255) NOT NULL,
                full_name VARCHAR(160),
                phone VARCHAR(40),
                is_verified BOOLEAN NOT NULL DEFAULT FALSE,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                last_login_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
            )
        ");

        // =========================
        // Bangladesh Locations
        // =========================

        $this->addSql("
            CREATE TABLE divisions (
                id BIGSERIAL PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                bn_name VARCHAR(120),
                code VARCHAR(30),
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
            )
        ");

        $this->addSql("
            CREATE TABLE districts (
                id BIGSERIAL PRIMARY KEY,
                division_id BIGINT NOT NULL,
                name VARCHAR(120) NOT NULL,
                bn_name VARCHAR(120),
                code VARCHAR(30),
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                CONSTRAINT fk_district_division
                    FOREIGN KEY (division_id)
                    REFERENCES divisions(id)
                    ON DELETE CASCADE
            )
        ");

        $this->addSql("
            CREATE TABLE upazilas (
                id BIGSERIAL PRIMARY KEY,
                district_id BIGINT NOT NULL,
                name VARCHAR(120) NOT NULL,
                bn_name VARCHAR(120),
                code VARCHAR(30),
                type VARCHAR(40) NOT NULL DEFAULT 'upazila',
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                CONSTRAINT fk_upazila_district
                    FOREIGN KEY (district_id)
                    REFERENCES districts(id)
                    ON DELETE CASCADE
            )
        ");

        $this->addSql("
            CREATE TABLE local_areas (
                id BIGSERIAL PRIMARY KEY,
                upazila_id BIGINT NOT NULL,
                name VARCHAR(160) NOT NULL,
                bn_name VARCHAR(160),
                code VARCHAR(30),
                type VARCHAR(40) NOT NULL DEFAULT 'other',
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                CONSTRAINT fk_local_area_upazila
                    FOREIGN KEY (upazila_id)
                    REFERENCES upazilas(id)
                    ON DELETE CASCADE
            )
        ");

        // =========================
        // Issue Core
        // =========================

        $this->addSql("
            CREATE TABLE issue_categories (
                id BIGSERIAL PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                slug VARCHAR(140) NOT NULL UNIQUE,
                description TEXT,
                severity_weight INT NOT NULL DEFAULT 1,
                is_active BOOLEAN NOT NULL DEFAULT TRUE,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
            )
        ");

        $this->addSql("
            CREATE TABLE issues (
                id BIGSERIAL PRIMARY KEY,
                report_reference VARCHAR(40) NOT NULL UNIQUE,
                title VARCHAR(180) NOT NULL,
                description TEXT NOT NULL,
                status VARCHAR(40) NOT NULL DEFAULT 'submitted',
                priority VARCHAR(40) NOT NULL DEFAULT 'normal',
                address_text VARCHAR(255) NOT NULL,
                latitude NUMERIC(10,7),
                longitude NUMERIC(10,7),

                submitted_by_id BIGINT,
                assigned_to_id BIGINT,

                category_id BIGINT NOT NULL,
                division_id BIGINT NOT NULL,
                district_id BIGINT NOT NULL,
                upazila_id BIGINT NOT NULL,
                local_area_id BIGINT,

                confirmation_count INT NOT NULL DEFAULT 0,
                comment_count INT NOT NULL DEFAULT 0,
                photo_count INT NOT NULL DEFAULT 0,

                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                assigned_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                resolved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                rejected_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,

                CONSTRAINT fk_issue_submitted_by
                    FOREIGN KEY (submitted_by_id)
                    REFERENCES users(id)
                    ON DELETE SET NULL,

                CONSTRAINT fk_issue_assigned_to
                    FOREIGN KEY (assigned_to_id)
                    REFERENCES users(id)
                    ON DELETE SET NULL,

                CONSTRAINT fk_issue_category
                    FOREIGN KEY (category_id)
                    REFERENCES issue_categories(id),

                CONSTRAINT fk_issue_division
                    FOREIGN KEY (division_id)
                    REFERENCES divisions(id),

                CONSTRAINT fk_issue_district
                    FOREIGN KEY (district_id)
                    REFERENCES districts(id),

                CONSTRAINT fk_issue_upazila
                    FOREIGN KEY (upazila_id)
                    REFERENCES upazilas(id),

                CONSTRAINT fk_issue_local_area
                    FOREIGN KEY (local_area_id)
                    REFERENCES local_areas(id)
                    ON DELETE SET NULL
            )
        ");

        $this->addSql("
            CREATE TABLE issue_photos (
                id BIGSERIAL PRIMARY KEY,
                issue_id BIGINT NOT NULL,
                uploaded_by_id BIGINT,
                filename VARCHAR(255) NOT NULL,
                original_name VARCHAR(255),
                mime_type VARCHAR(100),
                file_size INT,
                storage_path VARCHAR(255) NOT NULL,
                caption VARCHAR(255),
                type VARCHAR(40) NOT NULL DEFAULT 'report_photo',
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,

                CONSTRAINT fk_issue_photo_issue
                    FOREIGN KEY (issue_id)
                    REFERENCES issues(id)
                    ON DELETE CASCADE,

                CONSTRAINT fk_issue_photo_user
                    FOREIGN KEY (uploaded_by_id)
                    REFERENCES users(id)
                    ON DELETE SET NULL
            )
        ");

        // =========================
        // Workflow / Moderation
        // =========================

        $this->addSql("
            CREATE TABLE issue_status_logs (
                id BIGSERIAL PRIMARY KEY,
                issue_id BIGINT NOT NULL,
                old_status VARCHAR(40),
                new_status VARCHAR(40) NOT NULL,
                changed_by_id BIGINT,
                note TEXT,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,

                CONSTRAINT fk_status_log_issue
                    FOREIGN KEY (issue_id)
                    REFERENCES issues(id)
                    ON DELETE CASCADE,

                CONSTRAINT fk_status_log_user
                    FOREIGN KEY (changed_by_id)
                    REFERENCES users(id)
                    ON DELETE SET NULL
            )
        ");

        $this->addSql("
            CREATE TABLE issue_assignments (
                id BIGSERIAL PRIMARY KEY,
                issue_id BIGINT NOT NULL,
                assigned_to_id BIGINT NOT NULL,
                assigned_by_id BIGINT,
                note TEXT,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                unassigned_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,

                CONSTRAINT fk_assignment_issue
                    FOREIGN KEY (issue_id)
                    REFERENCES issues(id)
                    ON DELETE CASCADE,

                CONSTRAINT fk_assignment_assigned_to
                    FOREIGN KEY (assigned_to_id)
                    REFERENCES users(id)
                    ON DELETE CASCADE,

                CONSTRAINT fk_assignment_assigned_by
                    FOREIGN KEY (assigned_by_id)
                    REFERENCES users(id)
                    ON DELETE SET NULL
            )
        ");

        $this->addSql("
            CREATE TABLE issue_rejections (
                id BIGSERIAL PRIMARY KEY,
                issue_id BIGINT NOT NULL,
                rejected_by_id BIGINT,
                reason VARCHAR(80) NOT NULL,
                details TEXT,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,

                CONSTRAINT fk_rejection_issue
                    FOREIGN KEY (issue_id)
                    REFERENCES issues(id)
                    ON DELETE CASCADE,

                CONSTRAINT fk_rejection_user
                    FOREIGN KEY (rejected_by_id)
                    REFERENCES users(id)
                    ON DELETE SET NULL
            )
        ");

        $this->addSql("
            CREATE TABLE duplicate_issues (
                id BIGSERIAL PRIMARY KEY,
                original_issue_id BIGINT NOT NULL,
                duplicate_issue_id BIGINT NOT NULL,
                marked_by_id BIGINT,
                note TEXT,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,

                CONSTRAINT fk_duplicate_original
                    FOREIGN KEY (original_issue_id)
                    REFERENCES issues(id)
                    ON DELETE CASCADE,

                CONSTRAINT fk_duplicate_duplicate
                    FOREIGN KEY (duplicate_issue_id)
                    REFERENCES issues(id)
                    ON DELETE CASCADE,

                CONSTRAINT fk_duplicate_user
                    FOREIGN KEY (marked_by_id)
                    REFERENCES users(id)
                    ON DELETE SET NULL
            )
        ");

        // =========================
        // Community Interaction
        // =========================

        $this->addSql("
            CREATE TABLE issue_comments (
                id BIGSERIAL PRIMARY KEY,
                issue_id BIGINT NOT NULL,
                author_id BIGINT,
                message TEXT NOT NULL,
                type VARCHAR(40) NOT NULL DEFAULT 'public_comment',
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,

                CONSTRAINT fk_comment_issue
                    FOREIGN KEY (issue_id)
                    REFERENCES issues(id)
                    ON DELETE CASCADE,

                CONSTRAINT fk_comment_author
                    FOREIGN KEY (author_id)
                    REFERENCES users(id)
                    ON DELETE SET NULL
            )
        ");

        $this->addSql("
            CREATE TABLE issue_confirmations (
                id BIGSERIAL PRIMARY KEY,
                issue_id BIGINT NOT NULL,
                user_id BIGINT NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,

                CONSTRAINT fk_confirmation_issue
                    FOREIGN KEY (issue_id)
                    REFERENCES issues(id)
                    ON DELETE CASCADE,

                CONSTRAINT fk_confirmation_user
                    FOREIGN KEY (user_id)
                    REFERENCES users(id)
                    ON DELETE CASCADE,

                CONSTRAINT uniq_issue_confirmation UNIQUE (issue_id, user_id)
            )
        ");

        $this->addSql("
            CREATE TABLE issue_followers (
                id BIGSERIAL PRIMARY KEY,
                issue_id BIGINT NOT NULL,
                user_id BIGINT NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,

                CONSTRAINT fk_follower_issue
                    FOREIGN KEY (issue_id)
                    REFERENCES issues(id)
                    ON DELETE CASCADE,

                CONSTRAINT fk_follower_user
                    FOREIGN KEY (user_id)
                    REFERENCES users(id)
                    ON DELETE CASCADE,

                CONSTRAINT uniq_issue_follower UNIQUE (issue_id, user_id)
            )
        ");

        // =========================
        // Organizations / Teams
        // =========================

        $this->addSql("
            CREATE TABLE organizations (
                id BIGSERIAL PRIMARY KEY,
                name VARCHAR(160) NOT NULL,
                slug VARCHAR(180) NOT NULL UNIQUE,
                description TEXT,
                email VARCHAR(180),
                phone VARCHAR(40),
                website VARCHAR(255),
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
            )
        ");

        $this->addSql("
            CREATE TABLE volunteer_teams (
                id BIGSERIAL PRIMARY KEY,
                organization_id BIGINT,
                name VARCHAR(160) NOT NULL,
                description TEXT,
                division_id BIGINT,
                district_id BIGINT,
                upazila_id BIGINT,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,

                CONSTRAINT fk_team_organization
                    FOREIGN KEY (organization_id)
                    REFERENCES organizations(id)
                    ON DELETE SET NULL,

                CONSTRAINT fk_team_division
                    FOREIGN KEY (division_id)
                    REFERENCES divisions(id)
                    ON DELETE SET NULL,

                CONSTRAINT fk_team_district
                    FOREIGN KEY (district_id)
                    REFERENCES districts(id)
                    ON DELETE SET NULL,

                CONSTRAINT fk_team_upazila
                    FOREIGN KEY (upazila_id)
                    REFERENCES upazilas(id)
                    ON DELETE SET NULL
            )
        ");

        $this->addSql("
            CREATE TABLE team_members (
                id BIGSERIAL PRIMARY KEY,
                team_id BIGINT NOT NULL,
                user_id BIGINT NOT NULL,
                role VARCHAR(40) NOT NULL DEFAULT 'member',
                joined_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                left_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,

                CONSTRAINT fk_team_member_team
                    FOREIGN KEY (team_id)
                    REFERENCES volunteer_teams(id)
                    ON DELETE CASCADE,

                CONSTRAINT fk_team_member_user
                    FOREIGN KEY (user_id)
                    REFERENCES users(id)
                    ON DELETE CASCADE
            )
        ");

        // =========================
        // Notifications / Audit
        // =========================

        $this->addSql("
            CREATE TABLE notifications (
                id BIGSERIAL PRIMARY KEY,
                user_id BIGINT NOT NULL,
                type VARCHAR(80) NOT NULL,
                title VARCHAR(180) NOT NULL,
                message TEXT NOT NULL,
                is_read BOOLEAN NOT NULL DEFAULT FALSE,
                related_issue_id BIGINT,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,

                CONSTRAINT fk_notification_user
                    FOREIGN KEY (user_id)
                    REFERENCES users(id)
                    ON DELETE CASCADE,

                CONSTRAINT fk_notification_issue
                    FOREIGN KEY (related_issue_id)
                    REFERENCES issues(id)
                    ON DELETE SET NULL
            )
        ");

        $this->addSql("
            CREATE TABLE audit_logs (
                id BIGSERIAL PRIMARY KEY,
                actor_id BIGINT,
                action VARCHAR(120) NOT NULL,
                entity_type VARCHAR(120) NOT NULL,
                entity_id BIGINT,
                old_values JSONB,
                new_values JSONB,
                ip_address VARCHAR(80),
                user_agent TEXT,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,

                CONSTRAINT fk_audit_actor
                    FOREIGN KEY (actor_id)
                    REFERENCES users(id)
                    ON DELETE SET NULL
            )
        ");

        // =========================
        // Messenger
        // =========================

        $this->addSql("
            CREATE TABLE messenger_messages (
                id BIGINT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
                body TEXT NOT NULL,
                headers TEXT NOT NULL,
                queue_name VARCHAR(190) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY(id)
            )
        ");

        // =========================
        // Indexes
        // =========================

        $this->addSql("CREATE INDEX idx_district_division ON districts(division_id)");
        $this->addSql("CREATE INDEX idx_upazila_district ON upazilas(district_id)");
        $this->addSql("CREATE INDEX idx_local_area_upazila ON local_areas(upazila_id)");

        $this->addSql("CREATE INDEX idx_issue_status ON issues(status)");
        $this->addSql("CREATE INDEX idx_issue_priority ON issues(priority)");
        $this->addSql("CREATE INDEX idx_issue_category ON issues(category_id)");
        $this->addSql("CREATE INDEX idx_issue_division ON issues(division_id)");
        $this->addSql("CREATE INDEX idx_issue_district ON issues(district_id)");
        $this->addSql("CREATE INDEX idx_issue_upazila ON issues(upazila_id)");
        $this->addSql("CREATE INDEX idx_issue_local_area ON issues(local_area_id)");
        $this->addSql("CREATE INDEX idx_issue_created_at ON issues(created_at)");

        $this->addSql("CREATE INDEX idx_issue_photo_issue ON issue_photos(issue_id)");
        $this->addSql("CREATE INDEX idx_status_log_issue ON issue_status_logs(issue_id)");
        $this->addSql("CREATE INDEX idx_assignment_issue ON issue_assignments(issue_id)");
        $this->addSql("CREATE INDEX idx_comment_issue ON issue_comments(issue_id)");
        $this->addSql("CREATE INDEX idx_confirmation_issue ON issue_confirmations(issue_id)");
        $this->addSql("CREATE INDEX idx_follower_issue ON issue_followers(issue_id)");
        $this->addSql("CREATE INDEX idx_notification_user ON notifications(user_id)");

        $this->addSql("CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)");

        // =========================
        // Seed: Divisions
        // =========================

        $this->addSql("
            INSERT INTO divisions (name, bn_name, code) VALUES
            ('Barishal', 'বরিশাল', '10'),
            ('Chattogram', 'চট্টগ্রাম', '20'),
            ('Dhaka', 'ঢাকা', '30'),
            ('Khulna', 'খুলনা', '40'),
            ('Mymensingh', 'ময়মনসিংহ', '45'),
            ('Rajshahi', 'রাজশাহী', '50'),
            ('Rangpur', 'রংপুর', '55'),
            ('Sylhet', 'সিলেট', '60')
        ");

        // =========================
        // Seed: Issue Categories
        // =========================

        $this->addSql("
            INSERT INTO issue_categories (name, slug, description, severity_weight, is_active) VALUES
            ('Waste', 'waste', 'Garbage, dumping, and waste collection problems', 3, true),
            ('Drainage', 'drainage', 'Blocked drains and sewer overflow issues', 4, true),
            ('Road Damage', 'road-damage', 'Broken roads, potholes, and damaged surfaces', 4, true),
            ('Streetlight', 'streetlight', 'Broken or unsafe public lighting', 2, true),
            ('Waterlogging', 'waterlogging', 'Flooded or waterlogged streets', 5, true),
            ('Public Safety', 'public-safety', 'Safety hazards in public spaces', 5, true),
            ('Footpath', 'footpath', 'Damaged, blocked, or unsafe walking paths', 3, true),
            ('Illegal Dumping', 'illegal-dumping', 'Unauthorized dumping of waste or materials', 4, true),
            ('Noise', 'noise', 'Noise complaints and public disturbance issues', 2, true),
            ('Other', 'other', 'Other civic issues', 1, true)
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE IF EXISTS messenger_messages CASCADE");
        $this->addSql("DROP TABLE IF EXISTS audit_logs CASCADE");
        $this->addSql("DROP TABLE IF EXISTS notifications CASCADE");
        $this->addSql("DROP TABLE IF EXISTS team_members CASCADE");
        $this->addSql("DROP TABLE IF EXISTS volunteer_teams CASCADE");
        $this->addSql("DROP TABLE IF EXISTS organizations CASCADE");
        $this->addSql("DROP TABLE IF EXISTS issue_followers CASCADE");
        $this->addSql("DROP TABLE IF EXISTS issue_confirmations CASCADE");
        $this->addSql("DROP TABLE IF EXISTS issue_comments CASCADE");
        $this->addSql("DROP TABLE IF EXISTS duplicate_issues CASCADE");
        $this->addSql("DROP TABLE IF EXISTS issue_rejections CASCADE");
        $this->addSql("DROP TABLE IF EXISTS issue_assignments CASCADE");
        $this->addSql("DROP TABLE IF EXISTS issue_status_logs CASCADE");
        $this->addSql("DROP TABLE IF EXISTS issue_photos CASCADE");
        $this->addSql("DROP TABLE IF EXISTS issues CASCADE");
        $this->addSql("DROP TABLE IF EXISTS issue_categories CASCADE");
        $this->addSql("DROP TABLE IF EXISTS local_areas CASCADE");
        $this->addSql("DROP TABLE IF EXISTS upazilas CASCADE");
        $this->addSql("DROP TABLE IF EXISTS districts CASCADE");
        $this->addSql("DROP TABLE IF EXISTS divisions CASCADE");
        $this->addSql("DROP TABLE IF EXISTS users CASCADE");
    }
}