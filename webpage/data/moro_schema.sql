-- ===========================================================
--  Database: moro_db
--  Home Maintenance Scheduler Backend
--  Author: ChatGPT (Generated for Ethan Hartman)
-- ===========================================================

-- Drop old database if needed (optional)
DROP DATABASE IF EXISTS moro_db;

-- Create fresh database
CREATE DATABASE moro_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE moro_db;

-- ===========================================================
--  TABLE: items
--  Represents an item that needs maintenance:
--  home, vehicle, appliance, tool, etc.
-- ===========================================================

CREATE TABLE items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(50) NOT NULL,
    purchase_date DATE NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ===========================================================
--  TABLE: maintenance_tasks
--  Recurring maintenance tasks for an item
--  Example: Change oil, Replace filter, Clean coils, etc.
-- ===========================================================

CREATE TABLE maintenance_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    task_name VARCHAR(255) NOT NULL,
    description TEXT,

    frequency_value INT NOT NULL,   -- e.g. every 30
    frequency_unit ENUM('days','weeks','months','years') NOT NULL,

    priority ENUM('low','medium','high') DEFAULT 'medium',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (item_id)
        REFERENCES items(id)
        ON DELETE CASCADE
);

CREATE INDEX idx_task_item ON maintenance_tasks (item_id);

-- ===========================================================
--  TABLE: task_schedule
--  Represents each *upcoming* due date for a task.
--  Used by the countdown screen.
-- ===========================================================

CREATE TABLE task_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    due_date DATE NOT NULL,
    completed TINYINT(1) DEFAULT 0,

    FOREIGN KEY (task_id)
        REFERENCES maintenance_tasks(id)
        ON DELETE CASCADE
);

CREATE INDEX idx_schedule_task ON task_schedule (task_id);
CREATE INDEX idx_schedule_due ON task_schedule (due_date);

-- ===========================================================
--  TABLE: task_history
--  Represents a *completed* maintenance event.
--  Stores notes, cost, timestamp, and links to schedule instance.
-- ===========================================================

CREATE TABLE task_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    schedule_id INT NULL,

    note TEXT,
    cost DECIMAL(10,2) DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (task_id)
        REFERENCES maintenance_tasks(id)
        ON DELETE CASCADE,

    FOREIGN KEY (schedule_id)
        REFERENCES task_schedule(id)
        ON DELETE SET NULL
);

CREATE INDEX idx_history_task ON task_history (task_id);

-- ===========================================================
--  TABLE: photos
--  Stores one or more photos per completed task.
-- ===========================================================

CREATE TABLE photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    history_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (history_id)
        REFERENCES task_history(id)
        ON DELETE CASCADE
);

CREATE INDEX idx_photos_history ON photos (history_id);

-- ===========================================================
-- Insert recommended default categories (if needed)
-- Item categories (user-defined but suggested defaults)
-- ===========================================================

INSERT INTO items (name, category, purchase_date, notes)
VALUES 
    ('Home (General)', 'Home', NULL, 'General home maintenance'),
    ('Vehicle (General)', 'Vehicle', NULL, 'General vehicle maintenance'),
    ('Appliance (General)', 'Appliance', NULL, 'General appliance upkeep'),
    ('Tools (General)', 'Tools', NULL, 'Tool care and maintenance'),
    ('Outdoor Equipment', 'Outdoor', NULL, 'Lawn, garden, and outdoor devices');

-- OPTIONAL: You can comment out the above if you don't want default entries.

-- ===========================================================
-- END OF FILE
-- ===========================================================
