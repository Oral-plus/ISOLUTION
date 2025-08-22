<?php
// Utility script to validate and test city mappings

require_once __DIR__ . '/employee_creation_script.php';

// Function to test city mapping
function testCityMapping() {
    $testCities = [
        'medellin',
        'bogota',
        'cali',
        'barranquilla',
        'invalid_city',
        '',
        null
    ];
    
    echo "Testing city mappings:\n";
    echo "=====================\n";
    
    foreach ($testCities as $city) {
        $normalized = normalizeCityName($city);
        echo "Input: '" . ($city ?? 'NULL') . "' -> Output: '$normalized'\n";
    }
}

// Function to validate employee data structure
function testEmployeeValidation() {
    echo "\nTesting employee validation:\n";
    echo "============================\n";
    
    // Test valid employee
    $validEmployee = [
        'NumeroIdentificacion' => '1000098645',
        'Nombre' => 'ANDRES FELIPE MONTOYA RUEDA',
        'Login' => 'andres.montoya@empresa.com',
        'Correo' => 'andres.montoya@empresa.com',
        'FechaIngreso' => '2025-03-03T00:00:00',
        'Ciudad' => 'MEDELLÍN'
    ];
    
    $validation = validateEmployeeData($validEmployee);
    echo "Valid employee test: " . ($validation['valid'] ? 'PASSED' : 'FAILED') . "\n";
    if (!$validation['valid']) {
        echo "Errors: " . $validation['message'] . "\n";
    }
    
    // Test invalid employee
    $invalidEmployee = [
        'NumeroIdentificacion' => '',
        'Nombre' => '',
        'Login' => 'invalid-email',
        'FechaIngreso' => ''
    ];
    
    $validation = validateEmployeeData($invalidEmployee);
    echo "Invalid employee test: " . ($validation['valid'] ? 'FAILED' : 'PASSED') . "\n";
    echo "Expected errors: " . $validation['message'] . "\n";
}

// Run tests if script is called directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    testCityMapping();
    testEmployeeValidation();
}
?>
