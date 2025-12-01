# User Update Logging System

## Overview
A comprehensive logging system has been implemented to track and debug user update failures, particularly useful for identifying differences between localhost (XAMPP) and cPanel environments.

## What's Been Added

### 1. Enhanced `updateUser()` Function
- **Location**: `config.php` (lines 419-489)
- **Features**:
  - Detailed logging of all update attempts
  - Captures SQL queries, parameters, and execution results
  - Records database connection status
  - Logs PDO exceptions and general exceptions
  - Includes server environment information

### 2. Logging Functions
- **`logUserUpdateError()`**: Writes detailed logs to file
- **`getLatestUserUpdateError()`**: Retrieves the most recent error log entry

### 3. Enhanced Error Display
- **Location**: `admin/users/edit.php` (lines 107-149)
- **Features**:
  - Shows detailed error information in the UI
  - Displays SQL queries, PDO errors, and server information
  - Maintains security by escaping user input

## Log File Location
```
/logs/user_update_errors.log
```

## Log Format
Each log entry contains:
```json
{
  "timestamp": "2025-01-15 10:30:45",
  "user_id": 5,
  "result": "failed",
  "data_fields": ["first_name", "last_name", "position"],
  "data_count": 3,
  "fields_to_update": ["`first_name` = ?", "`last_name` = ?"],
  "values_count": 2,
  "sql_query": "UPDATE users SET `first_name` = ?, `last_name` = ? WHERE id = ?",
  "sql_values": ["John", "Doe", 5],
  "affected_rows": 0,
  "db_connection": "success",
  "error": "SQL execution failed",
  "pdo_error": ["00000", null, null],
  "exception_message": "SQLSTATE[HY093]: Invalid parameter number",
  "exception_code": "HY093",
  "exception_file": "/path/to/config.php",
  "exception_line": 457,
  "server_info": {
    "php_version": "8.1.0",
    "server_software": "Apache/2.4.54",
    "document_root": "/var/www/html",
    "request_uri": "/admin/users/edit.php?id=5",
    "user_agent": "Mozilla/5.0..."
  }
}
```

## How to Use

### 1. Test the System
Run the test script:
```bash
php test_user_update_logging.php
```

### 2. View Logs
Check the log file:
```bash
tail -f logs/user_update_errors.log
```

### 3. Debug on cPanel
1. Try to update a user on cPanel
2. If it fails, check the error message in the UI
3. Look at the detailed error information displayed
4. Check the log file for complete details

## Common Issues to Look For

### 1. Database Connection Issues
- **Log Field**: `db_connection`
- **Common Causes**: Wrong credentials, database server down, network issues

### 2. SQL Syntax Errors
- **Log Field**: `sql_query`, `pdo_error`
- **Common Causes**: Field name mismatches, data type issues

### 3. Permission Issues
- **Log Field**: `pdo_error`
- **Common Causes**: Database user lacks UPDATE permissions

### 4. Data Type Mismatches
- **Log Field**: `sql_values`, `pdo_error`
- **Common Causes**: Trying to insert wrong data types

### 5. Field Name Issues
- **Log Field**: `fields_to_update`
- **Common Causes**: Column names don't exist in database

## Troubleshooting Steps

### Step 1: Check the UI Error Message
The error message now shows:
- Error type
- Exception details
- SQL query
- Affected rows
- Server information

### Step 2: Check the Log File
Look for:
- Complete SQL query
- All parameter values
- PDO error details
- Server environment differences

### Step 3: Compare Environments
Compare logs between:
- Localhost (working)
- cPanel (failing)

Look for differences in:
- PHP version
- Server software
- Database configuration
- Field names/structures

## Security Notes
- All user input is properly escaped
- Sensitive data (like passwords) is excluded from logs
- Log files should be protected from public access

## File Permissions
Ensure the logs directory is writable:
```bash
chmod 755 logs/
chmod 644 logs/user_update_errors.log
```

## Cleanup
To prevent log files from growing too large, consider implementing log rotation or periodic cleanup.

## Example Debugging Session

1. **User reports**: "Failed to update user" on cPanel
2. **Check UI**: Shows detailed error with SQL query
3. **Check log**: Shows PDO error "Column 'invalid_field' doesn't exist"
4. **Root cause**: Field name mismatch between localhost and cPanel database schemas
5. **Solution**: Update field names or database schema

This logging system provides complete visibility into user update failures, making it much easier to identify and resolve issues between different environments.
