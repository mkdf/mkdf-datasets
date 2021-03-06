<?php

namespace MKDF\Datasets\DatasetsFeature;

use MKDF\Datasets\Service\DatasetsFeatureInterface;

class BasicFeature implements DatasetsFeatureInterface {
    private $active = false;
    public function getController(){
        return \MKDF\Datasets\Controller\DatasetController::class;
    }
    public function getViewAction(){
        return 'details';
    }
    public function getEditAction(){
        return 'edit';
    }
    public function getViewHref($dataset_id){
        return '/dataset/details/' . $dataset_id;
    }
    public function getEditHref($dataset_id){
        return '/dataset/edit/' . $dataset_id;
    }
    public function hasFeature($dataset_id){
        // They all have this one
        return true;
    }
    public function getLabel(){
        return '<i class="fas fa-info-circle"></i> Overview';
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