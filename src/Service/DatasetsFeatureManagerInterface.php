<?php


namespace MKDF\Datasets\Service;

interface DatasetsFeatureManagerInterface {
    
    public function registerFeature(DatasetsFeatureInterface $f);
    
    public function getFeatures($dataset_id);
}