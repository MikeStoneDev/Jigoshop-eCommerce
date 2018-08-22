<?php

namespace Jigoshop\Extensions;

use Jigoshop\Api\Routing\ControllerInterface;
use Jigoshop\Container\Configurations\Configuration;
use Jigoshop\Container\Configurations\ConfigurationInterface;
use Jigoshop\Exception;
use Jigoshop\Extensions;

/**
 * Class Extension
 * @package Jigoshop\Extensions;
 * @author Krzysztof Kasowski
 */
abstract class Extension
{
    /** @var  Extension  */
    private static $instance;
    /** @var  Extensions\Extension\Plugin  */
    private $plugin;
    /** @var  \ReflectionClass */
    private $reflection;
    /** @var Extensions\Extension\Render */
    private $render;

    /**
     * Extension constructor.
     *
     * @throws \ReflectionException
     *
     * @param string $fileName
     */
    public function __construct($fileName)
    {
        $this->plugin = new Extensions\Extension\Plugin($fileName);
        $this->reflection = new \ReflectionClass($this);
        if($this->plugin->getTemplateDir()) {
            $this->render = new Extensions\Extension\Render($this->plugin->getTemplateDir());
        }
    }

    /**
     * @param Extension $extension
     */
    public static function setInstance(Extension $extension)
    {
        self::$instance = $extension;
    }

    /**
     * @return Extension
     */
    public static function getInstance()
    {
        return self::$instance;
    }

    /**
     * @throws Exception
     *
     * @return Extensions\Extension\Render
     */
    public static function getRender()
    {
        if(!self::getInstance()->_getRender() instanceof Extensions\Extension\Render) {
            throw new Exception(__('Template dir was not specified', 'jigoshop-ecommerce'));
        }

        return self::getInstance()->_getRender();
    }

    /**
     * @return Extension\Plugin
     */
    public function getPlugin()
    {
        return $this->plugin;
    }

    /**
     * @return Extension\Render
     */
    public function _getRender()
    {
        return $this->render;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return dirname($this->reflection->getFileName()).'/'.$this->reflection->getShortName();
    }

    /**
     * @return string
     */
    public function getNamespace()
    {
        return $this->reflection->getName();
    }

    /**
     * @return ConfigurationInterface
     */
    public function getConfiguration()
    {
        $configuration = '\\'.$this->getNamespace().'\\Configuration';
        if(class_exists($configuration)) {
            return new $configuration();
        }

        return null;
    }

    /**
     * @return InstallerInterface
     */
    public function getInstaller()
    {
        $installer = '\\'.$this->getNamespace().'\\Installer';
        if(class_exists($installer)) {
            return new $installer();
        }

        return null;
    }


    /**
     * @return ControllerInterface
     */
    public function getApiController()
    {
        $apiController = '\\'.$this->getNamespace().'\\ApiController';
        if(class_exists($apiController)) {
            return new $apiController();
        }

        return null;
    }
}