<?php

namespace MKDF\Datasets\DatasetsFeature;

use MKDF\Datasets\Service\DatasetsFeatureInterface;

class PermissionsFeature implements DatasetsFeatureInterface {
    private $active = false;
    public function getController(){
        return \MKDF\Datasets\Controller\DatasetController::class;
    }
    public function getViewAction(){
        return 'permissions-details';
    }
    public function getEditAction(){
        return 'permissions-details';
    }
    public function getViewHref($dataset_id){
        return '/dataset/permissions-details/' . $dataset_id;
    }
    public function getEditHref($dataset_id){
        return '/dataset/permissions-details/' . $dataset_id;
    }
    public function hasFeature($dataset_id){
        // Only return true if the current user has admin rights on this dataset
        return true;
    }
    public function getLabel(){
        return '<i class="fas fa-eye-slash"></i> Access control';
    }
    public function isActive(){
        return $this->active;
    }
    public function setActive($bool){
        $this->active = !!$bool;
    }
    public function initialiseDataset($dataset_id) {

    }
}