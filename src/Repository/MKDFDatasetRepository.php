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
            'port'     => $this->_config['db']['port']
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
            'allDatasets'       => 'SELECT id, title, description, uuid, user_id, date_created, date_modified FROM dataset ORDER BY date_created DESC',
            'datasetTypes'      => 'SELECT id, name, description FROM dataset_type',
            'allVisibleDatasets'=> 'SELECT DISTINCT d.id, d.title, d.description, d.type, d.uuid, d.user_id, d.date_created, d.date_modified '.
                                    'FROM '.
                                         'dataset d, '.
                                         'dataset_permission dp '.
                                   'WHERE '.
                                          'd.id = dp.dataset_id AND '.
                                        '('.
                                          '(dp.role_id = '.$this->fp('login_status').' AND dp.v = 1) '.
                                            ' OR '.
                                          '(d.user_id = '.$this->fp('user_id').' AND dp.role_id = '.$this->fp('logged_in_identifier').')'.
                                            ' OR '.
                                          '(dp.role_id = '.$this->fp('user_id').' AND dp.v = 1)'.
                                        ') ORDER BY d.date_created DESC ',
            'userDatasets'      => 'SELECT id, title, description, uuid, user_id, date_created, date_modified FROM dataset '.
                ' WHERE user_id = '.$this->fp('user_id').' ORDER BY date_created DESC',
            'oneDataset'        => 'SELECT id, title, description, uuid, user_id, type FROM dataset WHERE id = ' . $this->fp('id'),
            'datasetCount'      => 'SELECT COUNT(id) AS count FROM dataset',
            'insertDataset'     => 'INSERT INTO dataset (title, description, uuid, user_id, type) VALUES ('.$this->fp('title').', '.$this->fp('description').', '.$this->fp('uuid').', '.$this->fp('user_id').', '.$this->fp('type').')',
            'updateDataset'     => 'UPDATE dataset SET title = '.$this->fp('title').
                ', description = '.$this->fp('description'). ', date_modified =  CURRENT_TIMESTAMP '.
                ' WHERE id = ' .$this->fp('id'),
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

        ];
    }

    private function addQueryLimit($query, $limit) {
        return $query . ' LIMIT ' . $limit;
    }

    private function getQuery($query){
        return $this->_queries[$query];
    }

    public function findAllDatasets($userId = -1, $limit = 0) {
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
        $query = $this->getQuery('allVisibleDatasets');
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

    /**
     * Set permissions for a given dataset. If no permissions passed, just create the default
     * set (everyone can view, logged in users can read/subscribe, owner has full access)
     * User_IDs:
     * 0 = owner
     * -1 = logged in
     * -2 - everyone/anonymous
     * Permissions/Actions:
     * R - Read/subscribe
     * W - Write/Push data
     * V - View listing
     * D - Delete
     * G - Grant permissions
     * @param int $dataset_id
     * @param array $permissions
     */
    public function setDefaultDatasetPermissions($dataset_id, $permissions = []) {
        if ($permissions == []) {
            $permissions = [
                '0'  => ['R', 'W', 'V', 'D', 'G'],
                '-1' => ['R', 'V'],
                '-2' => ['V']
            ];
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
            'v'        => 1,
            'r'        => 1,
            'w'        => 1,
            'd'        => 1,
            'g'        => 1
        ];
        $statement = $this->_adapter->createStatement($this->getQuery('insertPermission'));
        $statement->execute($parameters);

        $parameters = [
            'dataset_id'    => $dataset_id,
            'role_id'       => -1, //logged in user
            'v'        => 1,
            'r'        => 1,
            'w'        => 0,
            'd'        => 0,
            'g'        => 0
        ];
        $statement = $this->_adapter->createStatement($this->getQuery('insertPermission'));
        $statement->execute($parameters);

        $parameters = [
            'dataset_id'    => $dataset_id,
            'role_id'       => -2, //anonymous
            'v'        => 1,
            'r'        => 0,
            'w'        => 0,
            'd'        => 0,
            'g'        => 0
        ];
        $statement = $this->_adapter->createStatement($this->getQuery('insertPermission'));
        $statement->execute($parameters);
    }

    public function createDatasetPermission ($datasetId, $roleId, $v, $r, $w, $d, $g) {
        $parameters = [
            'dataset_id'    => $datasetId,
            'role_id'       => $roleId,
            'v'        => $v,
            'r'        => $r,
            'w'        => $w,
            'd'        => $d,
            'g'        => $g
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
        return true;
    }

}