
       
    <?php
// Script for automatic employee creation - designed to run as a scheduled task
// Only processes employees that haven't been successfully created yet

// Set script execution time limit (0 = no limit)
set_time_limit(0);

// Include the API functions
require_once __DIR__ . '/consulta_funcionarios.php';

// SQL Server connection parameters - replace with your actual database credentials
$dbServer = '192.168.2.244';
$dbName = 'RBOSKY3';
$dbUser = 'sa';
$dbPass = 'Sky2022*!';

// Batch size for processing records
$batchSize = 50;

// Log file paths
$logFilePath = __DIR__ . '/employee_creation_log.txt';
$summaryLogPath = __DIR__ . '/employee_creation_summary.txt';
$processedEmployeesFile = __DIR__ . '/processed_employees.json';

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

// Function to load the list of already processed employees
function loadProcessedEmployees() {
    global $processedEmployeesFile;
    
    if (file_exists($processedEmployeesFile)) {
        $content = file_get_contents($processedEmployeesFile);
        if (!empty($content)) {
            return json_decode($content, true);
        }
    }
    
    // If file doesn't exist or is empty, return an empty array
    return [];
}

// Function to save an employee as processed
function saveProcessedEmployee($documentNumber, $employeeName) {
    global $processedEmployeesFile;
    
    $processedEmployees = loadProcessedEmployees();
    
    // Add the employee to the processed list
    $processedEmployees[$documentNumber] = [
        'name' => $employeeName,
        'processed_at' => date('Y-m-d H:i:s')
    ];
    
    // Save the updated list
    file_put_contents($processedEmployeesFile, json_encode($processedEmployees, JSON_PRETTY_PRINT));
}

// Function to check if an employee has already been processed
function isEmployeeProcessed($documentNumber) {
    $processedEmployees = loadProcessedEmployees();
    return isset($processedEmployees[$documentNumber]);
}

// Function to count total employees in the database
function countEmployeesInDatabase($conn) {
    // Adjust this query according to your employee table structure
    // This example assumes you have an EMPLOYEES table or similar
    $sql = "SELECT COUNT(*) as total FROM OHEM WHERE U_Comentarios = 'activo' AND empID = 177 ";
    
    $stmt = sqlsrv_query($conn, $sql);
    
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        $errorMessage = "";
        
        if ($errors) {
            foreach ($errors as $error) {
                $errorMessage .= "SQLSTATE: " . $error['SQLSTATE'] . ", Code: " . $error['code'] . ", Message: " . $error['message'] . "\n";
            }
        }
        
        writeLog("Error counting employees: $errorMessage");
        return 0;
    }
    
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    
    return $row['total'] ?? 0;
}

// Helper function to format dates for API (CORREGIDA)
function formatEmployeeDate($date) {
    if (empty($date)) {
        return null;
    }
    
    try {
        // Si es un objeto DateTime de SQL Server
        if ($date instanceof DateTime) {
            return $date->format('Y-m-d\TH:i:s');
        }
        
        // Si es string
        if (is_string($date)) {
            $cleanDate = trim($date);
            
            // Si ya está en formato correcto yyyy-MM-ddTHH:mm:ss
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $cleanDate)) {
                return $cleanDate;
            }
            
            // Si es formato yyyy-MM-dd HH:mm:ss, convertir
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $cleanDate)) {
                return str_replace(' ', 'T', $cleanDate);
            }
            
            // Si es solo fecha yyyy-MM-dd
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $cleanDate)) {
                return $cleanDate . 'T00:00:00';
            }
            
            // Intentar parsear cualquier otro formato
            $dateTime = new DateTime($cleanDate);
            return $dateTime->format('Y-m-d\TH:i:s');
        }
        
        // Si es timestamp
        if (is_numeric($date)) {
            $dateTime = new DateTime();
            $dateTime->setTimestamp($date);
            return $dateTime->format('Y-m-d\TH:i:s');
        }
        
    } catch (Exception $e) {
        // Si hay error, usar fecha actual para FechaIngreso o null para FechaNacimiento
        writeLog("Error formatting date: " . $e->getMessage() . " - Original: " . print_r($date, true));
        return date('Y-m-d\TH:i:s'); // Fecha actual en formato correcto
    }
    
    return null;
}

// Function to map database fields to API fields
function mapEmployeeData($dbEmployee) {
    // Map database fields to API fields according to FUNCIONARIOS API documentation
    // Adjust these mappings according to your actual database schema
    return [
       
          'TipoIdentificacion' => 'CC',
        'NumeroIdentificacion' => $dbEmployee['passportNo'] ?? '',
        'Nombre' => trim(($dbEmployee['firstName'] ?? '') . ' ' . ($dbEmployee['lastName'] ?? '')),
        'Login' => generateEmployeeLogin($dbEmployee),
        'Correo' => $dbEmployee['email'] ?? '',
        'FechaNacimiento' => formatEmployeeDate($dbEmployee['birthDate'] ?? null),
        'Genero' => mapGender($dbEmployee['sex'] ?? ''),
       
        'Zona' => 'Urbana', // Default value
        'Cargo' => $dbEmployee['position'] ?? 'Empleado',
        'FechaIngreso' => formatEmployeeDate($dbEmployee['startDate'] ?? date('Y-m-d')),
        'Jornada' => 'Normal', // Default value
        'TipoVinculacion' => 'Laboral', // Default value
        'Eps' =>  'GENERAL',
        'Afp' => 'GENERAL',
        'Arl' =>  'GENERAL',
     'Activo' =>  '1',
        'Direccion' => $dbEmployee['homeAddr'] ?? '',
        'Telefono' => $dbEmployee['homeTel'] ?? '',
        'CodActividadEconomica' => 2, // Default value, adjust as needed
        'CodOrigenRecursos' => 2, // Default value, adjust as needed

     
        'EsNivelGlobal' => 0, // Default to branch level
       'Departamento'=> 'Bogota',
                   'Ciudad' => 'BOGOTÁ D.C.',
                           
                       
    ];
}

// Helper function to determine document type based on the document number format
function mapDocumentType($documentNumber) {
    if (empty($documentNumber)) {
        return 'CC';
    }
    
    // If it contains only digits, assume it's CC
    if (preg_match('/^\d+$/', $documentNumber)) {
        return 'CC';
    }
    
    return 'CC'; // Default to Cédula de Ciudadanía
}

// Helper function to map gender from database to API format
function mapGender($dbGender) {
    $gender = strtoupper(trim($dbGender));
    
    switch ($gender) {
        case 'M':
        case 'MALE':
        case 'MASCULINO':
            return 'Masculino';
        case 'F':
        case 'FEMALE':
        case 'FEMENINO':
            return 'Femenino';
        default:
            return 'Masculino'; // Default value
    }
}

// Helper function to generate employee login
function generateEmployeeLogin($dbEmployee) {
    // If email exists, use it as login
    if (!empty($dbEmployee['email'])) {
        return $dbEmployee['email'];
    }
    
    // Generate login from name and ID
    $firstName = strtolower(trim($dbEmployee['firstName'] ?? ''));
    $lastName = strtolower(trim($dbEmployee['lastName'] ?? ''));
    $passportNo = $dbEmployee['passportNo'] ?? '';
    
    // Clean names
    $firstName = preg_replace('/[^a-z]/', '', $firstName);
    $lastName = preg_replace('/[^a-z]/', '', $lastName);
    
    // Create login
    $login = substr($firstName, 0, 3) . substr($lastName, 0, 3) . substr($passportNo, -4);
    
    return $login . '@empresa.com';
}

// Function to log the results of each employee creation
function logEmployeeResult($employeeName, $documentNumber, $success, $message, $data = null) {
    global $logFilePath;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] Employee: $employeeName ($documentNumber) - " . 
                ($success ? "SUCCESS" : "FAILED") . 
                " - $message\n";
    
    if ($data) {
        $logEntry .= "Response data: " . json_encode($data) . "\n";
    }
    
    $logEntry .= "----------------------------------------\n";
    
    file_put_contents($logFilePath, $logEntry, FILE_APPEND);
}

// Function to write summary to a separate log file
function writeSummary($totalEmployees, $processedCount, $successCount, $failCount, $skippedCount, $startTime) {
    global $summaryLogPath;
    
    $endTime = time();
    $executionTime = $endTime - $startTime;
    $minutes = floor($executionTime / 60);
    $seconds = $executionTime % 60;
    
    $summary = "=== EMPLOYEE CREATION SUMMARY ===\n";
    $summary .= "Date: " . date('Y-m-d H:i:s') . "\n";
    $summary .= "Total employees in database: $totalEmployees\n";
    $summary .= "Employees processed this run: $processedCount\n";
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
    
    writeLog("Starting automatic employee creation process (only processing new/missing employees)...");
    
    // Connect to the database
    writeLog("Connecting to database...");
    $conn = connectToSQLServer($dbServer, $dbName, $dbUser, $dbPass);
    writeLog("Database connection established.");
    
    // Get total count of employees
    $totalEmployees = countEmployeesInDatabase($conn);
    writeLog("Found $totalEmployees total employees in the database.");
    
    // Load the list of already processed employees
    $processedEmployees = loadProcessedEmployees();
    $alreadyProcessedCount = count($processedEmployees);
    writeLog("Found $alreadyProcessedCount already processed employees.");
    
    // Initialize counters
    $processedCount = 0;
    $successCount = 0;
    $failCount = 0;
    $skippedCount = 0;
    
    // Process employees in batches
    $offset = 0;
    
    while ($offset < $totalEmployees) {
        // Clear memory between batches
        if ($offset > 0) {
            gc_collect_cycles();
        }
        
        // Fetch a batch of employees
        writeLog("Fetching batch of employees (offset: $offset, limit: $batchSize)...");
        
        // Adjust this query according to your employee table structure
        $sql = "SELECT * FROM OHEM WHERE U_Comentarios = 'activo' AND empID = 177 ORDER BY passportNo OFFSET $offset ROWS FETCH NEXT $batchSize ROWS ONLY";
        
        $stmt = sqlsrv_query($conn, $sql);
        
        if ($stmt === false) {
            $errors = sqlsrv_errors();
            $errorMessage = "";
            
            if ($errors) {
                foreach ($errors as $error) {
                    $errorMessage .= "SQLSTATE: " . $error['SQLSTATE'] . ", Code: " . $error['code'] . ", Message: " . $error['message'] . "\n";
                }
            }
            
            writeLog("Error fetching employees batch: $errorMessage");
            $offset += $batchSize;
            continue;
        }
        
        $batchEmployeeCount = 0;
        
        // Process each employee in the batch
        while ($dbEmployee = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $batchEmployeeCount++;
            
            // Map database fields to API fields
            $employeeData = mapEmployeeData($dbEmployee);
            
            // Skip if essential data is missing
            if (empty($employeeData['Nombre']) || empty($employeeData['NumeroIdentificacion'])) {
                writeLog("Skipping employee with missing essential data: " . 
                     ($employeeData['Nombre'] ?? 'Unknown') . " - " . 
                     ($employeeData['NumeroIdentificacion'] ?? 'No document'));
                continue;
            }
            
            $documentNumber = $employeeData['NumeroIdentificacion'];
            $employeeName = $employeeData['Nombre'];
            
            // Check if this employee has already been processed
            if (isEmployeeProcessed($documentNumber)) {
                writeLog("Skipping already processed employee: $employeeName ($documentNumber)");
                $skippedCount++;
                continue;
            }
            
            // Count this as a processed employee
            $processedCount++;
            
            writeLog("Creating employee $processedCount: $employeeName ($documentNumber)...");
            
            // Log the dates being sent for debugging
            writeLog("Dates being sent - FechaNacimiento: " . ($employeeData['FechaNacimiento'] ?? 'NULL') . 
                    ", FechaIngreso: " . ($employeeData['FechaIngreso'] ?? 'NULL'));
            
            // Validate employee data before sending
            $validation = validateEmployeeData($employeeData);
            if (!$validation['valid']) {
                writeLog("VALIDATION FAILED: Employee $employeeName - " . $validation['message']);
                $failCount++;
                logEmployeeResult($employeeName, $documentNumber, false, $validation['message'], null);
                continue;
            }
            
            // Create employee via API
            $result = createEmployee($employeeData);
            
            if ($result['success']) {
                writeLog("SUCCESS: Employee $employeeName created successfully.");
                $successCount++;
                logEmployeeResult($employeeName, $documentNumber, true, "Employee created successfully", $result['data']);
                
                // Mark this employee as processed
                saveProcessedEmployee($documentNumber, $employeeName);
            } else {
                writeLog("FAILED: Employee $employeeName - " . $result['message']);
                $failCount++;
                logEmployeeResult($employeeName, $documentNumber, false, $result['message'], $result['data']);
            }
            
            // Free some memory
            unset($employeeData, $result);
        }
        
        // Free statement resources
        sqlsrv_free_stmt($stmt);
        
        // If we got fewer records than batch size, we've reached the end
        if ($batchEmployeeCount < $batchSize) {
            break;
        }
        
        // Move to next batch
        $offset += $batchSize;
        
        writeLog("Processed $processedCount new employees so far...");
    }
    
    // Write summary
    writeLog("Process completed.");
    writeSummary($totalEmployees, $processedCount, $successCount, $failCount, $skippedCount, $startTime);
    
    // Close the database connection
    sqlsrv_close($conn);
    
} catch (Exception $e) {
    writeLog("Error: " . $e->getMessage());
}
?>