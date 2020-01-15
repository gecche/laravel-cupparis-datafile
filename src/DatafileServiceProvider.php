<?php namespace Cupparis\Datafile;

use Illuminate\Support\ServiceProvider;

class DatafileServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = true;


    /**
     * Booting
     */
    public function boot()
    {

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
            return new DatafileManager($this->app->events);
        });
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array('datafile');
	}

}
