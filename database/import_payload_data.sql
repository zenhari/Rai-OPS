-- Import Payload Data for Aircraft ID 7 (EP-NEB)
-- Route format: origin_icao-destination_icao
-- Data format: origin, destination, temp_20, temp_25, temp_35, temp_40

USE raimon_fleet;

-- Set aircraft information
SET @aircraft_id = 7;
SET @aircraft_registration = 'EP-NEB';

-- Insert or update payload data
-- Using INSERT ... ON DUPLICATE KEY UPDATE to handle existing records

INSERT INTO `payload_data` 
(`route_code`, `aircraft_id`, `aircraft_registration`, `temperature_20`, `temperature_25`, `temperature_35`, `temperature_40`, `notes`)
VALUES
('OIMM-UTDD', @aircraft_id, @aircraft_registration, 9123.00, 9123.00, 9123.00, 7981.00, NULL),
('UTDD-OIMM', @aircraft_id, @aircraft_registration, 9123.00, 9123.00, 9123.00, 9123.00, NULL),
('OIYY-OIBK', @aircraft_id, @aircraft_registration, 9308.00, 9308.00, 9308.00, 8819.00, NULL),
('OIBK-OIYY', @aircraft_id, @aircraft_registration, 9308.00, 9308.00, 9308.00, 9308.00, NULL),
('OIII-OIYY', @aircraft_id, @aircraft_registration, 9308.00, 9308.00, 9308.00, 7241.00, NULL),
('OIYY-OIII', @aircraft_id, @aircraft_registration, 9308.00, 9308.00, 9308.00, 9308.00, NULL),
('OIMM-OIYY', @aircraft_id, @aircraft_registration, 9308.00, 9308.00, 9308.00, 8728.00, NULL),
('OIYY-OIMM', @aircraft_id, @aircraft_registration, 9086.00, 9086.00, 9086.00, 8219.00, NULL),
('OIII-OING', @aircraft_id, @aircraft_registration, 9308.00, 9308.00, 9308.00, 7841.00, NULL),
('OING-OIII', @aircraft_id, @aircraft_registration, 9308.00, 9308.00, 9308.00, 9308.00, NULL),
('OIII-OISS', @aircraft_id, @aircraft_registration, 9308.00, 9308.00, 9308.00, 7727.00, NULL),
('OISS-OIII', @aircraft_id, @aircraft_registration, 9308.00, 9308.00, 8835.00, 4910.00, NULL),
('OIGG-URWA', @aircraft_id, @aircraft_registration, 9945.00, 9945.00, 9945.00, 9945.00, NULL),
('URWA-OIGG', @aircraft_id, @aircraft_registration, 9947.00, 9947.00, 9947.00, 9947.00, NULL),
('OIII-OIGG', @aircraft_id, @aircraft_registration, 9308.00, 9308.00, 9308.00, 8441.00, NULL),
('OIGG-OIII', @aircraft_id, @aircraft_registration, 9308.00, 9308.00, 9308.00, 9308.00, NULL),
('OIGG-OISS', @aircraft_id, @aircraft_registration, 9308.00, 9308.00, 9308.00, 9308.00, NULL),
('OISS-OIGG', @aircraft_id, @aircraft_registration, 9308.00, 9308.00, 8835.00, 4910.00, NULL),
('OIGG-OITT', @aircraft_id, @aircraft_registration, 9186.00, 9186.00, 9186.00, 9186.00, NULL),
('OITT-OIGG', @aircraft_id, @aircraft_registration, 9308.00, 9308.00, 9308.00, 9160.00, NULL),
('OIGG-OIMM', @aircraft_id, @aircraft_registration, 9308.00, 9308.00, 9308.00, 9308.00, NULL),
('OIMM-OIGG', @aircraft_id, @aircraft_registration, 9308.00, 9308.00, 9308.00, 8228.00, NULL),
('OIGG-OIAA', @aircraft_id, @aircraft_registration, 9308.00, 9308.00, 9308.00, 9308.00, NULL),
('OIAA-OIGG', @aircraft_id, @aircraft_registration, 9308.00, 9308.00, 9308.00, 9308.00, NULL),
('OIGG-OIFM', @aircraft_id, @aircraft_registration, 9308.00, 9308.00, 9308.00, 9308.00, NULL),
('OIFM-OIGG', @aircraft_id, @aircraft_registration, 9308.00, 9308.00, 9308.00, 7894.00, NULL),
('OIAA-OIBK', @aircraft_id, @aircraft_registration, 9308.00, 9308.00, 9308.00, 9308.00, NULL),
('OIBK-OIAA', @aircraft_id, @aircraft_registration, 9286.00, 9286.00, 9286.00, 9286.00, NULL),
('OIAA-OIII', @aircraft_id, @aircraft_registration, 9308.00, 9308.00, 9308.00, 9308.00, NULL),
('OIII-OIAA', @aircraft_id, @aircraft_registration, 9308.00, 9308.00, 9308.00, 6641.00, NULL),
('OIGG-OIKK', @aircraft_id, @aircraft_registration, 9308.00, 9308.00, 9308.00, 9308.00, NULL),
('OIKK-OIGG', @aircraft_id, @aircraft_registration, 9308.00, 9308.00, 8637.00, 6635.00, NULL)
ON DUPLICATE KEY UPDATE
    `temperature_20` = VALUES(`temperature_20`),
    `temperature_25` = VALUES(`temperature_25`),
    `temperature_35` = VALUES(`temperature_35`),
    `temperature_40` = VALUES(`temperature_40`),
    `aircraft_registration` = VALUES(`aircraft_registration`),
    `updated_at` = CURRENT_TIMESTAMP;

-- Verify the insert
SELECT COUNT(*) as total_records FROM `payload_data` WHERE `aircraft_id` = @aircraft_id;

