<?php


namespace MKDF\Datasets\Service\Factory;


use Interop\Container\ContainerInterface;
use MKDF\Datasets\Repository\MKDFDatasetRepository;
use MKDF\Datasets\Service\DatasetPermissionManager;

class DatasetPermissionManagerFactory
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $repository = $container->get(MKDFDatasetRepository::class);
        return new DatasetPermissionManager($repository);
    }

}