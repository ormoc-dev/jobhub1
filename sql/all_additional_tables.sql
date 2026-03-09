-- =====================================================
-- WORKLINK JOB PORTAL - COMPLETE DATABASE SCHEMA
-- =====================================================
-- This is the SINGLE source of truth for the database
-- All tables, indexes, views, procedures, and data are included
-- 
-- Features included:
-- - User management (admin, employee, employer) with approval system
-- - Company profiles with contact person information
-- - Job postings and applications
-- - Interview scheduling and results tracking
-- - Offer management system
-- - Login attempt tracking and security
-- - User activity tracking for inactive account management
-- - Enhanced employee profiles with skills and experience
-- - Profile picture support for employees
-- - Account deletion logging and automation
-- - Performance indexes and views
-- - System configuration
-- - Stored procedures for common operations
-- - Automated cleanup events
-- - Support & Helpdesk System
-- - Compliance & Privacy Management
-- - Content Management System
-- - Matching & Recommendation Engine
--
-- Approval System:
-- - Employees: Auto-approved (status = 'active')
-- - Employers: Require admin approval (status = 'pending')
--
-- Version: 6.0 (Consolidated)
-- Last Updated: 2026
-- =====================================================

CREATE DATABASE IF NOT EXISTS jobhub1;
USE jobhub1;

-- =====================================================
-- CORE TABLES
-- =====================================================

-- Users table (for all three roles)
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'employee', 'employer') NOT NULL,
    status ENUM('active', 'pending', 'suspended') DEFAULT 'active',
    profile_picture VARCHAR(255) NULL,
    two_factor_enabled BOOLEAN DEFAULT 0,
    two_factor_secret VARCHAR(255) NULL,
    email_notifications BOOLEAN DEFAULT 1,
    system_alerts BOOLEAN DEFAULT 1,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Companies table (moved before employee_profiles due to foreign key dependency)
CREATE TABLE IF NOT EXISTS companies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    company_name VARCHAR(100) NOT NULL,
    contact_number VARCHAR(15) NOT NULL,
    contact_email VARCHAR(100) NOT NULL,
    contact_first_name VARCHAR(50) NOT NULL,
    contact_last_name VARCHAR(50) NOT NULL,
    contact_position VARCHAR(100) NOT NULL,
    location_address TEXT NOT NULL,
    business_permit VARCHAR(255),
    company_logo VARCHAR(255),
    supporting_document VARCHAR(255),
    description TEXT,
    status ENUM('active', 'pending', 'suspended') DEFAULT 'pending',
    featured ENUM('yes', 'no') DEFAULT 'no',
    featured_until DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Employee profiles (enhanced with skills and experience)
CREATE TABLE IF NOT EXISTS employee_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    employee_id VARCHAR(20) UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    address TEXT,
    location VARCHAR(200),
    sex ENUM('Male', 'Female') NOT NULL,
    date_of_birth DATE NOT NULL,
    place_of_birth VARCHAR(100),
    contact_no VARCHAR(15) NOT NULL,
    civil_status ENUM('Single', 'Married', 'Divorced', 'Widowed') NOT NULL,
    position VARCHAR(100),
    hired_date DATE,
    highest_education VARCHAR(100) NOT NULL,
    skills VARCHAR(255) NULL,
    experience_level VARCHAR(50) NULL,
    preferred_job_type VARCHAR(50) NULL,
    preferred_salary_range VARCHAR(50) NULL,
    document1 VARCHAR(255),
    document2 VARCHAR(255),
    profile_picture VARCHAR(255) NULL,
    company_id INT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL
);

-- Job categories
CREATE TABLE IF NOT EXISTS job_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Job postings
CREATE TABLE IF NOT EXISTS job_postings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    requirements TEXT,
    category_id INT,
    location VARCHAR(100),
    salary_range VARCHAR(50),
    employment_type ENUM('Onsite', 'Work from Home', 'Hybrid') DEFAULT 'Onsite',
    job_type ENUM('Full Time', 'Part Time', 'Freelance', 'Internship', 'Contract-Based', 'Temporary', 'Work From Home', 'On-Site', 'Hybrid', 'Seasonal') DEFAULT 'Full Time',
    experience_level ENUM('0 to 1 year', '1 to 2 years', '2 to 5 years', '5 to 10 years', '10+ years') DEFAULT '0 to 1 year',
    education_requirement VARCHAR(100) NULL,
    qualification TEXT NULL,
    courses TEXT NULL,
    employees_required ENUM('1', '2-5', '6-10', '11-20', '21-50', '50+') DEFAULT '1',
    status ENUM('active', 'pending', 'closed', 'rejected') DEFAULT 'active',
    posted_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deadline DATE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES job_categories(id) ON DELETE SET NULL
);

-- Job applications
CREATE TABLE IF NOT EXISTS job_applications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    job_id INT NOT NULL,
    employee_id INT NOT NULL,
    cover_letter VARCHAR(255) NULL,
    resume VARCHAR(255),
    id_document VARCHAR(255) NULL,
    tor_document VARCHAR(255) NULL,
    employment_certificate VARCHAR(255) NULL,
    seminar_certificate VARCHAR(255) NULL,
    certificate_of_attachment VARCHAR(255) NULL,
    certificate_of_reports VARCHAR(255) NULL,
    certificate_of_good_standing VARCHAR(255) NULL,
    status ENUM('pending', 'reviewed', 'accepted', 'rejected') DEFAULT 'pending',
    interview_status ENUM('uninterview', 'interviewed') NULL DEFAULT NULL,
    -- Interview scheduling columns
    interview_date DATE NULL,
    interview_time TIME NULL,
    interview_mode ENUM('onsite', 'online', 'phone') NULL,
    interview_location VARCHAR(255) NULL,
    interview_notes TEXT NULL,
    -- Interview result columns
    interview_result ENUM('passed', 'failed', 'pending') NULL,
    interview_rating INT NULL,
    interview_feedback TEXT NULL,
    -- Offer management columns
    offer_sent BOOLEAN DEFAULT 0,
    offer_salary VARCHAR(100) NULL,
    offer_start_date DATE NULL,
    offer_notes TEXT NULL,
    offer_status ENUM('pending', 'accepted', 'rejected', 'withdrawn') NULL,
    offer_sent_date TIMESTAMP NULL,
    applied_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_date TIMESTAMP NULL,
    FOREIGN KEY (job_id) REFERENCES job_postings(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(id) ON DELETE CASCADE
);

-- Saved jobs (favorites)
CREATE TABLE IF NOT EXISTS saved_jobs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    job_id INT NOT NULL,
    saved_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES job_postings(id) ON DELETE CASCADE,
    UNIQUE KEY unique_save (employee_id, job_id)
);

-- Messages table (for future messaging feature)
CREATE TABLE IF NOT EXISTS messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    subject VARCHAR(200),
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    sent_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Notifications table for in-app notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    related_id INT NULL,
    is_read BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_type (type)
);

-- Account deletion log table (for inactive account management)
CREATE TABLE IF NOT EXISTS account_deletion_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    deletion_reason VARCHAR(255) DEFAULT 'Account inactive for 4 years',
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Login attempts tracking table
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ip_address VARCHAR(45) NOT NULL,
    username VARCHAR(100) NOT NULL,
    attempts INT DEFAULT 1,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    locked_until TIMESTAMP NULL,
    INDEX idx_ip_username (ip_address, username),
    INDEX idx_locked_until (locked_until)
);

-- System configuration table
CREATE TABLE IF NOT EXISTS system_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    config_key VARCHAR(100) UNIQUE NOT NULL,
    config_value TEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Document verifications table (supports all document types used in the app)
CREATE TABLE IF NOT EXISTS document_verifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    application_id INT NOT NULL,
    document_type ENUM('resume', 'id_document', 'document1', 'document2', 'tor_document', 'employment_certificate', 'seminar_certificate', 'cover_letter', 'certificate_of_attachment', 'certificate_of_reports', 'certificate_of_good_standing') NOT NULL,
    verified_by INT NOT NULL,
    verification_status ENUM('verified', 'rejected') NOT NULL,
    verification_notes TEXT NULL,
    verified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES job_applications(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_application (application_id),
    INDEX idx_verified_by (verified_by),
    INDEX idx_document_type (document_type)
);

-- =====================================================
-- SUPPORT & HELPDESK TABLES
-- =====================================================

CREATE TABLE IF NOT EXISTS support_tickets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ticket_number VARCHAR(20) NOT NULL UNIQUE,
    user_id INT,
    user_name VARCHAR(100),
    user_email VARCHAR(100) NOT NULL,
    user_type ENUM('employee', 'employer', 'guest') DEFAULT 'guest',
    subject VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    category ENUM('technical', 'billing', 'account', 'general', 'bug_report', 'feature_request') DEFAULT 'general',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('open', 'in_progress', 'pending', 'resolved', 'closed') DEFAULT 'open',
    assigned_to INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    INDEX(status),
    INDEX(priority),
    INDEX(category)
);

CREATE TABLE IF NOT EXISTS ticket_replies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ticket_id INT NOT NULL,
    user_id INT,
    user_name VARCHAR(100),
    message TEXT NOT NULL,
    is_admin BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS error_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    error_type ENUM('php', 'javascript', 'database', 'security', 'api', 'system') DEFAULT 'system',
    severity ENUM('info', 'warning', 'error', 'critical') DEFAULT 'error',
    message TEXT NOT NULL,
    file VARCHAR(255),
    line INT,
    stack_trace TEXT,
    user_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    request_uri VARCHAR(255),
    request_method VARCHAR(10),
    is_resolved BOOLEAN DEFAULT FALSE,
    resolved_by INT,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(error_type),
    INDEX(severity),
    INDEX(is_resolved)
);

CREATE TABLE IF NOT EXISTS system_health (
    id INT PRIMARY KEY AUTO_INCREMENT,
    component VARCHAR(50) NOT NULL,
    status ENUM('operational', 'degraded', 'partial_outage', 'major_outage', 'maintenance') DEFAULT 'operational',
    response_time INT,
    last_check TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    details JSON,
    INDEX(component)
);

CREATE TABLE IF NOT EXISTS maintenance_schedules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    scheduled_start DATETIME NOT NULL,
    scheduled_end DATETIME NOT NULL,
    actual_start DATETIME NULL,
    actual_end DATETIME NULL,
    status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
    affected_services JSON,
    created_by INT,
    created_at DATETIME DEFAULT NULL
);

-- =====================================================
-- COMPLIANCE & PRIVACY TABLES
-- =====================================================

CREATE TABLE IF NOT EXISTS privacy_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action_type ENUM('data_access', 'data_export', 'data_deletion', 'consent_change', 'profile_update', 'login_attempt', 'password_change') NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    affected_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at)
);

CREATE TABLE IF NOT EXISTS terms_policies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    policy_type ENUM('terms_of_service', 'privacy_policy', 'cookie_policy', 'acceptable_use', 'data_processing', 'community_guidelines') NOT NULL,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT NOT NULL,
    version VARCHAR(20) NOT NULL,
    effective_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT FALSE,
    requires_acceptance BOOLEAN DEFAULT TRUE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_policy_type (policy_type),
    INDEX idx_is_active (is_active)
);

CREATE TABLE IF NOT EXISTS policy_acceptances (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    policy_id INT NOT NULL,
    accepted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    UNIQUE KEY unique_acceptance (user_id, policy_id),
    INDEX idx_user_id (user_id),
    INDEX idx_policy_id (policy_id)
);

CREATE TABLE IF NOT EXISTS audit_trails (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    admin_id INT,
    action_category ENUM('user_management', 'content_moderation', 'system_config', 'data_access', 'security', 'compliance', 'financial') NOT NULL,
    action_type VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    old_values JSON,
    new_values JSON,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    risk_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_admin_id (admin_id),
    INDEX idx_action_category (action_category),
    INDEX idx_risk_level (risk_level),
    INDEX idx_created_at (created_at)
);

CREATE TABLE IF NOT EXISTS data_retention_policies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    policy_name VARCHAR(100) NOT NULL,
    data_type ENUM('user_accounts', 'job_applications', 'messages', 'activity_logs', 'uploaded_files', 'session_data', 'analytics', 'backups') NOT NULL,
    retention_period INT NOT NULL COMMENT 'in days',
    action_after_expiry ENUM('delete', 'anonymize', 'archive', 'review') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    auto_execute BOOLEAN DEFAULT FALSE,
    last_executed_at TIMESTAMP NULL,
    notification_days INT DEFAULT 30 COMMENT 'days before action to notify',
    legal_basis TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_data_type (data_type),
    INDEX idx_is_active (is_active)
);

CREATE TABLE IF NOT EXISTS data_deletion_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    request_type ENUM('full_deletion', 'partial_deletion', 'data_export', 'account_anonymization') NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'rejected', 'cancelled') DEFAULT 'pending',
    reason TEXT,
    data_categories JSON,
    admin_notes TEXT,
    processed_by INT,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    scheduled_deletion_date DATE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_request_type (request_type)
);

-- =====================================================
-- SYSTEM & ADMIN TABLES
-- =====================================================

CREATE TABLE IF NOT EXISTS admin_roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    permissions JSON,
    is_system BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS security_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type VARCHAR(20) DEFAULT 'string',
    description TEXT,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS platform_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value TEXT,
    config_type VARCHAR(20) DEFAULT 'string',
    category VARCHAR(50),
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS api_keys (
    id INT PRIMARY KEY AUTO_INCREMENT,
    key_name VARCHAR(100) NOT NULL,
    api_key VARCHAR(255) NOT NULL UNIQUE,
    api_secret VARCHAR(255),
    service_type ENUM('internal', 'external', 'webhook') DEFAULT 'internal',
    permissions JSON,
    rate_limit INT DEFAULT 1000,
    is_active BOOLEAN DEFAULT TRUE,
    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS automation_rules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    rule_name VARCHAR(100) NOT NULL,
    rule_type ENUM('cleanup', 'notification', 'status_change', 'archive') NOT NULL,
    conditions JSON,
    actions JSON,
    is_active BOOLEAN DEFAULT TRUE,
    last_executed_at TIMESTAMP NULL,
    execution_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- CONTENT MANAGEMENT TABLES
-- =====================================================

CREATE TABLE IF NOT EXISTS announcements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    type ENUM('info', 'warning', 'success', 'urgent') DEFAULT 'info',
    target_audience ENUM('all', 'employers', 'employees') DEFAULT 'all',
    is_active TINYINT(1) DEFAULT 1,
    start_date DATE NULL,
    end_date DATE NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS faqs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    question VARCHAR(500) NOT NULL,
    answer TEXT NOT NULL,
    category ENUM('general', 'employers', 'jobseekers', 'account', 'technical') DEFAULT 'general',
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS career_resources (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    content TEXT NOT NULL,
    category ENUM('resume', 'interview', 'career', 'skills', 'workplace') DEFAULT 'career',
    image VARCHAR(255) NULL,
    is_featured TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS system_pages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    slug VARCHAR(100) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    meta_description VARCHAR(300),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =====================================================
-- MATCHING & RECOMMENDATION TABLES
-- =====================================================

CREATE TABLE IF NOT EXISTS matching_rules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    rule_name VARCHAR(100) NOT NULL,
    rule_type ENUM('skill', 'experience', 'education', 'location', 'salary') NOT NULL,
    weight DECIMAL(3,2) DEFAULT 1.00,
    is_required BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS skill_taxonomy (
    id INT PRIMARY KEY AUTO_INCREMENT,
    skill_name VARCHAR(100) NOT NULL,
    parent_id INT NULL,
    category VARCHAR(100) NOT NULL,
    proficiency_levels JSON,
    synonyms JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES skill_taxonomy(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS recommendation_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('number', 'boolean', 'string', 'json') DEFAULT 'string',
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =====================================================
-- EMPLOYER REPORTS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS employer_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    reported_by INT NOT NULL,
    report_type ENUM('fraudulent', 'scam', 'harassment', 'misleading', 'spam', 'other') NOT NULL,
    description TEXT NOT NULL,
    evidence VARCHAR(255) NULL,
    status ENUM('pending', 'investigating', 'resolved', 'dismissed') DEFAULT 'pending',
    admin_notes TEXT NULL,
    resolved_by INT NULL,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- EMPLOYER SETTINGS TABLES
-- =====================================================

CREATE TABLE IF NOT EXISTS company_team_members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employer_id INT NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    role_title VARCHAR(100) NOT NULL,
    status VARCHAR(30) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_employer_email (employer_id, email),
    FOREIGN KEY (employer_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS login_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    device_type VARCHAR(20) NOT NULL,
    browser VARCHAR(50) NOT NULL,
    location VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT 1,
    session_id VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_active (is_active),
    INDEX idx_last_activity (last_activity)
);

-- =====================================================
-- FAST APPLICATIONS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS fast_applications (
    application_id INT PRIMARY KEY,
    is_priority BOOLEAN DEFAULT FALSE,
    priority_score INT DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES job_applications(id) ON DELETE CASCADE,
    INDEX idx_priority (is_priority, priority_score)
);

-- =====================================================
-- FOLLOWED COMPANIES TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS followed_companies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    company_id INT NOT NULL,
    followed_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    UNIQUE KEY unique_follow (employee_id, company_id)
);

-- =====================================================
-- DEFAULT DATA INSERTS
-- =====================================================

-- Insert default admin user (admin@gmail.com / admin123)
INSERT IGNORE INTO users (username, email, password, role, status) VALUES 
('admin', 'admin@gmail.com', '$2y$10$YQ3O0yxQ7qvY7896dYPgyuEI6wea6M1VgTJWDY.qxW4YNyPE1NQEi', 'admin', 'active');

-- Insert default categories
INSERT IGNORE INTO job_categories (category_name, description) VALUES 
('Information Technology', 'Software development, IT support, cybersecurity, etc.'),
('Healthcare', 'Medical professionals, nurses, healthcare support'),
('Education', 'Teachers, professors, educational administration'),
('Finance', 'Banking, accounting, financial analysis'),
('Marketing', 'Digital marketing, sales, advertising'),
('Engineering', 'Civil, mechanical, electrical, software engineering'),
('Human Resources', 'Recruitment, employee relations, HR management'),
('Customer Service', 'Support representatives, call center agents'),
('Manufacturing', 'Production, quality control, industrial work'),
('Administrative', 'Office administration, clerical work, data entry');

-- Insert system configuration (with approval system settings)
INSERT IGNORE INTO system_config (config_key, config_value, description) VALUES 
('site_name', 'WORKLINK', 'Website name'),
('site_url', 'http://localhost/jobhub1/', 'Website URL'),
('max_login_attempts', '8', 'Maximum login attempts before lockout'),
('lockout_duration', '300', 'Lockout duration in seconds (5 minutes)'),
('password_min_length', '8', 'Minimum password length'),
('registration_approval_required', 'true', 'Whether admin approval is required for employer registration'),
('job_approval_required', 'false', 'Whether admin approval is required for job postings'),
('employee_auto_approval', 'true', 'Whether employees are automatically approved upon registration'),
('employer_approval_required', 'true', 'Whether employers require admin approval upon registration'),
('inactive_account_deletion_enabled', 'true', 'Enable automatic deletion of inactive job seeker accounts'),
('inactive_account_period', '4', 'Years of inactivity before account deletion'),
('profile_picture_required', 'false', 'Whether profile picture is required for employees'),
('skills_max_count', '3', 'Maximum number of skills allowed per employee');

-- Default security settings
INSERT IGNORE INTO security_settings (setting_key, setting_value, setting_type, description) VALUES 
('max_login_attempts', '5', 'number', 'Maximum failed login attempts before lockout'),
('lockout_duration', '30', 'number', 'Account lockout duration in minutes'),
('session_timeout', '120', 'number', 'Session timeout in minutes'),
('password_min_length', '8', 'number', 'Minimum password length'),
('require_2fa_admin', '0', 'boolean', 'Require 2FA for admin accounts'),
('require_2fa_employer', '0', 'boolean', 'Require 2FA for employer accounts'),
('ip_whitelist', '', 'text', 'Whitelisted IP addresses (comma separated)'),
('ip_blacklist', '', 'text', 'Blacklisted IP addresses (comma separated)'),
('force_https', '1', 'boolean', 'Force HTTPS connections'),
('csrf_protection', '1', 'boolean', 'Enable CSRF protection');

-- Default recommendation settings
INSERT IGNORE INTO recommendation_settings (setting_key, setting_value, setting_type, description) VALUES 
('min_match_score', '60', 'number', 'Minimum match score percentage to show recommendations'),
('max_recommendations', '10', 'number', 'Maximum number of job recommendations per user'),
('skill_weight', '40', 'number', 'Weight percentage for skill matching'),
('experience_weight', '25', 'number', 'Weight percentage for experience matching'),
('education_weight', '20', 'number', 'Weight percentage for education matching'),
('location_weight', '15', 'number', 'Weight percentage for location matching'),
('enable_ai_matching', '1', 'boolean', 'Enable AI-powered job matching'),
('enable_email_notifications', '1', 'boolean', 'Send email notifications for new matches'),
('refresh_interval', '24', 'number', 'Hours between recommendation refreshes'),
('consider_salary_range', '1', 'boolean', 'Include salary expectations in matching algorithm'),
('boost_premium_jobs', '1', 'boolean', 'Give higher visibility to premium job postings'),
('match_algorithm', 'weighted', 'string', 'Matching algorithm type (weighted, ai, hybrid)');

-- Default data retention policies
INSERT IGNORE INTO data_retention_policies (policy_name, data_type, retention_period, action_after_expiry, is_active, auto_execute, notification_days, legal_basis) VALUES 
('Inactive User Accounts', 'user_accounts', 730, 'delete', TRUE, FALSE, 30, 'GDPR Article 17 - Right to erasure after 2 years of inactivity'),
('Old Job Applications', 'job_applications', 365, 'anonymize', TRUE, FALSE, 14, 'Data minimization - applications older than 1 year'),
('Message History', 'messages', 180, 'archive', TRUE, FALSE, 7, 'Business communication retention policy'),
('Activity Logs', 'activity_logs', 90, 'delete', TRUE, TRUE, 0, 'Operational data - 90 day rolling window'),
('Uploaded Files', 'uploaded_files', 365, 'review', TRUE, FALSE, 30, 'Document retention for compliance verification'),
('Session Data', 'session_data', 30, 'delete', TRUE, TRUE, 0, 'Security best practice - short session retention'),
('Analytics Data', 'analytics', 365, 'anonymize', TRUE, TRUE, 0, 'Statistical analysis - anonymized after 1 year'),
('System Backups', 'backups', 90, 'delete', TRUE, TRUE, 7, 'Disaster recovery - 90 day backup retention');

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================

CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_users_status ON users(status);
CREATE INDEX IF NOT EXISTS idx_users_last_activity ON users(last_activity);
CREATE INDEX IF NOT EXISTS idx_companies_status ON companies(status);
CREATE INDEX IF NOT EXISTS idx_job_postings_status ON job_postings(status);
CREATE INDEX IF NOT EXISTS idx_job_postings_company ON job_postings(company_id);
CREATE INDEX IF NOT EXISTS idx_job_applications_status ON job_applications(status);
CREATE INDEX IF NOT EXISTS idx_job_applications_job ON job_applications(job_id);
CREATE INDEX IF NOT EXISTS idx_job_applications_employee ON job_applications(employee_id);
CREATE INDEX IF NOT EXISTS idx_employee_profiles_skills ON employee_profiles(skills);
CREATE INDEX IF NOT EXISTS idx_employee_profiles_experience ON employee_profiles(experience_level);
CREATE INDEX IF NOT EXISTS idx_account_deletion_log_deleted_at ON account_deletion_log(deleted_at);

-- =====================================================
-- VIEWS FOR COMMON QUERIES
-- =====================================================

CREATE OR REPLACE VIEW active_jobs AS
SELECT jp.*, c.company_name, c.contact_email, c.contact_first_name, c.contact_last_name, c.contact_position, c.location_address, jc.category_name
FROM job_postings jp
JOIN companies c ON jp.company_id = c.id
LEFT JOIN job_categories jc ON jp.category_id = jc.id
WHERE jp.status = 'active' AND c.status = 'active';

CREATE OR REPLACE VIEW job_application_details AS
SELECT ja.*, jp.title as job_title, jp.location, c.company_name, 
       ep.first_name, ep.last_name, u.email as applicant_email, ep.contact_no
FROM job_applications ja
JOIN job_postings jp ON ja.job_id = jp.id
JOIN companies c ON jp.company_id = c.id
JOIN employee_profiles ep ON ja.employee_id = ep.id
JOIN users u ON ep.user_id = u.id;

-- =====================================================
-- STORED PROCEDURES
-- =====================================================

DELIMITER //

DROP PROCEDURE IF EXISTS CleanupOldLoginAttempts //
CREATE PROCEDURE CleanupOldLoginAttempts()
BEGIN
    DELETE FROM login_attempts 
    WHERE last_attempt < DATE_SUB(NOW(), INTERVAL 24 HOUR);
END //

DROP PROCEDURE IF EXISTS GetUserStats //
CREATE PROCEDURE GetUserStats()
BEGIN
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'employee' AND status = 'active') as active_employees,
        (SELECT COUNT(*) FROM users WHERE role = 'employer' AND status = 'active') as active_employers,
        (SELECT COUNT(*) FROM companies WHERE status = 'active') as active_companies,
        (SELECT COUNT(*) FROM job_postings WHERE status = 'active') as active_jobs,
        (SELECT COUNT(*) FROM job_applications) as total_applications;
END //

-- Create a stored procedure to clean up inactive accounts
DROP PROCEDURE IF EXISTS CleanupInactiveAccounts //
CREATE PROCEDURE CleanupInactiveAccounts()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE user_id_var INT;
    DECLARE username_var VARCHAR(50);
    DECLARE email_var VARCHAR(100);
    
    -- Cursor for inactive job seeker accounts
    DECLARE inactive_cursor CURSOR FOR 
        SELECT u.id, u.username, u.email 
        FROM users u 
        WHERE u.role = 'employee' 
        AND u.last_activity < DATE_SUB(NOW(), INTERVAL 4 YEAR)
        AND u.status = 'active';
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN inactive_cursor;
    
    read_loop: LOOP
        FETCH inactive_cursor INTO user_id_var, username_var, email_var;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Log the deletion
        INSERT INTO account_deletion_log (user_id, username, email, deletion_reason)
        VALUES (user_id_var, username_var, email_var, 'Account inactive for 4 years');
        
        -- Delete the user (cascade will handle related records)
        DELETE FROM users WHERE id = user_id_var;
        
    END LOOP;
    
    CLOSE inactive_cursor;
END //

DELIMITER ;

-- =====================================================
-- EVENTS FOR AUTOMATION
-- =====================================================

-- Create an event to run the cleanup procedure monthly
DROP EVENT IF EXISTS cleanup_inactive_accounts;
CREATE EVENT IF NOT EXISTS cleanup_inactive_accounts
ON SCHEDULE EVERY 1 MONTH
STARTS CURRENT_TIMESTAMP
DO
  CALL CleanupInactiveAccounts();

-- Enable event scheduler
SET GLOBAL event_scheduler = ON;

-- =====================================================
-- UPGRADE SCRIPTS (Safe to run multiple times)
-- =====================================================

SET @db := DATABASE();

-- Add interview and offer columns if they don't exist
SET @stmt = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'interview_date'
    ),
    'SELECT "interview_date exists"',
    'ALTER TABLE job_applications ADD COLUMN interview_date DATE NULL AFTER interview_status'
  )
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'interview_time'
    ),
    'SELECT "interview_time exists"',
    'ALTER TABLE job_applications ADD COLUMN interview_time TIME NULL AFTER interview_date'
  )
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'interview_mode'
    ),
    'SELECT "interview_mode exists"',
    'ALTER TABLE job_applications ADD COLUMN interview_mode ENUM(''onsite'', ''online'', ''phone'') NULL AFTER interview_time'
  )
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'interview_location'
    ),
    'SELECT "interview_location exists"',
    'ALTER TABLE job_applications ADD COLUMN interview_location VARCHAR(255) NULL AFTER interview_mode'
  )
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'interview_notes'
    ),
    'SELECT "interview_notes exists"',
    'ALTER TABLE job_applications ADD COLUMN interview_notes TEXT NULL AFTER interview_location'
  )
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'interview_result'
    ),
    'SELECT "interview_result exists"',
    'ALTER TABLE job_applications ADD COLUMN interview_result ENUM(''passed'', ''failed'', ''pending'') NULL AFTER interview_notes'
  )
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'interview_rating'
    ),
    'SELECT "interview_rating exists"',
    'ALTER TABLE job_applications ADD COLUMN interview_rating INT NULL AFTER interview_result'
  )
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'interview_feedback'
    ),
    'SELECT "interview_feedback exists"',
    'ALTER TABLE job_applications ADD COLUMN interview_feedback TEXT NULL AFTER interview_rating'
  )
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'offer_sent'
    ),
    'SELECT "offer_sent exists"',
    'ALTER TABLE job_applications ADD COLUMN offer_sent BOOLEAN DEFAULT 0 AFTER interview_feedback'
  )
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'offer_salary'
    ),
    'SELECT "offer_salary exists"',
    'ALTER TABLE job_applications ADD COLUMN offer_salary VARCHAR(100) NULL AFTER offer_sent'
  )
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'offer_start_date'
    ),
    'SELECT "offer_start_date exists"',
    'ALTER TABLE job_applications ADD COLUMN offer_start_date DATE NULL AFTER offer_salary'
  )
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'offer_notes'
    ),
    'SELECT "offer_notes exists"',
    'ALTER TABLE job_applications ADD COLUMN offer_notes TEXT NULL AFTER offer_start_date'
  )
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'offer_status'
    ),
    'SELECT "offer_status exists"',
    'ALTER TABLE job_applications ADD COLUMN offer_status ENUM(''pending'', ''accepted'', ''rejected'', ''withdrawn'') NULL AFTER offer_notes'
  )
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'offer_sent_date'
    ),
    'SELECT "offer_sent_date exists"',
    'ALTER TABLE job_applications ADD COLUMN offer_sent_date TIMESTAMP NULL AFTER offer_status'
  )
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

-- V5 specific upgrades
SET @stmt = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'id_document'
    ),
    'SELECT "id_document exists"',
    'ALTER TABLE job_applications ADD COLUMN id_document VARCHAR(255) NULL AFTER resume'
  )
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'tor_document'
    ),
    'SELECT "tor_document exists"',
    'ALTER TABLE job_applications ADD COLUMN tor_document VARCHAR(255) NULL AFTER id_document'
  )
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'employment_certificate'
    ),
    'SELECT "employment_certificate exists"',
    'ALTER TABLE job_applications ADD COLUMN employment_certificate VARCHAR(255) NULL AFTER tor_document'
  )
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'seminar_certificate'
    ),
    'SELECT "seminar_certificate exists"',
    'ALTER TABLE job_applications ADD COLUMN seminar_certificate VARCHAR(255) NULL AFTER employment_certificate'
  )
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'certificate_of_attachment'
    ),
    'SELECT "certificate_of_attachment exists"',
    'ALTER TABLE job_applications ADD COLUMN certificate_of_attachment VARCHAR(255) NULL AFTER seminar_certificate'
  )
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'certificate_of_reports'
    ),
    'SELECT "certificate_of_reports exists"',
    'ALTER TABLE job_applications ADD COLUMN certificate_of_reports VARCHAR(255) NULL AFTER certificate_of_attachment'
  )
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'certificate_of_good_standing'
    ),
    'SELECT "certificate_of_good_standing exists"',
    'ALTER TABLE job_applications ADD COLUMN certificate_of_good_standing VARCHAR(255) NULL AFTER certificate_of_reports'
  )
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

-- =====================================================
-- VERIFICATION
-- =====================================================

SELECT 'WORKLINK Database Schema - Complete Setup Finished!' AS info;
SELECT 'Version: 6.0 (Consolidated)' AS version;
SELECT 'All tables, indexes, views, and procedures have been created.' AS status;
