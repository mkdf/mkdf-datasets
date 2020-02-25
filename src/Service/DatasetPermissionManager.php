<?php


namespace MKDF\Datasets\Service;

use MKDF\Datasets\Repository\MKDFDatasetRepositoryInterface;

class DatasetPermissionManager
{
    private $_repository;

    public function __construct(MKDFDatasetRepositoryInterface $repository)
    {
        $this->_repository = $repository;
    }

    public function canView($datasetID, $userID) {

        return false;
    }

    public function canRead($datasetID, $userID) {
        return false;
    }

    public function canWrite($datasetID, $userID) {
        return false;
    }

    public function canEdit($dataset, $userID) {
        $datasetID = $dataset->id;
        if ($userID == -1){
            $roleID = -2; //This is the roleID for anonymous users
        }
        elseif($userID == $dataset->user_id){
            return true;
        }
        else {
            $roleID = $userID;
        }
        $permissions = $this->_repository->findDatasetRolePermission($datasetID,$roleID);
        return $permissions['d'];
    }

}