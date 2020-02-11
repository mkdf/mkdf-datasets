<?php

namespace MKDF\Datasets\DatasetsFeature;

use MKDF\Datasets\Service\DatasetsFeatureInterface;

class MetadataFeature implements DatasetsFeatureInterface {
    private $active = false;
    public function getController(){
        return \DatahubDatasets\Controller\DatasetController::class;
    }
    public function getViewAction(){
        return 'metadata-details';
    }
    public function getEditAction(){
        return 'metadata-edit';
    }
    public function getViewHref($dataset_id){
        return '/dataset/metadata-details/' . $dataset_id;
    }
    public function getEditHref($dataset_id){
        return '/dataset/metadata-edit/' . $dataset_id;
    }
    public function hasFeature($dataset_id){
        // They all have this one
        return true;
    }
    public function getLabel(){
        return 'Metadata';
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