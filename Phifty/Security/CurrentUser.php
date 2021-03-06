<?php
namespace Phifty\Security;
use Phifty\Session;
use Exception;
use LogicException;
use RuntimeException;
use BadMethodCallException;
use LazyRecord\BaseModel;
use Kendo\IdentifierProvider\ActorIdentifierProvider;
use Kendo\IdentifierProvider\RoleIdentifierProvider;
use Kendo\IdentifierProvider\RecordIdentifierProvider;

/**
 * CurrentUserRole interface, for getting roles from model.
 */
use Phifty\Security\CurrentUserRole;

/**
 * @package Phifty
 *
 * Phifty CurrentUser object
 *
 * managing current user data stash, you can
 * define your custom user model and your custom current user class
 * to customize this.
 *
 * This class is mixined with current user model class.
 *
 * TODO: support login from cookie
 *
 *   $currentUser = new CurrentUser;  // load current user from session data
 *
 *   $currentUser = new CurrentUser(array(
 *       'model_class' => 'UserBundle\Model\User',
 *   ));
*/
class CurrentUser implements RoleIdentifierProvider, ActorIdentifierProvider, RecordIdentifierProvider
{
    /**
     * @var BaseModel User model class
     */
    protected $userModelClass;

    /**
     * @var mixed User model record
     */
    protected $record; // user model record

    /**
     * @var string model primary key
     */
    public $primaryKey = 'id';

    /**
     * @var string session prefix string
     */
    public $sessionPrefix = '__user_';

    /**
     * @var Phifty\Session Session Manager
     */
    protected $session;

    protected $context = [];

    public function __construct($args = array() )
    {
        $record = null;
        if (is_object($args)) {
            $record = $args;
        } else {
            if (isset($args['record']) ) {
                $record = $args['record'];
                $this->userModelClass = get_class($record);
            } else {
                $this->userModelClass =
                    isset($args['model_class'])
                        ? $args['model_class']
                        : kernel()->config->get( 'framework', 'CurrentUser.Model' )
                            ?: 'UserBundle\Model\User';  // default user model (UserBundle\Model\User)
            }

            if ( isset($args['session_prefix']) ) {
                $this->sessionPrefix = $args['session_prefix'];
            }
            if ( isset($args['primary_key']) ) {
                $this->primaryKey = $args['primary_key'];
            }
        }

        /**
         * Initialize a session pool with prefix 'user_'
         */
        $this->session = new Session( $this->sessionPrefix );

        /* if record is specified, update session from record */
        if ($record) {
            if ( ! $this->setRecord( $record ) ) {
                throw new Exception('CurrentUser can not be loaded from record.');
            }
        } else {
            // load record from session,
            // get current user record id, and find record from it.
            //
            // TODO: use virtual loading, do not manipulate database if we have
            // data in session already.
            //
            // TODO: provide a verify option to verify database item before
            // loading.
            if ( $userId = $this->session->get( $this->primaryKey ) ) {
                $class = $this->userModelClass;
                $virtualRecord = new $class;
                foreach ( $virtualRecord->getColumnNames() as $name ) {
                    $virtualRecord->$name = $this->session->get($name);
                }
                $this->record = $virtualRecord;
                // $this->setRecord(new $this->userModelClass(array( $this->primaryKey => $userId )));
            }
        }
    }

    /**
     * Set user model class
     *
     * @param string $class user model class
     */
    public function setModelClass($class)
    {
        $this->userModelClass = $class;
    }


    public function getSession()
    {
        return $this->session;
    }

    /**
     * Get user model class.
     */
    public function getModelClass()
    {
        return $this->userModelClass;
    }

    /**
     * Reload record and update session
     */
    public function updateSession()
    {
        if ($this->record) {
            $this->record->reload();
            $this->updateSessionFromRecord($this->record);
        }
    }


    public function loginAs(BaseModel $anotherUser)
    {
        if ($this->record) {
            // Save the current user record into the context
            $userId = $this->record->get($this->primaryKey);
            $this->context[] = $userId;
        }
        $this->setRecord($anotherUser);
    }

    /**
     * Update session data from record
     *
     * @param mixed User record object
     */
    protected function updateSessionFromRecord(BaseModel $record)
    {
        // get column maes to register 
        foreach ($record->getColumnNames() as $name) {
            $val = $record->$name;
            $this->session->set( $name, is_object($val) ? $val->__toString() : $val );
        }
        if ($record instanceof CurrentUserRole) {
            $this->session->set('roles', $record->getRoles() );
        } else if ( method_exists($record,'getRoles') ) {
            $this->session->set('roles', $record->getRoles() );
        } else if (isset($record->role)) {
            if ($record->role instanceof BaseModel) {
                $this->session->set('roles', array($record->role->identity) );
            } else {
                $this->session->set('roles', array($record->role) );
            }
        } else {
            $this->session->set('roles', array() );
        }
    }

    /**
     * Set the user record as current user.
     *
     * This method is used for logging in or changing current user.
     *
     * @param mixed User record object
     *
     * @return bool
     */
    public function setRecord(BaseModel $record)
    {
        if ($record && $record->id) {
            $this->record = $record;
            $this->updateSessionFromRecord($record);
            return true;
        }
        return false;
    }

    public function getRecord()
    {
        if ($this->record && $this->record->id) {
            return $this->record;
        }
    }

    /**
     * Integrate setter with model record object
     */
    public function __set( $key , $value )
    {
        if ($this->record) {
            $this->record->update(array($key => $value));
            $this->session->set($key, $value);
        }
    }

    public function __isset($key)
    {
        return $this->session->has($key)
             || ($this->record && $this->record->__isset($key));
    }

    /**
     * Mixin getter with model record object
     *
     * @param  string $key session key
     * @return mixed
     */
    public function __get( $key )
    {
        if ($val = $this->session->get($key)) {
            return $val;
        }
        if ($this->record && isset($this->record->$key) ) {
            return $this->record->$key;
        }
        // throw new Exception('CurrentUser Record is undefined.');
    }

    /**
     * Mixin with user record object.
     */
    public function __call($method,$args)
    {
        if ($this->record) {
            if ( method_exists($this->record,$method) ) {
                return call_user_func_array(array($this->record,$method), $args);
            } else {
                throw new BadMethodCallException("Record $method not found.");
            }
        }
    }



    /**
     * Returns role identities
     *
     * @return string[] returns role identities
     */
    public function getRoles()
    {
        if ( $roles = $this->session->get('roles') ) {
            return $roles;
        }
        if ($this->record && $this->record->id) {
            if ($this->record instanceof CurrentUserRole) {
                return $this->record->getRoles();
            } elseif ( method_exists($this->record,'getRoles') ) {
                return $this->record->getRoles();
            }
        }

        return array();
    }

    /**
     * Check if a role exists.
     *
     * @param  string  $roleId
     * @return boolean
     */
    public function hasRole($roleId)
    {
        if ( $roles = $this->session->get('roles') ) {
            if ( is_object($roleId) )

                return in_array($roleId->__toString(), $roles );
            return in_array($roleId , $roles);
        }
        if ($this->record && $this->record->id) {
            return $this->record->hasRole($roleId);
        }
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id; // call __get
    }

    public function logout()
    {
        $this->session->clear();
    }

    /*******************
     * Helper functions
     *******************/

    // XXX: should be integrated with ACL
    /**
     * deprecated API, use hasLoggedIn instead.
     *
     * @deprecated 
     */
    public function isLogged()
    {
        // deprecated
        return $this->getId();
    }


    /**
     * Check if an user has logged in.
     *
     * @return integer user id
     */
    public function hasLoggedIn()
    {
        return $this->getId();
    }

    /**
     * @return string
     */
    public function getRoleIdentifier()
    {
        $roles = $this->getRoles();
        if (count($roles) == 1) {
            return $roles[0];
        } else if (count($roles) > 1) {
            throw new RuntimeException('Multi-role is not supported with Kendo ACL');
        }
        return null;
    }

    /**
     * Current user is always 'user' actor since users are human beings.
     *
     * @return string 'user'
     */
    public function getActorIdentifier()
    {
        return 'user';
    }


    public function getRecordIdentifier()
    {
        return $this->getId();
    }



}
