<?php
namespace MKDF\Datasets\Form;

use MKDF\Datasets\Repository\MKDFDatasetRepositoryInterface;
use Zend\Form\Form;
use Zend\Form\Element\Hidden;
use Zend\Form\Element\Text;
use Zend\Form\Element\Textarea;
use Zend\Form\Element\Radio;
use Zend\Form\Element\Submit;

class DatasetForm extends Form
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
        // Add "title" field
        $this->add([
            'type'  => 'text',
            'name' => 'title',
            'options' => [
                'label' => 'Title',
            ],
        ]);

        // Add "description" field
        $this->add([
            'type'  => 'textarea',
            'name' => 'description',
            'options' => [
                'label' => 'Description',
            ],
        ]);


        $datasetTypes = $this->_repository->findDatasetTypes();
        $valueOptions = [];
        foreach ($datasetTypes as $option) {
            $id = $option->id;
            $label = $option->name . " - " . $option->description;
            $valueOptions[$id] = $label;
        }

        $this->add([
            'type' => 'radio',
            'name' => 'datasetTypes',
            'options' => [
                'label' => 'Dataset type',
                'value_options' => $valueOptions,
            ],
        ]);

        // Add the Submit button
        $this->add([
            'type'  => 'submit',
            'name' => 'submit',
            'attributes' => [
                'value' => 'Create'
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

        // Add input for "title" field
        $inputFilter->add([
            'name'     => 'title',
            'required' => true,
            'filters'  => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name'    => 'StringLength',
                    'options' => [
                        'min' => 1,
                        'max' => 128
                    ],
                ],
            ],
        ]);

        // Add input for "description" field
        $inputFilter->add([
            'name'     => 'description',
            'required' => true,
            'filters'  => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name'    => 'StringLength',
                    'options' => [
                        'min' => 1,
                        'max' => 1024
                    ],
                ],
            ],
        ]);

        $inputFilter->add([
            'name'     => 'datasetTypes',
            'required' => false,
        ]);

    }
}