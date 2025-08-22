<?php
// Script for automatic client creation - designed to run as a scheduled task
// Only processes clients that haven't been successfully created yet

// Set script execution time limit (0 = no limit)
set_time_limit(0);

// Include the API functions
require_once __DIR__ . '/consulta.php';

// SQL Server connection parameters - replace with your actual database credentials
$dbServer = '192.168.2.244';
$dbName = 'RBOSKY3';
$dbUser = 'sa';
$dbPass = 'Sky2022*!';

// Batch size for processing records
$batchSize = 50;

// Log file paths
$logFilePath = __DIR__ . '/client_creation_log.txt';
$summaryLogPath = __DIR__ . '/client_creation_summary.txt';
$processedClientsFile = __DIR__ . '/processed_clients.json';

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

// Function to load the list of already processed clients
function loadProcessedClients() {
    global $processedClientsFile;
    
    if (file_exists($processedClientsFile)) {
        $content = file_get_contents($processedClientsFile);
        if (!empty($content)) {
            return json_decode($content, true);
        }
    }
    
    // If file doesn't exist or is empty, return an empty array
    return [];
}

// Function to save a client as processed
function saveProcessedClient($documentNumber, $clientName) {
    global $processedClientsFile;
    
    $processedClients = loadProcessedClients();
    
    // Add the client to the processed list
    $processedClients[$documentNumber] = [
        'name' => $clientName,
        'processed_at' => date('Y-m-d H:i:s')
    ];
    
    // Save the updated list
    file_put_contents($processedClientsFile, json_encode($processedClients, JSON_PRETTY_PRINT));
}

// Function to check if a client has already been processed
function isClientProcessed($documentNumber) {
    $processedClients = loadProcessedClients();
    return isset($processedClients[$documentNumber]);
}

// Function to count total clients in the database with U_ISOLUCIONES = 'ISOLUCIONES'
function countClientsInDatabase($conn) {
    // ERROR FOUND HERE - Missing COUNT(*) in SELECT statement
    $sql = "SELECT COUNT(*) as total FROM OCRD WHERE U_ISOLUCIONES = 'ISOLUCIONES' AND CardType = 'C'";
    
    $stmt = sqlsrv_query($conn, $sql);
    
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        $errorMessage = "";
        
        if ($errors) {
            foreach ($errors as $error) {
                $errorMessage .= "SQLSTATE: " . $error['SQLSTATE'] . ", Code: " . $error['code'] . ", Message: " . $error['message'] . "\n";
            }
        }
        
        writeLog("Error counting clients: $errorMessage");
        return 0;
    }
    
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    
    return $row['total'] ?? 0;
}

// Function to map database fields to API fields
function mapClientData($dbClient) {
    // Map database fields to API fields
    // Adjust these mappings according to your actual database schema
    return [
        'Nombredelcliente' => $dbClient['CardName'] ?? '',
        'Celular' => $dbClient['Cellular'] ?? null,
        'CodCiudad' => $dbClient['City'] ?? '',
        'TipoCliente' => $dbClient['GroupCode'] ?? null,
        'TipoDocIdentidad' => mapDocumentType($dbClient['CardCode'] ?? ''),
        'Contacto' => $dbClient['CntctPrsn'] ?? '',
        'Direccion' => $dbClient['Address'] ?? null,
        'Documento' => $dbClient['CardCode'] ?? '',
        'Email' => $dbClient['E_Mail'] ?? '',
        'Fax' => $dbClient['Fax'] ?? null,
        'Telefono' => $dbClient['Phone1'] ?? null,
        'URL' => $dbClient['IntrntSite'] ?? null,
        'FechaCreacion' => date('Y/m/d'),
        'Activo' => 1,
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

// Function to log the results of each client creation
function logClientResult($clientName, $documentNumber, $success, $message, $data = null) {
    global $logFilePath;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] Client: $clientName ($documentNumber) - " . 
                ($success ? "SUCCESS" : "FAILED") . 
                " - $message\n";
    
    if ($data) {
        $logEntry .= "Response data: " . json_encode($data) . "\n";
    }
    
    $logEntry .= "----------------------------------------\n";
    
    file_put_contents($logFilePath, $logEntry, FILE_APPEND);
}

// Function to write summary to a separate log file
function writeSummary($totalClients, $processedCount, $successCount, $failCount, $skippedCount, $startTime) {
    global $summaryLogPath;
    
    $endTime = time();
    $executionTime = $endTime - $startTime;
    $minutes = floor($executionTime / 60);
    $seconds = $executionTime % 60;
    
    $summary = "=== CLIENT CREATION SUMMARY ===\n";
    $summary .= "Date: " . date('Y-m-d H:i:s') . "\n";
    $summary .= "Total clients in database: $totalClients\n";
    $summary .= "Clients processed this run: $processedCount\n";
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
    
    writeLog("Starting automatic client creation process (only processing new/missing clients)...");
    
    // Connect to the database
    writeLog("Connecting to database...");
    $conn = connectToSQLServer($dbServer, $dbName, $dbUser, $dbPass);
    writeLog("Database connection established.");
    
    // Get total count of clients
    $totalClients = countClientsInDatabase($conn);
    writeLog("Found $totalClients total clients in the database.");
    
    // Load the list of already processed clients
    $processedClients = loadProcessedClients();
    $alreadyProcessedCount = count($processedClients);
    writeLog("Found $alreadyProcessedCount already processed clients.");
    
    // Initialize counters
    $processedCount = 0;
    $successCount = 0;
    $failCount = 0;
    $skippedCount = 0;
    
    // Process clients in batches
    $offset = 0;
    
    while ($offset < $totalClients) {
        // Clear memory between batches
        if ($offset > 0) {
            gc_collect_cycles();
        }
        
        // Fetch a batch of clients
        writeLog("Fetching batch of clients (offset: $offset, limit: $batchSize)...");
        
        $sql = "SELECT * FROM OCRD WHERE U_ISOLUCIONES = 'ISOLUCIONES' AND CardType = 'C' ORDER BY CardCode OFFSET $offset ROWS FETCH NEXT $batchSize ROWS ONLY";
        
        $stmt = sqlsrv_query($conn, $sql);
        
        if ($stmt === false) {
            $errors = sqlsrv_errors();
            $errorMessage = "";
            
            if ($errors) {
                foreach ($errors as $error) {
                    $errorMessage .= "SQLSTATE: " . $error['SQLSTATE'] . ", Code: " . $error['code'] . ", Message: " . $error['message'] . "\n";
                }
            }
            
            writeLog("Error fetching clients batch: $errorMessage");
            $offset += $batchSize;
            continue;
        }
        
        $batchClientCount = 0;
        
        // Process each client in the batch
        while ($dbClient = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $batchClientCount++;
            
            // Map database fields to API fields
            $clientData = mapClientData($dbClient);
            
            // Skip if essential data is missing
            if (empty($clientData['Nombredelcliente']) || empty($clientData['Documento'])) {
                writeLog("Skipping client with missing essential data: " . 
                     ($clientData['Nombredelcliente'] ?? 'Unknown') . " - " . 
                     ($clientData['Documento'] ?? 'No document'));
                continue;
            }
            
            $documentNumber = $clientData['Documento'];
            $clientName = $clientData['Nombredelcliente'];
            
            // Check if this client has already been processed
            if (isClientProcessed($documentNumber)) {
                writeLog("Skipping already processed client: $clientName ($documentNumber)");
                $skippedCount++;
                continue;
            }
            
            // Count this as a processed client
            $processedCount++;
            
            writeLog("Creating client $processedCount: $clientName ($documentNumber)...");
            
            // Create client via API
            $result = createClient($clientData);
            
            if ($result['success']) {
                writeLog("SUCCESS: Client $clientName created successfully.");
                $successCount++;
                logClientResult($clientName, $documentNumber, true, "Client created successfully", $result['data']);
                
                // Mark this client as processed
                saveProcessedClient($documentNumber, $clientName);
            } else {
                writeLog("FAILED: Client $clientName - " . $result['message']);
                $failCount++;
                logClientResult($clientName, $documentNumber, false, $result['message'], $result['data']);
            }
            
            // Free some memory
            unset($clientData, $result);
        }
        
        // Free statement resources
        sqlsrv_free_stmt($stmt);
        
        // If we got fewer records than batch size, we've reached the end
        if ($batchClientCount < $batchSize) {
            break;
        }
        
        // Move to next batch
        $offset += $batchSize;
        
        writeLog("Processed $processedCount new clients so far...");
    }
    
    // Write summary
    writeLog("Process completed.");
    writeSummary($totalClients, $processedCount, $successCount, $failCount, $skippedCount, $startTime);
    
    // Close the database connection
    sqlsrv_close($conn);
    
} catch (Exception $e) {
    writeLog("Error: " . $e->getMessage());
}
?>