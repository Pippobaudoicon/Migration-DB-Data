# PHP Database Migration Script

This script is designed to migrate data from a source MySQL database to a destination MySQL database. It supports migrating specific tables, filtering data with WHERE clauses, batch processing for large tables, and updating AUTO_INCREMENT values.

## Features

*   **Selective Table Migration**: Specify which tables to migrate in the configuration.
*   **Data Filtering**: Use WHERE clauses to migrate only a subset of data from source tables.
*   **Batch Processing**: Inserts data in batches to handle large datasets efficiently and prevent timeouts.
*   **Upsert Functionality**: Uses `INSERT ... ON DUPLICATE KEY UPDATE` to insert new rows or update existing rows if a duplicate key is found.
*   **AUTO_INCREMENT Update**: Automatically updates the `AUTO_INCREMENT` value on the destination table after data migration if a primary key with auto-increment is specified.
*   **Error Logging**: Logs detailed messages, including errors and successful operations, to a specified log file and prints them to the console.
*   **Configuration-Driven**: All migration parameters are controlled through a single configuration array.
*   **Performance Optimizations**:
    *   Employs unbuffered queries for fetching data from the source database to reduce memory usage.
    *   Sets long session timeouts (`wait_timeout`, `interactive_timeout`) for the source database connection to prevent disconnections during lengthy operations.

## Requirements

*   PHP (tested with PHP 8.x, but should be compatible with recent versions)
*   PDO extension for PHP (`pdo_mysql`)
*   Access to both source and destination MySQL databases.

## Configuration

The script is configured via the `$config` array within the `migration_db.php` file. Below is an overview of the configuration options:

```php
$config = [
    // Source database configuration
    'source' => [
        'host' => 'your_source_host',
        'db'   => 'your_source_dbname',
        'user' => 'your_source_user',
        'pass' => 'your_source_password'
    ],
    // Destination database configuration
    'destination' => [
        'host' => 'your_destination_host',
        'db'   => 'your_destination_dbname',
        'user' => 'your_destination_user',
        'pass' => 'your_destination_password'
    ],
    // List of tables to migrate with their options
    'tables' => [
        'your_table_name' => [
            'where'            => 'your_filter_condition', // Optional: SQL WHERE clause (without "WHERE") to filter source data. E.g., 'creation_date > "2023-01-01"'
            'primary_key_ai'   => 'your_auto_increment_pk_column', // Optional: Name of the auto-incrementing primary key. Used to update AUTO_INCREMENT on the destination.
            'charset'          => 'latin1', // Optional: Character set to use for THIS table on the destination connection (e.g., 'utf8', 'latin1'). Overrides the global connection charset for this table's operations.
            'batch_size'       => 50, // Optional: Number of rows to process in each batch for this table. Overrides global 'batch_size'.
        ],
        // Example: Migrating 'users' table
        // 'users' => [
        //     'where' => 'last_login > "2024-01-01"',
        //     'primary_key_ai' => 'id',
        //     'batch_size' => 200
        // ],
        // Example: Migrating 'products' table with a specific charset and no filter
        // 'products' => [
        //     'where' => '', // No filter, migrate all data
        //     'primary_key_ai' => 'product_id',
        //     'charset' => 'utf8mb4',
        // ],
    ],
    'log_file'   => __DIR__ . '/migration_log.txt', // Path to the log file.
    'batch_size' => 100, // Default batch size for insertions if not specified per table.
];
```

## Usage

1.  **Configure the script**: Modify the `$config` array in `.php` with your database details and table migration settings.
2.  **Run the script**: Execute the script from the command line:
    ```bash
    php .php
    ```
3.  **Check Logs**: Monitor the console output and the specified `log_file` for progress and any potential errors.

## How it Works

1.  **Initialization**:
    *   Reads the configuration.
    *   Connects to the destination database. The connection is set to use `utf8mb4`.
    *   Connects to the source database. The connection is set to use `utf8mb4` and configured for unbuffered queries to optimize memory usage for large datasets.
    *   Sets long session timeouts (`wait_timeout` and `interactive_timeout`) for the source connection to prevent disconnections during long operations.

2.  **Table Iteration**:
    *   For each table defined in the `$config['tables']` array:
        *   Determines the batch size (uses table-specific `batch_size` if provided, otherwise defaults to the global `batch_size`).
        *   If a `charset` is specified for the table in the configuration, it sets the character set (e.g., `SET NAMES 'your_charset'`) on the destination database connection specifically for the operations on this table.
        *   Constructs the `SELECT` query, including any `WHERE` clause specified in the table's options.
        *   Fetches data from the source table row by row (due to unbuffered queries).

3.  **Batch Processing**:
    *   Collects rows fetched from the source table into a batch.
    *   When the batch reaches the defined `batch_size`, the `insert_batch` function is called.
    *   The `insert_batch` function constructs an `INSERT INTO ... ON DUPLICATE KEY UPDATE ...` SQL statement. This means:
        *   If a row with the same primary key (or unique key) does not exist in the destination table, it's inserted.
        *   If a row with the same primary key (or unique key) already exists, its columns are updated with the values from the source row.
    *   The batch insertion is performed within a database transaction for atomicity. If any error occurs during the batch insert, the transaction is rolled back.
    *   Success or failure of the batch operation is logged.

4.  **AUTO_INCREMENT Update**:
    *   If `primary_key_ai` is specified for a table in the configuration, after all data for that table has been processed:
        *   The script queries the destination table for the `MAX()` value of the specified auto-increment column.
        *   It then executes an `ALTER TABLE ... AUTO_INCREMENT = ...` command to set the next auto-increment value to `max_id + 1`. This ensures that new rows inserted directly into the destination table (after migration) will not conflict with migrated IDs.

5.  **Logging**:
    *   All significant operations (connections, batch inserts/updates, errors, AUTO_INCREMENT updates) are timestamped and logged to both the console and the `log_file` specified in the configuration.

## Important Notes

*   **Backup Databases**: Always back up both your source and destination databases before running any migration script.
*   **Primary/Unique Keys**: The `ON DUPLICATE KEY UPDATE` functionality relies on primary or unique keys being defined on your destination tables to correctly identify existing rows for updates.
*   **Data Integrity**: Ensure that data types, column names, and constraints are compatible between source and destination tables. The script assumes column names are identical.
*   **Performance**: For very large databases, migration can take a significant amount of time. Run the script in an environment where it can execute uninterrupted (e.g., using `screen` or `nohup` on Linux systems if running remotely via SSH).
