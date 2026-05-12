CREATE DATABASE ecoprotean;
USE ecoprotean;

-- ─────────────────────────────────────────────
--  Users Table
-- ─────────────────────────────────────────────
CREATE TABLE users (
    user_id    INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50)  NOT NULL,
    last_name  VARCHAR(50)  NOT NULL,
    email      VARCHAR(100) UNIQUE,
    password   VARCHAR(255) NOT NULL,
    role       ENUM('user', 'manager', 'admin') NOT NULL,
    last_login DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Sample Data for Users
-- ALL PASSWORDS: "password123" (bcrypt hashed)
INSERT INTO users (first_name, last_name, email, password, role) VALUES
('Admin',  'User',      'admin@example.com',   '$2a$12$2/v0lBTlevQICjVQZoMBsegpXCXN87MtKutHSd1ROufkuRHea1w7.', 'admin'),
('Maria',  'Santos',    'manager@example.com', '$2a$12$2/v0lBTlevQICjVQZoMBsegpXCXN87MtKutHSd1ROufkuRHea1w7.', 'manager'),
('Juan',   'Dela Cruz', 'user@example.com',    '$2a$12$2/v0lBTlevQICjVQZoMBsegpXCXN87MtKutHSd1ROufkuRHea1w7.', 'user');

-- ─────────────────────────────────────────────
--  Activity Logs Table
-- ─────────────────────────────────────────────
CREATE TABLE activity_logs (
    log_id     INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    action     VARCHAR(200) NOT NULL,
    description TEXT        NOT NULL,
    ip_address VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- Sample Data for Activity Logs
INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES
(1, 'LOGIN',          'Admin logged into the system',         '192.168.1.10'),
(2, 'VIEW_DASHBOARD', 'Manager viewed dashboard',             '192.168.1.11'),
(3, 'VIEW_RISKMAP',   'User viewed risk map',                 '192.168.1.12');

-- ─────────────────────────────────────────────
--  Locations Table
-- ─────────────────────────────────────────────
CREATE TABLE locations (
    location_id   INT AUTO_INCREMENT PRIMARY KEY,
    location_name VARCHAR(150) NOT NULL,
    latitude      DECIMAL(10,8) NOT NULL,
    longitude     DECIMAL(11,8) NOT NULL,
    risk_level    ENUM('Low', 'Medium', 'High') NOT NULL,
    description   TEXT NOT NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Sample Data for Locations
INSERT INTO locations (location_name, latitude, longitude, risk_level, description) VALUES
('Forest Zone A',    8.34117600, 124.89299300, 'High',   'High landslide risk area'),
('Riverbank Area B', 8.37497300, 124.90242700, 'Medium', 'Flood-prone riverbank'),
('Community Park C', 8.40231500, 124.89983000, 'Low',    'Low environmental risk area');

-- ─────────────────────────────────────────────
--  Sensors Table
-- ─────────────────────────────────────────────
CREATE TABLE sensors (
    sensor_id   INT AUTO_INCREMENT PRIMARY KEY,
    location_id INT         NOT NULL,
    sensor_type VARCHAR(50) NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(location_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- Sample Data for Sensors
INSERT INTO sensors (location_id, sensor_type) VALUES
(1, 'Motion'),
(2, 'Motion'),
(3, 'Motion');

-- ─────────────────────────────────────────────
--  Simulation Data Table
-- ─────────────────────────────────────────────
CREATE TABLE simulation_data (
    sim_id         INT AUTO_INCREMENT PRIMARY KEY,
    sensor_id      INT NOT NULL,
    movement_level INT NOT NULL CHECK (movement_level BETWEEN 0 AND 100),
    timestamp      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sensor_id) REFERENCES sensors(sensor_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- Sample Data for Simulation Data
INSERT INTO simulation_data (sensor_id, movement_level) VALUES
(1, 85),
(1, 78),
(2, 40),
(2, 55),
(3, 15);

-- ─────────────────────────────────────────────
--  Sensor Requests Table
--  Stores requests made by users to access
--  sensor data for a specific location.
--  Must be approved by a manager before
--  the user can view the sensor history.
--
--  Data selection fields (chosen by user
--  during request, respected on delivery):
--    date_range    — preset range or 'custom'
--    custom_from   — start date if custom
--    custom_to     — end date if custom
--    fields        — comma-separated list of
--                    requested data columns:
--                    movement, risk, cause, timestamp
--    interval_type — raw | hourly | daily
--    format_pref   — view | download | both
--
--  Rejection:
--    rejection_remarks — required when rejecting,
--                        shown to the user in their
--                        My Requests panel
-- ─────────────────────────────────────────────
CREATE TABLE sensor_requests (
    request_id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id            INT  NOT NULL,
    location_id        INT  NOT NULL,
    reason             TEXT NOT NULL,
    intended_use       TEXT NOT NULL,

    -- Data selection preferences
    date_range         VARCHAR(20)  NOT NULL DEFAULT 'last_30_days',
    custom_from        DATE         DEFAULT NULL,
    custom_to          DATE         DEFAULT NULL,
    fields             VARCHAR(255) NOT NULL DEFAULT 'movement,risk,cause,timestamp',
    interval_type      VARCHAR(20)  NOT NULL DEFAULT 'raw',
    format_pref        VARCHAR(20)  NOT NULL DEFAULT 'both',

    -- Review
    status             ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    rejection_remarks  TEXT         DEFAULT NULL,
    requested_at       DATETIME     DEFAULT CURRENT_TIMESTAMP,
    reviewed_at        DATETIME     DEFAULT NULL,
    reviewed_by        INT          DEFAULT NULL,

    FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (location_id) REFERENCES locations(location_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
);

-- ─────────────────────────────────────────────
--  Useful Queries (for reference)
-- ─────────────────────────────────────────────

-- Get all activity logs with user info
SELECT
    al.log_id,
    CONCAT(u.first_name, ' ', u.last_name) AS full_name,
    u.email,
    u.role,
    al.action,
    al.description,
    al.ip_address,
    al.created_at
FROM activity_logs al
JOIN users u ON al.user_id = u.user_id
ORDER BY al.created_at DESC;

-- Get all sensor requests with user and location info
-- (includes new data selection and rejection fields)
SELECT
    sr.request_id,
    CONCAT(u.first_name, ' ', u.last_name) AS requested_by,
    l.location_name,
    sr.reason,
    sr.intended_use,
    sr.date_range,
    sr.custom_from,
    sr.custom_to,
    sr.fields,
    sr.interval_type,
    sr.format_pref,
    sr.status,
    sr.rejection_remarks,
    sr.requested_at,
    sr.reviewed_at,
    CONCAT(m.first_name, ' ', m.last_name) AS reviewed_by
FROM sensor_requests sr
JOIN users     u ON sr.user_id     = u.user_id
JOIN locations l ON sr.location_id = l.location_id
LEFT JOIN users m ON sr.reviewed_by = m.user_id
ORDER BY sr.requested_at DESC;

-- Get latest simulation data per sensor with location info
SELECT
    l.location_name,
    s.sensor_id,
    s.sensor_type,
    sd.movement_level,
    CASE
        WHEN sd.movement_level < 30 THEN 'Low'
        WHEN sd.movement_level < 60 THEN 'Medium'
        ELSE 'High'
    END AS risk_level,
    sd.timestamp
FROM locations l
JOIN sensors s ON s.location_id = l.location_id
JOIN simulation_data sd ON sd.sensor_id = s.sensor_id
    AND sd.sim_id = (
        SELECT MAX(sim_id)
        FROM simulation_data
        WHERE sensor_id = s.sensor_id
    )
ORDER BY l.location_id ASC;

-- Get sensor history for an approved request
-- respecting the user's date_range and interval preferences
-- (application layer handles interval aggregation;
--  this query returns the raw filtered rows)
SELECT
    sd.sim_id,
    sd.movement_level,
    sd.timestamp,
    CASE
        WHEN sd.movement_level < 30 THEN 'low'
        WHEN sd.movement_level < 60 THEN 'medium'
        ELSE 'high'
    END AS risk_level,
    CASE
        WHEN sd.movement_level < 30 THEN 'Wind'
        WHEN sd.movement_level < 60 THEN 'Rain / Soil Softening'
        ELSE 'Ground Instability'
    END AS cause
FROM simulation_data sd
JOIN sensors s ON sd.sensor_id = s.sensor_id
WHERE s.location_id = :location_id
  AND sd.timestamp >= :date_from
  AND sd.timestamp <= :date_to
ORDER BY sd.timestamp DESC;