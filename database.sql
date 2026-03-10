CREATE DATABASE ecoprotean;
USE ecoprotean;

-- Users Table
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,                         -- CHANGED: added first_name column
    last_name VARCHAR(50) NOT NULL,                          -- CHANGED: added last_name column
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255) NOT NULL,                          -- CHANGED: added NOT NULL
    role ENUM('user', 'manager', 'admin') NOT NULL,          -- CHANGED: added NOT NULL
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- SAMPLE DATA FOR USERS
-- ALL PASSWORDS FOR EACH ROLE ARE THE SAME FOR TESTING PURPOSES: "password123" (hashed using bcrypt)
INSERT INTO users (first_name, last_name, email, password, role) VALUES
('Admin', 'User', 'admin@example.com', '$2a$12$2/v0lBTlevQICjVQZoMBsegpXCXN87MtKutHSd1ROufkuRHea1w7.', 'admin'),
('Maria', 'Santos', 'manager@example.com', '$2a$12$2/v0lBTlevQICjVQZoMBsegpXCXN87MtKutHSd1ROufkuRHea1w7.', 'manager'),
('Juan', 'Dela Cruz', 'user@example.com', '$2a$12$2/v0lBTlevQICjVQZoMBsegpXCXN87MtKutHSd1ROufkuRHea1w7.', 'user');

-- Activity Logs Table
CREATE TABLE activity_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    ip_address VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- Sample Data for Activity Logs
INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES
(1, 'LOGIN', 'Admin logged into the system', '192.168.1.10'),
(2, 'ADD_RECOMMENDATION', 'Manager added tree recommendation', '192.168.1.11'),
(3, 'VIEW_DASHBOARD', 'User viewed environmental dashboard', '192.168.1.12');

-- Locations Table for Environmental Risk Assessment
CREATE TABLE locations (
    location_id INT AUTO_INCREMENT PRIMARY KEY,
    location_name VARCHAR(150) NOT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    risk_level ENUM('Low', 'Medium', 'High') NOT NULL,
    description TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
-- Sample Data for Locations
INSERT INTO locations (location_name, latitude, longitude, risk_level, description) VALUES
('Forest Zone A',    8.34117600, 124.89299300, 'High',   'High landslide risk area'),
('Riverbank Area B', 8.37497300, 124.90242700, 'Medium', 'Flood-prone riverbank'),
('Community Park C', 8.40231500, 124.89983000, 'Low',    'Low environmental risk area');

-- Sensors Table for Environmental Monitoring
CREATE TABLE sensors (
    sensor_id INT AUTO_INCREMENT PRIMARY KEY,
    location_id INT NOT NULL,
    sensor_type VARCHAR(50) NOT NULL,                        -- CHANGED: added sensor_type column
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(location_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
-- Sample Data for Sensors
INSERT INTO sensors (location_id, sensor_type) VALUES                -- CHANGED: updated insert
(1, 'Motion'),
(2, 'Motion'),
(3, 'Motion');

-- Simulation Data Table for Sensor Readings
CREATE TABLE simulation_data (
    sim_id INT AUTO_INCREMENT PRIMARY KEY,
    sensor_id INT NOT NULL,
    movement_level INT NOT NULL CHECK (movement_level BETWEEN 0 AND 100),
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
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

-- Tree Recommendations Table
CREATE TABLE tree_recommendations (
    recommendation_id INT AUTO_INCREMENT PRIMARY KEY,
    location_id INT NOT NULL,
    tree_name VARCHAR(100) NOT NULL,
    reason TEXT NOT NULL,
    recommended_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(location_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (recommended_by) REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
-- Sample Data for Tree Recommendations
INSERT INTO tree_recommendations (location_id, tree_name, reason, recommended_by) VALUES
(1, 'Narra', 'Strong roots help prevent soil erosion', 2),
(2, 'Bamboo', 'Effective for riverbank stabilization', 2),
(3, 'Acacia', 'Provides shade and improves air quality', 1);

-- Query to Retrieve Activity Logs with User Information
SELECT 
    a.log_id,
    u.first_name,
    u.last_name,
    u.email,
    u.role,
    a.action,
    a.description,
    a.ip_address,
    a.created_at
FROM activity_logs a
JOIN users u ON a.user_id = u.user_id;

-- Query to Retrieve Environmental Risk Assessments with Location Information
SELECT 
    t.tree_name,
    t.reason,
    l.location_name,
    u.first_name AS recommended_by
FROM tree_recommendations t
JOIN locations l ON t.location_id = l.location_id
JOIN users u ON t.recommended_by = u.user_id;