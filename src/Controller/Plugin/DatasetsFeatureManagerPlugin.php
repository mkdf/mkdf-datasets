<?php


namespace MKDF\Datasets\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use MKDF\Datasets\Service\DatasetsFeatureManagerInterface;

class DatasetsFeatureManagerPlugin extends AbstractPlugin
{
    private $_manager;

    public function __construct(DatasetsFeatureManagerInterface $manager)
    {
        $this->_manager = $manager;
    }

    public function __invoke()
    {
        return $this->_manager;
    }

}