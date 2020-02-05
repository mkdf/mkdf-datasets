<?php

namespace MKDF\Datasets\DatasetsFeature;

use MKDF\Datasets\Service\DatasetsFeatureInterface;

class PermissionsFeature implements DatasetsFeatureInterface {
    private $active = false;
    public function getController(){
        return \DatahubDatasets\Controller\DatasetController::class;
    }
    public function getViewAction(){
        return 'permissions-details';
    }
    public function getEditAction(){
        return 'permissions-edit';
    }
    public function getViewHref($dataset_id){
        return '/dataset/permissions-details/' . $dataset_id;
    }
    public function getEditHref($dataset_id){
        return '/dataset/permissions-edit/' . $dataset_id;
    }
    public function hasFeature($dataset_id){
        // They all have this one
        return true;
    }
    public function getLabel(){
        return 'Permissions';
    }
    public function isActive(){
        return $this->active;
    }
    public function setActive($bool){
        $this->active = !!$bool;
    }
}