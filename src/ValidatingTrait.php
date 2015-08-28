<?php namespace Watson\Validating;

use \Illuminate\Support\MessageBag;
use \Illuminate\Support\Facades\Input;
use \Illuminate\Support\Facades\Validator;
use \Illuminate\Validation\Factory;

trait ValidatingTrait {

    /**
     * Error messages as provided by the validator.
     *
     * @var \Illuminate\Support\MessageBag
     */
    protected $validationErrors;

    /**
     * Warning messages as provided by the validator.
     *
     * @var \Illuminate\Support\MessageBag
     */
    protected $validationWarnings;

    /**
     * Whether the model should undergo validation
     * when saving or not.
     *
     * @var bool
     */
    protected $validating = true;

    /**
     * The Validator factory class used for validation.
     *
     * @return \Illuminate\Validation\Factory
     */
    protected $validator;

    /**
     * Boot the trait. Adds an observer class for validating.
     *
     * @return void
     */
    public static function bootValidatingTrait()
    {
        static::observe(new ValidatingObserver);
    }

    /**
     * Returns whether or not the model will attempt to validate
     * itself when saving.
     *
     * @return bool
     */
    public function getValidating()
    {
        return $this->validating;
    }

     /**
     * Set whether the model should attempt validation on saving.
     *
     * @param  bool $value
     * @return void
     */
    public function setValidating($value)
    {
        $this->validating = (boolean) $value;
    }

    /**
     * Returns whether the model will raise an exception or
     * return a boolean when validating.
     *
     * @return bool
     */
    public function getThrowValidationExceptions()
    {
        return isset($this->throwValidationExceptions) ? $this->throwValidationExceptions : false;
    }

    /**
     * Set whether the model should raise an exception or
     * return a boolean on a failed validation.
     *
     * @param  bool $value
     * @return void
     * @throws InvalidArgumentException
     */
    public function setThrowValidationExceptions($value)
    {
        $this->throwValidationExceptions = (boolean) $value;
    }

    /**
     * Returns whether or not the model will add it's unique
     * identifier to the rules when validating.
     *
     * @return bool
     */
    public function getInjectUniqueIdentifier()
    {
        return isset($this->injectUniqueIdentifier) ? $this->injectUniqueIdentifier : true;
    }

    /**
     * Set the model to add unique identifier to rules when performing
     * validation.
     *
     * @param  bool $value
     * @return void
     * @throws InvalidArgumentException
     */
    public function setInjectUniqueIdentifier($value)
    {
        $this->injectUniqueIdentifier = (boolean) $value;
    }

    /**
     * Get the model.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function getModel()
    {
        return $this;
    }

    /**
     * Get the custom validation messages being used by the model.
     *
     * @return array
     */
    public function getValidationMessages()
    {
        return isset($this->validationMessages) ? $this->validationMessages : [];
    }

    /**
     * Get the validating attribute names.
     *
     * @return mixed
     */
    public function getValidationAttributeNames()
    {
        return isset($this->validationAttributeNames) ? $this->validationAttributeNames : null;
    }

    /**
     * Set the validating attribute names.
     *
     * @param  array  $attributeNames
     * @return mixed
     */
    public function setValidationAttributeNames(array $attributeNames = null)
    {
        $this->validationAttributeNames = $attributeNames;
    }
    
    /**
     * In OOP, objects are not suppose to only represent the data that they
     * hold, they are suppose to represent the actions they could perform
     * 
     * each class should extend like this so that their actions can require
     * validation. Examples would be sendEmailAgain, publish, delete, etc.
     * 
     *     $ruleKeys = ValidatingTrait::getValidatableStates();
     */
    public function getValidatableStates()
    {
        $ruleKeys = ['saving','save'];
        if ($this->getModel()->getKey())
        {
            $ruleKeys[] = 'updating';
            $ruleKeys[] = 'update';
        }
        else
        {
            $ruleKeys[] = 'creating';
            $ruleKeys[] = 'create';
        }
        return $ruleKeys;
    }

    /**
     * Get the global validation rules.
     *
     * @return array
     */
    public function getRules(array $ruleKeys=array(),$type='errors',$onlyRequested=false)
    {
        $rules = $this->rules;
        $outputRules = $this->rules;
        
        $ruleKeys = $onlyRequested ? [] : $this->getModel()->getValidatableStates();
        
        $ruleSets = array_map(function($ruleSet)use($rules,$type)
        {
            if (is_array($ruleSet))
            {
                return $ruleSet;
            }
            if (is_string($ruleSet))
            {
                return array_get($rules,$type.'.'.$ruleSet,[]);
            }
            throw new \Exception ('Cant make validation rules from '.gettype($ruleSet).', expecting string or array');
        },$ruleKeys);
        
        $mergedRules = call_user_func_array('array_merge_recursive',$ruleSets);
        
        foreach ($mergedRules as $field => $rules)
        {
            if (is_array($rules))
            {
                $outputRules[$field] = implode("|", $rules);
            }
            else
            {
                $outputRules[$field] = $rules;
            }
        }
        
        return $outputRules;
    }

    /**
     * Handy method for using the static call Model::rules(). Protected access
     * only to allow __callStatic to get to it.
     *
     * @return array
     */
    protected function rules()
    {
        return $this->getRules();
    }

    /**
     * Set the global validation rules.
     *
     * @param  array $rules
     * @return void
     */
    public function setRules(array $rules = null)
    {
        $this->rules = $rules;
    }

    /**
     * Get the validation error messages from the model.
     *
     * @return \Illuminate\Support\MessageBag
     */
    public function getErrors()
    {
        if (!$this->validationErrors)
        {
            $this->validationErrors = new MessageBag;
        }

        return $this->validationErrors;
    }
    public function getWarnings()
    {
        if (!$this->validationWarnings)
        {
            $this->validationWarnings = new MessageBag;
        }

        return $this->validationWarnings;
    }
    /**
     * Set the error messages.
     *
     * @param  \Illuminate\Support\MessageBag $validationErrors
     * @return void
     */
    public function setErrors(MessageBag $validationErrors)
    {
        $this->validationErrors = $validationErrors;
    }

    /**
     * Returns whether the model is valid or not.
     *
     * @return bool
     */
    public function isValid()
    {
        $rules = $this->getRules();

        return $this->performValidation($rules);
    }

    /**
     * Returns if the model is valid, otherwise throws an exception.
     *
     * @return bool
     * @throws \Watson\Validating\ValidationException
     */
    public function isValidOrFail()
    {
        if ( ! $this->isValid())
        {
            $this->throwValidationException();
        }

        return true;
    }

    /**
     * Returns whether the model is invalid or not.
     *
     * @return bool
     */
    public function isInvalid()
    {
        return ! $this->isValid();
    }

    /**
     * Force the model to be saved without undergoing validation.
     *
     * @return bool
     */
    public function forceSave()
    {
        $currentValidatingSetting = $this->getValidating();

        $this->setValidating(false);

        $result = $this->getModel()->save();

        $this->setValidating($currentValidatingSetting);

        return $result;
    }

    /**
     * Perform a one-off save that will raise an exception on validation error
     * instead of returning a boolean (which is the default behaviour).
     *
     * @return void
     * @throws \Watson\Validating\ValidatingException
     */
    public function saveOrFail()
    {
        if ( ! $this->getModel()->save())
        {
            $this->throwValidationException();
        }
    }

    /**
     * Perform a one-off save that will return a boolean on
     * validation error instead of raising an exception.
     *
     * @return bool
     */
    public function saveOrReturn()
    {
        return $this->getModel()->save();
    }

    /**
     * Get the Validator instance
     *
     * @return \Illuminate\Validation\Factory
     */
    public function getValidator()
    {
        return $this->validator ?: Validator::getFacadeRoot();
    }

    /**
     * Set the Validator instance
     *
     * @param \Illuminate\Validation\Factory $validator
     */
    public function setValidator(Factory $validator)
    {
        $this->validator = $validator;
    }

    /**
     * Make a Validator instance for a given ruleset.
     *
     * @param  array $rules
     * @return \Illuminate\Validation\Factory
     */
    protected function makeValidator($rules = [])
    {
        // Get the model attributes.
        $attributes = $this->getModel()->getAttributes();

        if ($this->getInjectUniqueIdentifier())
        {
            $rules = $this->injectUniqueIdentifierToRules($rules);
        }

        $messages = $this->getValidationMessages();

        $validator = $this->getValidator()->make($attributes, $rules, $messages);

        if ($this->getValidationAttributeNames())
        {
            $validator->setAttributeNames($this->getValidationAttributeNames());
        }

        return $validator;
    }

    /**
     * Validate the model against it's rules, returning whether
     * or not it passes and setting the error messages on the
     * model if required.
     *
     * @param  array $rules refer to validation subindexes
     * @return bool
     * @throws \Watson\Validating\ValidationException
     */
    public function performValidation($rules = [])
    {
        $validation = $this->makeValidator($rules);

        $result = $validation->passes();

        $this->getErrors()->merge($validation->messages());

        return $result;
    }
    
    /**
     * made warnings copy
     * 
     */
    public function performWarningsValidation($rules = [])
    {
        $validation = $this->makeValidator($rules);

        $result = $validation->passes();

        $this->setWarnings($validation->messages());

        return $result;
    }
    
    /**
     * 
     * @param array $addStates if we are testing for a state change, pass the new state here
     */
    public function getValidationRuleKeysFromState(array $addStates = array())
    {
        /*
         *  example usage
        if (!$this->getModel()->getKey())
        {
            $addStates[] = 'creating';
        }
        else
        {
            $addStates[] = 'updating';
        }
        */
        
        return $addStates;
    }

    /**
     * Throw a validation exception.
     *
     * @throws \Watson\Validating\ValidationException
     */
    public function throwValidationException()
    {
        $exception = new ValidationException(get_class($this) . ' model could not be persisted as it failed validation.');

        $exception->setModel($this);
        $exception->setErrors($this->getErrors());

        throw $exception;
    }

    /**
     * Update the unique rules of the global rules to
     * include the model identifier.
     *
     * @return void
     */
    public function updateRulesUniques()
    {
        $rules = $this->getRules();

        $this->setRules($this->injectUniqueIdentifierToRules($rules));
    }

    /**
     * If the model already exists and it has unique validations
     * it is going to fail validation unless we also pass it's
     * primary key to the rule so that it may be ignored.
     *
     * This will go through all the rules and append the model's
     * primary key to the unique rules so that the validation
     * will work as expected.
     *
     * @param  array $rules
     * @return array
     */
    protected function injectUniqueIdentifierToRules(array $rules)
    {
        foreach ($rules as $field => &$ruleset)
        {
            // If the ruleset is a pipe-delimited string, convert it to an array.
            $ruleset = is_string($ruleset) ? explode('|', $ruleset) : $ruleset;

            foreach ($ruleset as &$rule)
            {
                if (starts_with($rule, 'unique:'))
                {
                    $rule = $this->prepareUniqueRule($rule, $field);
                }
            }
        }

        return $rules;
    }

    /**
     * Take a unique rule, add the database table, column and
     * model identifier if required.
     *
     * @param  string $rule
     * @param  string $field
     * @return string
     */
    protected function prepareUniqueRule($rule, $field)
    {
        $parameters = array_filter(explode(',', substr($rule, 7)));

        // If the table name isn't set, get it.
        if ( ! isset($parameters[0]))
        {
            $parameters[0] = $this->getModel()->getTable();
        }

        // If the field name isn't set, infer it.
        if ( ! isset($parameters[1]))
        {
            $parameters[1] = $field;
        }

        if($this->exists)
        {
            // If the identifier isn't set, add it.
            if ( ! isset($parameters[2]) || strtolower($parameters[2]) === 'null')
            {
                $parameters[2] = $this->getModel()->getKey();
            }

            // Add the primary key if it isn't set in case it isn't id.
            if ( ! isset($parameters[3]))
            {
                $parameters[3] = $this->getModel()->getKeyName();
            }
        }

        return 'unique:' . implode(',', $parameters);
    }

}
