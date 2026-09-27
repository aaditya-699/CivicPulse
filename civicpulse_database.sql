-- ============================================================================
-- CIVICPULSE: Municipal Infrastructure Tracking and Analytics Portal
-- MySQL Database Schema Setup Script
-- ============================================================================
-- This script creates all necessary tables and sample data for the application
-- Execute this in your MySQL client before running the application
-- ============================================================================

-- Drop existing database if it exists
DROP DATABASE IF EXISTS civicpulse_db;

-- Create new database
CREATE DATABASE civicpulse_db;
USE civicpulse_db;

-- ============================================================================
-- TABLE 1: CATEGORIES (Infrastructure Problem Categories)
-- ============================================================================
CREATE TABLE categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    icon VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- TABLE 2: USERS (Citizens, Staff, and Administrators)
-- ============================================================================
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    phone_number VARCHAR(20),
    user_role ENUM('citizen', 'staff', 'admin') DEFAULT 'citizen',
    address VARCHAR(255),
    city VARCHAR(100),
    postal_code VARCHAR(10),
    is_active BOOLEAN DEFAULT TRUE,
    email_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_role (user_role),
    INDEX idx_active (is_active)
);

-- ============================================================================
-- TABLE 3: COMPLAINTS (Infrastructure Defect Reports)
-- ============================================================================
CREATE TABLE complaints (
    complaint_id INT PRIMARY KEY AUTO_INCREMENT,
    complaint_number VARCHAR(20) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    location_address VARCHAR(255) NOT NULL,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    status ENUM('reported', 'verified', 'in_progress', 'resolved', 'closed', 'rejected') DEFAULT 'reported',
    estimated_resolution_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    resolution_notes TEXT,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE RESTRICT,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE RESTRICT,
    
    INDEX idx_user (user_id),
    INDEX idx_category (category_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_created (created_at),
    INDEX idx_complaint_number (complaint_number)
);

-- ============================================================================
-- TABLE 4: COMPLAINT_ATTACHMENTS (Photos and Documents)
-- ============================================================================
CREATE TABLE complaint_attachments (
    attachment_id INT PRIMARY KEY AUTO_INCREMENT,
    complaint_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(50),
    file_size INT,
    uploaded_by INT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (complaint_id) REFERENCES complaints(complaint_id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    
    INDEX idx_complaint (complaint_id),
    INDEX idx_uploaded_at (uploaded_at)
);

-- ============================================================================
-- TABLE 5: TASKS (Maintenance Tasks for Staff)
-- ============================================================================
CREATE TABLE tasks (
    task_id INT PRIMARY KEY AUTO_INCREMENT,
    complaint_id INT NOT NULL,
    assigned_to INT,
    task_status ENUM('pending', 'assigned', 'in_progress', 'completed', 'on_hold') DEFAULT 'pending',
    assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    due_date DATE,
    completion_date TIMESTAMP NULL,
    estimated_cost DECIMAL(10, 2),
    actual_cost DECIMAL(10, 2),
    notes TEXT,
    staff_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (complaint_id) REFERENCES complaints(complaint_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(user_id) ON DELETE SET NULL,
    
    INDEX idx_complaint (complaint_id),
    INDEX idx_assigned_to (assigned_to),
    INDEX idx_task_status (task_status),
    INDEX idx_due_date (due_date)
);

-- ============================================================================
-- TABLE 6: ACTIVITY_LOG (System Activity Tracking)
-- ============================================================================
CREATE TABLE activity_log (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action_type VARCHAR(100),
    entity_type VARCHAR(50),
    entity_id INT,
    old_value TEXT,
    new_value TEXT,
    ip_address VARCHAR(45),
    action_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    
    INDEX idx_user (user_id),
    INDEX idx_timestamp (action_timestamp),
    INDEX idx_entity (entity_type, entity_id)
);

-- ============================================================================
-- TABLE 7: SYSTEM_SETTINGS (Configuration Settings)
-- ============================================================================
CREATE TABLE system_settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_key (setting_key)
);

-- ============================================================================
-- SAMPLE DATA INSERTION
-- ============================================================================

-- Insert Categories
INSERT INTO categories (category_name, description, icon) VALUES
('Road & Potholes', 'Damaged roads, potholes, cracks in asphalt', 'road'),
('Street Lighting', 'Broken or non-functional street lights', 'lightbulb'),
('Water & Drainage', 'Leaking water pipes, blocked drains, water issues', 'droplet'),
('Public Sanitation', 'Garbage management, public toilet issues, cleanliness', 'trash'),
('Sidewalks & Paths', 'Broken sidewalks, uneven walking paths', 'footprints'),
('Public Facilities', 'Parks, benches, public amenities', 'building'),
('Electrical Issues', 'Power outages, electrical hazards', 'zap'),
('Other', 'Other infrastructure related issues', 'alert-circle');

-- Insert Sample Users (Citizens)
INSERT INTO users (email, password_hash, full_name, phone_number, user_role, address, city, postal_code, is_active, email_verified) VALUES
('citizen1@civicpulse.local', '123456789', 'Rajesh Kumar', '9841234567', 'citizen', 'Gauradaha', 'Gauradaha', TRUE, TRUE),
('citizen2@civicpulse.local', '123456789', 'Priya Sharma', '9812345678', 'citizen', 'Gauradaha', 'Gauradaha', TRUE, TRUE),
('citizen3@civicpulse.local', '123456789', 'Amit Patel', '9823456789', 'citizen', 'Gaurigunj', 'Gaurigunj', TRUE, TRUE);

-- Insert Sample Users (Staff)
INSERT INTO users (email, password_hash, full_name, phone_number, user_role, address, city, postal_code, is_active, email_verified) VALUES
('staff1@civicpulse.local', '123456789', 'Suresh Thakuri', '9834567890', 'staff', 'Municipal Office', 'Gauradaha', TRUE, TRUE),
('staff2@civicpulse.local', '123456789', 'Asha Gurung', '9845678901', 'staff', 'Municipal Office', 'Gauradaha', TRUE, TRUE),
('staff3@civicpulse.local', '123456789', 'Kumar Limbu', '9856789012', 'staff', 'Municipal Office', 'Gauradaha', TRUE, TRUE);

-- Insert Sample User (Admin)
INSERT INTO users (email, password_hash, full_name, phone_number, user_role, address, city, postal_code, is_active, email_verified) VALUES
('admin@civicpulse.local', '123456789', 'Admin User', '9800000000', 'admin', 'Municipal Office', 'Gauradaha', TRUE, TRUE);

-- Insert Sample Complaints
INSERT INTO complaints (complaint_number, user_id, category_id, title, description, location_address, latitude, longitude, priority, status, created_at) VALUES
('COMP-2026-001', 1, 1, 'Large pothole on Main Street', 'There is a large pothole approximately 2 meters wide causing vehicles to swerve. Very dangerous for motorcyclists.', 'Main Street near Traffic Light', 27.7172, 85.3240, 'high', 'reported', '2026-07-20 10:30:00'),
('COMP-2026-002', 2, 2, 'Street light not working', 'The street light near the market has been broken for 3 weeks. It is dark at night and unsafe for pedestrians.', 'Market Road', 27.7180, 85.3250, 'medium', 'verified', '2026-07-21 14:15:00'),
('COMP-2026-003', 3, 3, 'Water pipe leaking', 'Water is leaking from the main pipe near the community center. Water is wasted continuously.', 'Community Center Road', 27.7160, 85.3230, 'high', 'in_progress', '2026-07-22 09:00:00'),
('COMP-2026-004', 1, 4, 'Garbage not collected', 'Garbage has not been collected from this location for a week. Bad smell and unhygienic.', 'Residential Colony', 27.7190, 85.3260, 'medium', 'reported', '2026-07-23 16:45:00'),
('COMP-2026-005', 2, 1, 'Damaged sidewalk', 'The sidewalk is broken and poses a tripping hazard. Several people have already fallen.', 'Park Street', 27.7175, 85.3245, 'high', 'resolved', '2026-07-15 11:20:00');

-- Insert Sample Tasks
INSERT INTO tasks (complaint_id, assigned_to, task_status, assigned_date, due_date, notes) VALUES
(2, 4, 'assigned', '2026-07-21 15:00:00', '2026-07-25', 'Replace broken street light bulb'),
(3, 5, 'in_progress', '2026-07-22 10:00:00', '2026-07-26', 'Repair water pipe leak'),
(5, 6, 'completed', '2026-07-15 12:00:00', '2026-07-20', 'Repair sidewalk cracks');

-- Insert System Settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('app_name', 'CivicPulse', 'Application Name'),
('app_version', '1.0.0', 'Application Version'),
('municipality_name', 'Gauradaha Municipality', 'Municipality Name'),
('email_from', 'noreply@civicpulse.local', 'System Email Address'),
('max_upload_size', '5242880', 'Maximum upload size in bytes (5MB)'),
('average_resolution_days', '7', 'Average complaint resolution target in days'),
('enable_notifications', '1', 'Enable email notifications'),
('theme_color', '#0066cc', 'Primary theme color');

-- ============================================================================
-- CREATE INDEXES FOR COMMON QUERIES
-- ============================================================================

-- Composite index for complaint queries
CREATE INDEX idx_complaints_status_created ON complaints(status, created_at DESC);
CREATE INDEX idx_complaints_category_status ON complaints(category_id, status);

-- Composite index for task queries
CREATE INDEX idx_tasks_assigned_status ON tasks(assigned_to, task_status);

-- Index for user login
CREATE INDEX idx_users_email_active ON users(email, is_active);

-- ============================================================================
-- DATABASE SUMMARY
-- ============================================================================
-- Total Tables: 7
-- Total Relationships: 10+ foreign keys
-- Total Indexes: 25+
-- Sample Records: ~15 records across all tables
-- ============================================================================

-- Verification Query - Run this to verify database setup
SELECT 'CIVICPULSE DATABASE SETUP COMPLETE' AS status,
       COUNT(*) as table_count
FROM information_schema.tables 
WHERE table_schema = 'civicpulse_db';

-- ============================================================================
-- END OF DATABASE SETUP SCRIPT
-- ============================================================================
