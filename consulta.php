<?php
/**
 * ISOLUCIÓN API Client
 * Funciones para interactuar con la API de ISOLUCIÓN
 * 
 * @author Tu Nombre
 * @version 1.0
 */

session_start();

// ============================================================================
// CONFIGURACIÓN DE LA API
// ============================================================================

// Credenciales de la API
$username = 'administrador';
$password = 'Sky2025+';
$apiKey = 'fbaa4d84-76db-4b1f-b4d4-af28a5aabf30-oralplus.isolucion.co';

// URL base de la API
$baseApiUrl = 'https://apiiso02.isolucion.co/api';

// Configuración de timeouts
$defaultTimeout = 30;
$defaultConnectTimeout = 10;

// ============================================================================
// FUNCIONES AUXILIARES PARA cURL
// ============================================================================

/**
 * Configura los headers básicos para las peticiones a la API
 * 
 * @param string $username Usuario de la API
 * @param string $password Contraseña de la API
 * @param string $apiKey Clave de la API
 * @return array Headers configurados
 */
function getApiHeaders($username, $password, $apiKey) {
    $auth = base64_encode("$username:$password");
    return [
        'Content-Type: application/json',
        'Authorization: Basic ' . $auth,
        'apiKey: ' . $apiKey
    ];
}

/**
 * Procesa la respuesta de cURL y devuelve un resultado estandarizado
 * 
 * @param resource $ch Handle de cURL
 * @param string $response Respuesta de la API
 * @return array Resultado estandarizado
 */
function processCurlResponse($ch, $response) {
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    if ($curlError) {
        return [
            'success' => false,
            'message' => 'Error cURL: ' . $curlError,
            'data' => null,
            'httpCode' => $httpCode
        ];
    }
    
    // Intentar decodificar la respuesta JSON
    $decodedResponse = json_decode($response, true);
    
    if ($httpCode >= 400) {
        $errorMessage = 'Error HTTP: ' . $httpCode;
        if ($decodedResponse && isset($decodedResponse['Message'])) {
            $errorMessage .= ' - ' . $decodedResponse['Message'];
        }
        
        return [
            'success' => false,
            'message' => $errorMessage,
            'data' => $decodedResponse,
            'httpCode' => $httpCode
        ];
    }
    
    // Verificar si la respuesta tiene StatusCode (formato ISOLUCIÓN)
    if ($decodedResponse && isset($decodedResponse['StatusCode'])) {
        if ($decodedResponse['StatusCode'] == 200) {
            return [
                'success' => true,
                'message' => $decodedResponse['Message'] ?? 'Operación exitosa',
                'data' => $decodedResponse,
                'httpCode' => $httpCode
            ];
        } else {
            return [
                'success' => false,
                'message' => $decodedResponse['Message'] ?? 'Error en la operación',
                'data' => $decodedResponse,
                'httpCode' => $httpCode
            ];
        }
    }
    
    // Respuesta exitosa sin StatusCode específico
    return [
        'success' => true,
        'message' => 'Operación exitosa',
        'data' => $decodedResponse ?? $response,
        'httpCode' => $httpCode
    ];
}

// ============================================================================
// FUNCIONES PRINCIPALES DE cURL
// ============================================================================

/**
 * Envía datos por POST a la API con cURL
 * 
 * @param string $url URL de destino
 * @param string $jsonData Datos en formato JSON
 * @param string $username Usuario de la API
 * @param string $password Contraseña de la API
 * @param string $apiKey Clave de la API
 * @return array Resultado de la operación
 */
function postApiDataWithCurl($url, $jsonData, $username, $password, $apiKey) {
    global $defaultTimeout, $defaultConnectTimeout;
    
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonData,
        CURLOPT_HTTPHEADER => getApiHeaders($username, $password, $apiKey),
        CURLOPT_TIMEOUT => $defaultTimeout,
        CURLOPT_CONNECTTIMEOUT => $defaultConnectTimeout,
        CURLOPT_SSL_VERIFYPEER => false, // Solo para desarrollo
        CURLOPT_VERBOSE => false
    ]);
    
    $response = curl_exec($ch);
    $result = processCurlResponse($ch, $response);
    
    curl_close($ch);
    return $result;
}

/**
 * Envía datos por PUT a la API con cURL
 * 
 * @param string $url URL de destino
 * @param string $jsonData Datos en formato JSON
 * @param string $username Usuario de la API
 * @param string $password Contraseña de la API
 * @param string $apiKey Clave de la API
 * @return array Resultado de la operación
 */
function putApiDataWithCurl($url, $jsonData, $username, $password, $apiKey) {
    global $defaultTimeout, $defaultConnectTimeout;
    
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => $jsonData,
        CURLOPT_HTTPHEADER => getApiHeaders($username, $password, $apiKey),
        CURLOPT_TIMEOUT => $defaultTimeout,
        CURLOPT_CONNECTTIMEOUT => $defaultConnectTimeout,
        CURLOPT_SSL_VERIFYPEER => false, // Solo para desarrollo
        CURLOPT_VERBOSE => false
    ]);
    
    $response = curl_exec($ch);
    $result = processCurlResponse($ch, $response);
    
    curl_close($ch);
    return $result;
}

/**
 * Obtiene datos con GET desde la API con cURL
 * 
 * @param string $url URL de destino
 * @param string $username Usuario de la API
 * @param string $password Contraseña de la API
 * @param string $apiKey Clave de la API
 * @return array Resultado de la operación
 */
function getApiDataWithCurl($url, $username, $password, $apiKey) {
    global $defaultTimeout, $defaultConnectTimeout;
    
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => getApiHeaders($username, $password, $apiKey),
        CURLOPT_TIMEOUT => $defaultTimeout,
        CURLOPT_CONNECTTIMEOUT => $defaultConnectTimeout,
        CURLOPT_SSL_VERIFYPEER => false, // Solo para desarrollo
        CURLOPT_VERBOSE => false
    ]);
    
    $response = curl_exec($ch);
    $result = processCurlResponse($ch, $response);
    
    curl_close($ch);
    return $result;
}

// ============================================================================
// FUNCIONES ESPECÍFICAS PARA CLIENTES
// ============================================================================

/**
 * Crea un nuevo cliente en ISOLUCIÓN
 * 
 * @param array $clienteData Datos del cliente
 * @return array Resultado de la operación
 */
function createClient($clienteData) {
    global $username, $password, $apiKey, $baseApiUrl;
    
    $url = $baseApiUrl . '/clientes';
    $jsonData = json_encode($clienteData);
    
    return postApiDataWithCurl($url, $jsonData, $username, $password, $apiKey);
}

/**
 * Actualiza un cliente existente en ISOLUCIÓN
 * 
 * @param array $clienteData Datos del cliente a actualizar
 * @return array Resultado de la operación
 */
function updateClient($clienteData) {
    global $username, $password, $apiKey, $baseApiUrl;
    
    $url = $baseApiUrl . '/clientes/';
    $jsonData = json_encode($clienteData);
    
    return putApiDataWithCurl($url, $jsonData, $username, $password, $apiKey);
}

/**
 * Obtiene la lista de clientes desde ISOLUCIÓN
 * 
 * @param array $filters Filtros opcionales para la consulta
 * @return array Resultado de la operación
 */
function getClients($filters = []) {
    global $username, $password, $apiKey, $baseApiUrl;
    
    $url = $baseApiUrl . '/clientes';
    
    // Agregar filtros como parámetros GET si se proporcionan
    if (!empty($filters)) {
        $queryString = http_build_query($filters);
        $url .= '?' . $queryString;
    }
    
    return getApiDataWithCurl($url, $username, $password, $apiKey);
}

/**
 * Obtiene un cliente específico por su documento
 * 
 * @param string $documento Número de documento del cliente
 * @return array Resultado de la operación
 */
function getClientByDocument($documento) {
    global $username, $password, $apiKey, $baseApiUrl;
    
    $url = $baseApiUrl . '/clientes/' . urlencode($documento);
    
    return getApiDataWithCurl($url, $username, $password, $apiKey);
}

// ============================================================================
// FUNCIONES DE UTILIDAD
// ============================================================================

/**
 * Valida los datos mínimos requeridos para un cliente
 * 
 * @param array $clienteData Datos del cliente
 * @return array Resultado de la validación
 */
function validateClientData($clienteData) {
    $requiredFields = ['Nombredelcliente', 'TipoDocIdentidad', 'Documento'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (empty($clienteData[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        return [
            'valid' => false,
            'message' => 'Faltan campos requeridos: ' . implode(', ', $missingFields),
            'missing_fields' => $missingFields
        ];
    }
    
    // Validar formato de email si se proporciona
    if (!empty($clienteData['Email']) && !filter_var($clienteData['Email'], FILTER_VALIDATE_EMAIL)) {
        return [
            'valid' => false,
            'message' => 'El formato del email no es válido',
            'missing_fields' => []
        ];
    }
    
    return [
        'valid' => true,
        'message' => 'Datos válidos',
        'missing_fields' => []
    ];
}

/**
 * Registra errores en un archivo de log
 * 
 * @param string $message Mensaje de error
 * @param array $context Contexto adicional del error
 */
function logApiError($message, $context = []) {
    $logFile = __DIR__ . '/api_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message";
    
    if (!empty($context)) {
        $logEntry .= " | Context: " . json_encode($context);
    }
    
    $logEntry .= PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// ============================================================================
// FUNCIONES DE TESTING (OPCIONAL)
// ============================================================================

/**
 * Prueba la conectividad con la API
 * 
 * @return array Resultado de la prueba
 */
function testApiConnection() {
    global $username, $password, $apiKey, $baseApiUrl;
    
    $url = $baseApiUrl . '/clientes';
    $result = getApiDataWithCurl($url, $username, $password, $apiKey);
    
    if ($result['success']) {
        return [
            'success' => true,
            'message' => 'Conexión exitosa con la API',
            'data' => $result
        ];
    } else {
        logApiError('Error en prueba de conexión', $result);
        return [
            'success' => false,
            'message' => 'Error de conexión: ' . $result['message'],
            'data' => $result
        ];
    }
}

?>