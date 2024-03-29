<?php

namespace MKDF\Datasets\Repository;

use MKDF\Datasets\Entity\Dataset;
use MKDF\Core\Entity\Bucket;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Adapter\Driver\ResultInterface;
use Zend\Db\ResultSet\ResultSet;


class MKDFDatasetRepository implements MKDFDatasetRepositoryInterface
{
    private $_config;
    private $_adapter;
    private $_queries;

    public function __construct($config)
    {
        $this->_config = $config;
        $this->_adapter = new Adapter([
            'driver'   => 'Pdo_Mysql',
            'database' => $this->_config['db']['dbname'],
            'username' => $this->_config['db']['user'],
            'password' => $this->_config['db']['password'],
            'host'     => $this->_config['db']['host'],
            'port'     => $this->_config['db']['port'],
            'charset'  => $this->_config['db']['charset'],
        ]);
        $this->buildQueries();
    }

    private function fp($param) {
        return $this->_adapter->driver->formatParameterName($param);
    }

    private function qi($param) {
        return $this->_adapter->platform->quoteIdentifier($param);
    }

    private function buildQueries(){
        $this->_queries = [
            'isReady'           => 'SELECT ID FROM dataset LIMIT 1',
            'allDatasets'       => 'SELECT id, title, description, uuid, user_id, date_created, date_modified FROM dataset ORDER BY date_created DESC',
            'datasetTypes'      => 'SELECT id, name, description FROM dataset_type',
            'allVisibleDatasets'=> 'SELECT DISTINCT d.id, d.title, d.description, d.type, t.name AS typelabel, d.uuid, d.user_id, d.date_created, d.date_modified '.
                                    'FROM '.
                                         'dataset d '.
                                         'JOIN dataset_permission dp ON d.id = dp.dataset_id '.
                                         'LEFT JOIN dataset_type t ON d.type = t.id '.
                                   'WHERE '.

                                        '('.
                                          '(dp.role_id = '.$this->fp('login_status').' AND dp.v = 1) '.
                                            ' OR '.
                                          '(d.user_id = '.$this->fp('user_id').' AND dp.role_id = '.$this->fp('logged_in_identifier').')'.
                                            ' OR '.
                                          '(dp.role_id = '.$this->fp('user_id').' AND dp.v = 1)'.
                                        ') ORDER BY d.date_created DESC ',
            'allVisibleDatasetsFilter'=> 'SELECT DISTINCT d.id, d.title, d.description, d.type, t.name AS typelabel, d.uuid, d.user_id, d.date_created, d.date_modified '.
                                        'FROM '.
                                            'dataset d '.
                                        'JOIN dataset_permission dp ON d.id = dp.dataset_id '.
                                        'LEFT JOIN dataset_type t ON d.type = t.id '.
                                        'LEFT JOIN dataset__metadata dm ON d.id = dm.dataset_id '.
                                        'WHERE '.

                                            '(d.title LIKE '.$this->fp('search_title').' OR d.description LIKE '.$this->fp('search_desc').' OR dm.value LIKE '.$this->fp('search_meta').') AND '.
                                            ' dm.meta_id = 4 AND '.
                                            '('.
                                                '(dp.role_id = '.$this->fp('login_status').' AND dp.v = 1) '.
                                                ' OR '.
                                                '(d.user_id = '.$this->fp('user_id').' AND dp.role_id = '.$this->fp('logged_in_identifier').')'.
                                                ' OR '.
                                                '(dp.role_id = '.$this->fp('user_id').' AND dp.v = 1)'.
                                            ') ORDER BY d.date_created DESC ',
            'allVisibleDatasetLocations'=> 'SELECT d.id, d.title, dmlat.value as latitude, dmlon.value as longitude '.
' FROM dataset__metadata dmlat, dataset__metadata dmlon, dataset d '.
' JOIN dataset_permission dp ON d.id = dp.dataset_id '.
' WHERE '.
' d.id = dmlat.dataset_id AND '.
' d.id = dmlon.dataset_id AND '.
' dmlat.meta_id = 1 AND dmlon.meta_id = 2 AND '.

                '('.
                '(dp.role_id = '.$this->fp('login_status').' AND dp.v = 1) '.
                ' OR '.
                '(d.user_id = '.$this->fp('user_id').' AND dp.role_id = '.$this->fp('logged_in_identifier').')'.
                ' OR '.
                '(dp.role_id = '.$this->fp('user_id').' AND dp.v = 1)'.
                ') ',
            'userDatasets'      => 'SELECT d.id, d.title, d.description, d.uuid, d.user_id, d.date_created, d.date_modified, d.type, t.name AS typelabel FROM dataset d '.
                'LEFT JOIN dataset_type t ON d.type = t.id '.
                ' WHERE user_id = '.$this->fp('user_id').' ORDER BY date_created DESC',
            'oneDataset'        => 'SELECT d.id, d.title, d.description, d.uuid, d.user_id, t.name AS type '.
                ' FROM dataset d '.
                'LEFT JOIN dataset_type t ON d.type = t.id '.
                ' WHERE d.id = ' . $this->fp('id'),
            'datasetCount'      => 'SELECT COUNT(id) AS count FROM dataset',
            'insertDataset'     => 'INSERT INTO dataset (title, description, uuid, user_id, type) VALUES ('.$this->fp('title').', '.$this->fp('description').', '.$this->fp('uuid').', '.$this->fp('user_id').', '.$this->fp('type').')',
            'updateDataset'     => 'UPDATE dataset SET title = '.$this->fp('title').
                ', description = '.$this->fp('description'). ', date_modified =  CURRENT_TIMESTAMP '.
                ' WHERE id = ' .$this->fp('id'),
            'updateDatasetAttribution' => 'UPDATE dataset SET attribution = '.$this->fp('attribution').
                ' WHERE id = ' .$this->fp('dataset_id'),
            'deleteDataset'      => 'DELETE FROM dataset WHERE id = ' . $this->fp('id'),
            'deletePermissions' => 'DELETE FROM dataset_permission WHERE dataset_id = '. $this->fp('dataset_id'),
            'insertPermission'  => 'INSERT INTO dataset_permission (role_id, dataset_id, v, r, w, d, g) VALUES ('.
                $this->fp('role_id').', '.$this->fp('dataset_id').', '.$this->fp('v').', '.$this->fp('r').', '.$this->fp('w').', '.$this->fp('d').', '.$this->fp('g').')',
            'deletePermission'  => 'DELETE FROM dataset_permission WHERE role_id = '.$this->fp('role_id').' AND dataset_id = '.$this->fp('dataset_id'),
            'updatePermissions' => 'UPDATE dataset_permission SET %s = '.$this->fp('action').' WHERE '.
                'dataset_id = '.$this->fp('dataset_id').' AND '.
                'role_id = '.$this->fp('role_id'),
            'datasetPermissions' => 'SELECT p.role_id, p.dataset_id, p.v, p.r, p.w, p.d, p.g, u.email AS label '
                .'FROM dataset_permission p LEFT OUTER JOIN user u ON p.role_id = u.id '
                .' WHERE p.dataset_id='.$this->fp('dataset_id'),
            'datasetRolePermission' => 'SELECT v, r, w, d, g FROM dataset_permission WHERE '
                .' dataset_id = '.$this->fp('dataset_id').' AND role_id = '.$this->fp('role_id'),
            'datasetMetadata' => 'SELECT m.name, m.description, dm.value FROM dataset__metadata dm '.
                'JOIN metadata m ON dm.meta_id = m.id '.
                'WHERE dataset_id = '.$this->fp('dataset_id'),
            'singleMetaValue' => 'SELECT m.id AS meta_id, dm.id AS dataset_meta_id, m.name, m.description, dm.value FROM dataset__metadata dm '.
                'JOIN metadata m ON dm.meta_id = m.id '.
                'WHERE dataset_id = '.$this->fp('dataset_id').
                ' AND m.name = '.$this->fp('key'),
            'datasetGeospatial' => 'SELECT m.id AS meta_id, dm.id AS dataset_meta_id, m.name, m.description, dm.value FROM dataset__metadata dm '.
                'JOIN metadata m ON dm.meta_id = m.id '.
                'WHERE dataset_id = '.$this->fp('dataset_id').
                ' AND (m.name = "latitude" OR m.name = "longitude")',
            'insertDatasetMetadataByName' => 'INSERT INTO dataset__metadata (dataset_id, meta_id, value) '.
                'SELECT '.$this->fp('dataset_id').', id, '.$this->fp('value').' FROM metadata WHERE name = '.$this->fp('meta_name'),
            'updateDatasetMetadata' => 'UPDATE dataset__metadata SET value='.$this->fp('value').' WHERE id = '.$this->fp('dataset_meta_id'),
            'datasetLicences' => 'SELECT dl.id, l.name, l.description, l.id AS licence_id FROM licence l, dataset__licence dl WHERE '.
                'dl.dataset_id = '.$this->fp('dataset_id').' AND dl.licence_id = l.id',
            'datasetOwners' => 'SELECT d.id, o.name FROM owner o, dataset__owner d WHERE '.
                'd.dataset_id = '.$this->fp('dataset_id').' AND d.owner_id = o.id',
            'getDatasetOwner' => 'SELECT d.id FROM dataset__owner d, owner o WHERE d.dataset_id = '.$this->fp('dataset_id').
                ' AND o.name = '.$this->fp('owner_name').' AND d.owner_id = o.id',
            'insertDatasetOwner' => 'INSERT INTO dataset__owner (dataset_id, owner_id) '.
                'SELECT '.$this->fp('dataset_id').', id FROM owner WHERE name = '.$this->fp('owner_name'),
            'insertOwner' => 'INSERT INTO owner (name) VALUES ('.$this->fp('name').') '.
                'ON DUPLICATE KEY UPDATE name = '.$this->fp('name'),
            'deleteDatasetOwner' => 'DELETE FROM dataset__owner WHERE id = '.$this->fp('id'),
            'allDatasetLicences' => 'SELECT id, name, uri FROM licence',
            'getLicence'        => 'SELECT name, description, uri FROM licence WHERE id = '.$this->fp('id'),
            'allDatasetOwnerNames' => 'SELECT name FROM owner',
            'getDatasetLicence' => 'SELECT id FROM dataset__licence where dataset_id = '.$this->fp('dataset_id').
                ' AND licence_id = '.$this->fp('licence_id'),
            'insertDatasetLicence' => 'INSERT INTO dataset__licence (dataset_id, licence_id) VALUES ('.$this->fp('dataset_id').', '.$this->fp('licence_id').')',
            'deleteDatasetLicence' => 'DELETE FROM dataset__licence WHERE id = '.$this->fp('id'),
            'datasetOwnerDetails' => 'SELECT u.id, u.email, u.full_name FROM user u, dataset d WHERE u.id = d.user_id AND d.id = '.$this->fp('dataset_id'),
        ];
    }

    private function addQueryLimit($query, $limit) {
        return $query . ' LIMIT ' . $limit;
    }

    private function getQuery($query){
        return $this->_queries[$query];
    }

    public function findAllDatasets($userId = -1, $txtSearch = "", $limit = 0) {
        $datasetCollection = [];
        if ($userId > 0) {
            $loginStatus = -1; //signifies logged in, in roles table
        }
        else {
            $loginStatus = -2; //as per roles in roles table
            $userId = -2; //if not logged in, use -2 (anonymous) to query against the role permissions
        }

        if ($txtSearch != ""){
            $parameters = [
                'login_status'  => $loginStatus,
                'user_id'       => $userId,
                'logged_in_identifier' => -1,
                'search_title' => '%'.$txtSearch.'%',
                'search_desc' => '%'.$txtSearch.'%',
                'search_meta' => '%'.$txtSearch.'%'
            ];
            $query = $this->getQuery('allVisibleDatasetsFilter');
        }
        else{
            $parameters = [
                'login_status'  => $loginStatus,
                'user_id'       => $userId,
                'logged_in_identifier' => -1
            ];
            $query = $this->getQuery('allVisibleDatasets');
        }

        if ($limit > 0) {
            $query = $this->addQueryLimit($query, $limit);
        }
        $statement = $this->_adapter->createStatement($query);
        $result    = $statement->execute($parameters);
        if ($result instanceof ResultInterface && $result->isQueryResult()) {
            $resultSet = new ResultSet;
            $resultSet->initialize($result);
            foreach ($resultSet as $row) {
                $dataset = new Dataset();
                $dataset->setProperties($row);
                array_push($datasetCollection, $dataset);
            }
            return $datasetCollection;
        }
        return [];
    }

    public function findDatasetLocations ($userId) {
        $datasetCollection = [];
        if ($userId > 0) {
            $loginStatus = -1; //signifies logged in, in roles table
        }
        else {
            $loginStatus = -2; //as per roles in roles table
            $userId = -2; //if not logged in, use -2 (anonymous) to query against the role permissions
        }
        $parameters = [
            'login_status'  => $loginStatus,
            'user_id'       => $userId,
            'logged_in_identifier' => -1
        ];
        $query = $this->getQuery('allVisibleDatasetLocations');
        $statement = $this->_adapter->createStatement($query);
        $result    = $statement->execute($parameters);
        if ($result instanceof ResultInterface && $result->isQueryResult()) {
            $resultSet = new ResultSet;
            $resultSet->initialize($result);
            foreach ($resultSet as $row) {
                array_push($datasetCollection, $row);
            }
        }
        return $datasetCollection;
    }

    public function findUserDatasets($userId = 0) {
        $datasetCollection = [];
        if ($userId > 0) {
            $parameters = [
                'user_id'       => $userId
            ];
            $query = $this->getQuery('userDatasets');
            $statement = $this->_adapter->createStatement($query);
            $result    = $statement->execute($parameters);
            if ($result instanceof ResultInterface && $result->isQueryResult()) {
                $resultSet = new ResultSet;
                $resultSet->initialize($result);
                foreach ($resultSet as $row) {
                    array_push($datasetCollection, $row);
                }
            }
        }
        return $datasetCollection;
    }

    /**
     * @param $id int
     * @return Dataset
     */
    public function findDataset($id) {
        $parameters = [
            'id'   => $id
        ];
        $statement = $this->_adapter->createStatement($this->getQuery('oneDataset'));
        $result    = $statement->execute($parameters);
        $dataset = new Dataset();
        if ($result instanceof ResultInterface && $result->isQueryResult()) {
            //$resultSet = new ResultSet;
            //$resultSet->initialize($result);
            $dataset->setProperties($result->current());
        }
        return $dataset;
    }

    public function getDatasetOwner($id) {
        $parameters = [
            'dataset_id' => $id
        ];
        $statement = $this->_adapter->createStatement($this->getQuery('datasetOwnerDetails'));
        $result    = $statement->execute($parameters);
        $currentResult = null;
        if ($result instanceof ResultInterface && $result->isQueryResult()) {
            $currentResult = $result->current();
        }
        return $currentResult;
    }

    public function findDatasetTypes() {
        $datasetTypes = [];
        $parameters = [];
        $statement = $this->_adapter->createStatement($this->getQuery('datasetTypes'));
        $result    = $statement->execute($parameters);
        if ($result instanceof ResultInterface && $result->isQueryResult()) {
            $resultSet = new ResultSet;
            $resultSet->initialize($result);
            foreach ($resultSet as $row) {
                array_push($datasetTypes, $row);
            }
        }
        return $datasetTypes;
    }

    public function getDatasetCount() {
        $parameters = [];
        $statement = $this->_adapter->createStatement($this->getQuery('datasetCount'));
        $result    = $statement->execute($parameters);
        $datasetCount = 0;
        if ($result instanceof ResultInterface && $result->isQueryResult()) {
            $currentResult = $result->current();
            $datasetCount = (int)$currentResult['count'];
        }
        return $datasetCount;
    }

    public function findDatasetRolePermission($datasetID, $roleID) {
        $permissions = [
            'v' => 0,
            'r' => 0,
            'w' => 0,
            'd' => 0,
            'g' => 0
        ];
        //$permissions = false;
        $parameters = [
            'dataset_id'    => $datasetID,
            'role_id'       => $roleID
        ];
        $statement = $this->_adapter->createStatement($this->getQuery('datasetRolePermission'));
        $result    = $statement->execute($parameters);
        if ($result instanceof ResultInterface && $result->isQueryResult()) {
            $permissions = $result->current();
            //print_r($permissions);
        }
        return $permissions;
    }

    public function findDatasetPermissions($id) {
        $permissions = [];
        $parameters = [
            'dataset_id'   => $id
        ];
        $statement = $this->_adapter->createStatement($this->getQuery('datasetPermissions'));
        $result    = $statement->execute($parameters);
        if ($result instanceof ResultInterface && $result->isQueryResult()) {
            $resultSet = new ResultSet;
            $resultSet->initialize($result);
            foreach ($resultSet as $row) {
                /*
                 * LABEL is set as the user's email address via an OUTER JOIN, so is null if the role_id doesn't match
                 * a user. For role_id 0, 01, 02, set teh labels manually below...
                 */
                switch ($row->role_id) {
                    case 0:
                        $row->label = 'Dataset owner';
                        break;
                    case -1:
                        $row->label = 'Logged in user';
                        break;
                    case -2:
                        $row->label = 'Anonymous';
                        break;
                    default:
                        //do nothing
                }
                $b = new Bucket();
                $b->setProperties($row);
                array_push($permissions, $b);
            }
            return $permissions;
        }
        return [];
    }


    public function insertDataset($data){
        //CREATE DATASET
        $data['uuid'] = Dataset::genUuid();
        $statement = $this->_adapter->createStatement($this->getQuery('insertDataset'));
        $statement->execute($data);
        $id = $this->_adapter->getDriver()->getLastGeneratedValue();
        return $id;
    }

    public function findDatasetMetadata($id){
        $metadata = [];
        $parameters = [
            'dataset_id' => $id,
        ];
        $statement = $this->_adapter->createStatement($this->getQuery('datasetMetadata'));
        $result    = $statement->execute($parameters);
        if ($result instanceof ResultInterface && $result->isQueryResult()) {
            $resultSet = new ResultSet;
            $resultSet->initialize($result);
            foreach ($resultSet as $row) {
                array_push($metadata, $row);
            }
        }
        return $metadata;
    }

    public function findDatasetGeospatial($id){
        $metadata = [];
        $parameters = [
            'dataset_id' => $id,
        ];
        $statement = $this->_adapter->createStatement($this->getQuery('datasetGeospatial'));
        $result    = $statement->execute($parameters);
        if ($result instanceof ResultInterface && $result->isQueryResult()) {
            $resultSet = new ResultSet;
            $resultSet->initialize($result);
            foreach ($resultSet as $row) {
                array_push($metadata, $row);
            }
        }
        return $metadata;
    }

    public function updateDatasetGeospatial($id, $lat, $lon) {
        //Check if geospatial metadata already set, and update it if so. Else insert appropriate metadata
        $metadata = $this->findDatasetGeospatial($id);
        if (count($metadata) == 0){
            //INSERT new metadata entries

            $parameters = [
                'meta_name' => 'latitude',
                'dataset_id' => $id,
                'value' => $lat,
            ];
            $statement = $this->_adapter->createStatement($this->getQuery('insertDatasetMetadataByName'));
            $result    = $statement->execute($parameters);
            $parameters = [
                'meta_name' => 'longitude',
                'dataset_id' => $id,
                'value' => $lon,
            ];
            $statement = $this->_adapter->createStatement($this->getQuery('insertDatasetMetadataByName'));
            $result    = $statement->execute($parameters);

        }
        else {
            //UPDATE existing metadata entries
            foreach ($metadata as $row) {
                if ($row['name'] == 'latitude') {
                    $parameters = [
                        'dataset_meta_id' => $row['dataset_meta_id'],
                        'value' => $lat,
                    ];
                }
                else {
                    $parameters = [
                        'dataset_meta_id' => $row['dataset_meta_id'],
                        'value' => $lon,
                    ];
                }
                $statement = $this->_adapter->createStatement($this->getQuery('updateDatasetMetadata'));
                $result    = $statement->execute($parameters);
            }
        }
    }

    /**
     * Set permissions for a given dataset. If no permissions passed, just create the default
     * set (everyone can view, logged in users can read/subscribe, owner has full access)
     * User_IDs:
     * 0 = owner
     * -1 = logged in
     * -2 - everyone/anonymous
     * Permissions/Actions:
     * r - Read/subscribe
     * w - Write/Push data
     * v - View listing
     * d - Delete
     * g - Grant permissions
     * @param int $dataset_id
     */
    public function setDefaultDatasetPermissions($dataset_id) {
        // Default permissions
        $permissions = [
            'owner'  => [
                'v'        => 1,
                'r'        => 1,
                'w'        => 1,
                'd'        => 1,
                'g'        => 1
            ],
            'user' => [
                'v'        => 1,
                'r'        => 1,
                'w'        => 0,
                'd'        => 0,
                'g'        => 0

            ],
            'anonymous' => [
                'v'        => 1,
                'r'        => 0,
                'w'        => 0,
                'd'        => 0,
                'g'        => 0
            ]
        ];

        // Default permissions from configuration (if exists)
        if ( array_key_exists('mkdf-datasets', $this->_config) &&
        array_key_exists('default_permissions', $this->_config['mkdf-datasets'])){
            $default_permissions = $this->_config['mkdf-datasets']['default_permissions'];
            // Configuration allows two keys: user and anonymous ('owner' could be supported as well but is currently not)
            foreach (['user','anonymous'] as $key ) {
                if ( array_key_exists($key, $default_permissions)){
                    foreach( ['r', 'w', 'v', 'd', 'g'] as $per){
                        if( in_array($per, array_map('strtolower', $default_permissions[$key] ))){
                            $permissions[$key] [$per] = 1;
                        }else{
                            $permissions[$key] [$per] = 0;
                        }
                    }
                }
            }
        }

        //FIRST, DELETE ALL PERMISSIONS IN THE DB RELATING TO THIS DATASET
        //DB delete code here...
        $parameters = [
            'dataset_id'    => $dataset_id
        ];
        $statement = $this->_adapter->createStatement($this->getQuery('deletePermissions'));
        $statement->execute($parameters);

        //THEN CREATE NEW SET OF PERMISSIONS
        //DB inserts here...
        $parameters = [
            'dataset_id'    => $dataset_id,
            'role_id'       => 0, //dataset owner
            'v'        => $permissions['owner']['v'],
            'r'        => $permissions['owner']['r'],
            'w'        => $permissions['owner']['w'],
            'd'        => $permissions['owner']['d'],
            'g'        => $permissions['owner']['g']
        ];
        $statement = $this->_adapter->createStatement($this->getQuery('insertPermission'));
        $statement->execute($parameters);

        $parameters = [
            'dataset_id'    => $dataset_id,
            'role_id'       => -1, //logged in user
            'v'        => $permissions['user']['v'],
            'r'        => $permissions['user']['r'],
            'w'        => $permissions['user']['w'],
            'd'        => $permissions['user']['d'],
            'g'        => $permissions['user']['g']
        ];
        $statement = $this->_adapter->createStatement($this->getQuery('insertPermission'));
        $statement->execute($parameters);

        $parameters = [
            'dataset_id'    => $dataset_id,
            'role_id'       => -2, //anonymous
            'v'        => $permissions['anonymous']['v'],
            'r'        => $permissions['anonymous']['r'],
            'w'        => $permissions['anonymous']['w'],
            'd'        => $permissions['anonymous']['d'],
            'g'        => $permissions['anonymous']['g']
        ];
        $statement = $this->_adapter->createStatement($this->getQuery('insertPermission'));
        $statement->execute($parameters);
    }

    public function createDatasetPermission ($datasetId, $roleId, $v, $r, $w, $d, $g) {
        $parameters = [
            'dataset_id'    => $datasetId,
            'role_id'       => $roleId,
            'v'        => $v, //view in catalogue
            'r'        => $r, //read (data)
            'w'        => $w, //write (data)
            'd'        => $d, //delete (from catalogue)
            'g'        => $g //admin/grant (make changes to catalogue entry)
        ];
        $statement = $this->_adapter->createStatement($this->getQuery('insertPermission'));
        $statement->execute($parameters);
    }

    public function deleteDatasetPermissions($datasetId, $roleId) {
        $parameters = [
            'dataset_id'    => $datasetId,
            'role_id'       => $roleId
        ];
        $statement = $this->_adapter->createStatement($this->getQuery('deletePermission'));
        $statement->execute($parameters);
    }

    public function updateDataset($id, $title, $description) {
        $parameters = [
            'id'        => $id,
            'title'     => $title,
            'description' => $description,
        ];
        $statement = $this->_adapter->createStatement($this->getQuery('updateDataset'));
        $result    = $statement->execute($parameters);
        if ($result->getAffectedRows() > 0) {
            return true;
        }
        return false;
    }

    public function updateDatasetPermission ($dataset_id, $role_id, $permission, $action) {
        $parameters = [
            'dataset_id'    => (int)$dataset_id,
            'role_id'       => (int)$role_id,
            'action'        => (int)$action
        ];
        $baseQuery = $this->getQuery('updatePermissions');
        $fullQuery = sprintf($baseQuery, $permission);
        $statement = $this->_adapter->createStatement($fullQuery);
        //print_r($statement);
        $statement->execute($parameters);
    }

    function deleteDataset($id) {
        $statement = $this->_adapter->createStatement($this->getQuery('deleteDataset'));
        $outcome = $statement->execute(['id'=>$id]);
        //FIXME - Backend streams and files are not deleted. Review this decision...
        return true;
    }

    public function getSingleMetaValue($id, $key) {
        $metadata = [];
        $parameters = [
            'dataset_id' => $id,
            'key' => $key,
        ];
        $statement = $this->_adapter->createStatement($this->getQuery('singleMetaValue'));
        $result    = $statement->execute($parameters);
        if ($result instanceof ResultInterface && $result->isQueryResult()) {
            $resultSet = new ResultSet;
            $resultSet->initialize($result);
            foreach ($resultSet as $row) {
                array_push($metadata, $row);
            }
        }
        return $metadata;
    }

    public function updateDatasetAttribution($datasetId, $attribution) {
        //Check if attribution metadata already set, and update it if so. Else insert appropriate metadata
        $metadata = $this->getSingleMetaValue($datasetId, 'attribution');
        if (count($metadata) == 0){
            //INSERT new  entry
            $parameters = [
                'meta_name' => 'attribution',
                'dataset_id' => $datasetId,
                'value' => $attribution,
            ];
            $statement = $this->_adapter->createStatement($this->getQuery('insertDatasetMetadataByName'));
            $result    = $statement->execute($parameters);
        }
        else {
            //UPDATE existing entry
            $parameters = [
                'dataset_meta_id' => $metadata[0]['dataset_meta_id'],
                'value' => $attribution,
            ];
            $statement = $this->_adapter->createStatement($this->getQuery('updateDatasetMetadata'));
            $result    = $statement->execute($parameters);
        }
    }

    public function getDatasetLicenses($id) {
        $licenses = [];
        $parameters = [
            'dataset_id' => $id
        ];
        $statement = $this->_adapter->createStatement($this->getQuery('datasetLicences'));
        $result    = $statement->execute($parameters);
        if ($result instanceof ResultInterface && $result->isQueryResult()) {
        $resultSet = new ResultSet;
        $resultSet->initialize($result);
        foreach ($resultSet as $row) {
            array_push($licenses, $row);
        }
    }
        return $licenses;
    }

    public function deleteDatasetLicence($datasetLicenceId) {
        //$datasetLicenceId is the id of the dataset__licence relation entry, not the
        //ID of the actual licence in teh licence table
        $parameters = [
            'id' => $datasetLicenceId
        ];
        $statement = $this->_adapter->createStatement($this->getQuery('deleteDatasetLicence'));
        $result    = $statement->execute($parameters);
    }

    public function addDatasetLicence($datasetId, $licenceId) {
        //First, check if this dataset/licence combo already exists...
        $parameters = [
            'dataset_id' => $datasetId,
            'licence_id' => $licenceId,
        ];
        $statement = $this->_adapter->createStatement($this->getQuery('getDatasetLicence'));
        $result    = $statement->execute($parameters);
        if ($result instanceof ResultInterface && $result->isQueryResult()) {
            $resultSet = new ResultSet;
            $resultSet->initialize($result);
            if (count($result) > 0) {
                //licence already allocated to this dataset
                return 0;
            }
        }

        //If not, add it...
        $statement = $this->_adapter->createStatement($this->getQuery('insertDatasetLicence'));
        $result    = $statement->execute($parameters);

        //Now get all licences for this dataset and update the dataset metadata field accordingly...

        return 1;
    }

    public function getAllLicences() {
        $licenses = [];
        $parameters = [];
        $statement = $this->_adapter->createStatement($this->getQuery('allDatasetLicences'));
        $result    = $statement->execute($parameters);
        if ($result instanceof ResultInterface && $result->isQueryResult()) {
            $resultSet = new ResultSet;
            $resultSet->initialize($result);
            foreach ($resultSet as $row) {
                array_push($licenses, $row);
            }
        }
        return $licenses;
    }

    public function getDatasetOwners($id) {
        $owners = [];
        $parameters = [
            'dataset_id' => $id
        ];
        $statement = $this->_adapter->createStatement($this->getQuery('datasetOwners'));
        $result    = $statement->execute($parameters);
        if ($result instanceof ResultInterface && $result->isQueryResult()) {
            $resultSet = new ResultSet;
            $resultSet->initialize($result);
            foreach ($resultSet as $row) {
                array_push($owners, $row);
            }
        }
        return $owners;
    }

    public function getOwnerNames() {
        $owners = [];
        $parameters = [];
        $statement = $this->_adapter->createStatement($this->getQuery('allDatasetOwnerNames'));
        $result    = $statement->execute($parameters);
        if ($result instanceof ResultInterface && $result->isQueryResult()) {
            $resultSet = new ResultSet;
            $resultSet->initialize($result);
            foreach ($resultSet as $row) {
                array_push($owners, $row['name']);
            }
        }
        return $owners;
    }

    public function addDatasetOwner($datasetId, $ownerName) {
        //First, check if this dataset/owner combo already exists...
        $parameters = [
            'dataset_id' => $datasetId,
            'owner_name' => $ownerName,
        ];
        $statement = $this->_adapter->createStatement($this->getQuery('getDatasetOwner'));
        $result    = $statement->execute($parameters);
        if ($result instanceof ResultInterface && $result->isQueryResult()) {
            $resultSet = new ResultSet;
            $resultSet->initialize($result);
            if (count($result) > 0) {
                //owner already allocated to this dataset
                return 0;
            }
        }

        //If not, add it...
        //First add into the owner table
        $ownerParams = [
            'name' => $ownerName
        ];
        $statement = $this->_adapter->createStatement($this->getQuery('insertOwner'));
        $result    = $statement->execute($ownerParams);
        //then add the database relation
        $statement = $this->_adapter->createStatement($this->getQuery('insertDatasetOwner'));
        $result    = $statement->execute($parameters);

        //Now get all owners for this dataset and update the dataset metadata field accordingly...

        return 1;
    }

    public function deleteDatasetOwner($id) {
        $parameters = [
            'id' => $id
        ];
        $statement = $this->_adapter->createStatement($this->getQuery('deleteDatasetOwner'));
        $result    = $statement->execute($parameters);
    }

    public function getLicence($licenceId) {
        $parameters = [
            'id'   => $licenceId
        ];
        $statement = $this->_adapter->createStatement($this->getQuery('getLicence'));
        $result    = $statement->execute($parameters);
        $licence = [];
        if ($result instanceof ResultInterface && $result->isQueryResult()) {
            $licence = $result->current();
        }
        return $licence;
    }
    
    public function init(){
        try {
            $statement = $this->_adapter->createStatement($this->getQuery('isReady'));
            $result    = $statement->execute();
            return false;
        } catch (\Exception $e) {
            // XXX Maybe raise a warning here?
        }
        $sql = file_get_contents(dirname(__FILE__) . '/../../sql/setup.sql');
        $this->_adapter->getDriver()->getConnection()->execute($sql);
        return true;
    }
}