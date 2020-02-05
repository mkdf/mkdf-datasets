<?php
namespace MKDF\Datasets\Controller\Factory;

use MKDF\Datasets\Controller\DatasetController;
use MKDF\Datasets\Repository\MKDFDatasetRepositoryInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class DatasetControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get("Config");
        $repository = $container->get(MKDFDatasetRepositoryInterface::class);
        return new DatasetController($repository, $config);
    }
}