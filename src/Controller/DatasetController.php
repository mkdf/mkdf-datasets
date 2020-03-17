<?php
/**
 * @link      http://github.com/zendframework/ZendSkeletonModule for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace MKDF\Datasets\Controller;

use MKDF\Datasets\Form;
use MKDF\Datasets\Entity\Dataset;
use MKDF\Datasets\Repository\MKDFDatasetRepositoryInterface;
use MKDF\Datasets\Service\DatasetPermissionManager;
use MKDF\Datasets\Service\DatasetPermissionManagerInterface;
use MKDF\Datasets\Service\DatasetsFeatureManagerInterface;
use MKDF\Datasets\Service\Factory\DatasetPermissionManagerFactory;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Session\Container;
use Zend\Paginator\Adapter;
use Zend\Paginator\Paginator;

class DatasetController extends AbstractActionController
{
    private $config;
    private $_repository;
    private $_permissionManager;

    public function __construct(MKDFDatasetRepositoryInterface $repository, array $config, DatasetPermissionManager $permissionManager)
    {
        $this->config = $config;
        $this->_repository = $repository;
        $this->_permissionManager = $permissionManager;
    }

    private function datasetCollectionToArray($datasetCollection) {
        $result = [];
        foreach ($datasetCollection as $dataset) {
            array_push($result, $dataset->getProperties());
        }
        return $result;
    }

    public function indexAction()
    {
        $user = $this->currentUser();
        $actions = [];
        //anonymous/logged-out user will return an ID of -1
        $userId = $user->getId();
        if ($userId > 0) {
            $actions = [
                'label' => 'Actions',
                'class' => '',
                'buttons' => [[ 'type' => 'primary', 'label' => 'Create a new dataset', 'icon' => 'create', 'target' => 'dataset', 'params' => ['action' => 'add']]]
            ];
        }

        $txtSearch = $this->params()->fromQuery('txt', "");
        if ($txtSearch == ""){
            $datasetCollection = $this->_repository->findAllDatasets($userId);
        }
        else{
            $datasetCollection = $this->_repository->findAllDatasets($userId,$txtSearch);
        }


        $paginator = new Paginator(new Adapter\ArrayAdapter($this->datasetCollectionToArray($datasetCollection)));
        $page = $this->params()->fromQuery('page', 1);
        $paginator->setCurrentPageNumber($page);
        $paginator->setItemCountPerPage(10);
        return new ViewModel([
            'message' => 'Datasets ',
            'datasets' => $paginator,
            'currentUserId' => $user->getId(),
            'actions' => $actions,
            'url_params' => $this->params()->fromQuery(),
            'txt_search' => $txtSearch,
        ]);
    }

    public function detailsAction() {
        $id = (int) $this->params()->fromRoute('id', 0);
        $dataset = $this->_repository->findDataset($id);
        $user_id = $this->currentUser()->getId();
        $permissions = $this->_repository->findDatasetPermissions($id);
        $message = "Dataset: " . $id;
        $actions = [];
        $can_view = $this->_permissionManager->canView($dataset,$user_id);
        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);
        $can_delete = $this->_permissionManager->canDelete($dataset,$user_id);
        $actions = [
            'label' => 'Actions',
            'class' => '',
            'buttons' => []
        ];
        if ($can_edit) {
            $actions['buttons'][] = ['type'=>'warning','label'=>'Edit', 'icon'=>'edit', 'target'=> 'dataset', 'params'=> ['id' => $dataset->id, 'action' => 'edit']];
        }
        if ($can_delete) {
            $actions['buttons'][] = ['type'=>'danger','label'=>'Delete', 'icon'=>'delete', 'target'=> 'dataset', 'params'=> ['id' => $dataset->id, 'action' => 'delete-confirm']];
        }

        if ($can_view) {
            return new ViewModel([
                'message' => $message,
                'dataset' => $dataset,
                'permissions' => $permissions,
                'features' => $this->datasetsFeatureManager()->getFeatures($id),
                'actions' => $actions
            ]);
        }
        else {
            $this->flashMessenger()->addErrorMessage('Unauthorised to view dataset.');
            return $this->redirect()->toRoute('dataset', ['action'=>'index']);
        }
    }
    
    public function permissionsDetailsAction() {
        $id = (int) $this->params()->fromRoute('id', 0);
        $dataset = $this->_repository->findDataset($id);
        $user_id = $this->currentUser()->getId();
        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);
        if ($can_edit) {
            $permissions = $this->_repository->findDatasetPermissions($id);
            $message = "Dataset: " . $id;
            return new ViewModel([
                'message' => $message,
                'dataset' => $dataset,
                'permissions' => $permissions,
                'features' => $this->datasetsFeatureManager()->getFeatures($id),
            ]);
        }
        else {
            $this->flashMessenger()->addErrorMessage('Unauthorised to view dataset permissions.');
            return $this->redirect()->toRoute('dataset', ['action'=>'details', 'id' => $id]);
        }
    }

    public function permissionsAddAction() {
        $id = (int) $this->params()->fromRoute('id', 0);
        $dataset = $this->_repository->findDataset($id);
        $user_id = $this->currentUser()->getId();

        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);
        if($can_edit){
            if($this->getRequest()->isPost()) {
                $data = $this->params()->fromPost();

                $userId =  $this->userIdFromEmail($data['inputEmail']);
                if ($userId == 0) {
                    $this->flashMessenger()->addErrorMessage('No such user - '.$data['inputEmail']);
                    return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
                }

                //INSERT PERMISSIONS HERE...
                $this->_repository->createDatasetPermission($id,$userId,0,0,0,0,0);

                $this->flashMessenger()->addSuccessMessage('User '.$data['inputEmail'].' added to dataset permissions.');
                return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
            }
            else {
                $this->flashMessenger()->addErrorMessage('Unable to add user to dataset permissions - error with form data');
                return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
            }

        }else{
            $this->flashMessenger()->addErrorMessage('Unauthorised to edit dataset permissions.');
            return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
        }
    }
    
    public function permissionsEditAction() {
        $id = (int) $this->params()->fromRoute('id', 0);
        $roleId = $this->params()->fromQuery('role', '');
        $action = $this->params()->fromQuery('action', '');
        $permission = $this->params()->fromQuery('permission', '');
        $dataset = $this->_repository->findDataset($id);
        $user_id = $this->currentUser()->getId();

        //Check for missing params
        if ($roleId == '' || $action == '' || $permission == '') {
            $this->flashMessenger()->addErrorMessage('Incorrect parameters supplied.');
            return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
        }

        //Check for changes that are forbidden
        //Dataset owner - permissions cannot be changed
        if ($roleId == 0) {
            $this->flashMessenger()->addErrorMessage('Permissions cannot be changed for this user.');
            return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
        }

        //Logged in users, only 'v' and 'r' can be changed
        if ($roleId == -1 && !($permission == 'v' || $permission == 'r')) {
            $this->flashMessenger()->addErrorMessage('These permissions cannot be changed for this user.');
            return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
        }

        //Anonymous users, only 'v' can be changed
        if ($roleId == -2 && !($permission == 'v')) {
            $this->flashMessenger()->addErrorMessage('These permissions cannot be changed for this user.');
            return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
        }

        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);
        $messages = [];
        if($can_edit){
            $this->_repository->updateDatasetPermission($id, $roleId, $permission, (int)$action);

            $this->flashMessenger()->addSuccessMessage('Permissions updated.');
            return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
        }else{
            $this->flashMessenger()->addErrorMessage('Unauthorised to edit dataset permissions.');
            return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
        }
    }

    public function permissionsDeleteAction () {
        $id = (int) $this->params()->fromRoute('id', 0);
        $roleId = $this->params()->fromQuery('role_id', '');
        $user_id = $this->currentUser()->getId();
        $dataset = $this->_repository->findDataset($id);

        //Check for missing params
        if ($roleId == '') {
            $this->flashMessenger()->addErrorMessage('Incorrect parameters supplied.');
            return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
        }

        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);
        $messages = [];
        if($can_edit){
            $this->_repository->deleteDatasetPermissions($id, $roleId);

            $this->flashMessenger()->addSuccessMessage('Permissions deleted.');
            return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
        }else{
            $this->flashMessenger()->addErrorMessage('Unauthorised to edit dataset permissions.');
            return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
        }
    }

    public function addAction(){
        $form = new Form\DatasetForm($this->_repository);
        // Check if user has submitted the form
        $messages = [];
        if($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if($form->isValid()){
                // Get User Id
                $user_id = $this->currentUser()->getId();
                // Write data
                $id = $this->_repository->insertDataset(['title' => $data['title'], 'description'=>$data['description'],'user_id'=>$user_id,'type'=>$data['datasetTypes']]);
                $this->_repository->setDefaultDatasetPermissions($id);

                //Run through dataset features and check for additional initialisation routines.
                $features = $this->datasetsFeatureManager()->getFeatures($id);
                foreach ($features as $feature) {
                    if ($feature->hasFeature($id)) {
                        echo($feature->initialiseDataset($id));
                    }
                }

                // Redirect to "view" page
                $this->flashMessenger()->addSuccessMessage('New dataset was created.');
                return $this->redirect()->toRoute('dataset', ['action'=>'details', 'id' => $id]);
            }else{
                $messages[] = [ 'type'=> 'warning', 'message'=>'Please check the content of the form.'];
            }
        }
        // Pass form variable to view
        return new ViewModel(['form' => $form, 'messages' => $messages ]);
    }

    public function editAction() {
        $id = (int) $this->params()->fromRoute('id', 0);
        $dataset = $this->_repository->findDataset($id);
        $user_id = $this->currentUser()->getId();
        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);
        $messages = [];
        if($can_edit){
            $form = new Form\DatasetForm($this->_repository);
            if($this->getRequest()->isPost()) {
                $data = $this->params()->fromPost();
                //print_r($data);
                $form->setData($data);
                if($form->isValid()){
                    // Get User Id
                    $user_id = $this->currentUser()->getId();
                    // Write data
                    $output = $this->_repository->updateDataset($id, $data['title'], $data['description']);
                    // Redirect to "view" page
                    $this->flashMessenger()->addSuccessMessage('The dataset was updated succesfully.');
                    return $this->redirect()->toRoute('dataset', ['action'=>'details', 'id'=>$id]);
                }else{
                    $messages[] = [ 'type'=> 'warning', 'message'=>'Please check the content of the form.'];
                }
            } else{
                $form->setData($dataset->getProperties());
            }
            // Pass form variable to view
            return new ViewModel(
                [
                    'form' => $form,
                    'messages' => $messages,
                    'features' => $this->datasetsFeatureManager()->getFeatures($id),
                    'dataset_id' => $id
                ]
            );
        }else{
            $this->flashMessenger()->addErrorMessage('Unauthorised to edit dataset.');
            return $this->redirect()->toRoute('dataset', ['action'=>'details', 'id' => $id]);
        }
    }

    public function deleteAction(){
        $id = (int) $this->params()->fromRoute('id', 0);
        $token = $this->params()->fromQuery('token', '');
        $dataset = $this->_repository->findDataset($id);
        if($dataset == null){
            throw new \Exception('Not found');
        }
        $user_id = $this->currentUser()->getId();
        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);
        $can_delete = $this->_permissionManager->canDelete($dataset,$user_id);
        $container = new Container('Dataset_Management');
        $valid_token = ($container->delete_token == $token);
        if($can_delete && $valid_token){
            $outcome = $this->_repository->deleteDataset($id);
            unset($container->delete_token);
            $this->flashMessenger()->addSuccessMessage('The dataset was deleted successfully.');
            return $this->redirect()->toRoute('dataset', ['action'=>'index']);
        }else{
            // FIXME Better handling security
            throw new \Exception('Unauthorized. Delete token was ' . (($valid_token)?'valid':'invalid') . '.');
        }
    }

    public function deleteConfirmAction(){
        //
        $id = (int) $this->params()->fromRoute('id', 0);
        $dataset = $this->_repository->findDataset($id);
        $user_id = $this->currentUser()->getId();
        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);
        $can_delete = $this->_permissionManager->canDelete($dataset,$user_id);
        if($can_delete){
            $token = uniqid(true);
            $container = new Container('Dataset_Management');
            $container->delete_token = $token;
            $messages[] = [ 'type'=> 'warning', 'message' =>
                'Are you sure you want to delete this dataset?'];
            return new ViewModel(['dataset' => $dataset, 'token' => $token, 'messages' => $messages]);
        }else{
            // FIXME Better handling security
            throw new \Exception('Unauthorized');
        }
    }

    public function mydatasetsAction() {
        $user = $this->currentUser();
        //anonymous/logged-out user will return an ID of -1
        $userId = $user->getId();
        $actions = [];

        if ($userId > 0) {
            $actions = [
                'label' => 'Actions',
                'class' => '',
                'buttons' => [[ 'type' => 'primary', 'label' => 'Create a new dataset', 'icon' => 'create', 'target' => 'dataset', 'params' => ['action' => 'add']]]
            ];
        }

        $userDatasets = $this->_repository->findUserDatasets($userId);

        $paginator = new Paginator(new Adapter\ArrayAdapter($userDatasets));
        $page = $this->params()->fromQuery('page', 1);
        $paginator->setCurrentPageNumber($page);
        $paginator->setItemCountPerPage(10);

        return new ViewModel([
            'message' => 'Datasets ',
            //'datasets' => $this->datasetCollectionToArray($datasetCollection),
            'datasets' => $paginator,
            'user' => $user,
            'userid' => $userId,
            'actions' => $actions,
            'features' => $this->accountFeatureManager()->getFeatures($userId),
        ]);
    }

    public function geospatialDetailsAction() {
        $id = (int) $this->params()->fromRoute('id', 0);
        $dataset = $this->_repository->findDataset($id);
        $user_id = $this->currentUser()->getId();
        //$permissions = $this->_repository->findDatasetPermissions($id);
        $metadata = $this->_repository->findDatasetGeospatial($id);
        $message = "Dataset: " . $id;
        $actions = [];
        $can_view = $this->_permissionManager->canView($dataset,$user_id);
        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);
        if ($can_edit) {
            $actions = [
                'label' => 'Actions',
                'class' => '',
                'buttons' => [
                    ['type'=>'warning','label'=>'Edit', 'icon'=>'edit', 'target'=> 'dataset', 'params'=> ['id' => $dataset->id, 'action' => 'geospatial-edit']],
                ]
            ];
        }
        if ($can_view) {
            return new ViewModel([
                'message' => $message,
                'dataset' => $dataset,
                'metadata' => $metadata,
                'features' => $this->datasetsFeatureManager()->getFeatures($id),
                'actions' => $actions
            ]);
        }
        else {
            $this->flashMessenger()->addErrorMessage('Unauthorised to view dataset.');
            return $this->redirect()->toRoute('dataset', ['action'=>'index']);
        }

    }

    public function geospatialEditAction() {
        $id = (int) $this->params()->fromRoute('id', 0);
        $dataset = $this->_repository->findDataset($id);
        $user_id = $this->currentUser()->getId();
        //$permissions = $this->_repository->findDatasetPermissions($id);
        $metadata = $this->_repository->findDatasetGeospatial($id);
        $spatial = [
            'latitude' => null,
            'longitude' => null
        ];
        foreach ($metadata as $row) {
            $spatial[$row['name']] = $row['value'];
        }
        $message = "Dataset: " . $id;
        $actions = [];
        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);
        if ($can_edit) {
            $form = new Form\GeospatialForm($this->_repository);
            if($this->getRequest()->isPost()) {
                $data = $this->params()->fromPost();
                //print_r($data);
                $form->setData($data);
                if($form->isValid()){
                    // Write data
                    $output = $this->_repository->updateDatasetGeospatial($id, $data['latitude'], $data['longitude']);
                    // Redirect to "view" page
                    $this->flashMessenger()->addSuccessMessage('Location information updated succesfully.');
                    return $this->redirect()->toRoute('dataset', ['action'=>'geospatial-details', 'id'=>$id]);
                }else{
                    $messages[] = [ 'type'=> 'warning', 'message'=>'Please check the content of the form.'];
                }
            } else{
                $form->setData($spatial);
            }
            // Pass form variable to view
            return new ViewModel(
                [
                    'form' => $form,
                    'messages' => $messages,
                    'features' => $this->datasetsFeatureManager()->getFeatures($id),
                    'dataset_id' => $id
                ]
            );
        }
        else {
            $this->flashMessenger()->addErrorMessage('Unauthorised to edit dataset.');
            return $this->redirect()->toRoute('dataset', ['action'=>'details', 'id'=>$id]);
        }
    }

    public function ownershipDetailsAction() {
        $id = (int) $this->params()->fromRoute('id', 0);
        $dataset = $this->_repository->findDataset($id);
        $user_id = $this->currentUser()->getId();
        $actions = [];
        $can_view = $this->_permissionManager->canView($dataset,$user_id);
        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);
        if ($can_edit) {
            $actions = [
                'label' => 'Actions',
                'class' => '',
                'buttons' => [
                    ['type'=>'warning','label'=>'Edit', 'icon'=>'edit', 'target'=> 'dataset', 'params'=> ['id' => $dataset->id, 'action' => 'ownership-edit']],
                ]
            ];
        }
        if ($can_view) {
            return new ViewModel([
                'dataset' => $dataset,
                'features' => $this->datasetsFeatureManager()->getFeatures($id),
                'actions' => $actions
            ]);
        }
        else {
            $this->flashMessenger()->addErrorMessage('Unauthorised to view dataset.');
            return $this->redirect()->toRoute('dataset', ['action'=>'index']);
        }
    }
}
