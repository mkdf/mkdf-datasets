<?php


namespace MKDF\Datasets\Controller\Plugin\Factory;

use MKDF\Datasets\Service\DatasetsFeatureManagerInterface;
use MKDF\Datasets\Controller\Plugin\DatasetsFeatureManagerPlugin;
use Interop\Container\ContainerInterface;

class DatasetsFeatureManagerPluginFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $m = $container->get(DatasetsFeatureManagerInterface::class);
        return new DatasetsFeatureManagerPlugin($m);
    }
}