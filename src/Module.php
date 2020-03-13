<?php
/**
 * @link      http://github.com/zendframework/ZendSkeletonModule for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace MKDF\Datasets;

use MKDF\Core\Service\AccountFeatureManagerInterface;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\Controller\AbstractActionController;
use MKDF\Datasets\DatasetsFeature\BasicFeature;
use MKDF\Datasets\DatasetsFeature\PermissionsFeature;
use MKDF\Datasets\DatasetsFeature\MetadataFeature;
use MKDF\Datasets\DatasetsFeature\AccountDatasetsFeature;
use MKDF\Datasets\Service\DatasetsFeatureManagerInterface;
use MKDF\Datasets\Repository\MKDFDatasetRepositoryInterface;

class Module
{
    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }
    
    /**
     * This method is called once the MVC bootstrapping is complete and allows
     * to register event listeners.
     */
    public function onBootstrap(MvcEvent $event)
    {
        // Initialisation
        $repository = $event->getApplication()->getServiceManager()->get(MKDFDatasetRepositoryInterface::class);
        $repository->init();
        
        // Get event manager.
        $featureManager = $event->getApplication()->getServiceManager()->get(DatasetsFeatureManagerInterface::class);
        $featureManager->registerFeature($event->getApplication()->getServiceManager()->get(BasicFeature::class));
        $featureManager->registerFeature($event->getApplication()->getServiceManager()->get(MetadataFeature::class));
        $featureManager->registerFeature($event->getApplication()->getServiceManager()->get(PermissionsFeature::class));

        $accountFeatureManager = $event->getApplication()->getServiceManager()->get(AccountFeatureManagerInterface::class);
        $accountFeatureManager->registerFeature($event->getApplication()->getServiceManager()->get(AccountDatasetsFeature::class));
        
        $eventManager = $event->getApplication()->getEventManager();
        $sharedEventManager = $eventManager->getSharedManager();
        // Register the event listener method.
        $sharedEventManager->attach(AbstractActionController::class,
            MvcEvent::EVENT_DISPATCH, [$this, 'onDispatch'], 1000);

        // $application = $event->getApplication();
    }
    
    public function onDispatch(MvcEvent $event)
    {   
        // Get controller and action to which the HTTP request was dispatched.
        $controllerName = $event->getRouteMatch()->getParam('controller', null);
        $actionName = $event->getRouteMatch()->getParam('action', null);
        // Set active dataset feature
        $featureManager = $event->getApplication()->getServiceManager()->get(DatasetsFeatureManagerInterface::class);
        foreach($featureManager->getFeatures() as $f){
            if($f->getController() == $controllerName && ($f->getViewAction() == $actionName || $f->getEditAction() == $actionName)){
                $f->setActive(true);
            }
        }
        return true;
    }
    
}