<?php

namespace MKDF\Datasets\Service\Factory;

use Interop\Container\ContainerInterface;
use MKDF\Datasets\Service\DatasetsFeatureManager;

class DatasetsFeatureManagerFactory
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new DatasetsFeatureManager();
    }
}