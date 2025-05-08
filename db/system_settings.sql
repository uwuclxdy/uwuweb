-- System Settings Table Addition

USE uwuweb;

-- Create system_settings table if it doesn't exist
CREATE TABLE IF NOT EXISTS system_settings
(
    id               INT AUTO_INCREMENT PRIMARY KEY,
    school_name      VARCHAR(100) NOT NULL DEFAULT 'ŠCC Celje',
    current_year     VARCHAR(20)  NOT NULL DEFAULT '2024/2025',
    school_address   TEXT,
    session_timeout  INT          NOT NULL DEFAULT 30,
    grade_scale      VARCHAR(20)  NOT NULL DEFAULT '1-5',
    maintenance_mode BOOLEAN      NOT NULL DEFAULT FALSE,
    created_at       TIMESTAMP             DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP             DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings if table is empty
INSERT INTO system_settings (school_name, current_year, school_address, session_timeout, grade_scale, maintenance_mode)
SELECT 'High School Example', '2024/2025', '', 30, '1-5', FALSE
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM system_settings);
