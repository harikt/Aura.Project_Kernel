<?php
namespace Aura\Project_Kernel;

use Aura\Di\Container;
use Composer\Autoload\ClassLoader;

class ProjectKernel
{
    protected $di;
    
    protected $base;
    
    protected $mode;
    
    protected $packages = array(
        'library' => array(),
        'kernel' => array(),
    );
    
    protected $config_log = array();
    
    public function __construct(
        ClassLoader $loader,
        Container $di,
        $base,
        $mode
    ) {
        $loader->add('', "{$base}/src");
        $di->set('loader', $loader);
        
        $di->params[__CLASS__]['base'] = $base;
        
        $this->di   = $di;
        $this->base = $base;
        $this->mode = $mode;
    }
    
    public function __invoke()
    {
        $this->loadPackages();
        $this->loadConfig('define');
        $this->di->lock();
        $this->loadConfig('modify');
        
        $logger = $this->di->get('logger');
        foreach ($this->config_log as $messages) {
            foreach ($messages as $message) {
                $logger->debug(__CLASS__ . " config {$message}");
            }
        }
    }
    
    protected function loadPackages()
    {
        $file = str_replace(
            '/',
            DIRECTORY_SEPARATOR,
            "{$this->base}/vendor/composer/installed.json"
        );
        
        $installed = json_decode(file_get_contents($file));
        foreach ($installed as $package) {
            if (! isset($package->extra->aura->type)) {
                continue;
            }
            $type = $package->extra->aura->type;
            $dir = "{$this->base}/vendor/{$package->name}";
            $this->packages[$type][$package->name] = $dir;
        }
    }
    
    protected function loadConfig($stage)
    {
        // the config includer
        $includer = $this->di->newInstance('Aura\Includer\Includer');
        
        // pass DI container to the config files
        $includer->setVars(array('di' => $this->di));
        
        // always load the default configs
        $includer->setFiles(array(
            "config/default/{$stage}.php",
            "config/default/{$stage}/*.php",
        ));
        
        // load any non-default configs
        if ($this->mode != 'default') {
            $includer->addFiles(array(
                "config/{$this->mode}/{$stage}.php",
                "config/{$this->mode}/{$stage}/*.php",
            ));
        }
        
        // load in this order: library packages, kernel packages, project
        $includer->addDirs($this->packages['library']);
        $includer->addDirs($this->packages['kernel']);
        $includer->addDir($this->base);
        
        // actually do the loading
        $includer->load();
        
        // retain the debug messages for logging
        $this->config_log[] = $includer->getDebug();
    }
}
