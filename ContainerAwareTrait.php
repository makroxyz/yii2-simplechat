<?php

namespace bubasuma\simplechat;

use bubasuma\simplechat\helpers\ClassMapHelper;
use Yii;
use yii\base\InvalidConfigException;
use yii\di\Container;

/**
 * Description of ContainerAwareTrait
 *
 * @author marco
 */
trait ContainerAwareTrait
{
    /**
     * @return Container
     */
    public function getDi()
    {
        return Yii::$container;
    }
    /**
     * Gets a class from the container.
     *
     * @param string $class  he class name or an alias name (e.g. `foo`) that was previously registered via [[set()]]
     *                       or [[setSingleton()]]
     * @param array  $params constructor parameters
     * @param array  $config attributes
     *
     * @throws InvalidConfigException
     * @return object
     */
    public function make($class, $params = [], $config = [])
    {
        return $this->getDi()->get($class, $params, $config);
    }
    /**
     * @throws InvalidConfigException
     * @return \Da\User\Helper\AuthHelper|object
     *
     */
//    public function getAuth()
//    {
//        return $this->getDi()->get(AuthHelper::class);
//    }
    /**
     * @throws InvalidConfigException
     * @return \Da\User\Helper\ClassMapHelper|object
     *
     */
    public function getClassMap()
    {
        return $this->getDi()->get(ClassMapHelper::class);
    }
}