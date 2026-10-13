<?php

namespace Vendor\Validator\Contracts;

use Vendor\Validator\DTO\ValidationResult;

interface ValidatorInterface
{
    /**
     * Execute the validation and return the result.
     *
     * @param array $options
     * @return ValidationResult
     */
    public function validate(array $options = array());

    /**
     * Get the validator name identifier.
     *
     * @return string
     */
    public function getName();
}
