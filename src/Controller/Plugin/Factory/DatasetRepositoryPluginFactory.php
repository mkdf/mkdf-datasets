<?php

namespace MKDF\Datasets\Controller\Plugin\Factory;

use MKDF\Core\Repository\MKDFCoreRepositoryInterface;
use MKDF\Datasets\Controller\Plugin\DatasetRepositoryPlugin;
use MKDF\Datasets\Repository\MKDFDatasetRepositoryInterface;
use Interop\Container\ContainerInterface;

class DatasetRepositoryPluginFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $repository = $container->get(MKDFDatasetRepositoryInterface::class);
        return new DatasetRepositoryPlugin($repository);
    }
}