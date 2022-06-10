<?php
namespace MKDF\Datasets\Controller\Factory;

use MKDF\Datasets\Controller\DatasetController;
use MKDF\Datasets\Repository\MKDFDatasetRepositoryInterface;
use MKDF\Datasets\Service\DatasetPermissionManagerInterface;
use MKDF\Keys\Repository\MKDFKeysRepositoryInterface;
use MKDF\Stream\Repository\MKDFStreamRepositoryInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class DatasetControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get("Config");
        $viewRenderer = $container->get('ViewRenderer');
        $repository = $container->get(MKDFDatasetRepositoryInterface::class);
        $keys_repository = $container->get(MKDFKeysRepositoryInterface::class);
        $stream_repository = $container->get(MKDFStreamRepositoryInterface::class);
        $permissionManager = $container->get(DatasetPermissionManagerInterface::class);
        return new DatasetController($repository, $keys_repository, $stream_repository, $config, $permissionManager, $viewRenderer);
    }
}