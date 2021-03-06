<?php
namespace Phifty\ServiceProvider;
use GearmanClient;
use GearmanWorker;
use ConfigKit\Accessor;
use Universal\Container\ObjectContainer;
use Exception;
use Facebook\FacebookSession;

/*
  Facebook4ServiceProvider:
    AppId: {appId}
    AppSecret: {app secret}
*/

class Facebook4ServiceProvider extends BaseServiceProvider implements ServiceOptionValidator
{
    public function getId() { return 'Facebook'; }

    public function register($kernel, $options = array())
    {
        FacebookSession::setDefaultApplication($options['AppId'], $options['AppSecret']);
        $kernel->facebookSession = function() use ($options) {
            return FacebookSession::newAppSession();
        };
    }

    public function validateOptions($options = array()) {
        if ( ! isset($options['AppId']) || ! isset($options['AppSecret']) ) {
            throw new Exception('AppId or AppSecret is not defined.');
        }
    }
}
