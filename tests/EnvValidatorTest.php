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

    public function testSensitiveVariableIsRejected()
    {
        $_ENV['DB_PASSWORD'] = 'super_secret_value';

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $result = $validator->validate(array(
            'required' => array('DB_PASSWORD'),
        ));

        $this->assertFalse($result->isSuccess());

        $errors = $result->getErrors();
        $found = false;
        foreach ($errors as $error) {
            if (strpos($error, 'Sensitive environment variable') !== false && strpos($error, 'DB_PASSWORD') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function testSensitiveVariableValueIsNeverReturned()
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

        $detailsJson = json_encode($result->toArray());

        $this->assertFalse($result->isSuccess());
        $this->assertFalse(strpos($detailsJson, 'should_not_be_visible') !== false);
    }

    public function testSensitiveRuleVariableIsRemovedFromRules()
    {
        $_ENV['API_TOKEN'] = 'abc123';
        $_ENV['APP_ENV'] = 'production';

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $result = $validator->validate(array(
            'required' => array('APP_ENV'),
            'rules' => array(
                'API_TOKEN' => array('equals' => 'abc123'),
                'APP_ENV' => array('equals' => 'production'),
            ),
        ));

        $this->assertFalse($result->isSuccess());

        $details = $result->getDetails();
        $rulesChecked = $details['variables_checked']['rules'];
        $this->assertNotContains('API_TOKEN', $rulesChecked);
        $this->assertContains('APP_ENV', $rulesChecked);
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

    public function testSensitiveInOptionalIsRejected()
    {
        $_ENV['CLIENT_SECRET'] = 'secret_value';

        $provider = new EnvironmentProvider();
        $validator = new EnvValidator($provider);

        $result = $validator->validate(array(
            'optional' => array('CLIENT_SECRET'),
        ));

        $this->assertFalse($result->isSuccess());
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
