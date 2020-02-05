<?php


namespace MKDF\Datasets\Service;

class DatasetsFeatureManager implements DatasetsFeatureManagerInterface {
    
    private $features = [];
    private $active = NULL;
    public function registerFeature(DatasetsFeatureInterface $f){
        if(!in_array($f, $this->features)){
            $this->features[] = $f;
        }
    }
    
    public function getFeatures($dataset_id = NULL){
        $features = [];
        foreach($this->features as $f){
            if($dataset_id == null || $f->hasFeature($dataset_id)){
                array_push($features, $f);
            }
        }
        return $features;
    }
    
    public function setActive(MvcEvent $event){
        
    }
}