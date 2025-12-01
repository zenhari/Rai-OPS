USE raiops_data;

-- Add new cost fields to route_prices table
ALTER TABLE `route_prices`
ADD COLUMN `handling_cost` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Handling cost in Toman' AFTER `other_costs`,
ADD COLUMN `fueling_services_cost` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Fueling Services cost in Toman' AFTER `handling_cost`,
ADD COLUMN `deicing_antiicing_cost` DECIMAL(12, 2) DEFAULT NULL COMMENT 'De-icing & Anti-icing cost in Toman' AFTER `fueling_services_cost`,
ADD COLUMN `catering_cost` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Catering cost in Toman' AFTER `deicing_antiicing_cost`,
ADD COLUMN `governmental_regulatory_costs` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Governmental and Regulatory Costs in Toman' AFTER `catering_cost`,
ADD COLUMN `tax_accounting_costs` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Tax and Accounting Costs in Toman' AFTER `governmental_regulatory_costs`,
ADD COLUMN `commercial_sales_services_cost` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Commercial and Sales Services cost in Toman' AFTER `tax_accounting_costs`,
ADD COLUMN `documentation_records_cost` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Documentation and Records cost in Toman' AFTER `commercial_sales_services_cost`,
ADD COLUMN `it_services_cost` DECIMAL(12, 2) DEFAULT NULL COMMENT 'IT Services, Systems and Equipment cost in Toman' AFTER `documentation_records_cost`,
ADD COLUMN `personnel_hr_cost` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Personnel and Human Resources cost in Toman' AFTER `it_services_cost`,
ADD COLUMN `miscellaneous_indirect_costs` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Miscellaneous / Indirect Costs in Toman' AFTER `personnel_hr_cost`,
ADD COLUMN `delay_cost` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Delay and Related Costs (Delay Cost) in Toman' AFTER `miscellaneous_indirect_costs`,
ADD COLUMN `flight_cancellation_cost` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Flight Cancellation Cost in Toman' AFTER `delay_cost`,
ADD COLUMN `regulatory_penalties` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Regulatory Penalties in Toman' AFTER `flight_cancellation_cost`,
ADD COLUMN `passenger_compensation_cost` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Passenger Compensation for Long Delays in Toman' AFTER `regulatory_penalties`,
ADD COLUMN `hotel_catering_passengers_cost` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Hotel and Catering for Passengers cost in Toman' AFTER `passenger_compensation_cost`,
ADD COLUMN `crew_hotel_accommodation_cost` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Crew Hotel Accommodation cost in Toman' AFTER `hotel_catering_passengers_cost`,
ADD COLUMN `extended_parking_cost` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Additional Cost for Extended Parking in Toman' AFTER `crew_hotel_accommodation_cost`,
ADD COLUMN `aircraft_positioning_cost` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Aircraft Positioning / Ferry Cost in Toman' AFTER `extended_parking_cost`,
ADD COLUMN `crew_positioning_cost` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Crew Positioning Cost in Toman' AFTER `aircraft_positioning_cost`,
ADD COLUMN `overflight_charges` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Overflight Charges in Toman' AFTER `crew_positioning_cost`,
ADD COLUMN `buildings_facilities_cost` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Buildings and Facilities cost in Toman' AFTER `overflight_charges`,
ADD COLUMN `aircraft_nightstop_cost` DECIMAL(12, 2) DEFAULT NULL COMMENT 'Aircraft Night-Stop Cost in Toman' AFTER `buildings_facilities_cost`;

-- Update the total_cost generated column to include only the new fields
ALTER TABLE `route_prices`
DROP COLUMN `total_cost`,
ADD COLUMN `total_cost` DECIMAL(12, 2) GENERATED ALWAYS AS (
    COALESCE(handling_cost, 0) +
    COALESCE(fueling_services_cost, 0) +
    COALESCE(deicing_antiicing_cost, 0) +
    COALESCE(catering_cost, 0) +
    COALESCE(airport_fees, 0) +
    COALESCE(governmental_regulatory_costs, 0) +
    COALESCE(tax_accounting_costs, 0) +
    COALESCE(commercial_sales_services_cost, 0) +
    COALESCE(documentation_records_cost, 0) +
    COALESCE(it_services_cost, 0) +
    COALESCE(personnel_hr_cost, 0) +
    COALESCE(miscellaneous_indirect_costs, 0) +
    COALESCE(delay_cost, 0) +
    COALESCE(flight_cancellation_cost, 0) +
    COALESCE(regulatory_penalties, 0) +
    COALESCE(passenger_compensation_cost, 0) +
    COALESCE(hotel_catering_passengers_cost, 0) +
    COALESCE(crew_hotel_accommodation_cost, 0) +
    COALESCE(extended_parking_cost, 0) +
    COALESCE(aircraft_positioning_cost, 0) +
    COALESCE(crew_positioning_cost, 0) +
    COALESCE(overflight_charges, 0) +
    COALESCE(buildings_facilities_cost, 0) +
    COALESCE(aircraft_nightstop_cost, 0)
) STORED COMMENT 'Total calculated cost in Toman';

