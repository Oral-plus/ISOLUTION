<?php
// Script for automatic client status update - sets Activo = 1 for clients
// Based on the working connection from the client creation script

// Set script execution time limit (0 = no limit)
set_time_limit(0);

// Include the API functions (this now contains the updateClient function)
require_once __DIR__ . '/consulta.php';

// SQL Server connection parameters - using the same as the working script
$dbServer = '192.168.2.244';
$dbName = 'RBOSKY3';
$dbUser = 'sa';
$dbPass = 'Sky2022*!';

// Batch size for processing records
$batchSize = 20; // Smaller batch for API updates

// Log file paths
$logFilePath = __DIR__ . '/client_update_log.txt';
$summaryLogPath = __DIR__ . '/client_update_summary.txt';
$updatedClientsFile = __DIR__ . '/updated_clients.json';

// Function to write to log file
function writeLog($message) {
    global $logFilePath;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFilePath, $logMessage, FILE_APPEND);
    
    // Also output to console when running from command line
    echo $logMessage;
}

// Function to connect to SQL Server database (same as original)
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

// Function to load the list of already updated clients
function loadUpdatedClients() {
    global $updatedClientsFile;
    
    if (file_exists($updatedClientsFile)) {
        $content = file_get_contents($updatedClientsFile);
        if (!empty($content)) {
            return json_decode($content, true);
        }
    }
    
    // If file doesn't exist or is empty, return an empty array
    return [];
}

// Function to save a client as updated
function saveUpdatedClient($documentNumber, $clientName) {
    global $updatedClientsFile;
    
    $updatedClients = loadUpdatedClients();
    
    // Add the client to the updated list
    $updatedClients[$documentNumber] = [
        'name' => $clientName,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Save the updated list
    file_put_contents($updatedClientsFile, json_encode($updatedClients, JSON_PRETTY_PRINT));
}

// Function to check if a client has already been updated
function isClientUpdated($documentNumber) {
    $updatedClients = loadUpdatedClients();
    return isset($updatedClients[$documentNumber]);
}

// Function to count clients that need status update
function countClientsToUpdate($conn) {
    // Count clients where Activo is not 1 and U_ISOLUCIONES = 'ISOLUCIONES'
    $sql = "SELECT COUNT(*) as total FROM OCRD 
            WHERE U_ISOLUCIONES = 'ISOLUCIONES' 
            AND CardType = 'C' 
            ";
    
    $stmt = sqlsrv_query($conn, $sql);
    
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        $errorMessage = "";
        
        if ($errors) {
            foreach ($errors as $error) {
                $errorMessage .= "SQLSTATE: " . $error['SQLSTATE'] . ", Code: " . $error['code'] . ", Message: " . $error['message'] . "\n";
            }
        }
        
        writeLog("Error counting clients to update: $errorMessage");
        return 0;
    }
    
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    
    return $row['total'] ?? 0;
}

// Function to map database fields to API update fields (based on original mapping)
function mapClientUpdateData($dbClient) {
    return [
        'Nombredelcliente' => $dbClient['CardName'] ?? '',
        'TipoDocIdentidad' => mapDocumentType($dbClient['CardCode'] ?? ''),
        'Documento' => $dbClient['CardCode'] ?? '',
        'Activo' => 1, // This is the main field we're updating
        'Celular' => $dbClient['Cellular'] ?? null,
        'CodCiudad' => $dbClient['City'] ?? '',
        'TipoCliente' => $dbClient['GroupCode'] ?? null,
        'Contacto' => $dbClient['CntctPrsn'] ?? '',
        'Direccion' => $dbClient['Address'] ?? null,
        'Email' => $dbClient['E_Mail'] ?? '',
        'Fax' => $dbClient['Fax'] ?? null,
        'Telefono' => $dbClient['Phone1'] ?? null,
        'URL' => $dbClient['IntrntSite'] ?? null,
        
    ];
}

// Helper function to determine document type (same as original)
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

// Function to log the results of each client update
function logUpdateResult($clientName, $documentNumber, $success, $message, $data = null) {
    global $logFilePath;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] UPDATE - Client: $clientName ($documentNumber) - " . 
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
    
    $summary = "=== CLIENT STATUS UPDATE SUMMARY ===\n";
    $summary .= "Date: " . date('Y-m-d H:i:s') . "\n";
    $summary .= "Total clients needing update: $totalClients\n";
    $summary .= "Clients processed this run: $processedCount\n";
    $summary .= "Successfully updated: $successCount\n";
    $summary .= "Failed: $failCount\n";
    $summary .= "Skipped (already updated): $skippedCount\n";
    $summary .= "Execution time: $minutes minutes, $seconds seconds\n";
    $summary .= "=====================================\n\n";
    
    file_put_contents($summaryLogPath, $summary, FILE_APPEND);
    
    // Also output to console
    echo $summary;
}

// Main execution
try {
    $startTime = time();
    
    writeLog("Starting automatic client status update process (setting Activo = 1)...");
    
    // Test API connection first
    writeLog("Testing API connection...");
    $connectionTest = testApiConnection();
    if (!$connectionTest['success']) {
        writeLog("API connection test failed: " . $connectionTest['message']);
        die("API connection failed. Check your credentials and network connection.");
    }
    writeLog("API connection test successful.");
    
    // Connect to the database
    writeLog("Connecting to database...");
    $conn = connectToSQLServer($dbServer, $dbName, $dbUser, $dbPass);
    writeLog("Database connection established.");
    
    // Get total count of clients that need updating
    $totalClients = countClientsToUpdate($conn);
    writeLog("Found $totalClients clients that need status update.");
    
    if ($totalClients == 0) {
        writeLog("No clients need updating. Exiting.");
        sqlsrv_close($conn);
        exit(0);
    }
    
    // Load the list of already updated clients
    $updatedClients = loadUpdatedClients();
    $alreadyUpdatedCount = count($updatedClients);
    writeLog("Found $alreadyUpdatedCount already updated clients.");
    
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
        
        // Fetch a batch of clients that need updating
        writeLog("Fetching batch of clients (offset: $offset, limit: $batchSize)...");
        
        $sql = "SELECT * FROM OCRD 
                WHERE U_ISOLUCIONES = 'ISOLUCIONES' 
                AND CardType = 'C' 
                
                ORDER BY CardCode 
                OFFSET $offset ROWS FETCH NEXT $batchSize ROWS ONLY";
        
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
            $clientData = mapClientUpdateData($dbClient);
            
            // Validate client data before sending
            $validation = validateClientData($clientData);
            if (!$validation['valid']) {
                writeLog("Skipping client with invalid data: " . 
                     ($clientData['Nombredelcliente'] ?? 'Unknown') . " - " . 
                     $validation['message']);
                continue;
            }
            
            $documentNumber = $clientData['Documento'];
            $clientName = $clientData['Nombredelcliente'];
            
            // Check if this client has already been updated
            if (isClientUpdated($documentNumber)) {
                writeLog("Skipping already updated client: $clientName ($documentNumber)");
                $skippedCount++;
                continue;
            }
            
            // Count this as a processed client
            $processedCount++;
            
            writeLog("Updating client $processedCount: $clientName ($documentNumber) - Setting Activo = 1...");
            
            // Update client via API (using the function from consulta.php)
            $result = updateClient($clientData);
            
            if ($result['success']) {
                writeLog("SUCCESS: Client $clientName status updated successfully.");
                $successCount++;
                logUpdateResult($clientName, $documentNumber, true, "Client status updated to Activo = 1", $result['data']);
                
                // Mark this client as updated
                saveUpdatedClient($documentNumber, $clientName);
                
                // Update the local database to reflect the change
                $updateLocalSql = "UPDATE OCRD SET U_Activo = 1 WHERE CardCode = ?";
                $updateStmt = sqlsrv_prepare($conn, $updateLocalSql, array($documentNumber));
                if ($updateStmt) {
                    if (sqlsrv_execute($updateStmt)) {
                        writeLog("Local database updated for client: $clientName");
                    } else {
                        writeLog("Warning: Failed to update local database for client: $clientName");
                    }
                    sqlsrv_free_stmt($updateStmt);
                }
                
            } else {
                writeLog("FAILED: Client $clientName - " . $result['message']);
                $failCount++;
                logUpdateResult($clientName, $documentNumber, false, $result['message'], $result['data']);
                
                // Log the error using the new logging function
                logApiError("Failed to update client: $clientName", [
                    'document' => $documentNumber,
                    'error' => $result['message'],
                    'data' => $clientData
                ]);
            }
            
            // Add a small delay between API calls to avoid overwhelming the server
            usleep(500000); // 0.5 seconds delay
            
            // Free some memory
            unset($clientData, $result, $validation);
        }
        
        // Free statement resources
        sqlsrv_free_stmt($stmt);
        
        // If we got fewer records than batch size, we've reached the end
        if ($batchClientCount < $batchSize) {
            break;
        }
        
        // Move to next batch
        $offset += $batchSize;
        
        writeLog("Processed $processedCount clients so far...");
    }
    
    // Write summary
    writeLog("Update process completed.");
    writeSummary($totalClients, $processedCount, $successCount, $failCount, $skippedCount, $startTime);
    
    // Close the database connection
    sqlsrv_close($conn);
    
} catch (Exception $e) {
    writeLog("Error: " . $e->getMessage());
    logApiError("Script execution error", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
}
?>