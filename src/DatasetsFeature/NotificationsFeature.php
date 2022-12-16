<?php

namespace MKDF\Datasets\DatasetsFeature;

use MKDF\Datasets\Service\DatasetsFeatureInterface;

class NotificationsFeature implements DatasetsFeatureInterface
{
    private $active = false;

    public function getController()
    {
        return \MKDF\Datasets\Controller\DatasetController::class;
    }

    public function getViewAction()
    {
        return 'notifications-details';
    }

    public function getEditAction()
    {
        return 'notifications-details';
    }

    public function getViewHref($dataset_id)
    {
        return '/dataset/notifications-details/' . $dataset_id;
    }

    public function getEditHref($dataset_id)
    {
        return '/dataset/notifications-details/' . $dataset_id;
    }

    public function hasFeature($dataset_id)
    {
        // Only return true if the current user has admin rights on this dataset
        return true;
    }

    public function getLabel()
    {
        return '<i class="fas fa-exclamation-triangle"></i> Notifications';
    }

    public function isActive()
    {
        return $this->active;
    }

    public function setActive($bool)
    {
        $this->active = !!$bool;
    }

    public function initialiseDataset($dataset_id)
    {

    }
}