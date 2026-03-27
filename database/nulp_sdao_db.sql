CREATE DATABASE IF NOT EXISTS nulp_sdao_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE nulp_sdao_db;

-- USERS
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    school_id VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role_type ENUM('ORG_OFFICER','APPROVER','ADMIN') NOT NULL,
    account_status ENUM('ACTIVE','INACTIVE') DEFAULT 'ACTIVE',
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ORGANIZATIONS
CREATE TABLE organizations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_name VARCHAR(150) NOT NULL,
    organization_type VARCHAR(50) NOT NULL,
    college_department VARCHAR(100) NOT NULL,
    adviser_name VARCHAR(100) NULL,
    founded_date DATE NULL,
    organization_status ENUM('ACTIVE','INACTIVE','PENDING','SUSPENDED') DEFAULT 'PENDING',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- OFFICES
CREATE TABLE offices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    office_name VARCHAR(150) NOT NULL,
    office_head VARCHAR(150) NULL,
    office_email VARCHAR(150) NULL,
    office_status ENUM('ACTIVE','INACTIVE') DEFAULT 'ACTIVE',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ORGANIZATION OFFICERS
CREATE TABLE organization_officers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    position_title VARCHAR(100) NOT NULL,
    term_start DATE NULL,
    term_end DATE NULL,
    officer_status ENUM('ACTIVE','INACTIVE') DEFAULT 'ACTIVE',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_organization_officers_organization
        FOREIGN KEY (organization_id) REFERENCES organizations(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_organization_officers_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ORGANIZATION REGISTRATIONS
CREATE TABLE organization_registrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    submission_date DATE NOT NULL,
    registration_document VARCHAR(255) NULL,
    registration_notes TEXT NULL,
    registration_status ENUM('PENDING','UNDER_REVIEW','APPROVED','REJECTED','REVISION') DEFAULT 'PENDING',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_organization_registrations_organization
        FOREIGN KEY (organization_id) REFERENCES organizations(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_organization_registrations_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ORGANIZATION RENEWALS
CREATE TABLE organization_renewals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    submission_date DATE NOT NULL,
    renewal_document VARCHAR(255) NULL,
    renewal_notes TEXT NULL,
    renewal_status ENUM('PENDING','UNDER_REVIEW','APPROVED','REJECTED','REVISION') DEFAULT 'PENDING',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_organization_renewals_organization
        FOREIGN KEY (organization_id) REFERENCES organizations(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_organization_renewals_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ACTIVITY CALENDARS
CREATE TABLE activity_calendars (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    academic_year VARCHAR(50) NULL,
    semester VARCHAR(50) NULL,
    calendar_file VARCHAR(255) NULL,
    submission_date DATE NULL,
    calendar_status ENUM('PENDING','APPROVED','REJECTED','REVISION') DEFAULT 'PENDING',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_activity_calendars_organization
        FOREIGN KEY (organization_id) REFERENCES organizations(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ACTIVITY PROPOSALS
CREATE TABLE activity_proposals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    calendar_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    activity_title VARCHAR(200) NOT NULL,
    activity_description TEXT NULL,
    proposed_start_date DATE NULL,
    proposed_end_date DATE NULL,
    venue VARCHAR(255) NULL,
    estimated_budget DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    submission_date DATE NULL,
    proposal_status ENUM('PENDING','UNDER_REVIEW','APPROVED','REJECTED','REVISION') DEFAULT 'PENDING',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_activity_proposals_organization
        FOREIGN KEY (organization_id) REFERENCES organizations(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_activity_proposals_calendar
        FOREIGN KEY (calendar_id) REFERENCES activity_calendars(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_activity_proposals_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- APPROVAL WORKFLOWS
CREATE TABLE approval_workflows (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proposal_id BIGINT UNSIGNED NOT NULL,
    office_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    approval_level INT NOT NULL,
    current_step BOOLEAN NOT NULL DEFAULT FALSE,
    review_date DATE NULL,
    acted_at DATETIME NULL,
    decision_status ENUM('PENDING','APPROVED','REJECTED','REVISION_REQUIRED') DEFAULT 'PENDING',
    review_comments TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_approval_workflows_proposal_level
        UNIQUE (proposal_id, approval_level),

    CONSTRAINT fk_approval_workflows_proposal
        FOREIGN KEY (proposal_id) REFERENCES activity_proposals(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_approval_workflows_office
        FOREIGN KEY (office_id) REFERENCES offices(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_approval_workflows_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ACTIVITY REPORTS
CREATE TABLE activity_reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proposal_id BIGINT UNSIGNED NOT NULL,
    organization_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    report_submission_date DATE NULL,
    report_file VARCHAR(255) NULL,
    accomplishment_summary TEXT NULL,
    report_status ENUM('PENDING','REVIEWED','APPROVED','REJECTED') DEFAULT 'PENDING',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_activity_reports_proposal
        FOREIGN KEY (proposal_id) REFERENCES activity_proposals(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_activity_reports_organization
        FOREIGN KEY (organization_id) REFERENCES organizations(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_activity_reports_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- COMMUNICATION THREADS
CREATE TABLE communication_threads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    proposal_id BIGINT UNSIGNED NULL,
    thread_subject VARCHAR(200) NOT NULL,
    thread_type ENUM('PROPOSAL','REGISTRATION','RENEWAL','REPORT') NOT NULL,
    thread_status ENUM('OPEN','CLOSED') DEFAULT 'OPEN',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_communication_threads_organization
        FOREIGN KEY (organization_id) REFERENCES organizations(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_communication_threads_proposal
        FOREIGN KEY (proposal_id) REFERENCES activity_proposals(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- COMMUNICATION MESSAGES
CREATE TABLE communication_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    thread_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    message_content TEXT NOT NULL,
    sent_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    is_read BOOLEAN NOT NULL DEFAULT FALSE,

    CONSTRAINT fk_communication_messages_thread
        FOREIGN KEY (thread_id) REFERENCES communication_threads(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_communication_messages_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;