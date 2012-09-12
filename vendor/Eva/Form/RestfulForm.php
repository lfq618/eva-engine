<?php
/**
 * EvaEngine
 *
 * @link      https://github.com/AlloVince/eva-engine
 * @copyright Copyright (c) 2012 AlloVince (http://avnpc.com/)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Eva_Form
 * @author    AlloVince
 */

namespace Eva\Form;

use Zend\Form\Form;
use Zend\Form\Fieldset;
use Zend\Form\FormInterface;
use Zend\Config\Config;
use Zend\InputFilter\InputFilter;
use Zend\InputFilter\InputFilterProviderInterface;
use Zend\InputFilter\Factory as FilterFactory;
use Eva\File\Transfer\TransferFactory;

/**
 * Eva Form will automatic combination form Elements & Validators & Filters
 * Also allow add sub forms and unit validate
 * 
 * @category   Eva
 * @package    Eva_Form
 */
class RestfulForm extends Form implements InputFilterProviderInterface
{

    /**
    * Element definitions
    *
    * @var array
    */
    protected $baseElements = array();

    /**
    * Merge Element definitions
    *
    * @var array
    */
    protected $mergeElements = array();

    /**
    * Filter definitions
    *
    * @var array
    */
    protected $baseFilters = array();

    /**
    * Merge Filter definitions
    *
    * @var array
    */
    protected $mergeFilters = array();

    /**
    * Resful form method
    *
    * @var array
    */
    protected $restfulMethod;

    /**
    * If true, will generate elements id
    *
    * @var boolean
    */
    protected $autoElementId = true;

    /**
    * Element id prefix
    *
    * @var string
    */
    protected $idPrefix;

    //protected $fieldsMap = array();

    /**
    * Sub Forms
    *
    * @var array
    */
    protected $subForms = array();

    /**
    * Form element inited
    *
    * @var boolean
    */
    protected $elementInited = false;

    /**
    * File Transfer
    *
    * @var Eva\File\Transfer\TransferFactory
    */
    protected $fileTransfer;

    /**
    * File Transfer Options
    *
    * @var array
    */
    protected $fileTransferOptions = array();

    /**
    * Sub Form Groups
    *
    * @var array
    */
    protected $subFormGroups = array();

    /**
    * Parent Form
    *
    * @var Eva\Form\RestfulForm
    */
    protected $parent;

    public function setParent($parent)
    {
        $this->parent = $parent;
        return $this;
    }

    public function getParent()
    {
        return $this->parent;
    }

    public function add($elementOrFieldset, array $flags = array())
    {
        parent::add($elementOrFieldset, $flags);
        if(is_array($elementOrFieldset) && isset($elementOrFieldset['name'])){
            $elementOrFieldset = $this->get($elementOrFieldset['name']);
        }
        if ($elementOrFieldset instanceof RestfulForm) {
            $this->setParent($this);
        }
        return $this;
    }

    /**
    * Mvc View
    *
    * @var Zend\View
    */
    protected $view;

    public function getView()
    {
        return $this->view;
    }

    public function setView($view)
    {
        $this->view = $view;
        return $this;
    }


    public function setFileTransferOptions($fileTransferOptions)
    {
        $this->fileTransferOptions = $fileTransferOptions;
        return $this;
    }

    public function getFileTransferOptions()
    {
        return $this->fileTransferOptions;
    }

    public function setFileTransfer(TransferFactory $fileTransfer)
    {
        $this->fileTransfer = $fileTransfer;
        return $this;
    }

    public function getFileTransfer()
    {
        if($this->fileTransfer){
            return $this->fileTransfer;
        }
        return $this->fileTransfer = TransferFactory::factory($this->getFileTransferOptions());
    }

    public function setAutoElementId($autoElementId)
    {
        $this->autoElementId = (boolean) $autoElementId;
        return $this;
    }

    public function getAutoElementId()
    {
        return $this->autoElementId;
    }

    public function setIdPrefix($idPrefix)
    {
        $this->idPrefix = (string) $idPrefix;
        return $this;
    }

    public function getIdPrefix()
    {
        if($this->idPrefix){
            return $this->idPrefix;
        }

        return $this->idPrefix = get_class($this);
    }


    public function setMergeElements(array $elements = array())
    {
        $this->mergeElements = $elements;
        return $this;
    }

    public function getMergeElements()
    {
        return $this->mergeElements;
    }

    public function mergeElements(array $elements = array())
    {
        //TODO: could merge parent class elements
        $elements = $elements ? $elements : $this->mergeElements;
        return $this->merge($this->baseElements, $elements);
    }

    public function mergeFilters()
    {
        return $this->merge($this->baseFilters, $this->mergeFilters);
    }

    public function useSubFormGroup($groupName = 'default')
    {
        if(!isset($this->subFormGroups[$groupName])){
            throw new Exception\InvalidArgumentException(sprintf(
                'Sub Form Group %s not defined in Form %s', $groupName, get_class($this)
            ));
        }
        $subForms = $this->subFormGroups[$groupName];
        $this->setSubForms($subForms);
        return $this;
    }

    public function setSubForms(array $subForms = array())
    {
        foreach($subForms as $formName => $formConfig){
            $this->addSubForm($formName, $formConfig);
        }
        //$this->prepare();
        return $this;
    }

    public function getSubForm($subFormName)
    {
        return $this->get($subFormName);
    }

    public function addSubForm($formName, $formConfig = array())
    {
        if(is_array($formConfig)) {

            if(isset($formConfig[0])){
                $subFormClass = $formConfig[0];
            } elseif(isset($formConfig['formClass'])) {
                $subFormClass = $formConfig['formClass'];
            } else {
                throw new Exception\InvalidArgumentException(sprintf(
                    'Subform %s not find defined class', $formName
                ));
            }

        } elseif(is_string($formConfig)){

            $subFormClass = $formConfig;

        } else {
            throw new Exception\InvalidArgumentException(sprintf(
                'Subform %s config not correct, require string or object', $formName
            ));
        }

        if(!class_exists($subFormClass)){
            return $this;
        }

        $subForm = new $subFormClass($formName);
        $subForm->setParent($this);
        if(is_array($formConfig) && isset($formConfig['collection']) && $formConfig['collection']) {
            $object = isset($formConfig['object']) ? $formConfig['object'] : array();
            if(is_object($object) && method_exists($object, 'toArray')) {
                $values = $object->toArray();
            } else {
                $values = (array) $object;
            }

            $options = array(
                'allow_add' => true,
                'target_element' => $subForm,
            );
            $options = array_merge($options, $formConfig);
            if($values) {
                $options['count'] = count($values);
            }

            $this->add(array(
                'type' => 'Zend\Form\Element\Collection',
                'name' => $formName,
                'options' => $options,
            ));
            $this->get($formName)->populateValues($object);
        } else {
            $this->add($subForm);
        }
        return $this; 
    }

    public function init(array $options = array())
    {
        $elements = $this->mergeElements();
        foreach($elements as $element){
            $this->initElement($element);
        }
        return $this;
    }

    protected function initElement(array $element)
    {
        $element = $this->autoElementId($element);
        if(isset($element['type']) && false === strpos($element['type'], '\\')){
            $element['type'] = 'Zend\Form\Element\\' . ucfirst($element['type']);
        }
        return $this->add($element);
    }

    /*
    protected function initFilter()
    {
        $filters = $this->mergeFilters();
        $inputFilter = $this->getInputFilter();
        foreach($filters as $key => $filter){
            $inputFilter->add($filter, $key);
        }
        return $this;
    }
    */

    public function getInputFilterSpecification()
    {
        return $this->mergeFilters();
    }

    public function setMethod($method = '')
    {
        if(!$method){
            return $this;
        }

        $allowMethods = array('get', 'put', 'post', 'delete');
        $method = strtolower($method);

        if(false === in_array($method, $allowMethods)){
            throw new Exception\UnexpectedMethodException(sprintf(
                'Input Method %s is not correct http method',
                __METHOD__,
                $method
            ));
        }

        $restfulMethod = 'get';
        switch($method){
            case 'post' :
            $restfulMethod = 'post';
            break;
            case 'get' :
            break;
            case 'put' :
                $restfulMethod = 'put';
                $method = 'post';
                break;
            case 'delete' :
                $restfulMethod = 'delete';
                $method = 'post';
                break;
            default:
        }

        $this->setAttribute('method', $method);
        $this->restfulMethod = $restfulMethod;
        return $this;
    }

    public function setAction($action)
    {
        $this->setAttribute('action', $action);
        return $this;
    }

    public function restful($inputName = '_method')
    {
        return sprintf('<input type="hidden" name="' . $inputName . '" value="%s">', $this->restfulMethod);
    }

    public function isValid()
    {
        if(!$this->fileTransfer){
            return parent::isValid();
        }

        $elementValid = parent::isValid();
        $fileValid = $this->fileTransfer->isValid();
        $result = $elementValid && $fileValid;
        $this->isValid = $result;
        if (!$result) {
            $this->setMessages($this->fileTransfer->getMessages());
        }
        return $result;
    }

    public function bind($valuesOrObject, $flags = FormInterface::VALUES_NORMALIZED)
    {
        if(!$valuesOrObject){
            return $this;
        }

        if(is_array($valuesOrObject) || $valuesOrObject instanceof \Zend\Stdlib\Parameters){
            $this->setData((array) $valuesOrObject);
        } else {
            parent::bind($valuesOrObject);
        }
        return $this;
    }

    public function isError($elementName)
    {
        $element = $this->get($elementName);
        return $element->getMessages() ? true : false;
    }


    protected function autoElementId(array $element, $idPrefix = null)
    {
        if(!$this->autoElementId){
            return $element;
        }

        //TODO:form collection fix
        $idPrefix = $idPrefix ? $idPrefix : $this->getIdPrefix();
        $elementId = isset($element['attributes']['id']) ? $element['attributes']['id'] : $element['name'];
        $elementId = $idPrefix . '-' . $elementId;
        $elementId = str_replace(array('\\', '_', '[', ']'), '-', strtolower($elementId));
        $elementId = trim($elementId, '-');
        $element['attributes']['id'] = $elementId;
        return $element;
    }

    public function helper($elementName, $optionOrInputType = null, array $options = array(), array $setting = array())
    {
        $defaultSetting = array(
            'i18n' => true,
            'replace' => true,
            'reorder' => false,
        );
        $setting = array_merge($defaultSetting, $setting);

        $view = $this->getView();
        if(!$view && $this->parent){
            $view = $this->parent->getView();
        }
        if(!$view){
            throw new Exception\InvalidArgumentException(sprintf('Form view not found'));
        }

        $element = $this->get($elementName);
        if(!$element){
            throw new Exception\InvalidArgumentException(sprintf('Request Element %s not found', $elementName));
        }

        if($optionOrInputType){
            if(is_string($optionOrInputType)){
                $options = array_merge(array('type' => $optionOrInputType), $options);
            } else {
                $options = array_merge($optionOrInputType, $options);
            }
        }

        //Merge attributes
        if(false === $setting['replace']){
            $options = $this->merge($element->getAttributes(), $options);
        }

        //Merge Value Options
        if(isset($options['value_options']) && method_exists($element, 'getValueOptions')){
            $element->setValueOptions($this->merge($element->getValueOptions(), $options['value_options']));
        }
        return $view->input($element, $options); 
    }

    protected function merge(array $global, array $local)
    {
        if(!$local) {
            return $global;
        }

        $global = new Config($global);
        $local = new Config($local);
        $global->merge($local);
        return $global->toArray();
    }

    public function __construct($name = null, $subFormGroup = null)
    {
        parent::__construct($name);
        $this->init();
    }
}
