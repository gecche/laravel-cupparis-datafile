<?php namespace Gecche\Cupparis\Datafile;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class DatafileServiceProvider extends ServiceProvider {


    /**
     * Booting
     */
    public function boot()
    {

        $this->publishes([
            __DIR__.'/config/cupparis-datafile.php' => config_path('cupparis-datafile.php'),
        ]);

        $this->addValidationExtensions();

    }

	/**
	 * Register the commands
	 *
	 * @return void
	 */
	public function register()
	{
        $this->app->singleton('datafile', function($app)
        {
            return new DatafileManager($app->events);
        });
	}


	protected function addValidationExtensions() {

        /*
         * Stessa identica regola di unique ma per distinguerle
         */
        Validator::extend('unique_datafile', function ($attribute, $value, $parameters, $validator) {
            try {
                return $validator->validateUnique($attribute, $value, $parameters);
            } catch (InvalidArgumentException $e) {
                if ($e->getMessage() == "Validation rule unique requires at least 1 parameters.") {
                    throw new InvalidArgumentException("Validation rule unique_datafile requires at least 1 parameters.");
                }
            }
        });


        Validator::extend('exists_datafile', function ($attribute, $value, $parameters, $validator) {
            //Parametri minimi 4
            //Table for csv, Field csv, extra for csv, optionally "NULL", table for exists
            //requireParameterCount è protected
            if (count($parameters) < 4) {
                throw new InvalidArgumentException("Validation rule exists_datafile requires at least 4 parameters.");
            }

            $exists = false;

            $csvTable = $parameters[0];
            $csvColumn = $parameters[1];

            $extra = ($parameters[2] === 'null') ? array() : explode('#',$parameters[2]);

            $existsParameters = array_merge(array($csvTable,$csvColumn),$extra);
            $exists = $exists ||  $validator->validateExists($attribute, $value, $existsParameters);
            if ($exists) {
                return true;
            }

            $parameters = array_slice($parameters,3);

            $exists = $exists ||  $validator->validateExists($attribute, $value, $parameters);

            return $exists;
        });

	}


}
