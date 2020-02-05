<?php


namespace MKDF\Datasets\Controller\Plugin;

use MKDF\Datasets\Repository\MKDFDatasetRepositoryInterface;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class DatasetRepositoryPlugin extends AbstractPlugin
{
    private $_repository;

    public function __construct(MKDFDatasetRepositoryInterface $repository)
    {
        //$this->entityManager = $entityManager;
        $this->_repository = $repository;
    }

    public function __invoke()
    {
            return $this->_repository;
    }

}