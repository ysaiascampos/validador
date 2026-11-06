<?php

namespace Vendor\Validator\Tests;

use PHPUnit\Framework\TestCase;
use Vendor\Validator\Validators\EnvValidator;
use Vendor\Validator\Adapters\EnvironmentProvider;

class EnvValidatorTest extends TestCase
{
    public function testRequiredVariablesMissing()
    {
        $_ENV['EXISTING_VAR'] = 'some_value';

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $result = $validator->validate(array(
            'required' => array('EXISTING_VAR', 'MISSING_VAR'),
        ));

        $this->assertFalse($result->isSuccess());

        $errors = $result->getErrors();
        $hasMissingError = false;
        foreach ($errors as $error) {
            if (strpos($error, 'MISSING_VAR') !== false) {
                $hasMissingError = true;
                break;
            }
        }
        $this->assertTrue($hasMissingError);
    }

    public function testEqualsRule()
    {
        $_ENV['ENV_EQUALS'] = 'expected_value';

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $result = $validator->validate(array(
            'rules' => array(
                'ENV_EQUALS' => array(
                    'equals' => 'expected_value',
                ),
            ),
        ));

        $this->assertTrue($result->isSuccess());
    }

    public function testEqualsRuleFails()
    {
        $_ENV['ENV_EQUALS2'] = 'wrong_value';

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $result = $validator->validate(array(
            'rules' => array(
                'ENV_EQUALS2' => array(
                    'equals' => 'expected_value',
                ),
            ),
        ));

        $this->assertFalse($result->isSuccess());
    }

    public function testContainsRule()
    {
        $_ENV['ENV_CONTAINS'] = 'hello world test';

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $result = $validator->validate(array(
            'rules' => array(
                'ENV_CONTAINS' => array(
                    'contains' => 'world',
                ),
            ),
        ));

        $this->assertTrue($result->isSuccess());
    }

    public function testContainsRuleFails()
    {
        $_ENV['ENV_CONTAINS2'] = 'hello world';

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $result = $validator->validate(array(
            'rules' => array(
                'ENV_CONTAINS2' => array(
                    'contains' => 'missing',
                ),
            ),
        ));

        $this->assertFalse($result->isSuccess());
    }

    public function testBooleanRule()
    {
        $_ENV['ENV_BOOL'] = 'true';

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $result = $validator->validate(array(
            'rules' => array(
                'ENV_BOOL' => array(
                    'boolean' => true,
                ),
            ),
        ));

        $this->assertTrue($result->isSuccess());
    }

    public function testNumericRule()
    {
        $_ENV['ENV_NUM'] = '42';

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $result = $validator->validate(array(
            'rules' => array(
                'ENV_NUM' => array(
                    'numeric' => array('min' => 10, 'max' => 100),
                ),
            ),
        ));

        $this->assertTrue($result->isSuccess());
    }

    public function testNumericRuleOutOfRange()
    {
        $_ENV['ENV_NUM2'] = '200';

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $result = $validator->validate(array(
            'rules' => array(
                'ENV_NUM2' => array(
                    'numeric' => array('min' => 10, 'max' => 100),
                ),
            ),
        ));

        $this->assertFalse($result->isSuccess());
    }

    public function testCsvRule()
    {
        $_ENV['ENV_CSV'] = 'item1,item2,item3';

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $result = $validator->validate(array(
            'rules' => array(
                'ENV_CSV' => array(
                    'csv' => array(
                        'count' => 3,
                        'contains' => array('item1', 'item3'),
                    ),
                ),
            ),
        ));

        $this->assertTrue($result->isSuccess());
    }

    public function testJsonRule()
    {
        $_ENV['ENV_JSON'] = '{"name":"test","enabled":true,"count":5}';

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $result = $validator->validate(array(
            'rules' => array(
                'ENV_JSON' => array(
                    'json' => array(
                        'keys' => array('name', 'enabled', 'count'),
                    ),
                ),
            ),
        ));

        $this->assertTrue($result->isSuccess());
    }

    public function testJsonRuleMissingKeys()
    {
        $_ENV['ENV_JSON2'] = '{"name":"test"}';

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $result = $validator->validate(array(
            'rules' => array(
                'ENV_JSON2' => array(
                    'json' => array(
                        'keys' => array('name', 'missing_key'),
                    ),
                ),
            ),
        ));

        $this->assertFalse($result->isSuccess());
    }

    public function testRegexRule()
    {
        $_ENV['ENV_REGEX'] = 'ABC-123';

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $result = $validator->validate(array(
            'rules' => array(
                'ENV_REGEX' => array(
                    'regex' => '/^[A-Z]{3}-\d{3}$/',
                ),
            ),
        ));

        $this->assertTrue($result->isSuccess());
    }

    public function testRegexRuleFails()
    {
        $_ENV['ENV_REGEX2'] = 'invalid';

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $result = $validator->validate(array(
            'rules' => array(
                'ENV_REGEX2' => array(
                    'regex' => '/^\d+$/',
                ),
            ),
        ));

        $this->assertFalse($result->isSuccess());
    }

    public function testMinMaxStringLength()
    {
        $_ENV['ENV_LENGTH'] = 'hello';

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $result = $validator->validate(array(
            'rules' => array(
                'ENV_LENGTH' => array(
                    'min' => 3,
                    'max' => 10,
                ),
            ),
        ));

        $this->assertTrue($result->isSuccess());
    }

    public function testMinMaxStringLengthFails()
    {
        $_ENV['ENV_LENGTH2'] = 'ab';

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $result = $validator->validate(array(
            'rules' => array(
                'ENV_LENGTH2' => array(
                    'min' => 5,
                ),
            ),
        ));

        $this->assertFalse($result->isSuccess());
    }

    public function testGetName()
    {
        $_ENV['TEST_VAR'] = 'value';
        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $this->assertEquals('env', $validator->getName());
    }

    public function testSensitiveRequiredVariableIsCheckedForPresenceOnly()
    {
        $_ENV['DB_PASSWORD'] = 'super_secret_value';

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $result = $validator->validate(array(
            'required' => array('DB_PASSWORD'),
        ));

        // The variable exists, so the validation must pass. Being sensitive
        // only means its value is never exposed.
        $this->assertTrue($result->isSuccess());

        $details = $result->getDetails();
        $this->assertEquals('ok', $details['env_status']['DB_PASSWORD']);

        $detailsJson = json_encode($result->toArray());
        $this->assertFalse(strpos($detailsJson, 'super_secret_value') !== false);
    }

    public function testSensitiveVariableValueRuleIsEvaluatedWithoutExposingValue()
    {
        $_ENV['APP_KEY'] = 'should_not_be_visible';

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $result = $validator->validate(array(
            'rules' => array(
                'APP_KEY' => array(
                    'equals' => 'should_not_be_visible',
                ),
            ),
        ));

        // The rule is evaluated against the real value: it matches, so it passes.
        $this->assertTrue($result->isSuccess());
        $this->assertEmpty($result->getErrors());
        $this->assertFalse(strpos(json_encode($result->toArray()), 'should_not_be_visible') !== false);
    }

    public function testSensitiveVariableValueRuleFailsAndMasksValue()
    {
        $_ENV['APP_KEY'] = 'real-secret-app-key';

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $result = $validator->validate(array(
            'rules' => array(
                'APP_KEY' => array(
                    'equals' => 'a_different_value',
                ),
            ),
        ));

        // The mismatch is detected...
        $this->assertFalse($result->isSuccess());
        $this->assertContains('APP_KEY', implode(' ', $result->getErrors()));

        // ...but neither the real value nor the expected one is exposed.
        $errorsJson = json_encode($result->getErrors());
        $this->assertFalse(strpos($errorsJson, 'real-secret-app-key') !== false);
        $this->assertFalse(strpos($errorsJson, 'a_different_value') !== false);
        $this->assertFalse(strpos($errorsJson, '***') === false);
    }

    public function testSensitiveRuleVariableEvaluatesRulesButHidesValue()
    {
        $_ENV['API_TOKEN'] = 'abc123';
        $_ENV['APP_ENV'] = 'production';

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $result = $validator->validate(array(
            'required' => array('APP_ENV'),
            'rules' => array(
                'API_TOKEN' => array('equals' => 'a_different_value'),
                'APP_ENV' => array('equals' => 'production'),
            ),
        ));

        // API_TOKEN rule mismatch is reported, APP_ENV passes.
        $this->assertFalse($result->isSuccess());
        $this->assertNotEmpty($result->getErrors());

        $details = $result->getDetails();
        $this->assertEquals('ok', $details['env_status']['API_TOKEN']);
        $this->assertContains('API_TOKEN', $details['env_sensitive_variables']);
        $this->assertFalse(strpos(json_encode($result->toArray()), 'abc123') !== false);
    }

    public function testSensitiveCsvRuleValidatesAllExpectedItems()
    {
        $_ENV['SERVICIOS_MIGRADOS_TOKEN'] = 'svc-local,fdfa';

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $result = $validator->validate(array(
            'rules' => array(
                'SERVICIOS_MIGRADOS_TOKEN' => array(
                    'csv' => array(
                        'contains' => array('svc-local', 'duplicidad-domicilio'),
                    ),
                ),
            ),
        ));

        // 'duplicidad-domicilio' is missing from the CSV value.
        $this->assertFalse($result->isSuccess());
        $this->assertContains(
            'Environment variable SERVICIOS_MIGRADOS_TOKEN CSV is missing required item: duplicidad-domicilio',
            $result->getErrors()
        );
        $this->assertFalse(strpos(json_encode($result->toArray()), 'svc-local,fdfa') !== false);
    }

    public function testSensitiveContainsRuleAcceptsArrayOfExpectedValues()
    {
        $_ENV['RUNIFICADOR_REST_API_TOKEN_URL'] = 'https://api.example.com/v1/token';

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $result = $validator->validate(array(
            'rules' => array(
                'RUNIFICADOR_REST_API_TOKEN_URL' => array(
                    'contains' => array('api.example.com', 'not-present'),
                ),
            ),
        ));

        $this->assertFalse($result->isSuccess());
        $this->assertContains(
            'Environment variable RUNIFICADOR_REST_API_TOKEN_URL does not contain expected value: not-present',
            $result->getErrors()
        );
        $this->assertFalse(strpos(json_encode($result->toArray()), 'https://api.example.com/v1/token') !== false);
    }

    public function testTokenUrlVariableIsValidatedForPresenceAndRules()
    {
        $_ENV['RUNIFICADOR_REST_TOKEN_URL'] = 'https://example.com/token';

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $result = $validator->validate(array(
            'required' => array('RUNIFICADOR_REST_TOKEN_URL'),
            'rules' => array(
                'RUNIFICADOR_REST_TOKEN_URL' => array('contains' => 'value-that-does-not-match'),
            ),
        ));

        // Existence is validated and the value rule is evaluated too.
        $this->assertFalse($result->isSuccess());

        $details = $result->getDetails();
        $this->assertEquals('ok', $details['env_status']['RUNIFICADOR_REST_TOKEN_URL']);
        $this->assertContains('RUNIFICADOR_REST_TOKEN_URL', $details['env_sensitive_variables']);
        $this->assertFalse(strpos(json_encode($result->toArray()), 'example.com') !== false);
    }

    public function testKeyPrefixVariableIsValidatedForPresenceButNeverShown()
    {
        $_ENV['RUNIFICADOR_REST_CACHE_KEY_PRE'] = 'cache-prefix-xyz';

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $result = $validator->validate(array(
            'required' => array('RUNIFICADOR_REST_CACHE_KEY_PRE'),
        ));

        $this->assertTrue($result->isSuccess());

        $details = $result->getDetails();
        $this->assertEquals('ok', $details['env_status']['RUNIFICADOR_REST_CACHE_KEY_PRE']);
        $this->assertContains('RUNIFICADOR_REST_CACHE_KEY_PRE', $details['env_sensitive_variables']);
        $this->assertFalse(strpos(json_encode($result->toArray()), 'cache-prefix-xyz') !== false);
    }

    public function testNonSensitiveVariablesAreNotBlocked()
    {
        $_ENV['APP_ENV'] = 'production';

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $result = $validator->validate(array(
            'required' => array('APP_ENV'),
        ));

        $this->assertTrue($result->isSuccess());
    }

    public function testKeywordInsideWordIsNotConsideredSensitive()
    {
        $_ENV['MONKEY_LEVEL'] = 'low';

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $result = $validator->validate(array(
            'required' => array('MONKEY_LEVEL'),
        ));

        $this->assertTrue($result->isSuccess());
    }

    public function testSensitiveInOptionalIsNotRejected()
    {
        $_ENV['CLIENT_SECRET'] = 'secret_value';

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $result = $validator->validate(array(
            'optional' => array('CLIENT_SECRET'),
        ));

        $this->assertTrue($result->isSuccess());

        $details = $result->getDetails();
        $this->assertEquals('ok', $details['env_status']['CLIENT_SECRET']);
        $this->assertFalse(strpos(json_encode($result->toArray()), 'secret_value') !== false);
    }

    public function testMissingSensitiveRequiredVariableIsReportedWithoutValue()
    {
        unset($_ENV['DB_PASSWORD']);

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $result = $validator->validate(array(
            'required' => array('DB_PASSWORD'),
        ));

        // Existence is validated: missing is an error even for a secret...
        $this->assertFalse($result->isSuccess());
        $this->assertContains(
            'Required environment variable is missing: DB_PASSWORD',
            $result->getErrors()
        );

        // ...but no value is ever exposed.
        $details = $result->getDetails();
        $this->assertEquals('missing', $details['env_status']['DB_PASSWORD']);
        $this->assertFalse(isset($details['env_values']['DB_PASSWORD']));
    }

    public function testSensitiveValueIsNeverShownInShowValues()
    {
        $_ENV['API_TOKEN'] = 'secret-token-value';

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider, array(
            'required' => array(),
            'optional' => array(),
            'rules' => array(),
            'show_values' => array('API_TOKEN'),
        ));

        $result = $validator->validate();
        $details = $result->getDetails();

        $this->assertTrue($result->isSuccess());
        $this->assertFalse(isset($details['env_values']['API_TOKEN']));
        $this->assertFalse(strpos(json_encode($result->toArray()), 'secret-token-value') !== false);
    }

    public function testValidatesOptionalVariables()
    {
        $_ENV['OPT_VAR'] = 'some_value';

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $result = $validator->validate(array(
            'optional' => array(
                array(
                    'name' => 'OPT_VAR',
                    'rules' => array(
                        'equals' => 'some_value',
                    ),
                ),
                'MISSING_OPT',
            ),
        ));

        $this->assertTrue($result->isSuccess());
    }
}
