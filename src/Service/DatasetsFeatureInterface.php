<?php
namespace MKDF\Datasets\Service;


interface DatasetsFeatureInterface {
    public function getController();
    public function getViewAction();
    public function getEditAction();
    public function getViewHref($dataset_id);
    public function getEditHref($dataset_id);
    public function hasFeature($dataset_id);
    public function getLabel();
    public function isActive();
    public function setActive($bool);
}