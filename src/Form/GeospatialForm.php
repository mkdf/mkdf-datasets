<?php
namespace MKDF\Datasets\Form;

use MKDF\Datasets\Repository\MKDFDatasetRepositoryInterface;
use Zend\Form\Form;
use Zend\Form\Element\Hidden;
use Zend\Form\Element\Text;
use Zend\Form\Element\Textarea;
use Zend\Form\Element\Radio;
use Zend\Form\Element\Submit;

class GeospatialForm extends Form
{
    private $_repository;

    // Constructor.
    public function __construct(MKDFDatasetRepositoryInterface $repository)
    {
        // Define form name
        parent::__construct('dataset-form');
        // Set POST method for this form
        $this->setAttribute('method', 'post');

        $this->_repository = $repository;
        $this->addElements();
        $this->addInputFilter();

    }

    /**
     * This method adds elements to form (input fields and submit button).
     */
    protected function addElements()
    {
        // Add "latitude" field
        $this->add([
            'type'  => 'text',
            'name' => 'latitude',
            'id'    => 'latitude',
            'options' => [
                'label' => 'Latitude',
            ],
        ]);

        // Add "longitude" field
        $this->add([
            'type'  => 'text',
            'name' => 'longitude',
            'options' => [
                'label' => 'Longitude',
            ],
        ]);

        // Add the Update button
        $this->add([
            'type'  => 'submit',
            'name' => 'update',
            'attributes' => [
                'value' => 'Update'
            ],
        ]);
    }

    /**
     * This method creates input filter (used for form filtering/validation).
     */
    private function addInputFilter()
    {
        // Create main input filter
        $inputFilter = $this->getInputFilter();

        // Add input for "latitude" field
        $inputFilter->add([
            'name'     => 'latitude',
            'required' => true,
            'filters'  => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name'    => 'Float',
                ],
            ],
        ]);

        // Add input for "longitude" field
        $inputFilter->add([
            'name'     => 'longitude',
            'required' => true,
            'filters'  => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name'    => 'Float',
                ],
            ],
        ]);


    }
}