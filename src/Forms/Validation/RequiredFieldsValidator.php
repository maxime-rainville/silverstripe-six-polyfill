<?php

/**
 * CMS 6 Polyfill for SilverStripe\Forms\RequiredFields
 * 
 * This class provides forward compatibility by making the CMS 6 namespace
 * available in CMS 5, allowing you to migrate your code early.
 * 
 * @package silverstripe-six-polyfill
 */

namespace SilverStripe\Forms\Validation\Validation;

use SilverStripe\Core\ArrayLib;
use SilverStripe\Dev\Deprecation;
/**
 * Required Fields allows you to set which fields need to be present before
 * submitting the form. Submit an array of arguments or each field as a separate
 * argument.
 *
 * Validation is performed on a field by field basis through
 * {@link FormField::validate}.
 *
 */
class RequiredFieldsValidator extends Validator
{
    /**
     * Whether to globally allow whitespace only as a valid value for a required field
     * Can be overridden on a per-instance basis
     */
    private static bool $allow_whitespace_only = true;
    /**
     * List of required fields
     *
     * @var array
     */
    protected $required;
    /**
     * Whether to allow whitespace only as a valid value for a required field for this instance
     * By default, this is set to null which will revert to the global default
     */
    private ?bool $allowWhitespaceOnly = null;
    /**
     * Pass each field to be validated as a separate argument to the constructor
     * of this object. (an array of elements are ok).
     */
    public function __construct()
    {
        $required = func_get_args();
        if (isset($required[0]) && is_array($required[0])) {
            $required = $required[0];
        }
        if (!empty($required)) {
            $this->required = ArrayLib::valuekey($required);
        } else {
            $this->required = [];
        }
        parent::__construct();
    }
    /**
     * Get whether to allow whitespace only as a valid value for a required field
     */
    public function getAllowWhitespaceOnly() : ?bool
    {
        return $this->allowWhitespaceOnly ?? static::config()->get('allow_whitespace_only');
    }
    /**
     * Set whether to allow whitespace only as a valid value for a required field
     */
    public function setAllowWhitespaceOnly(?bool $allow)
    {
        $this->allowWhitespaceOnly = $allow;
    }
    /**
     * Clears all the validation from this object.
     *
     * @return $this
     */
    public function removeValidation()
    {
        parent::removeValidation();
        $this->required = [];
        return $this;
    }
    /**
     * Debug helper
     * @return string
     */
    public function debug()
    {
        if (!is_array($this->required)) {
            return false;
        }
        $result = "<ul>";
        foreach ($this->required as $name) {
            $result .= "<li>{$name}</li>";
        }
        $result .= "</ul>";
        return $result;
    }
    /**
     * Allows validation of fields via specification of a php function for
     * validation which is executed after the form is submitted.
     *
     * @param array $data
     *
     * @return boolean
     */
    public function php($data)
    {
        $valid = true;
        $fields = $this->form->Fields();
        foreach ($fields as $field) {
            $valid = $field->validate($this) && $valid;
        }
        if (!$this->required) {
            return $valid;
        }
        foreach ($this->required as $fieldName) {
            if (!$fieldName) {
                continue;
            }
            if ($fieldName instanceof FormField) {
                $formField = $fieldName;
                $fieldName = $fieldName->getName();
            } else {
                $formField = $fields->dataFieldByName($fieldName);
            }
            // submitted data for file upload fields come back as an array
            $value = $data[$fieldName] ?? null;
            if (is_array($value)) {
                if ($formField instanceof FileField && isset($value['error']) && $value['error']) {
                    $error = true;
                } else {
                    if (is_a($formField, HasOneRelationFieldInterface::class) && isset($value['value'])) {
                        $stringValue = (string) $value['value'];
                        $error = in_array($stringValue, ['0', '']);
                    } else {
                        $error = count($value ?? []) ? false : true;
                    }
                }
            } else {
                $stringValue = (string) $value;
                if (!$this->getAllowWhitespaceOnly()) {
                    $stringValue = preg_replace('/^\\s+/u', '', $stringValue);
                    $stringValue = preg_replace('/\\s+$/u', '', (string) $stringValue);
                }
                if (is_a($formField, HasOneRelationFieldInterface::class)) {
                    // test for blank string as well as '0' because older versions of silverstripe/admin FormBuilder
                    // forms created using redux-form would have a value of null for unsaved records
                    // the null value will have been converted to '' by the time it gets to this point
                    $error = in_array($stringValue, ['0', '']);
                } else {
                    $error = strlen((string) $stringValue) > 0 ? false : true;
                }
            }
            if ($formField && $error) {
                $errorMessage = _t('SilverStripe\\Forms\\Form.FIELDISREQUIRED', '{name} is required', ['name' => strip_tags('"' . ($formField->Title() ?: $fieldName) . '"')]);
                if ($msg = $formField->getCustomValidationMessage()) {
                    $errorMessage = $msg;
                }
                $this->validationError($fieldName, $errorMessage, "required");
                $valid = false;
            }
        }
        return $valid;
    }
    /**
     * Adds a single required field to required fields stack.
     *
     * @param string $field
     *
     * @return $this
     */
    public function addRequiredField($field)
    {
        $this->required[$field] = $field;
        return $this;
    }
    /**
     * Removes a required field
     *
     * @param string $field
     *
     * @return $this
     */
    public function removeRequiredField($field)
    {
        unset($this->required[$field]);
        return $this;
    }
    /**
     * Add {@link RequiredField} objects together
     *
     * @param RequiredFields $requiredFields
     * @return $this
     */
    public function appendRequiredFields($requiredFields)
    {
        $this->required = $this->required + ArrayLib::valuekey($requiredFields->getRequired());
        return $this;
    }
    /**
     * Returns true if the named field is "required".
     *
     * Used by {@link FormField} to return a value for FormField::Required(),
     * to do things like show *s on the form template.
     *
     * @param string $fieldName
     *
     * @return boolean
     */
    public function fieldIsRequired($fieldName)
    {
        return isset($this->required[$fieldName]);
    }
    /**
     * Return the required fields
     *
     * @return array
     */
    public function getRequired()
    {
        return array_values($this->required ?? []);
    }
    /**
     * @return bool
     */
    public function canBeCached() : bool
    {
        return count($this->getRequired() ?? []) === 0;
    }
}