<?php

namespace Danzabar\CLI;

use Danzabar\CLI\Tasks\Task;
use Phalcon\CLI\Dispatcher;
use Danzabar\CLI\Output\Output;
use Danzabar\CLI\Input\Input;
use Danzabar\CLI\Tasks\TaskPrepper;
use Danzabar\CLI\Tasks\TaskLibrary;
use Danzabar\CLI\Tasks\Helpers;
use Danzabar\CLI\Tasks\Utility\Help;
use Danzabar\CLI\Tools\PhpFileClassReader;
use Phalcony\Stdlib\Hydrator\ClassMethods;
use Phalcon\Events\Manager as EventsManager;
use InvalidArgumentException;

/**
 * The Application class for CLI commands
 *
 * @package CLI
 * @subpackage Application
 * @author Dan Cox
 *
 * Application class rewritten to be compatible with https://github.com/ovr/phalcony about different environments and module architecture
 * @see \Phalcony\Application
 */
class Application extends \Phalcon\Application
{
    const ENV_PRODUCTION = 'production';
    const ENV_STAGING = 'staging';
    const ENV_TESTING = 'testing';
    const ENV_DEVELOPMENT = 'development';

    /**
     * @var array
     */
    protected $configuration;

    /**
     * @var string
     */
    protected $env;

    /**
     * Instance of the Helpers class
     *
     * @var Helpers
     */
    protected $helpers;

    /**
     * The raw set of arguments from the CLI
     *
     * @var Array
     */
    protected $arguments;

    /**
     * The task Prepper instance
     *
     * @var TaskPrepper
     */
    protected $prepper;

    /**
     * Instance of the task library
     *
     * @var TaskLibrary
     */
    protected $library;

    /**
     * The name of the CLI
     *
     * @var string
     */
    protected $name;

    /**
     * The version of the CLI
     *
     * @var string
     */
    protected $version;

    /**
     * @param string $env
     * @param array $configuration
     * @param \Phalcon\DiInterface $di
     * @throws \Exception
     */
    public function __construct($env, array $configuration, \Phalcon\DiInterface $di = null)
    {
        $this->env = strtolower($env);
        $this->configuration = $configuration;

        switch ($this->env) {
            case self::ENV_PRODUCTION:
            case self::ENV_STAGING:
                ini_set('display_errors', 0);
                ini_set('display_startup_errors', 0);
                error_reporting(0);
                break;
            case self::ENV_TESTING:
            case self::ENV_DEVELOPMENT:
                ini_set('display_errors', 1);
                ini_set('display_startup_errors', 1);
                error_reporting(-1);
                break;
            default:
                throw new \Exception('Wrong application $env passed: ' . $env);
        }

        if (is_null($di))
            $di = new \Phalcon\DI\FactoryDefault\CLI;

        $this->prepper = new TaskPrepper($di);
        $this->helpers = new Helpers($di);
        $this->library = new TaskLibrary;

        parent::__construct($di);
    }

    /**
     * Register di services
     *
     * @throws \Exception
     */
    public function registerServices()
    {
        $di = $this->getDI();

        if (isset($this->configuration['services'])) {
            if (!is_array($this->configuration['services'])) {
                throw new \Exception('Config[services] must be an array');
            }

            if (count($this->configuration['services']) > 0) {
                foreach ($this->configuration['services'] as $serviceName => $serviceParameters) {
                    $class = $serviceParameters['class'];

                    $shared = false;
                    $service = false;

                    if (isset($serviceParameters['shared'])) {
                        $shared = (boolean) $serviceParameters['shared'];
                    }

                    if (is_callable($class)) {
                        $shared = true;
                        $service = $class($this);
                    } else if (is_object($class)) {
                        $shared = true;
                        $service = $class;
                    } else if (isset($serviceParameters['__construct'])) {
                        $shared = true;

                        if (!is_array($serviceParameters)) {
                            throw new \Exception('Parameters for service : "' . $serviceName . '" must be array');
                        }

                        $reflector = new \ReflectionClass($class);
                        $service = $reflector->newInstanceArgs($serviceParameters['__construct']);
                    } else {
                        if ($shared) {
                            $service = new $class();
                        } else {
                            $service = $class;
                        }
                    }

                    if (isset($serviceParameters['parameters'])) {
                        if ($shared === false) {
                            throw new \Exception('Service: "' . $serviceName . '" with parameters must be shared');
                        }

                        $service = ClassMethods::hydrate($serviceParameters['parameters'], $service);

                        $di->set($serviceName, $service, $shared);
                    }

                    $di->set($serviceName, $service, $shared);
                }
            }
        }
    }

    /**
     * Register loader
     */
    protected function registerLoader()
    {
        $config = &$this->configuration;

        $loader = new \Phalcon\Loader();

        if (isset($config['application']['registerNamespaces'])) {
            $loadNamespaces = $config['application']['registerNamespaces'];
        } else {
            $loadNamespaces = array();
        }

        foreach ($config['application']['modules'] as $module => $enabled) {
            $moduleName = ucfirst($module);
            $loadNamespaces[$moduleName . '\Model'] = APPLICATION_PATH . '/modules/' . $module . '/models/';
            $loadNamespaces[$moduleName . '\Service'] = APPLICATION_PATH . '/modules/' . $module . '/services/';
            $loadNamespaces[$moduleName . '\Task'] = APPLICATION_PATH . '/modules/' . $module . '/tasks/';
        }

        if (isset($config['application']['registerDirs'])) {
            $loader->registerDirs($config['application']['registerDirs']);
        }

        $loader->registerNamespaces($loadNamespaces)
            ->register();
    }

    /**
     * @return $this
     * @throws \Exception
     */
    public function bootstrap()
    {
        $this->registerLoader();
        $this->registerModules($this->configuration['application']['modules']);

        $eventsManager = new EventsManager();
        $this->setEventsManager($eventsManager);

        $this->registerServices();
        $this->di->set('application', $this, true);

        if (!$this->di->get('dispatcher'))
            $this->di->setShared('dispatcher', new Dispatcher);

        $this->setUpDispatcherDefaults();

        // Add the output and input streams to the DI
        $this->di->setShared('output', new Output);
        $this->di->setShared('input', new Input);
        $this->di->setShared('console', $this);

        $this->registerDefaultHelpers();

        $this->di->setShared('helpers', $this->helpers);

        $this->addDefaultCommands();

        return $this;
    }

    public function setTaskLibrary(TaskLibrary $library)
    {
        $this->library = $library;
        return $this;
    }

    /**
     * Adds the default commands like Help and List
     *
     * @return void
     */
    public function addDefaultCommands()
    {
        $this->add(new Help);
    }

    /**
     * Sets up the default settings for the dispatcher
     *
     * @return void
     */
    public function setUpDispatcherDefaults()
    {
        // Set the defaults for the dispatcher
        $this->dispatcher->setDefaultTask('Danzabar\CLI\Tasks\Utility\Help');
        $this->dispatcher->setDefaultAction('main');

        // Set no suffixes
        $this->dispatcher->setTaskSuffix('');
        $this->dispatcher->setActionSuffix('');
    }

    /**
     * Registers the question, confirmation and table helpers
     *
     * @return void
     */
    public function registerDefaultHelpers()
    {
        $this->helpers->registerHelper('question', 'Danzabar\CLI\Tasks\Helpers\Question');
        $this->helpers->registerHelper('confirm', 'Danzabar\CLI\Tasks\Helpers\Confirmation');
        $this->helpers->registerHelper('table', 'Danzabar\CLI\Tasks\Helpers\Table');
    }

    public function registerModules(array $modules, $merge = false)
    {
        parent::registerModules($modules, $merge);

        foreach ($this->getModules() as $key => $module) {
            if (isset($module['tasks']))
            {
                $moduleTasks = $module['tasks'];

                if (is_string($moduleTasks) && is_dir($moduleTasks))
                {
                    $this->addTaskDir($moduleTasks);
                    continue;
                } elseif (is_string($moduleTasks) && is_file($moduleTasks))
                {
                    $moduleTasks = include_once $moduleTasks;
                }

                if (is_array($moduleTasks))
                {
                    foreach ($moduleTasks as $task)
                    {
                        $inst = null;
                        if (is_string($task) && class_exists($task))
                        {
                            $inst = new $task;
                            if ($inst instanceof Task)
                                $this->add($inst);
                        } elseif (is_string($task) && is_file($task))
                            $this->addTaskFile($task);
                    }
                }
            }
        }

        return $this;
    }


    /**
     * Start the app
     *
     * @return Output
     */
    public function handle($args = array())
    {
        $arg = $this->formatArgs($args);

        /**
         * Arguments and Options
         *
         */
        if (!empty($arg)) {
            $this->prepper
                 ->load($arg['task'])
                 ->loadParams($arg['params'])
                 ->prep($arg['action']);

            $this->dispatcher->setTaskName($arg['task']);
            $this->dispatcher->setActionName($arg['action']);
            $this->dispatcher->setParams($arg['params']);
        }

        $this->di->setShared('library', $this->library);
        $this->dispatcher->setDI($this->di);

        return $this->dispatcher->dispatch();
    }

    /**
     * Adds a command to the library
     *
     * @return Application
     */
    public function add($command)
    {
        $tasks = $this->prepper
                    ->load(get_class($command))
                    ->describe();

        $this->library->add(['task' => $tasks, 'class' => $command]);

        return $this;
    }

    /**
     * Adds a command to the library
     *
     * @return Application
     */
    public function addTaskDir($path)
    {
        $dirIterator = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS);
        $fileIterator = new \RecursiveIteratorIterator($dirIterator);

        foreach ($fileIterator as $file)
        {
            /** @var $file \SplFileInfo */
            $this->addTaskFile($file->getRealPath());
        }

        return $this;
    }

    /**
     * Adds commands from file to the library
     *
     * @return Application
     */
    public function addTaskFile($path)
    {
        $classes = PhpFileClassReader::getPHPFileClasses($path);
        foreach($classes as $className)
        {
            $inst = new $className;
            if ($inst instanceof \Danzabar\CLI\Tasks\Task)
            {
                $tasks = $this->prepper
                    ->load($className)
                    ->describe();

                $this->library->add(['task' => $tasks, 'class' => $inst]);
            }
        }

        return $this;
    }

    /**
     * Find a command by name
     *
     * @return \Danzabar\CLI\Tasks\Task
     */
    public function find($name)
    {
        return $this->library->find($name);
    }

    /**
     * Format the arguments into a useable state
     *
     * @return array
     */
    public function formatArgs($args)
    {
        // The first argument is always the file
        unset($args[0]);

        if (isset($args[1])) {
            $command = explode(':', $args[1]);
            unset($args[1]);
        } else {
            // No Command Specified.
            return array();
        }

        try {

            $action = (isset($command[1]) ? $command[1] : 'main');
            $cmd = $this->library->find($command[0].':'.$action);
            $task = get_class($cmd);

            return array(
                'task'      => $task,
                'action'    => $action,
                'params'    => $args
            );

        } catch (\Exception $e) {

            // No Command FOUND
            return array();
        }
    }

    /**
     * Returns the helpers object
     *
     * @return Object
     */
    public function helpers()
    {
        return $this->helpers;
    }

    /**
     * Sets the suffix for task classes
     *
     * @return Application
     */
    public function setTaskSuffix($suffix = '')
    {
        $this->dispatcher->setTaskSuffix($suffix);
        return $this;
    }

    /**
     * Sets the action suffix
     *
     * @return Application
     */
    public function setActionSuffix($suffix = '')
    {
        $this->dispatcher->setActionSuffix($suffix);
        return $this;
    }

    /**
     * Gets the value of the name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the value of name
     *
     * @param string $name The name of the CLI
     *
     * @return Application
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Gets the value of version
     *
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Sets the value of version
     *
     * @param string $version The cli version
     *
     * @return Application
     */
    public function setVersion($version)
    {
        $this->version = $version;
        return $this;
    }

    /**
     * @return string
     */
    public function getEnv()
    {
        return $this->env;
    }

    /**
     * @return array
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * Get custom service parameters
     *
     * @param $serviceName
     * @return mixed
     */
    public function getParameters($serviceName)
    {
        if (isset($this->configuration['parameters'][$serviceName])) {
            return $this->configuration['parameters'][$serviceName];
        }

        throw new InvalidArgumentException('Wrong service : '.$serviceName.' passed to fetch from parameters');
    }
} // END class Application
