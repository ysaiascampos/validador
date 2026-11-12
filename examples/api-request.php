<?php

/**
 * Example: API Request to Validate Endpoint
 *
 * This example demonstrates how to make API requests to the
 * POST /api/validate endpoint using cURL.
 *
 * Usage:
 *   php examples/api-request.php
 */

echo "=== Validator API - Request Examples ===\n\n";

echo "Example 1: Basic validation request\n";
echo "-------------------------------------\n";
echo "curl -X POST http://localhost:8000/api/validate \\\n";
echo "  -H \"Authorization: Bearer your-api-token\" \\\n";
echo "  -H \"Content-Type: application/json\" \\\n";
echo "  -d '{}'\n\n";

echo "Example 2: Validate database checklist (configured in config/validator.php)\n";
echo "-------------------------------------\n";
echo "curl -X POST http://localhost:8000/api/validate \\\n";
echo "  -H \"Authorization: Bearer your-api-token\" \\\n";
echo "  -H \"Content-Type: application/json\" \\\n";
echo "  -d '{\"enable\":{\"database\":true}}'\n\n";
echo "The objects to validate (schemas, tables, columns, indexes, constraints...)\n";
echo "are declared in config/validator.php under database.expected_schemas or\n";
echo "database.checklist. Sending them in the request has no effect.\n\n";

echo "Example 3: Validate without database\n";
echo "-------------------------------------\n";
echo "curl -X POST http://localhost:8000/api/validate \\\n";
echo "  -H \"Authorization: Bearer your-api-token\" \\\n";
echo "  -H \"Content-Type: application/json\" \\\n";
echo "  -d '{\"enable\":{\"database\":false,\"env\":true,\"git\":true}}'\n\n";

echo "Example 4: With invalid token\n";
echo "-------------------------------------\n";
echo "curl -X POST http://localhost:8000/api/validate \\\n";
echo "  -H \"Authorization: Bearer wrong-token\" \\\n";
echo "  -H \"Content-Type: application/json\" \\\n";
echo "  -d '{}'\n\n";

echo "Expected response (success):\n";
echo json_encode(array(
    'status' => 'pass',
    'success' => true,
    'data' => array(
        'validator' => 'consolidated',
        'success' => true,
        'errors' => array(),
        'warnings' => array(),
        'details' => array(
            'database' => array(
                'validator' => 'database',
                'success' => true,
                'errors' => array(),
                'warnings' => array(),
                'details' => array(
                    'connections' => array('oracle'),
                    'schemas' => array('ASIGNACION' => 'exists'),
                    'tables' => array(
                        'ASIGNACION.EMPLEADOS' => 'exists',
                        'ASIGNACION.CARGOS' => 'not_exists',
                    ),
                ),
                'execution_time' => 150.25,
            ),
        ),
        'execution_time' => 155.30,
    ),
), JSON_PRETTY_PRINT) . "\n\n";

echo "Expected response (unauthorized):\n";
echo json_encode(array(
    'status' => 'error',
    'success' => false,
    'error' => 'Invalid API token',
), JSON_PRETTY_PRINT) . "\n";
