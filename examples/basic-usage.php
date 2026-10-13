<?php

/**
 * Example: Basic Usage of the Validator Library
 *
 * This example demonstrates how to use the validator library
 * programmatically. It covers database, environment, and git validations.
 *
 * Usage:
 *   php examples/basic-usage.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Vendor\Validator\Adapters\ConnectionResolver;
use Vendor\Validator\Adapters\EnvironmentProvider;
use Vendor\Validator\Adapters\JsonLoader;
use Vendor\Validator\Oracle\OracleRepository;
use Vendor\Validator\Services\ValidatorService;
use Vendor\Validator\Validators\DatabaseValidator;
use Vendor\Validator\Validators\EnvValidator;
use Vendor\Validator\Validators\GitValidator;

echo "=== Validator Library - Basic Usage Example ===\n\n";

// 1. Create the validator service with auto-discovery
$service = new ValidatorService(
    'Vendor\\Validator\\Validators\\',
    __DIR__ . '/../src/Validators'
);

// Alternatively, register validators manually:
$connectionResolver = new ConnectionResolver();
$envProvider = new EnvironmentProvider();

$oracleRepo = new OracleRepository($connectionResolver, 'oracle');

$config = array(
    'default_connection' => 'oracle',
    'database' => array(
        'expected_schemas' => array(
            'SCHEMA1' => array(
                'columns' => array(
                    'ID' => array(
                        'type' => 'NUMBER',
                        'length' => 22,
                        'nullable' => false,
                    ),
                ),
            ),
        ),
    ),
    'env' => array(
        'required' => array('APP_ENV', 'DB_CONNECTION'),
        'rules' => array(
            'APP_ENV' => array(
                'equals' => 'production',
            ),
        ),
    ),
    'git' => array(
        'require_clean' => true,
        'require_branch' => 'main',
    ),
);

// Register validators
$databaseValidator = new DatabaseValidator($oracleRepo, $config);
$envValidator = new EnvValidator($envProvider, $config['env']);
$gitValidator = new GitValidator(getcwd(), $config['git']);

$service->registerValidators(array(
    $databaseValidator,
    $envValidator,
    $gitValidator,
));

// 2. Run all validations
echo "Running all validators...\n";
$result = $service->validate(array(
    'database' => array(
        'connection' => 'oracle',
    ),
    'env' => array(
        'required' => array('APP_ENV', 'DB_CONNECTION', 'APP_KEY'),
        'rules' => array(
            'APP_ENV' => array(
                'equals' => 'production',
            ),
        ),
    ),
));

echo "\n=== Consolidated Result ===\n";
echo "Success: " . ($result->isSuccess() ? 'YES' : 'NO') . "\n";
echo "Errors: " . count($result->getErrors()) . "\n";
foreach ($result->getErrors() as $error) {
    echo "  - " . $error . "\n";
}
echo "Warnings: " . count($result->getWarnings()) . "\n";
foreach ($result->getWarnings() as $warning) {
    echo "  - " . $warning . "\n";
}
echo "Execution time: " . round($result->getExecutionTime(), 2) . " ms\n";

echo "\n=== Per-Validator Details ===\n";
foreach ($result->getDetails() as $key => $detail) {
    if (is_array($detail) && isset($detail['validator'])) {
        echo $detail['validator'] . ": " .
            ($detail['success'] ? 'PASS' : 'FAIL') . "\n";
        if (!empty($detail['errors'])) {
            foreach ($detail['errors'] as $err) {
                echo "  ERROR: " . $err . "\n";
            }
        }
    }
}

// 3. Run a specific validator
echo "\n=== Running Specific Validator ===\n";
$gitResult = $service->validateSpecific('git');
echo "Git Validator: " . ($gitResult->isSuccess() ? 'PASS' : 'FAIL') . "\n";

echo "\n=== Example Complete ===\n";
