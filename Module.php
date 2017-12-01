<?php
/**
 * @link https://github.com/bubasuma/yii2-simplechat
 * @copyright Copyright (c) 2015 bubasuma
 * @license http://opensource.org/licenses/BSD-3-Clause
 */

namespace bubasuma\simplechat;

use bubasuma\simplechat\helpers\ClassMapHelper;
use Yii;
use yii\base\BootstrapInterface;
use yii\base\InvalidConfigException;
use yii\console\Application as Console;
use yii\db\Connection;
use yii\di\Instance;
use yii\web\Application as Web;

/**
 * Module extends [[\yii\base\Module]] and represents a message system that stores
 * messages in database.
 *
 * The database must contain at less the following two tables:
 *
 * ~~~
 *
 * CREATE TABLE user (
 *     id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *     .. ..
 * );
 *
 * CREATE TABLE message (
 *     id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *     sender_id BIGINT UNSIGNED NOT NULL,
 *     receiver_id BIGINT UNSIGNED NOT NULL,
 *     text VARCHAR(1020) NOT NULL
 *     is_new BOOLEAN DEFAULT TRUE,
 *     is_deleted_by_sender BOOLEAN DEFAULT FALSE,
 *     is_deleted_by_receiver BOOLEAN DEFAULT FALSE,
 *     created_at DATETIME NOT NULL,
 *     CONSTRAINT fk_message_sender_id FOREIGN KEY (id)
 *         REFERENCES user (id) ON DELETE NO ACTION ON UPDATE CASCADE,
 *     CONSTRAINT fk_message_receiver_id FOREIGN KEY (id)
 *         REFERENCES user (id) ON DELETE NO ACTION ON UPDATE CASCADE,
 * );
 * ~~~
 *
 * The `user` table stores users, and the `message` table stores messages
 *
 * @author Buba Suma <bubasuma@gmail.com>
 * @since 1.0
 */
class Module extends \yii\base\Module implements BootstrapInterface
{
    /**
     * @var Connection|array|string the DB connection object or the application component ID of the DB connection.
     */
    public $db = 'db';
    public $controllerNamespace = 'bubasuma\simplechat\controllers';
    public $classMap = [];
    
//    public $useModuleTablePrefix = true;

    /**
     * Initializes simplechat module.
     * This method will initialize the [[db]] property to make sure it refers to a valid DB connection.
     * @throws InvalidConfigException if [[db]] is invalid.
     */
    public function init()
    {
        parent::init();
        $this->db = Instance::ensure($this->db, Connection::className());
        $map = $this->buildClassMap($this->classMap);
        $this->initContainer($map);
    }

    /**
     * @inheritdoc
     */
    public function bootstrap($app)
    {
        if ($app instanceof Web) {
            $app->getUrlManager()->addRules([
                'messages/<contactId:\d+>' => $this->id . '/default/index',
                'messages' => $this->id . '/default/index',
                'login-as/<userId:\d+>' => $this->id . '/default/login-as',
                'chat/get/messages/<contactId:\d+>' => $this->id . '/default/messages',
                'chat/get/conversations' => $this->id . '/default/conversations',
                'chat/delete/message/<id:\d+>' => $this->id . '/default/delete-message',
                'chat/delete/conversation/<contactId:\d+>' => $this->id . '/default/delete-conversation',
                'chat/post/message/<contactId:\d+>' => $this->id . '/default/create-message',
                'chat/unread/conversation/<contactId:\d+>' => $this->id . '/default/mark-conversation-as-unread',
                'chat/read/conversation/<contactId:\d+>' => $this->id . '/default/mark-conversation-as-read',
            ], false);
            if (!isset($app->getView()->renderers['twig'])) {
                $app->getView()->renderers['twig'] = [
                    'class' => 'yii\twig\ViewRenderer',
                    'options' => [
                        'auto_reload' => true,
                    ],
                    'globals' => [
                        'html' => ['class' => '\yii\helpers\Html'],
                    ],
                    'uses' => ['yii\bootstrap'],
                ];
            }
        } elseif ($app instanceof Console) {
            $app->controllerMap[$this->id] = [
                'class' => 'bubasuma\simplechat\console\DefaultController',
                'module' => $this,
            ];
        }
    }
    
    /**
     * Initialize container with module classes.
     *
     * @param array $map the previously built class map list
     */
    protected function initContainer($map)
    {
        $di = Yii::$container;
        try {
            $modelClassMap = [];
            foreach ($map as $class => $definition) {
                $di->set($class, $definition);
                $model = is_array($definition) ? $definition['class'] : $definition;
                $name = (substr($class, strrpos($class, '\\') + 1));
                $modelClassMap[$class] = $model;
                
                if (in_array($name, ['Message', 'Conversation'])) {
                    $di->set(
                        "bubasuma\\simplechat\\models\\{$name}Query",
                        function () use ($model) {
                            return $model::find();
                        }
                    );
                }
//                if (in_array($name, ['Filemodel'])) {
//                    $di->set(
//                        "hal\\mongodb\\filemodels\\models\\{$name}Query",
//                        function () use ($model) {
//                            return $model::find();
//                        }
//                    );
//                }
            }
            $di->setSingleton(ClassMapHelper::class, ClassMapHelper::class, [$modelClassMap]);
//            // search classes
//            if (!$di->has(FilemodelSearch::class)) {
//                $di->set(FilemodelSearch::class, [$di->get(FilemodelQuery::class)]);
//            }
        } catch (Exception $e) {
            die($e);
        }
    }
    
    /**
     * Builds class map according to user configuration.
     *
     * @param array $userClassMap user configuration on the module
     *
     * @return array
     */
    protected function buildClassMap(array $userClassMap)
    {
        $map = [];
        $defaults = [
            // --- models
            'Message' => 'bubasuma\simplechat\models\Message',
            'Conversation' => 'bubasuma\simplechat\models\Conversation',
            'User' => 'bubasuma\simplechat\models\User',
            'UserProfile' => 'bubasuma\simplechat\models\UserProfile',
        ];
        $routes = [
            'bubasuma\simplechat\models' => [
                'Message',
                'Conversation',
                'User',
                'UserProfile'
            ],
        ];
        $mapping = array_merge($defaults, $userClassMap);
        foreach ($mapping as $name => $definition) {
            $map[$this->getRoute($routes, $name) . "\\$name"] = $definition;
        }
        return $map;
    }
    /**
     * Returns the parent class name route of a short class name.
     *
     * @param array  $routes class name routes
     * @param string $name
     *
     * @throws Exception
     * @return int|string
     *
     */
    protected function getRoute(array $routes, $name)
    {
        foreach ($routes as $route => $names) {
            if (in_array($name, $names)) {
                return $route;
            }
        }
        throw new Exception("Unknown configuration class name '{$name}'");
    }

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
//        if ($this->useModuleTablePrefix) {
//            $this->db->tablePrefix = $this->id . '_';
//        }
        return true;
    }
}