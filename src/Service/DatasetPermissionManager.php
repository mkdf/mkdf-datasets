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

    public function hasCustomAccess($dataset, $userID){
        $datasetID = $dataset->id;
        $roleID = $userID;
        //echo("DATASET".$datasetID);
        //echo("USER".$userID);
        $permissions = $this->_repository->findDatasetRolePermission($datasetID,$roleID);
        if (!$permissions) {
            //echo("RETURNING FALSE");
            return false;
        }
        else {
            //echo("RETURNING TRUE");
            return true;
        }
    }

    public function canView($dataset, $userID) {
        $datasetID = $dataset->id;
        if ($userID == -1){ //not logged in (anonymous)
            $roleID = -2; //This is the roleID for anonymous users
        }
        elseif($userID == $dataset->user_id){
            //dataset owner
            return true;
        }
        else {
            $roleID = $userID;
        }
        $permissions = $this->_repository->findDatasetRolePermission($datasetID,$roleID);
        if (!$permissions) {
            $permissions = $this->_repository->findDatasetRolePermission($datasetID,-1); //check generic 'logged_in" permissions
        }
        return $permissions['v'];
    }

    public function canRead($dataset, $userID) {
        $datasetID = $dataset->id;
        if ($userID == -1){ //not logged in (anonymous)
            $roleID = -2; //This is the roleID for anonymous users
        }
        elseif($userID == $dataset->user_id){
            //dataset owner
            return true;
        }
        else {
            $roleID = $userID;
        }
        $permissions = $this->_repository->findDatasetRolePermission($datasetID,$roleID);
        if (!$permissions) {
            $permissions = $this->_repository->findDatasetRolePermission($datasetID,-1); //check generic 'logged_in" permissions
        }
        return $permissions['r'];
    }

    public function canWrite($dataset, $userID) {
        $datasetID = $dataset->id;
        if ($userID == -1){ //not logged in (anonymous)
            $roleID = -2; //This is the roleID for anonymous users
        }
        elseif($userID == $dataset->user_id){
            //dataset owner
            return true;
        }
        else {
            $roleID = $userID;
        }
        $permissions = $this->_repository->findDatasetRolePermission($datasetID,$roleID);
        return $permissions['w'];
    }

    public function canEdit($dataset, $userID) {
        $datasetID = $dataset->id;
        if ($userID == -1){
            $roleID = -2; //This is the roleID for anonymous users
        }
        elseif($userID == $dataset->user_id){
            //dataset owner
            return true;
        }
        else {
            $roleID = $userID;
        }
        $permissions = $this->_repository->findDatasetRolePermission($datasetID,$roleID);
        return $permissions['g'];
    }

    public function canDelete($dataset, $userID) {
        $datasetID = $dataset->id;
        if ($userID == -1){
            $roleID = -2; //This is the roleID for anonymous users
        }
        elseif($userID == $dataset->user_id){
            //dataset owner
            return true;
        }
        else {
            $roleID = $userID;
        }
        $permissions = $this->_repository->findDatasetRolePermission($datasetID,$roleID);
        return $permissions['d'];
    }

}