<?php
// Configuration for database migration
$config = [
    // Source database configuration
    'source' => [
        'host' => '',
        'db' => '',
        'user' => '',
        'pass' => ''
    ],
    // Destination database configuration
    'destination' => [
        'host' => '',
        'db' => '',
        'user' => '',
        'pass' => ''
    ],
    // List of tables to migrate with their options
    'tables' => [
        'amazon_api_transactions' => [
            'where' => 'creation_datetime > "2025-05-01 00:00:00"', // Condition to filter data
            'primary_key_ai' => 'id', // Primary key with auto increment
            // 'primary_key' => 'id', // Primary key (no longer needed)
            'charset' => 'latin1', // Table character set
            'batch_size' => 20, // Batch size for insertion
        ],
        'amazon_asin' => [
            'where' => '',
            // 'primary_key' => 'articolo',
            'charset' => 'latin1',
        ],
        'amazon_vendor_delivery_points' => [
            'where' => '',
            // 'primary_key' => 'fc_code',
            'charset' => 'latin1',
        ],
        'feed_updates' => [
            'where' => '',
            // 'primary_key' => 'ean',
            'charset' => 'latin1',
        ],
        'imballi' => [
            'where' => '',
            // 'primary_key' => 'im_cod',
            'charset' => 'latin1',
        ],
        // 'log' => [
        //     'where' => '',
        //     'primary_key_ai' => 'id',
        //     'primary_key' => 'id',
        //     'charset' => 'latin1',
        // ],
        'nazioni' => [
            'where' => '',
            // 'primary_key' => 'codice',
            'charset' => 'utf8',
        ],
        'ordini_in' => [
            'where' => 'in_data > "2025-04-01 00:00:00"',
            'primary_key_ai' => 'in_id',
            // 'primary_key' => 'in_id',
            'charset' => 'utf8',
        ],
        'params' => [
            'where' => '',
            // 'primary_key' => ['contesto', 'nomecampo'],
            'charset' => 'latin1',
        ],
        'preparatori' => [
            'where' => '',
            // 'primary_key' => 'ip',
            'charset' => 'latin1',
        ],
        'spedizioni' => [
            'where' => 'creation_date > "2025-04-01 00:00:00"',
            // 'primary_key' => 'num_sped  ',
            'charset' => 'utf8',
        ],
        'table1' => [
            'where' => '',
            'primary_key_ai' => 'idevento',
            // 'primary_key' => 'idevento',
            'charset' => 'utf8',
        ],
        'table1_societies' => [
            'where' => '',
            // 'primary_key' => 'society',
            'charset' => 'utf8mb4',
        ],
        'table1_users' => [
            'where' => '',
            // 'primary_key' => 'username',
            'charset' => 'utf8mb4',
        ],
    ],
    'log_file' => __DIR__ . '/migration_log.txt', // Log file path
    'batch_size' => 100, // Default batch size for insertion
];

// Function to log messages to the log file
function log_message($msg, $config) {
    $timestamp = date('Y-m-d H:i:s'); // Get current date and time
    $formatted_msg = "[$timestamp] $msg"; // Format message once
    // Append the message to the log file
    file_put_contents($config['log_file'], $formatted_msg . PHP_EOL, FILE_APPEND);
    // Print the message to standard output
    print_r($formatted_msg . PHP_EOL);
}

// Function to connect to the database
function connect_db($params) {
    // DSN connection string
    $connection_string = "mysql:host={$params['host']};dbname={$params['db']};charset=utf8mb4";
    // Create a new PDO instance
    $pdo = new PDO($connection_string, $params['user'], $params['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Set error mode to exception
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Set default fetch mode to associative array
    ]);
    // Set connection character set and collation
    $pdo->exec("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
    return $pdo; // Return the PDO object
}

// Function to insert a batch of data
function insert_batch($pdo, $table, $fields, $batch, $config) {
    // Create the field list for the SQL query
    $fieldList = implode(',', array_map(fn($f) => "`$f`", $fields));
    $placeholders = []; // Array for value placeholders
    $params = []; // Array for query parameters
    // Iterate over each row in the batch
    foreach ($batch as $i => $row) {
        $rowPlaceholders = [];
        // Iterate over each field in the row
        foreach ($fields as $f) {
            $ph = ":{$f}_{$i}"; // Create a unique placeholder
            $rowPlaceholders[] = $ph;
            $params[$ph] = $row[$f]; // Associate the value with the placeholder
        }
        $placeholders[] = '(' . implode(',', $rowPlaceholders) . ')'; // Add row placeholders to the general array
    }
    // Create the field list for the ON DUPLICATE KEY UPDATE clause
    $updateFields = array_map(fn($f) => "`$f`=VALUES(`$f`)", $fields);
    // Build the SQL query for insertion or update
    $sql = "INSERT INTO `$table` ($fieldList) VALUES " . implode(',', $placeholders) .
        " ON DUPLICATE KEY UPDATE " . implode(',', $updateFields);
    try {
        $pdo->beginTransaction(); // Start a transaction
        $stmt = $pdo->prepare($sql); // Prepare the query
        $stmt->execute($params); // Execute the query with parameters
        $pdo->commit(); // Commit the transaction
        log_message("[OK] Batch insert/update in table $table, row count: " . count($batch), $config);
    } catch (PDOException $e) {
        $pdo->rollBack(); // Rollback the transaction in case of error
        log_message("[ERROR] Batch insert/update in table $table -- " . $e->getMessage(), $config);
    }
}

print_r("=== Script start at " . date('Y-m-d H:i:s') . " ===");
// Connect to the destination database
try {
    $dst = connect_db($config['destination']);
} catch (PDOException $e) {
    log_message("[FATAL] Destination database connection failed: " . $e->getMessage(), $config);
    exit(1); // Exit if destination DB connection fails
}

// Connect to the source database (only once)
try {
    $src = connect_db($config['source']);
    $src->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false); // Use unbuffered queries
    // Set session timeouts for the source connection
    $src->exec("SET SESSION wait_timeout=28800");
    $src->exec("SET SESSION interactive_timeout=28800");
    log_message("[INFO] Source database connection established successfully.", $config);
} catch (PDOException $e) {
    log_message("[FATAL] Source database connection failed: " . $e->getMessage(), $config);
    exit(1); // Exit if source DB connection fails initially
}

// Iterate over each table defined in the configuration
foreach ($config['tables'] as $table => $opts) {
    // Determine the batch size for the current table
    $batch_size = $opts['batch_size'] ?? $config['batch_size']; // Use null coalescing operator

    // Set the character set for the destination connection, if specified
    if (!empty($opts['charset'])) {
        try {
            $dst->exec("SET NAMES '" . $opts['charset'] . "'");
        } catch (PDOException $e) {
            log_message("[ERROR] Cannot set charset for table $table (destination): " . $e->getMessage(), $config);
        }
    }
    // Costruisce la clausola WHERE, se specificata
    $where = (!empty($opts['where'])) ? 'WHERE ' . $opts['where'] : '';
    // Get the primary key with auto increment, if specified
    $primary_key_ai = $opts['primary_key_ai'] ?? null;
    try {
        // Execute the query to select data from the source table
        $rows = $src->query("SELECT * FROM `$table` $where");
    } catch (PDOException $e) {
        log_message("[ERROR] Cannot fetch data from table $table (source): " . $e->getMessage(), $config);
        continue; // Skip to the next table in case of error
    }
    $batch = []; // Initialize the batch
    $fields = null; // Initialize the field list
    // Iterate over each row retrieved from the source table
    foreach ($rows as $row) {
        // If the field list has not yet been defined, get it from the first row
        if ($fields === null) {
            $fields = array_keys($row);
        }
        $batch[] = $row; // Add the row to the batch
        // If the batch has reached the defined size, insert it
        if (count($batch) === $batch_size) {
            insert_batch($dst, $table, $fields, $batch, $config);
            $batch = []; // Reset the batch
        }
    }
    // Insert any remaining rows in the batch
    if (count($batch) > 0) {
        insert_batch($dst, $table, $fields, $batch, $config);
    }
    // If a primary key with auto increment is specified, update the AUTO_INCREMENT value in the destination table
    if ($primary_key_ai) {
        if (is_string($primary_key_ai)) {
            try {
                // Get the current maximum value of the primary key
                $stmt = $dst->query("SELECT MAX(`$primary_key_ai`) as max_id FROM `$table`");
                $max_val = $stmt->fetchColumn(); // fetchColumn() is suitable for single value result
                $max_id = $max_val ?? 0; // Default to 0 if MAX() returns NULL (e.g., empty table)
                // Build and execute the query to update AUTO_INCREMENT
                $auto_sql = "ALTER TABLE `$table` AUTO_INCREMENT = " . ((int)$max_id + 1);
                $dst->exec($auto_sql);
                log_message("[OK] $auto_sql", $config);
            } catch (PDOException $e) {
                log_message("[ERROR] $auto_sql -- " . $e->getMessage(), $config);
            }
        } else {
            log_message("[INFO] Skipping AUTO_INCREMENT update for table $table", $config);
        }
    }
}
print_r("=== Script end at " . date('Y-m-d H:i:s') . " ===");
