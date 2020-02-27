<?php

namespace MKDF\Datasets\DatasetsFeature;

use MKDF\Core\Service\AccountFeatureInterface;

class AccountDatasetsFeature implements AccountFeatureInterface
{
    private $active = false;

    public function getController() {
        return \MKDF\Datasets\Controller\DatasetController::class;
    }
    public function getViewAction(){
        return 'mydatasets';
    }
    public function getEditAction(){
        return 'mydatasets';
    }
    public function getViewHref(){
        return '/my-account/mydatasets';
    }
    public function getEditHref(){
        return '/my-account/mydatasets';
    }
    public function hasFeature(){
        // They all have this one
        return true;
    }
    public function getLabel(){
        return 'My datasets';
    }
    public function isActive(){
        return $this->active;
    }
    public function setActive($bool){
        $this->active = !!$bool;
    }

}