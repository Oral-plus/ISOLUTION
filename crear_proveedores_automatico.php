<?php
// Script for automatic supplier creation - designed to run as a scheduled task
// Only processes suppliers that haven't been successfully created yet

// Set script execution time limit (0 = no limit)
set_time_limit(0);

// Include the API functions
require_once __DIR__ . '/consulta_proveedores.php';

// SQL Server connection parameters - replace with your actual database credentials
$dbServer = '192.168.2.244';
$dbName = 'RBOSKY3';
$dbUser = 'sa';
$dbPass = 'Sky2022*!';

// Batch size for processing records
$batchSize = 50;

// Log file paths
$logFilePath = __DIR__ . '/supplier_creation_log.txt';
$summaryLogPath = __DIR__ . '/supplier_creation_summary.txt';
$processedSuppliersFile = __DIR__ . '/processed_suppliers.json';

// Function to write to log file
function writeLog($message) {
    global $logFilePath;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFilePath, $logMessage, FILE_APPEND);
    
    // Also output to console when running from command line
    echo $logMessage;
}

// Function to connect to SQL Server database
function connectToSQLServer($server, $database, $username, $password) {
    // Connection configuration
    $connectionInfo = array(
        "Database" => $database,
        "UID" => $username,
        "PWD" => $password,
        "CharacterSet" => "UTF-8"
    );

    // Establish connection
    $conn = sqlsrv_connect($server, $connectionInfo);
    
    if (!$conn) {
        $errors = sqlsrv_errors();
        $errorMessage = "";
        
        if ($errors) {
            foreach ($errors as $error) {
                $errorMessage .= "SQLSTATE: " . $error['SQLSTATE'] . ", Code: " . $error['code'] . ", Message: " . $error['message'] . "\n";
            }
        }
        
        writeLog("Database connection failed: $errorMessage");
        die("Database connection failed. See log for details.");
    }
    
    return $conn;
}

// Function to load the list of already processed suppliers
function loadProcessedSuppliers() {
    global $processedSuppliersFile;
    
    if (file_exists($processedSuppliersFile)) {
        $content = file_get_contents($processedSuppliersFile);
        if (!empty($content)) {
            return json_decode($content, true);
        }
    }
    
    // If file doesn't exist or is empty, return an empty array
    return [];
}

// Function to save a supplier as processed
function saveProcessedSupplier($documentNumber, $supplierName) {
    global $processedSuppliersFile;
    
    $processedSuppliers = loadProcessedSuppliers();
    
    // Add the supplier to the processed list
    $processedSuppliers[$documentNumber] = [
        'name' => $supplierName,
        'processed_at' => date('Y-m-d H:i:s')
    ];
    
    // Save the updated list
    file_put_contents($processedSuppliersFile, json_encode($processedSuppliers, JSON_PRETTY_PRINT));
}

// Function to check if a supplier has already been processed
function isSupplierProcessed($documentNumber) {
    $processedSuppliers = loadProcessedSuppliers();
    return isset($processedSuppliers[$documentNumber]);
}

// Function to count total suppliers in the database with U_ISOLUCIONES = 'ISOLUCIONES'
function countSuppliersInDatabase($conn) {
    $sql = "SELECT COUNT(*) as total FROM OCRD WHERE U_ISOLUCIONES = 'ISOLUCIONES' AND CardType = 'S'";
    
    $stmt = sqlsrv_query($conn, $sql);
    
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        $errorMessage = "";
        
        if ($errors) {
            foreach ($errors as $error) {
                $errorMessage .= "SQLSTATE: " . $error['SQLSTATE'] . ", Code: " . $error['code'] . ", Message: " . $error['message'] . "\n";
            }
        }
        
        writeLog("Error counting suppliers: $errorMessage");
        return 0;
    }
    
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    
    return $row['total'] ?? 0;
}

// Function to map database fields to API fields
function mapSupplierData($dbSupplier) {
    // Map database fields to API fields according to PROVEEDORES API documentation
    return [
        'TipoIdentificacion' => mapDocumentType($dbSupplier['CardCode'] ?? ''),
        'NumeroIdentificacion' => $dbSupplier['CardCode'] ?? '',
        'Nombre' => $dbSupplier['CardName'] ?? '',
        'Contacto' => $dbSupplier['CntctPrsn'] ?? '',
        'Telefono' => $dbSupplier['Phone1'] ?? '',
        'Email' => $dbSupplier['E_Mail'] ?? '',
        'Fax' => $dbSupplier['Fax'] ?? '',
        'Direccion' => $dbSupplier['Address'] ?? '',
        'Pais' => $dbSupplier['Country'] ?? 'Colombia',
        'Activo' => '1', // Active by default
        'CodActividadEconomica' => !empty($dbSupplier['IndustryC']) ? (int)$dbSupplier['IndustryC'] : null,
        'CodOrigenRecursos' => 2, // Default value, adjust as needed
        'CodSLFCanal' => 17, // Default value, adjust as needed
     
        
        'EsNivelGlobal' => 0, // Default to branch level
        'Sucursales' => '1' // Default sucursal, adjust as needed
    ];
}

// Helper function to determine document type based on the document number format
function mapDocumentType($documentNumber) {
    // This is a simple example - adjust the logic based on your actual requirements
    if (empty($documentNumber)) {
        return 'NIT';
    }
    
    // If it contains only digits and possibly a dash for verification digit
    if (preg_match('/^\d+(-\d)?$/', $documentNumber)) {
        return 'NIT';
    }
    
    return 'CC'; // Default to Cédula de Ciudadanía
}

// Helper function to get city code (you may need to implement this based on your city mapping)
function getCityCode($cityName) {
    // This is a simple example - you should implement proper city mapping
    $cityMapping = [
        'BOGOTA' => 1,
        'MEDELLIN' => 2,
        'CALI' => 3,
        'BARRANQUILLA' => 4,
        // Add more cities as needed
    ];
    
    $cityUpper = strtoupper(trim($cityName));
    return $cityMapping[$cityUpper] ?? 1; // Default to 1 if not found
}

// Function to log the results of each supplier creation
function logSupplierResult($supplierName, $documentNumber, $success, $message, $data = null) {
    global $logFilePath;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] Supplier: $supplierName ($documentNumber) - " . 
                ($success ? "SUCCESS" : "FAILED") . 
                " - $message\n";
    
    if ($data) {
        $logEntry .= "Response data: " . json_encode($data) . "\n";
    }
    
    $logEntry .= "----------------------------------------\n";
    
    file_put_contents($logFilePath, $logEntry, FILE_APPEND);
}

// Function to write summary to a separate log file
function writeSummary($totalSuppliers, $processedCount, $successCount, $failCount, $skippedCount, $startTime) {
    global $summaryLogPath;
    
    $endTime = time();
    $executionTime = $endTime - $startTime;
    $minutes = floor($executionTime / 60);
    $seconds = $executionTime % 60;
    
    $summary = "=== SUPPLIER CREATION SUMMARY ===\n";
    $summary .= "Date: " . date('Y-m-d H:i:s') . "\n";
    $summary .= "Total suppliers in database: $totalSuppliers\n";
    $summary .= "Suppliers processed this run: $processedCount\n";
    $summary .= "Successfully created: $successCount\n";
    $summary .= "Failed: $failCount\n";
    $summary .= "Skipped (already processed): $skippedCount\n";
    $summary .= "Execution time: $minutes minutes, $seconds seconds\n";
    $summary .= "==============================\n\n";
    
    file_put_contents($summaryLogPath, $summary, FILE_APPEND);
    
    // Also output to console
    echo $summary;
}

// Main execution
try {
    $startTime = time();
    
    writeLog("Starting automatic supplier creation process (only processing new/missing suppliers)...");
    
    // Connect to the database
    writeLog("Connecting to database...");
    $conn = connectToSQLServer($dbServer, $dbName, $dbUser, $dbPass);
    writeLog("Database connection established.");
    
    // Get total count of suppliers
    $totalSuppliers = countSuppliersInDatabase($conn);
    writeLog("Found $totalSuppliers total suppliers in the database.");
    
    // Load the list of already processed suppliers
    $processedSuppliers = loadProcessedSuppliers();
    $alreadyProcessedCount = count($processedSuppliers);
    writeLog("Found $alreadyProcessedCount already processed suppliers.");
    
    // Initialize counters
    $processedCount = 0;
    $successCount = 0;
    $failCount = 0;
    $skippedCount = 0;
    
    // Process suppliers in batches
    $offset = 0;
    
    while ($offset < $totalSuppliers) {
        // Clear memory between batches
        if ($offset > 0) {
            gc_collect_cycles();
        }
        
        // Fetch a batch of suppliers
        writeLog("Fetching batch of suppliers (offset: $offset, limit: $batchSize)...");
        
        $sql = "SELECT * FROM OCRD WHERE U_ISOLUCIONES = 'ISOLUCIONES' AND CardType = 'S' ORDER BY CardCode OFFSET $offset ROWS FETCH NEXT $batchSize ROWS ONLY";
        
        $stmt = sqlsrv_query($conn, $sql);
        
        if ($stmt === false) {
            $errors = sqlsrv_errors();
            $errorMessage = "";
            
            if ($errors) {
                foreach ($errors as $error) {
                    $errorMessage .= "SQLSTATE: " . $error['SQLSTATE'] . ", Code: " . $error['code'] . ", Message: " . $error['message'] . "\n";
                }
            }
            
            writeLog("Error fetching suppliers batch: $errorMessage");
            $offset += $batchSize;
            continue;
        }
        
        $batchSupplierCount = 0;
        
        // Process each supplier in the batch
        while ($dbSupplier = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $batchSupplierCount++;
            
            // Map database fields to API fields
            $supplierData = mapSupplierData($dbSupplier);
            
            // Skip if essential data is missing
            if (empty($supplierData['Nombre']) || empty($supplierData['NumeroIdentificacion'])) {
                writeLog("Skipping supplier with missing essential data: " . 
                     ($supplierData['Nombre'] ?? 'Unknown') . " - " . 
                     ($supplierData['NumeroIdentificacion'] ?? 'No document'));
                continue;
            }
            
            $documentNumber = $supplierData['NumeroIdentificacion'];
            $supplierName = $supplierData['Nombre'];
            
            // Check if this supplier has already been processed
            if (isSupplierProcessed($documentNumber)) {
                writeLog("Skipping already processed supplier: $supplierName ($documentNumber)");
                $skippedCount++;
                continue;
            }
            
            // Count this as a processed supplier
            $processedCount++;
            
            writeLog("Creating supplier $processedCount: $supplierName ($documentNumber)...");
            
            // Create supplier via API
            $result = createSupplier($supplierData);
            
            if ($result['success']) {
                writeLog("SUCCESS: Supplier $supplierName created successfully.");
                $successCount++;
                logSupplierResult($supplierName, $documentNumber, true, "Supplier created successfully", $result['data']);
                
                // Mark this supplier as processed
                saveProcessedSupplier($documentNumber, $supplierName);
            } else {
                writeLog("FAILED: Supplier $supplierName - " . $result['message']);
                $failCount++;
                logSupplierResult($supplierName, $documentNumber, false, $result['message'], $result['data']);
            }
            
            // Free some memory
            unset($supplierData, $result);
        }
        
        // Free statement resources
        sqlsrv_free_stmt($stmt);
        
        // If we got fewer records than batch size, we've reached the end
        if ($batchSupplierCount < $batchSize) {
            break;
        }
        
        // Move to next batch
        $offset += $batchSize;
        
        writeLog("Processed $processedCount new suppliers so far...");
    }
    
    // Write summary
    writeLog("Process completed.");
    writeSummary($totalSuppliers, $processedCount, $successCount, $failCount, $skippedCount, $startTime);
    
    // Close the database connection
    sqlsrv_close($conn);
    
} catch (Exception $e) {
    writeLog("Error: " . $e->getMessage());
}
?>