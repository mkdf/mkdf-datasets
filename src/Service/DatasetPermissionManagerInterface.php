<?php


namespace MKDF\Datasets\Service;


interface DatasetPermissionManagerInterface
{
    public function canView($datasetID, $userID);
    public function canRead($datasetID, $userID);
    public function canWrite($datasetID, $userID);
    public function canEdit($datasetID, $userID);
}