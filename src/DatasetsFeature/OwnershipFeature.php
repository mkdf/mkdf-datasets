<?php

namespace MKDF\Datasets\DatasetsFeature;

use MKDF\Datasets\Service\DatasetsFeatureInterface;

class OwnershipFeature implements DatasetsFeatureInterface
{
    private $active = false;
    public function getController(){
        return \MKDF\Datasets\Controller\DatasetController::class;
    }
    public function getViewAction(){
        return 'ownership-details';
    }
    public function getEditAction(){
        return 'attribution-edit';
    }
    public function getViewHref($dataset_id){
        return '/dataset/ownership-details/' . $dataset_id;
    }
    public function getEditHref($dataset_id){
        return '/dataset/attribution-edit/' . $dataset_id;
    }
    public function hasFeature($dataset_id){
        // They all have this one
        return true;
    }
    public function getLabel(){
        return '<i class="fas fa-copyright"></i> Ownership';
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

