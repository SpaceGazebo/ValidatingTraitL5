<?php namespace Watson\Validating;

use \Illuminate\Database\Eloquent\Model;
use \Illuminate\Support\Facades\Event;
use \Watson\Validating\ValidationException;
/**
 * we do not want to return the $validationResult
 * because false would cancel the remaining observers
 * and advanced validation error messages could get lost
 * this is cleaner than rewriting Laravel's
 * event-observer-priority-magic
 * 
 *     return false; // will still cancel the save,
 * but please use something like
 *     $model->getErrors()->add('is_published','Could not publish because not enough dragons!')
 * so that the user will not be lost
 */
class ValidatingObserver {

    /**
     * Register the validation event for saving the model. Saving validation
     * should only occur if creating and updating validation does not.
     *
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @return boolean
     */
    public function saving(Model $model)
    {
        $event = __FUNCTION__;
        $extraRules = $model->getValidatableStates();
        if ($model->processing)
        {
            $extraRules[] = $model->processing;
            $event = $model->processing;
        }
        
        // If the model has validating enabled, perform it.
        if ($model->getValidating())
        {
            // Fire the namespaced validating event and prevent validation
            // if it returns a value.
            if ($this->fireValidatingEvent($model, $event) !== null) return;

            $validationResult = $model->performValidation($model->getRules($extraRules,'errors',/*$onlyRequested*/false));
            $validationWarningsResult = $model->performWarningsValidation($model->getRules($extraRules,'warnings',/*$onlyRequested*/false));
            if ($validationResult === false)
            {
                // Fire the validating failed event.
                $this->fireValidatedEvent($model, 'failed');

                return;
            }
            // Fire the validating.passed event.
            $this->fireValidatedEvent($model, 'passed');
        }
        else
        {
            $this->fireValidatedEvent($model, 'skipped');
        }
    }

    /**
     * Register the validation event for restoring the model.
     *
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @return boolean
     */
    public function restoring(Model $model)
    {
        $event = __FUNCTION__;
        // If the model has validating enabled, perform it.
        if ($model->getValidating())
        {
            // Fire the namespaced validating event and prevent validation
            // if it returns a value.
            if ($this->fireValidatingEvent($model, $event) !== null) return;

            $validationResult = $model->performValidation($model, $model->getRules('restoring','errors',/*$onlyRequested*/true));
            if ($validationResult === false)
            {
                // Fire the validating failed event.
                $this->fireValidatedEvent($model, 'failed');

                return;
            }
            // Fire the validating.passed event.
            $this->fireValidatedEvent($model, 'passed');
        }
        else
        {
            $this->fireValidatedEvent($model, 'skipped');
        }
    }
    
    /**
     * Register the validation event for deleting the model.
     *
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @return boolean
     */
    public function deleting(Model $model)
    {
        $event = __FUNCTION__;
        // If the model has validating enabled, perform it.
        if ($model->getValidating())
        {
            // Fire the namespaced validating event and prevent validation
            // if it returns a value.
            if ($this->fireValidatingEvent($model, $event) !== null) return;

            $validationResult = $model->performValidation($model, $model->getRules('deleting','errors',/*$onlyRequested*/true));
            if ($validationResult === false)
            {
                // Fire the validating failed event.
                $this->fireValidatedEvent($model, 'failed');

                return;
            }
            // Fire the validating.passed event.
            $this->fireValidatedEvent($model, 'passed');
        }
        else
        {
            $this->fireValidatedEvent($model, 'skipped');
        }
    }

    /**
     * Fire the namespaced validating event.
     *
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @param  string $event
     * @return mixed
     */
    protected function fireValidatingEvent(Model $model, $event)
    {
        return Event::until("eloquent.validating: ".get_class($model), [$model, $event]);
    }

    /**
     * Fire the namespaced post-validation event.
     *
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @param  string $status
     * @return void
     */
    protected function fireValidatedEvent(Model $model, $status)
    {
        Event::fire("eloquent.validated: ".get_class($model), [$model, $status]);
    }

}
