<?php


namespace MKDF\Datasets\Repository\Factory;

use MKDF\Datasets\Repository\MKDFDatasetRepository;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class MKFDFDatasetRepositoryFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get("Config");
        return new MKDFDatasetRepository($config);
    }
}