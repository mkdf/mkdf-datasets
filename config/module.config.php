<?php

namespace MKDF\Datasets;

use Zend\ServiceManager\Factory\InvokableFactory;
use Zend\Router\Http\Literal;
use Zend\Router\Http\Segment;

return [
    'controllers' => [
        'factories' => [
            Controller\DatasetController::class => Controller\Factory\DatasetControllerFactory::class
        ],
    ],
    'service_manager' => [
        'aliases' => [
            Repository\MKDFDatasetRepositoryInterface::class => Repository\MKDFDatasetRepository::class,
            Service\DatasetsFeatureManagerInterface::class =>  Service\DatasetsFeatureManager::class,
            Service\DatasetPermissionManagerInterface::class => Service\DatasetPermissionManager::class
        ],
        'factories' => [
            DatasetsFeature\BasicFeature::class => InvokableFactory::class,
            DatasetsFeature\PermissionsFeature::class => InvokableFactory::class,
            DatasetsFeature\GeospatialFeature::class => InvokableFactory::class,
            DatasetsFeature\OwnershipFeature::class => InvokableFactory::class,
            DatasetsFeature\AccountDatasetsFeature::class => InvokableFactory::class,
            Repository\MKDFDatasetRepository::class => Repository\Factory\MKFDFDatasetRepositoryFactory::class,
            Service\DatasetsFeatureManager::class => Service\Factory\DatasetsFeatureManagerFactory::class,
            Service\DatasetPermissionManager::class => Service\Factory\DatasetPermissionManagerFactory::class
        ]
    ],
    'router' => [
        'routes' => [
            'dataset' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/dataset[/:action[/:id]]',
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id' => '[a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller'    => Controller\DatasetController::class,
                        'action'        => 'index',
                    ],
                ]
            ],
            'my-account/mydatasets' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/my-account/mydatasets',
                    'defaults' => [
                        'controller'    => Controller\DatasetController::class,
                        'action'        => 'mydatasets',
                    ],
                ]
            ]
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            'Dataset' => __DIR__ . '/../view',
        ],
        'strategies' => [
            'ViewJsonStrategy'
            ]
    ],
    'controller_plugins' => [
        'factories' => [
            Controller\Plugin\DatasetRepositoryPlugin::class => Controller\Plugin\Factory\DatasetRepositoryPluginFactory::class,
            Controller\Plugin\DatasetsFeatureManagerPlugin::class => Controller\Plugin\Factory\DatasetsFeatureManagerPluginFactory::class
        ],
        'aliases' => [
            'datasetRepository' => Controller\Plugin\DatasetRepositoryPlugin::class,
            'datasetsFeatureManager' => Controller\Plugin\DatasetsFeatureManagerPlugin::class,
        ]
    ],
    // The 'access_filter' key is used by the User module to restrict or permit
    // access to certain controller actions for unauthenticated visitors.
    'access_filter' => [
        'options' => [
            // The access filter can work in 'restrictive' (recommended) or 'permissive'
            // mode. In restrictive mode all controller actions must be explicitly listed
            // under the 'access_filter' config key, and access is denied to any not listed
            // action for users not logged in. In permissive mode, if an action is not listed
            // under the 'access_filter' key, access to it is permitted to anyone (even for
            // users not logged in. Restrictive mode is more secure and recommended.
            'mode' => 'restrictive'
        ],
        'controllers' => [
            Controller\DatasetController::class => [
                ['actions' => ['index'], 'allow' => '*'],
                ['actions' => ['details'], 'allow' => '@'],
                ['actions' => ['mydatasets'], 'allow' => '@'],
                ['actions' => ['add','edit','delete','delete-confirm'], 'allow' => '@']
            ],
        ]
    ],
    /*
    'navigation' => [
        'default' => [
            [
                'label' => 'Datasets',
                'route' => 'dataset',
                'pages' => [
                    [
                        'label' => 'Overview',
                        'uri'   => 'dataset/details',
                    ],
                ]
            ],
        ],
    ],
    */
];
