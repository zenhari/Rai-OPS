# Journey Log Fields Analysis

## Current Database Structure

### `pilot_journey_logs` Table (JSON Storage)
```sql
- id (int, AUTO_INCREMENT, PRIMARY KEY)
- pilot_name (varchar(255), NOT NULL)
- log_date (date, NOT NULL) 
- log_data (longtext, NOT NULL) -- Stores complete form data as JSON
- created_at (timestamp)
- updated_at (timestamp)
- UNIQUE KEY: (pilot_name, log_date)
```

### `journey_log_entries` Table (Structured Storage)
**Existing Fields:**
- Basic info: `id`, `pilot_name`, `log_date`, `created_at`, `updated_at`
- Flight data: `leg_number`, `flight_no`, `pc_fo`, `pfc`, `from_airport`, `to_airport`
- Timing: `ofb_time`, `onb_time`, `block_time`, `atd_time`, `ata_time`, `air_time`, `legs`
- Fuel: `fuel_uplift`, `fuel_off`, `fuel_on`, `fuel_used`, `fluid_type`, `mixture`
- Engine oil: `eng1_oil`, `eng2_oil`, `eng3_oil`, `apu_oil`
- Status: `fault`, `rvsm`, `rnps`
- Summary: `next_inspection_type`, `next_inspection_due`, `at_tt`, `at_rc`, `total_this_day`, `carried_forward`, `total_to_report`, `correction`
- Technical: `technical_remarks`, `actions_taken`, `sign_auth`

## Missing Fields Analysis

### 1. Engine Parameters Section (ENGINE, ELECTRICAL AND CABIN PARAMETERS)
**Form Fields Missing from Database:**
- `pl_parameter` - PL parameter
- `ias_m_parameter` - IAS/M parameter  
- `ioat_parameter` - IOAT parameter
- `eng_parameter` - Eng parameter
- `n1_percent` - N1(%) parameter
- `itt_parameter` - ITT parameter
- `n2_percent` - N2(%) parameter
- `ff_parameter` - FF parameter
- `op_parameter` - OP parameter
- `ot_parameter` - OT parameter
- `a_parameter` - A parameter
- `alt_ft` - Alt (ft) parameter
- `dp_psi` - DP psi parameter

### 2. Technical Log Section (TECHNICAL LOG)
**Form Fields Missing from Database:**
- `technical_leg_number` - Technical log leg number
- `technical_remarks_defects` - Technical remarks/defects (separate from existing technical_remarks)
- `actions_taken_technical` - Actions taken for technical issues (separate from existing actions_taken)
- `sign_auth_technical` - Sign & Auth for technical log (separate from existing sign_auth)

### 3. Release to Service Section
**Form Fields Missing from Database:**
- `release_to_service` - Maintenance release to service details

## Form Structure Analysis

### Main Flight Data Table
- ✅ All fields exist in database
- Fields: leg_number, flight_no, pc_fo, pfc, from_airport, to_airport, ofb_time, onb_time, block_time, atd_time, ata_time, air_time, legs, fuel_uplift, fuel_off, fuel_on, fuel_used, fluid_type, mixture, start_time, end_time, eng1_oil, eng2_oil, eng3_oil, apu_oil, fault, rvsm, rnps

### Engine Parameters Table (3 rows)
- ❌ No database fields exist
- Missing: pl_parameter, ias_m_parameter, ioat_parameter, eng_parameter, n1_percent, itt_parameter, n2_percent, ff_parameter, op_parameter, ot_parameter, a_parameter, alt_ft, dp_psi

### Technical Log Table (3 rows)  
- ❌ No database fields exist
- Missing: technical_leg_number, technical_remarks_defects, actions_taken_technical, sign_auth_technical

### Summary Section
- ✅ All fields exist in database
- Fields: next_inspection_type, next_inspection_due, at_tt, at_rc, total_this_day, carried_forward, total_to_report, correction

### Footer Section
- ❌ Missing: release_to_service

## Recommendations

1. **Run the SQL script** `add_missing_journey_log_fields.sql` to add all missing fields
2. **Update the save function** in `config.php` to handle the new fields
3. **Update the form collection** to include the new field names
4. **Test the complete save/load functionality** with all sections

## Impact Assessment

- **High Priority**: Engine Parameters and Technical Log sections are completely missing
- **Medium Priority**: Release to Service field is missing
- **Low Priority**: Existing fields are working correctly

The missing fields represent approximately 40% of the form data that cannot be properly saved to the structured database table.
