<?php

namespace MKDF\Datasets\DatasetsFeature;

use MKDF\Datasets\Service\DatasetsFeatureInterface;

class GeospatialFeature implements DatasetsFeatureInterface {
    private $active = false;
    public function getController(){
        return \MKDF\Datasets\Controller\DatasetController::class;
    }
    public function getViewAction(){
        return 'geospatial-details';
    }
    public function getEditAction(){
        return 'geospatial-edit';
    }
    public function getViewHref($dataset_id){
        return '/dataset/geospatial-details/' . $dataset_id;
    }
    public function getEditHref($dataset_id){
        return '/dataset/geospatial-edit/' . $dataset_id;
    }
    public function hasFeature($dataset_id){
        // They all have this one
        return true;
    }
    public function getLabel(){
        return 'Location';
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