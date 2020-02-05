<?php


namespace MKDF\Datasets\Repository;

use MKDF\Datasets\Entity\Dataset;

interface MKDFDatasetRepositoryInterface
{
    public function __construct($config);

    public function findAllDatasets($userId, $limit);
    public function findDataset($id);
}