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
use MKDF\Keys\Repository\MKDFKeysRepositoryInterface;
use MKDF\Stream\Repository\MKDFStreamRepositoryInterface;
use MKDF\Datasets\Service\Factory\DatasetPermissionManagerFactory;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Session\Container;
use Zend\Paginator\Adapter;
use Zend\Paginator\Paginator;
use Zend\Mail;
use Zend\Mail\Transport\Smtp as SmtpTransport;
use Zend\Mail\Transport\Sendmail as SendmailTransport;
use Zend\Mail\Transport\SmtpOptions;
use Zend\Mime\Message as MimeMessage;
use Zend\Mime\Part as MimePart;

class DatasetController extends AbstractActionController
{
    private $config;
    private $_repository;
    private $_keys_repository;
    private $_stream_repository;
    private $_permissionManager;


    public function __construct(MKDFDatasetRepositoryInterface $repository, MKDFKeysRepositoryInterface $keysRepository, MKDFStreamRepositoryInterface $stream_repository, array $config, DatasetPermissionManager $permissionManager, $viewRenderer)
    {
        $this->config = $config;
        $this->viewRenderer = $viewRenderer;
        $this->_repository = $repository;
        $this->_keys_repository = $keysRepository;
        $this->_stream_repository = $stream_repository;
        $this->_permissionManager = $permissionManager;
    }

    private function datasetCollectionToArray($datasetCollection) {
        $result = [];
        foreach ($datasetCollection as $dataset) {
            array_push($result, $dataset->getProperties());
        }
        return $result;
    }

    private function _sendEmail ($subject, $bodyHTML, $from, $fromLabel, $to, $toLabel) {
        // Send an email to user.

        $html = new MimePart($bodyHTML);
        $html->type = "text/html";

        $body = new MimeMessage();
        $body->addPart($html);

        $mail = new Mail\Message();
        $mail->setEncoding('UTF-8');
        $mail->setBody($body);
        $mail->setFrom($from, $fromLabel);
        $mail->addTo($to, $toLabel);
        $mail->setSubject($subject);

        // Setup SMTP/Sendmail transport
        //$transport = new SmtpTransport();
        //$options   = new SmtpOptions($this->config['smtp']);
        //$transport->setOptions($options);
        $transport = new SendmailTransport();
        $transport->send($mail);
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

        $messages = [];
        $flashMessenger = $this->flashMessenger();
        if ($flashMessenger->hasMessages()) {
            foreach($flashMessenger->getMessages() as $flashMessage) {
                $messages[] = [
                    'type' => 'warning',
                    'message' => $flashMessage
                ];
            }
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
            'messages' => $messages,
            'datasets' => $paginator,
            'currentUserId' => $user->getId(),
            'actions' => $actions,
            'url_params' => $this->params()->fromQuery(),
            'txt_search' => $txtSearch,
        ]);
    }

    public function locationsAction () {
        $user = $this->currentUser();
        $userId = $user->getId();
        $datasetCollection = $this->_repository->findDatasetLocations($userId);
        $geojson = [
            'type' => 'FeatureCollection',
            'name' => 'Datafeeds',
            'crs' => [
                'type' => 'name',
                'properties' => [
                    'name' => 'urn:ogc:def:crs:OGC:1.3:CRS84'
                ],
            ],
            'features' => []
        ];

        foreach ($datasetCollection as $item) {
            $url = $this->url()->fromRoute('dataset',['action'=>'details', 'id'=>$item['id']]);
            $feature = [
                'type' => 'Feature',
                'properties' => [
                    'title' => $item['title'],
                    'uuid' => $item['uuid'],
                    'url' => $url,
                    'name' => $item['title'],
                    'marker-color' => '#f00',
                    'marker-size' => 'small',
                    'visible' => 1
                ],
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float)$item['longitude'],(float)$item['latitude']]
                ],
            ];

            array_push( $geojson['features'],$feature);
        }
        return new JsonModel($geojson);
    }

    public function detailsAction() {
        $id = (int) $this->params()->fromRoute('id', 0);
        $dataset = $this->_repository->findDataset($id);
        $user_id = $this->currentUser()->getId();
        $myDataset = ($dataset->user_id == $user_id) ? true : false;
        $permissions = $this->_repository->findDatasetPermissions($id);
        $message = "Dataset: " . $id;

        $messages = [];
        $flashMessenger = $this->flashMessenger();
        if ($flashMessenger->hasMessages()) {
            foreach($flashMessenger->getMessages() as $flashMessage) {
                $messages[] = [
                    'type' => 'warning',
                    'message' => $flashMessage
                ];
            }
        }

        $actions = [];
        $can_view = $this->_permissionManager->canView($dataset,$user_id);
        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);
        $can_delete = $this->_permissionManager->canDelete($dataset,$user_id);
        $actions = [
            'label' => 'Actions',
            'class' => '',
            'buttons' => []
        ];
        $keys = null;
        if ($can_edit) {
            $actions['buttons'][] = ['type'=>'warning','label'=>'Edit', 'icon'=>'edit', 'target'=> 'dataset', 'params'=> ['id' => $dataset->id, 'action' => 'edit']];
            $keys = $this->_keys_repository->allDatasetKeys($id);
        }
        if ($can_delete) {
            $actions['buttons'][] = ['type'=>'danger','label'=>'Delete', 'icon'=>'delete', 'target'=> 'dataset', 'params'=> ['id' => $dataset->id, 'action' => 'delete-confirm']];
        }

        if ($can_view) {
            return new ViewModel([
                'message' => $message,
                'messages' => $messages,
                'dataset' => $dataset,
                'permissions' => $permissions,
                'features' => $this->datasetsFeatureManager()->getFeatures($id),
                'actions' => $actions,
                'myDataset' => $myDataset,
                'can_edit' => $can_edit,
                'keys' => $keys
            ]);
        }
        else {
            $this->flashMessenger()->addMessage('Unauthorised to view dataset.');
            return $this->redirect()->toRoute('dataset', ['action'=>'index']);
        }
    }

    public function permissionsRequestAction () {
        $id = (int) $this->params()->fromRoute('id', 0);
        $dataset = $this->_repository->findDataset($id);
        $user_id = $this->currentUser()->getId();
        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);
        $can_view = $this->_permissionManager->canView($dataset,$user_id);
        $can_read = $this->_permissionManager->canRead($dataset,$user_id);
        $can_write = $this->_permissionManager->canWrite($dataset,$user_id);

        $messages = [];
        $flashMessenger = $this->flashMessenger();
        if ($flashMessenger->hasMessages()) {
            foreach($flashMessenger->getMessages() as $flashMessage) {
                $messages[] = [
                    'type' => 'warning',
                    'message' => $flashMessage
                ];
            }
        }

        if (!$can_edit) {
            $permissions = $this->_repository->findDatasetPermissions($id);
            $accessRequests = json_decode($this->_stream_repository->getAccessRequests($dataset->uuid,$this->identity()));
            $message = "Dataset: " . $id;
            return new ViewModel([
                'message' => $message,
                'messages' => $messages,
                'dataset' => $dataset,
                'permissions' => $permissions,
                'features' => $this->datasetsFeatureManager()->getFeatures($id),
                'accessRequests' => $accessRequests,
                'user_id' => $user_id,
            ]);
        }
        else {
            return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
        }
    }

    public function sendAccessRequestAction () {
        $id = (int) $this->params()->fromRoute('id', 0);
        $dataset = $this->_repository->findDataset($id);
        $user_id = $this->currentUser()->getId();

        if($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();

            $fromEmail = $this->config['email']['from-email'];
            $fromLabel = $this->config['email']['from-label'];
            $ownerDetails = $this->_repository->getDatasetOwner($id);
            $toEmail = $ownerDetails['email'];
            $toLabel = $ownerDetails['full_name'];
            $accessControlLink = $this->url( 'dataset', ['action' => 'permissions-details', 'id' => $dataset->id], ['query' => ''] );

            $accessLevelLabel = '';
            $accessLevelCode = '';
            switch ($data['accessLevel']) {
                case 'READ':
                    $accessLevelLabel = 'Read - Will be able to register read-only keys on the dataset';
                    $accessLevelCode = 'r';
                    break;
                case 'WRITE':
                    $accessLevelLabel = 'Write - Will be able to register write-only keys on the dataset';
                    $accessLevelCode = 'w';
                    break;
                case 'READWRITE':
                    $accessLevelLabel = 'Read/Write - Will be able to register either read, write or read/write keys on the dataset';
                    $accessLevelCode = 'a';
                    break;
                case 'MANAGE':
                    $accessLevelLabel = 'Manage - Will have full admin access to the dataset, including managing permissions and key access';                    $accessLevelCode = 'r';
                    $accessLevelCode = 'g';
                    break;
                default:
                    $accessLevelLabel = 'unknown';
                    $accessLevelCode = '';
            }

            // BUILD EMAIL REQUEST BODY
            $bodyHtml = $this->viewRenderer->render(
                'mkdf/datasets/email/access-request',
                [
                    'datasetId'         => $id,
                    'user'              => $this->identity(),
                    'datasetTitle'      => $dataset->title,
                    'datasetUuid'       => $dataset->uuid,
                    'accessLevelLabel'  => $accessLevelLabel,
                    'requestDescription'=> $data['description'],
                ]);
            $subject = "Linked Data Hub access request";

            // ADD REQUEST TO REQUESTS DATASET
            $this->_stream_repository->createAccessRequest ($dataset->uuid, $this->identity(), $accessLevelCode, $data['description']);

            // SEND EMAIL TO DATASET OWNER/MANAGER(S)
            $this->_sendEmail($subject, $bodyHtml, $fromEmail, $fromLabel, $toEmail, $toLabel);

            $this->flashMessenger()->addMessage('An email has been sent to the dataset manager(s) to inform them of your request.');
            return $this->redirect()->toRoute('dataset', ['action'=>'permissions-request', 'id' => $id]);
        }
        else {
            $this->flashMessenger()->addMessage('Error: Unable to make dataset access request, missing form data.');
            return $this->redirect()->toRoute('dataset', ['action'=>'permissions-request', 'id' => $id]);
        }
    }

    public function accessRequestRespondAction () {
        $id = (int) $this->params()->fromRoute('id', 0);
        $dataset = $this->_repository->findDataset($id);
        $requestUser = $this->params()->fromQuery('user', null);
        $requestAccessLevel = $this->params()->fromQuery('accessLevel', null);
        $arId = $this->params()->fromQuery('arId', null);

        $user_id = $this->currentUser()->getId();
        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);
        $can_view = $this->_permissionManager->canView($dataset,$user_id);
        $can_read = $this->_permissionManager->canRead($dataset,$user_id);
        $can_write = $this->_permissionManager->canWrite($dataset,$user_id);

        $messages = [];
        $flashMessenger = $this->flashMessenger();
        if ($flashMessenger->hasMessages()) {
            foreach($flashMessenger->getMessages() as $flashMessage) {
                $messages[] = [
                    'type' => 'warning',
                    'message' => $flashMessage
                ];
            }
        }

        if ($can_edit) {

            switch ($requestAccessLevel) {
                case 'a':
                    $accessLevelLabel = 'Read/Write';
                    break;
                case 'r':
                    $accessLevelLabel = 'Read';
                    break;
                case 'w':
                    $accessLevelLabel = 'Write';
                    break;
                case 'g':
                    $accessLevelLabel = 'Manage';
                    break;
                default:
                    $accessLevelLabel = 'unknown';
            }

            if($this->getRequest()->isPost()) {
                $data = $this->params()->fromPost();

                $description = $data['description'];
                $decisionAccept = $data['decision'];



                if ($decisionAccept == "APPROVE") {
                    // APPROVE logic
                    // Check if user is already in ACL
                    $requestUserId =  $this->userIdFromEmail($requestUser);
                    if (!$this->_permissionManager->hasCustomAccess($dataset,$requestUserId))  {
                        $defaultPerms = $this->_repository->findDatasetRolePermission($id, -1);
                        //add custom user with default "logged in" permissions
                        $this->_repository->createDatasetPermission($id,$requestUserId,$defaultPerms['v'],$defaultPerms['r'],$defaultPerms['w'],0,$defaultPerms['g']);
                    }
                    // now update permissions
                    if ($requestAccessLevel == 'a'){
                        // 'a' is a shortcut for both 'r' and 'w'
                        $this->_repository->updateDatasetPermission($id, $requestUserId, 'r', 1);
                        $this->_repository->updateDatasetPermission($id, $requestUserId, 'w', 1);
                    }
                    else {
                        $this->_repository->updateDatasetPermission($id, $requestUserId, $requestAccessLevel, 1);
                    }

                    // BUILD EMAIL  BODY
                    $bodyHtml = $this->viewRenderer->render(
                        'mkdf/datasets/email/access-request-accepted',
                        [
                            'datasetId'         => $id,
                            'datasetTitle'      => $dataset->title,
                            'datasetUuid'       => $dataset->uuid,
                            'accessLevelLabel'  => $accessLevelLabel,
                            'responseDescription'=> $description,
                        ]);
                    $subject = "Linked Data Hub access request approved";

                    // Process the approval
                    $this->_stream_repository->approveAccessRequest($arId,$description);

                    // SEND EMAIL TO REQUESTER
                    $fromEmail = $this->config['email']['from-email'];
                    $fromLabel = $this->config['email']['from-label'];
                    $this->_sendEmail($subject, $bodyHtml, $fromEmail, $fromLabel, $requestUser, $requestUser);

                    $this->flashMessenger()->addMessage('Approved request: '.$requestUser.' ('.$accessLevelLabel.' access)');
                    return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
                }
                else {
                    // REJECT logic
                    // BUILD EMAIL  BODY
                    $bodyHtml = $this->viewRenderer->render(
                        'mkdf/datasets/email/access-request-rejected',
                        [
                            'datasetId'         => $id,
                            'datasetTitle'      => $dataset->title,
                            'datasetUuid'       => $dataset->uuid,
                            'accessLevelLabel'  => $accessLevelLabel,
                            'responseDescription'=> $description,
                        ]);
                    $subject = "Linked Data Hub access request rejected";

                    // Update access request table
                    $this->_stream_repository->rejectAccessRequest($arId,$description);

                    // SEND EMAIL TO REQUESTER
                    $fromEmail = $this->config['email']['from-email'];
                    $fromLabel = $this->config['email']['from-label'];
                    $this->_sendEmail($subject, $bodyHtml, $fromEmail, $fromLabel, $requestUser, $requestUser);

                    $this->flashMessenger()->addMessage('Rejected request: '.$requestUser.' ('.$accessLevelLabel.' access)');
                    return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
                }
            }
            else {
                // Not a POST request, display the request response form.
                return new ViewModel([
                    'messages' => $messages,
                    'dataset' => $dataset,
                    'features' => $this->datasetsFeatureManager()->getFeatures($id),
                    'user_id' => $user_id,
                    'requestUser' => $requestUser,
                    'requestAccessLevel' => $requestAccessLevel,
                    'requestAccessLevelLabel' => $accessLevelLabel,
                    'arId' => $arId,
                ]);
            }
        }
        else {
            // (!$can_edit)
            $this->flashMessenger()->addMessage('Unauthorised to respond to access requests.');
            return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
        }
    }
    
    public function permissionsDetailsAction() {
        $id = (int) $this->params()->fromRoute('id', 0);
        $dataset = $this->_repository->findDataset($id);
        $user_id = $this->currentUser()->getId();
        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);
        $keys = null;

        $messages = [];
        $flashMessenger = $this->flashMessenger();
        if ($flashMessenger->hasMessages()) {
            foreach($flashMessenger->getMessages() as $flashMessage) {
                $messages[] = [
                    'type' => 'warning',
                    'message' => $flashMessage
                ];
            }
        }

        if ($can_edit) {
            $keys = $this->_keys_repository->allDatasetKeys($id);
            $permissions = $this->_repository->findDatasetPermissions($id);
            $accessRequests = json_decode($this->_stream_repository->getAccessRequests($dataset->uuid,null));
            $message = "Dataset: " . $id;
            return new ViewModel([
                'message' => $message,
                'messages' => $messages,
                'dataset' => $dataset,
                'permissions' => $permissions,
                'features' => $this->datasetsFeatureManager()->getFeatures($id),
                'keys' => $keys,
                'accessRequests' => $accessRequests,
                'user_id' => $user_id,
            ]);
        }
        else {
            return $this->redirect()->toRoute('dataset', ['action'=>'permissions-request', 'id' => $id]);
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
                    $this->flashMessenger()->addMessage('No such user - '.$data['inputEmail']);
                    return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
                }

                if ($this->_permissionManager->hasCustomAccess($dataset,$userId)) {
                    $this->flashMessenger()->addMessage('Unable to add user to dataset permissions - user already exists. Please make changes by amending existing permissions.');
                    return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
                }
                else {
                    //INSERT PERMISSIONS HERE...
                    $this->_repository->createDatasetPermission($id,$userId,0,0,0,0,0);

                    $this->flashMessenger()->addMessage('User '.$data['inputEmail'].' added to dataset permissions.');
                    return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
                }


            }
            else {
                $this->flashMessenger()->addMessage('Unable to add user to dataset permissions - error with form data');
                return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
            }

        }else{
            $this->flashMessenger()->addMessage('Unauthorised to edit dataset permissions.');
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
            $this->flashMessenger()->addMessage('Incorrect parameters supplied.');
            return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
        }

        //Check for changes that are forbidden
        //Dataset owner - permissions cannot be changed
        if ($roleId == 0) {
            $this->flashMessenger()->addMessage('Permissions cannot be changed for this user.');
            return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
        }

        //Logged in users, only 'v' and 'r' can be changed
        if ($roleId == -1 && !($permission == 'v' || $permission == 'r')) {
            $this->flashMessenger()->addMessage('These permissions cannot be changed for this user.');
            return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
        }

        //Anonymous users, only 'v' can be changed
        if ($roleId == -2 && !($permission == 'v')) {
            $this->flashMessenger()->addMessage('These permissions cannot be changed for this user.');
            return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
        }

        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);
        $messages = [];
        if($can_edit){
            $this->_repository->updateDatasetPermission($id, $roleId, $permission, (int)$action);

            $this->flashMessenger()->addMessage('Permissions updated.');
            return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
        }else{
            $this->flashMessenger()->addMessage('Unauthorised to edit dataset permissions.');
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
            $this->flashMessenger()->addMessage('Incorrect parameters supplied.');
            return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
        }

        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);
        $messages = [];
        if($can_edit){
            $this->_repository->deleteDatasetPermissions($id, $roleId);

            $this->flashMessenger()->addMessage('Permissions deleted.');
            return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
        }else{
            $this->flashMessenger()->addMessage('Unauthorised to edit dataset permissions.');
            return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
        }
    }

    public function enablekeyAction ()
    {
        $user_id = $this->currentUser()->getId();
        $id = (int) $this->params()->fromRoute('id', 0);
        $dataset = $this->_repository->findDataset($id);
        $keyPassed = $this->params()->fromQuery('key', null);

        //Does the user have edit rights on this dataset? If not, fail.
        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);
        if (!$can_edit) {
            $this->flashMessenger()->addMessage('Disable key failed: You do not have access to manage this dataset\'s permissions.');
            return $this->redirect()->toRoute('dataset', ['action'=>'details', 'id' => $id]);
        }

        // Enable key access here...
        $keyReturned = $this->_keys_repository->getKeyUuidFromId($keyPassed);
        $keyUUID = $keyReturned['uuid'];
        try {
            $newPermission = $this->_keys_repository->restoreKeyPermission($keyPassed, $id);
        }
        catch(Exception $e) {
            $message = 'Error: ' .$e->getMessage();
            $this->flashMessenger()->addMessage($message);
            return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
        }
        $this->_stream_repository->setPermission($dataset->uuid, $keyUUID, $newPermission);
        //$this->_keys_repository->setKeyPermission($keyPassed, $id, 'd');

        $this->flashMessenger()->addMessage('The selected key has been reactived on this dataset.');
        return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
    }

    public function disablekeyAction () {
        $user_id = $this->currentUser()->getId();
        $id = (int) $this->params()->fromRoute('id', 0);
        $dataset = $this->_repository->findDataset($id);
        $keyPassed = $this->params()->fromQuery('key', null);
        $token = $this->params()->fromQuery('token', null);

        //Does the user have edit rights on this dataset? If not, fail.
        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);
        if (!$can_edit) {
            $this->flashMessenger()->addMessage('Disable key failed: You do not have access to manage this dataset\'s permissions.');
            return $this->redirect()->toRoute('dataset', ['action'=>'details', 'id' => $id]);
        }

        if (is_null($token)) {
            $token = uniqid(true);
            $container = new Container('Disable_Key');
            $container->delete_token = $token;
            $messages[] = [
                'type'=> 'warning',
                'message' => 'Are you sure you want to disable this key\'s access to the dataset? Applications will no longer have access to the dataset with this key.'
            ];
            return new ViewModel(
                [
                    'dataset' => $dataset,
                    'token' => $token,
                    'key' => $keyPassed,
                    'messages' => $messages
                ]
            );
        }
        else {
            $container = new Container('Disable_Key');
            $valid_token = ($container->delete_token == $token);
            if ($valid_token) {
                // Disable key access here...
                $keyReturned = $this->_keys_repository->getKeyUuidFromId($keyPassed);
                $keyUUID = $keyReturned['uuid'];
                $this->_stream_repository->removePermission($dataset->uuid, $keyUUID);
                // Set key to disabled state in keys repository
                $this->_keys_repository->setKeyPermission($keyPassed, $id, 'd');
                $this->flashMessenger()->addMessage('Disabled key access for dataset.');
                return $this->redirect()->toRoute('dataset', ['action'=>'permissions-details', 'id' => $id]);
            }
        }

        // IS TOKEN NULL, present confirmation page
        // ELSE
        // CHECK TOKEN
        // STREAM REPOSITORY -> REMOVE PERMISSION
        // KEYS REPOSITORY -> SET PERMISSIONS TO 'D'
        // RETURN WITH SUCCESS MESSAGE



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
                $form->setData($data);
                if($form->isValid()){
                    // Get User Id
                    $user_id = $this->currentUser()->getId();
                    // Write data
                    $output = $this->_repository->updateDataset($id, $data['title'], $data['description']);
                    // Redirect to "view" page
                    $this->flashMessenger()->addMessage('The dataset was updated succesfully.');
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
            $this->flashMessenger()->addMessage('Unauthorised to edit dataset.');
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
            $this->flashMessenger()->addMessage('The dataset was deleted successfully.');
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
            $messages[] = [ 'type'=> 'danger', 'message' =>
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
            'url_params' => $this->params()->fromQuery(),
            'features' => $this->accountFeatureManager()->getFeatures($userId),
        ]);
    }

    public function geospatialDetailsAction() {
        $id = (int) $this->params()->fromRoute('id', 0);
        $format = $this->params()->fromQuery('f', "");
        $dataset = $this->_repository->findDataset($id);
        $user_id = $this->currentUser()->getId();
        //$permissions = $this->_repository->findDatasetPermissions($id);
        $metadata = $this->_repository->findDatasetGeospatial($id);
        $lat = 0;
        $lon = 0;
        foreach ($metadata as $metaItem) {
            if ($metaItem['name'] == 'latitude') {
                $lat = $metaItem['value'];
            }
            if ($metaItem['name'] == 'longitude') {
                $lon = $metaItem['value'];
            }
        }
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
            if ($format == "json"){
                $geojson = [
                    'type' => 'FeatureCollection',
                    'name' => 'Datafeeds',
                    'crs' => [
                        'type' => 'name',
                        'properties' => [
                            'name' => 'urn:ogc:def:crs:OGC:1.3:CRS84'
                        ],
                    ],
                    'features' => []
                ];
                $feature = [
                    'type' => 'Feature',
                    'properties' => [
                        'title' => $dataset->title,
                        'uuid' => $dataset->uuid,
                        'name' => $dataset->title
                    ],
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [(float)$lon, (float)$lat]
                    ],
                ];

                array_push($geojson['features'], $feature);

                if ($lat == 0 && $lon == 0){
                    return new JsonModel([]);
                }
                else {
                    return new JsonModel($geojson);
                }
            }
            else {
                return new ViewModel([
                    'message' => $message,
                    'dataset' => $dataset,
                    'metadata' => $metadata,
                    'features' => $this->datasetsFeatureManager()->getFeatures($id),
                    'actions' => $actions
                ]);
            }
        }
        else {
            $this->flashMessenger()->addMessage('Unauthorised to view dataset.');
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
                $form->setData($data);
                if($form->isValid()){
                    // Write data
                    $output = $this->_repository->updateDatasetGeospatial($id, $data['latitude'], $data['longitude']);
                    // Redirect to "view" page
                    $this->flashMessenger()->addMessage('Location information updated succesfully.');
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
                    'dataset_id' => $id,
                    'dataset' => $dataset
                ]
            );
        }
        else {
            $this->flashMessenger()->addMessage('Unauthorised to edit dataset.');
            return $this->redirect()->toRoute('dataset', ['action'=>'details', 'id'=>$id]);
        }
    }

    public function attributionEditAction () {
        $id = (int) $this->params()->fromRoute('id', 0);
        $dataset = $this->_repository->findDataset($id);
        $user_id = $this->currentUser()->getId();
        $actions = [];
        $can_view = $this->_permissionManager->canView($dataset,$user_id);
        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);
        if ($can_edit) {
            if($this->getRequest()->isPost()) {
                $data = $this->params()->fromPost();
                //print_r($data);

                // Write data
                $output = $this->_repository->updateDatasetAttribution($id, $data['attribution']);
                // Redirect to "view" page
                $this->flashMessenger()->addMessage('Dataset attribution updated succesfully.');
                return $this->redirect()->toRoute('dataset', ['action'=>'ownership-details', 'id'=>$id]);

            } else{
                $attribution = $this->_repository->getSingleMetaValue($id, 'attribution');
                return new ViewModel([
                    'dataset' => $dataset,
                    'attribution' => $attribution,
                    'features' => $this->datasetsFeatureManager()->getFeatures($id),
                    'actions' => $actions,
                    'can_edit' => $can_edit,
                ]);
            }
        }
        else {
            $this->flashMessenger()->addMessage('Unauthorised to edit dataset.');
            return $this->redirect()->toRoute('dataset', ['action'=>'index']);
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
                    ['type'=>'warning','label'=>'Edit attribution', 'icon'=>'edit', 'target'=> 'dataset', 'params'=> ['id' => $dataset->id, 'action' => 'attribution-edit']],
                ]
            ];
        }
        if ($can_view) {
            $attribution = $this->_repository->getSingleMetaValue($id, 'attribution');
            $licences = $this->_repository->getDatasetLicenses($id);
            $owners = $this->_repository->getDatasetOwners($id);
            //$ownership = $this->_repository->getOwnership($id);
            return new ViewModel([
                'dataset' => $dataset,
                'licences' => $licences,
                'owners' => $owners,
                'attribution' => $attribution,
                'features' => $this->datasetsFeatureManager()->getFeatures($id),
                'actions' => $actions,
                'can_edit' => $can_edit,
                'licenceList' => $this->_repository->getAllLicences(),
                'ownerList' => $this->_repository->getOwnerNames(),
            ]);
        }
        else {
            $this->flashMessenger()->addMessage('Unauthorised to view dataset.');
            return $this->redirect()->toRoute('dataset', ['action'=>'index']);
        }
    }

    public function licenceAddAction() {
        $id = (int) $this->params()->fromRoute('id', 0);
        $licenceId = $this->params()->fromQuery('licence_id', '');
        $user_id = $this->currentUser()->getId();
        $dataset = $this->_repository->findDataset($id);
        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);
        if ($can_edit) {
            $outcome = $this->_repository->addDatasetLicence($id, $licenceId);
            if ($outcome == 1){
                $this->flashMessenger()->addSuccessMessage('The licence was added to the dataset.');
            }
            else {
                $this->flashMessenger()->addSuccessMessage('The licence is already assigned to the dataset.');
            }
            return $this->redirect()->toRoute('dataset', ['action'=>'ownership-details', 'id' => $id]);
        }
        else {
            $this->flashMessenger()->addMessage('Unauthorised to edit dataset licences.');
            return $this->redirect()->toRoute('dataset', ['action'=>'ownership-details', 'id' => $id]);
        }
    }

    public function licenceDeleteAction() {
        $id = (int) $this->params()->fromRoute('id', 0);
        $licenceId = $this->params()->fromQuery('licence_id', '');
        $user_id = $this->currentUser()->getId();
        $dataset = $this->_repository->findDataset($id);

        //Check for missing params
        if ($licenceId == '') {
            $this->flashMessenger()->addMessage('Incorrect parameters supplied.');
            return $this->redirect()->toRoute('dataset', ['action'=>'ownership-details', 'id' => $id]);
        }

        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);
        $messages = [];
        if($can_edit){
            $this->_repository->deleteDatasetLicence($licenceId);

            $this->flashMessenger()->addSuccessMessage('License removed.');
            return $this->redirect()->toRoute('dataset', ['action'=>'ownership-details', 'id' => $id]);
        }else{
            $this->flashMessenger()->addMessage('Unauthorised to edit dataset licences.');
            return $this->redirect()->toRoute('dataset', ['action'=>'ownership-details', 'id' => $id]);
        }
    }

    public function ownerAddAction() {
        $id = (int) $this->params()->fromRoute('id', 0);
        //$ownerName = $this->params()->fromQuery('inputOwner', '');
        $user_id = $this->currentUser()->getId();
        $dataset = $this->_repository->findDataset($id);
        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);

        if(!$this->getRequest()->isPost()) {
            $this->flashMessenger()->addMessage('Incorrect parameters supplied.');
            return $this->redirect()->toRoute('dataset', ['action'=>'ownership-details', 'id' => $id]);
        }
        $ownerName = $this->params()->fromPost('inputOwner', '');
        //Check for missing params
        if ($ownerName == '') {
            $this->flashMessenger()->addMessage('Incorrect parameters supplied.');
            return $this->redirect()->toRoute('dataset', ['action'=>'ownership-details', 'id' => $id]);
        }

        if ($can_edit) {
            $outcome = $this->_repository->addDatasetOwner($id, $ownerName);
            if ($outcome == 1){
                $this->flashMessenger()->addMessage('The owner was added to the dataset.');
            }
            else {
                $this->flashMessenger()->addMessage('The owner is already assigned to the dataset.');
            }
            return $this->redirect()->toRoute('dataset', ['action'=>'ownership-details', 'id' => $id]);
        }
        else {
            $this->flashMessenger()->addMessage('Unauthorised to edit dataset owners.');
            return $this->redirect()->toRoute('dataset', ['action'=>'ownership-details', 'id' => $id]);
        }
    }

    public function ownerDeleteAction() {
        $id = (int) $this->params()->fromRoute('id', 0);
        $datasetOwnerId = $this->params()->fromQuery('owner_id', '');
        $user_id = $this->currentUser()->getId();
        $dataset = $this->_repository->findDataset($id);

        //Check for missing params
        if ($datasetOwnerId == '') {
            $this->flashMessenger()->addMessage('Incorrect parameters supplied.');
            return $this->redirect()->toRoute('dataset', ['action'=>'ownership-details', 'id' => $id]);
        }

        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);
        $messages = [];
        if($can_edit){
            $this->_repository->deleteDatasetOwner($datasetOwnerId);

            $this->flashMessenger()->addMessage('Owner removed.');
            return $this->redirect()->toRoute('dataset', ['action'=>'ownership-details', 'id' => $id]);
        }else{
            $this->flashMessenger()->addMessage('Unauthorised to edit dataset owners.');
            return $this->redirect()->toRoute('dataset', ['action'=>'ownership-details', 'id' => $id]);
        }
    }

    public function licenceAction() {
        $id = (int) $this->params()->fromRoute('id', 0);
        $licence = $this->_repository->getLicence($id);
        return new ViewModel([
            'licenceId' => $id,
            'licence'   => $licence
        ]);
    }

    public function notificationsDetailsAction() {
        $id = (int) $this->params()->fromRoute('id', 0);
        //$format = $this->params()->fromQuery('f', "");
        $dataset = $this->_repository->findDataset($id);
        $user_id = $this->currentUser()->getId();

        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);

        if ($can_edit) {
            $notificationsDataset = $this->config['notifications']['notifications-dataset'];
            $notificationsKey = $this->config['notifications']['notifications-key'];
            $query = [
                'dataset' => $dataset->uuid,
                //'job-type' => 'PRIVACY-VIOLATION'
                '$or' => [['job-type' => 'PRIVACY-VIOLATION'],['job-type' => 'TOXICITY-NOTIFICATION']]
            ];
            $queryJSON = json_encode($query);
            $response = json_decode($this->_stream_repository->getDocuments ($notificationsDataset,999, null, $queryJSON), True);

            return new ViewModel([
                'features' => $this->datasetsFeatureManager()->getFeatures($id),
                'dataset' => $dataset,
                'notifications' => $response,
            ]);
        }
        else {
            $this->flashMessenger()->addMessage('Unauthorised to view dataset notifications.');
            return $this->redirect()->toRoute('dataset', ['action'=>'details', 'id'=>$id]);
        }

    }

    public function notificationsUpdateAction() {
        $id = (int) $this->params()->fromRoute('id', 0);
        $dataset = $this->_repository->findDataset($id);
        $notificationsDataset = $this->config['notifications']['notifications-dataset'];
        $notificationsKey = $this->config['notifications']['notifications-key'];
        $developmentMode = $this->config['notifications']['development'];
        $devEmails = $this->config['notifications']['development-emails'];

        $ownerDetails = $this->_repository->getDatasetOwner($id);
        $toEmail = $ownerDetails['email'];
        $toLabel = $ownerDetails['full_name'];
        $fromEmail = $this->config['email']['from-email'];
        $fromLabel = $this->config['email']['from-label'];

        /*
             * mogodb query:
             * {"$or":[{"emailed":{"$exists":false}},{"emailed":false}]}
             */
        $query = [
            'dataset' => $dataset->uuid,
            'job-type' => 'PRIVACY-VIOLATION',
            '$or' => [
                [
                    'emailed' => false
                ],
                [
                    'emailed' => [
                        '$exists' => false
                    ]
                ]
            ]
        ];
        $queryJSON = json_encode($query);
        //var_dump($queryJSON);
        $response = json_decode($this->_stream_repository->getDocuments ($notificationsDataset,999, null, $queryJSON), True);
        foreach ($response as $item) {
            //Check debug/dev status - are we sending to everyone right now?
            //Get dataset owner (done above)
            //build email text
            //send email - $this->_sendEmail ($subject, $bodyHTML, $from, $fromLabel, $to, $toLabel);
            //mark notification as sent in notification entry and push back to LDH API.

            // Only email if we're not in dev mode or the dataset owners are in the dev email list
            if (!$developmentMode || in_array($toEmail, $devEmails)) {
                // BUILD EMAIL REQUEST BODY
                $bodyHTML = $this->viewRenderer->render(
                    'mkdf/datasets/email/pii-notification',
                    [
                        'datasetId'     => $id,
                        'datasetTitle'  => $dataset->title,
                        'datasetUuid'   => $dataset->uuid,
                        'docId'         => $item['document ID'],
                    ]);
                $subject = "SPICE Linked Data Hub - Content scanner alert";
                $this->_sendEmail ($subject, $bodyHTML, $fromEmail, $fromLabel, $toEmail, $toLabel);
                $item['emailed'] = True;

                // push back to LDH with 'emailed' attribute set
                $this->_stream_repository->updateDocument ($notificationsDataset,json_encode($item), $item['_id']);
            }
        }
        return new ViewModel([
            'features' => $this->datasetsFeatureManager()->getFeatures($id),
            'dataset' => $dataset,
            'items' => $response,
            'bodyHtml' => $bodyHTML
        ]);
    }
}
