<?php

if (!function_exists('env')) {
    function env($key, $default = null) {
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        $value = getenv($key);
        return $value === false ? $default : $value;
    }
}

return array(

    /*
    |--------------------------------------------------------------------------
    | API Token
    |--------------------------------------------------------------------------
    |
    | Bearer token required to access the validation API endpoint.
    | This value should match the VALIDATOR_API_TOKEN in your .env file.
    |
    */
    'api_token' => env('VALIDATOR_API_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | API Auth Enabled
    |--------------------------------------------------------------------------
    |
    | Set to false to disable the Bearer token check on the validation API.
    | Useful during development when the Authorization header is not reaching
    | the application (e.g. Apache/mod_rewrite discarding it). Keep it true
    | in production.
    |
    */
    'auth_enabled' => true,

    /*
    | Visual Web Service tester. Keep disabled unless the application has a
    | controlled audience. Configure the hosts that business users may call.
    */
    'ws_tester' => array(
        'enabled'       => false,
        'allowed_hosts' => array(),
        'timeout'       => 30,
    ),

    /*
    |--------------------------------------------------------------------------
    | Default Connection
    |--------------------------------------------------------------------------
    |
    | The default database connection name to use for Oracle validations
    | when no connection is specified in the validation request.
    |
    */
    'default_connection' => env('VALIDATOR_DEFAULT_CONNECTION', 'oracle'),

    /*
    |--------------------------------------------------------------------------
    | Connection Schema Mapping
    |--------------------------------------------------------------------------
    |
    | Maps each database connection name to the Oracle schema (database user)
    | that should be validated when that connection is used. The schema is
    | typically the DB_USERNAME of each connection defined in your .env file.
    |
    | Example .env:
    |
    |   #ASIGNACION
    |   DB_CONNECTION=oracle
    |   DB_USERNAME=ASIGNACION
    |
    |   #EXPORT
    |   DB_EXPORT_CONNECTION=oracle
    |   DB_EXPORT_USERNAME=ASIGNACION_EXPORT
    |
    | Config:
    |
    |   'connections' => array(
    |       'oracle'        => env('DB_USERNAME', ''),
    |       'oracle_export' => env('DB_EXPORT_USERNAME', ''),
    |   ),
    |
    | If a connection has no entry here and no schemas are provided in the
    | request, the validator will use the schema of the current connected user.
    |
    */
    'connections' => array(
        'oracle'        => env('DB_USERNAME', ''),
        'oracle_export' => env('DB_EXPORT_USERNAME', ''),
    ),

    /*
    |--------------------------------------------------------------------------
    | Validators
    |--------------------------------------------------------------------------
    |
    | List of enabled validator classes. If empty, all validators implementing
    | ValidatorInterface under the Validators namespace will be auto-discovered.
    |
    */
    'validators' => array(),

    /*
    |--------------------------------------------------------------------------
    | Database Validation Settings
    |--------------------------------------------------------------------------
    |
    | Configure which database objects to validate. Each flag enables or
    | disables validation for that specific object type.
    |
    */
    'database' => array(

        'schemas'    => true,
        'tables'     => true,
        'columns'    => array(
            'enabled'    => true,
            'types'      => true,
            'length'     => true,
            'nullable'   => true,
            'defaults'   => true,
        ),
        'functions'  => true,
        'procedures' => true,
        'sequences'  => true,
        'views'      => true,
        'triggers'   => true,
        'packages'   => true,
        'synonyms'   => true,
        'indexes'    => true,
        'keys'       => array(
            'primary'   => true,
            'foreign'   => true,
        ),
        'constraints' => true,

        /*
        |--------------------------------------------------------------------------
        | Expected Schema Definitions
        |--------------------------------------------------------------------------
        |
        | Define the expected database structure for validation. Each schema
        | can list expected tables, their columns, and other objects.
        |
        */
        'expected_schemas' => array(),

    ),

    /*
    |--------------------------------------------------------------------------
    | Environment Validation Rules
    |--------------------------------------------------------------------------
    |
    | Define validation rules for environment variables. The required list is
    | checked by default against the project's connection variables. Sensitive
    | variables (PASSWORD, TOKEN, SECRET, KEY) are never validated or exposed.
    |
    */
    'env' => array(

        'required' => array(
            'APP_ENV',
            'DB_CONNECTION',
            'DB_HOST',
            'DB_PORT',
            'DB_DATABASE',
            'DB_USERNAME',
            'DB_CHARSET',
            'DB_EXPORT_CONNECTION',
            'DB_EXPORT_HOST',
            'DB_EXPORT_PORT',
            'DB_EXPORT_DATABASE',
            'DB_EXPORT_USERNAME',
            'DB_EXPORT_CHARSET',
        ),
        'optional' => array(),

        /*
        | Path to the .env file used as source of truth. When set and the file
        | exists, the validator compares the loaded values against it and reports
        | variables as 'stale' or 'not_loaded' when they differ or are missing
        | from the process (e.g. stale config cache). Leave null to use
        | base_path('.env') automatically in Laravel.
        */
        'env_file' => null,

        /*
        | Current environment name used to pick the per-environment rules block.
        | Leave null to auto-detect it from the APP_ENV variable (or from the
        | 'environment' option of the request).
        */
        'environment' => null,

        /*
        | Per-environment validation rules, keyed by environment name
        | (e.g. 'local', 'production'). Values can differ per environment.
        |
        | Semantics: if a block defines 'required' or 'optional', they replace
        | the base sections for that environment. 'rules' are merged per
        | variable, so an environment can override or add specific rules.
        |
        'environments' => array(
            'local' => array(
                'rules' => array(
                    'SERVICIOS_MIGRADOS' => array(
                        'csv' => array('contains' => array('svc-local')),
                    ),
                ),
            ),
            'production' => array(
                'rules' => array(
                    'SERVICIOS_MIGRADOS' => array(
                        'csv' => array('contains' => array('duplicidad-domicilio', 'fdfa', 'Data')),
                    ),
                ),
            ),
        ),
        */
        'environments' => array(),

        /*
        | When true (and env_file is set and readable), every variable defined
        | in the .env file is validated automatically against the loaded values,
        | even if it is not listed in required/optional/rules. By default it is
        | false: only the variables explicitly requested (config or request) are
        | validated. Sensitive variables (PASSWORD/TOKEN/SECRET/KEY) are always
        | skipped without exposing their values.
        */
        'auto_discover' => false,

        /*
        | Variables whose loaded value should be included in the response
        | (detail 'env_values'). Sensitive variables are never shown, even if
        | listed here.
        |
        | 'show_values' => array('SERVICIOS_MIGRADOS', 'MIX_PUSHER_APP_CLUSTER'),
        */
        'show_values' => array(),

        'rules'    => array(
            /*
            | Example: SERVICIOS_MIGRADOS='duplicidad-domicilio,fdfa,Data'
            | Validates that every required CSV item is present, in any order.
            | Set 'ignore_case' => true to compare case-insensitively.
            |
            'SERVICIOS_MIGRADOS' => array(
                'csv' => array(
                    'contains' => array('duplicidad-domicilio', 'fdfa', 'Data'),
                    'ignore_case' => true,
                ),
            ),
            */
        ),

    ),

    /*
    |--------------------------------------------------------------------------
    | Git Validation Settings
    |--------------------------------------------------------------------------
    |
    | Configure git validation requirements for the project.
    |
    */
    'git' => array(

        'require_clean'   => true,
        'require_branch'  => '',
        'require_commit'  => '',
        'require_tag'     => '',

    ),

);
