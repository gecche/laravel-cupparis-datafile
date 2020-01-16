<?php namespace Gecche\Cupparis\Datafile;

use Illuminate\Support\ServiceProvider;

class DatafileServiceProvider extends ServiceProvider {


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
            return new DatafileManager($app->events);
        });
	}


}
