<?php

namespace Vendor\Validator\DTO;

class ValidationResult
{
    /**
     * Whether the validation passed.
     *
     * @var bool
     */
    private $success;

    /**
     * List of error messages.
     *
     * @var array
     */
    private $errors;

    /**
     * List of warning messages.
     *
     * @var array
     */
    private $warnings;

    /**
     * Detailed validation information.
     *
     * @var array
     */
    private $details;

    /**
     * Execution time in milliseconds.
     *
     * @var float
     */
    private $executionTime;

    /**
     * Validator name that produced this result.
     *
     * @var string
     */
    private $validatorName;

    /**
     * Constructor.
     *
     * @param string $validatorName
     */
    public function __construct($validatorName = '')
    {
        $this->success = true;
        $this->errors = array();
        $this->warnings = array();
        $this->details = array();
        $this->executionTime = 0.0;
        $this->validatorName = $validatorName;
    }

    /**
     * Get whether the validation passed.
     *
     * @return bool
     */
    public function isSuccess()
    {
        return $this->success;
    }

    /**
     * Set the success status.
     *
     * @param bool $success
     * @return void
     */
    public function setSuccess($success)
    {
        $this->success = (bool) $success;
    }

    /**
     * Get all error messages.
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Add an error message.
     *
     * @param string $error
     * @return void
     */
    public function addError($error)
    {
        $this->errors[] = $error;
        $this->success = false;
    }

    /**
     * Set all error messages.
     *
     * @param array $errors
     * @return void
     */
    public function setErrors(array $errors)
    {
        $this->errors = $errors;
        if (!empty($errors)) {
            $this->success = false;
        }
    }

    /**
     * Get all warning messages.
     *
     * @return array
     */
    public function getWarnings()
    {
        return $this->warnings;
    }

    /**
     * Add a warning message.
     *
     * @param string $warning
     * @return void
     */
    public function addWarning($warning)
    {
        $this->warnings[] = $warning;
    }

    /**
     * Set all warning messages.
     *
     * @param array $warnings
     * @return void
     */
    public function setWarnings(array $warnings)
    {
        $this->warnings = $warnings;
    }

    /**
     * Get detailed validation information.
     *
     * @return array
     */
    public function getDetails()
    {
        return $this->details;
    }

    /**
     * Add a detail entry.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function addDetail($key, $value)
    {
        $this->details[$key] = $value;
    }

    /**
     * Set all details.
     *
     * @param array $details
     * @return void
     */
    public function setDetails(array $details)
    {
        $this->details = $details;
    }

    /**
     * Get execution time in milliseconds.
     *
     * @return float
     */
    public function getExecutionTime()
    {
        return $this->executionTime;
    }

    /**
     * Set execution time in milliseconds.
     *
     * @param float $executionTime
     * @return void
     */
    public function setExecutionTime($executionTime)
    {
        $this->executionTime = (float) $executionTime;
    }

    /**
     * Get the validator name.
     *
     * @return string
     */
    public function getValidatorName()
    {
        return $this->validatorName;
    }

    /**
     * Set the validator name.
     *
     * @param string $validatorName
     * @return void
     */
    public function setValidatorName($validatorName)
    {
        $this->validatorName = $validatorName;
    }

    /**
     * Merge another validation result into this one.
     *
     * @param ValidationResult $other
     * @return void
     */
    public function merge(self $other)
    {
        if (!$other->isSuccess()) {
            $this->success = false;
        }

        $this->errors = array_merge($this->errors, $other->getErrors());
        $this->warnings = array_merge($this->warnings, $other->getWarnings());
        $this->details = array_merge($this->details, $other->getDetails());
    }

    /**
     * Merge success status, errors and warnings only (no details).
     *
     * @param ValidationResult $other
     * @return void
     */
    public function mergeStatus(self $other)
    {
        if (!$other->isSuccess()) {
            $this->success = false;
        }

        $this->errors = array_merge($this->errors, $other->getErrors());
        $this->warnings = array_merge($this->warnings, $other->getWarnings());
    }

    /**
     * Convert the result to an array.
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            'validator'      => $this->validatorName,
            'success'        => $this->success,
            'errors'         => $this->errors,
            'warnings'       => $this->warnings,
            'details'        => $this->details,
            'execution_time' => $this->executionTime,
        );
    }
}
