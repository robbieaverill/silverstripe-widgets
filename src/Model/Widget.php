<?php

namespace SilverStripe\Widgets\Model;

use Exception;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Widgets\Model\WidgetArea;

/**
 * Widgets let CMS authors drag and drop small pieces of functionality into
 * defined areas of their websites.
 *
 * You can use forms in widgets by implementing a {@link WidgetController}.
 *
 * See {@link WidgetController} for more information.
 *
 * @package widgets
 */
class Widget extends DataObject
{
    /**
     * @var array
     */
    private static $db = array(
        "Title" => "Varchar(255)",
        "Sort" => "Int",
        "Enabled" => "Boolean",
    );

    /**
     * @var array
     */
    private static $defaults = array(
        'Enabled' => true,
    );

    /**
     * @var array
     */
    private static $casting = array(
        'CMSTitle' => 'Text',
        'Description' => 'Text',
    );

    private static $only_available_in = array();

    /**
     * @var array
     */
    private static $has_one = array(
        "Parent" => WidgetArea::class,
    );

    /**
     * @var string
     */
    private static $default_sort = "\"Sort\"";

    /**
     * @var string
     */
    private static $title = "Widget Title";

    /**
     * @var string
     */
    private static $cmsTitle = "Name of this widget";

    /**
     * @var string
     */
    private static $description = "Description of what this widget does.";

    /**
     * @var array
     */
    private static $summary_fields = array(
        'CMSTitle' => 'Title'
    );

    /**
     * @var string
     */
    private static $table_name = 'Widget';

    /**
     * @var WidgetController
     */
    protected $controller;

    public function populateDefaults()
    {
        parent::populateDefaults();
        $this->setField('Title', $this->getTitle());
    }

    /**
     * Note: Overloaded in {@link WidgetController}.
     *
     * @return string HTML
     */
    public function WidgetHolder()
    {
        return $this->renderWith("WidgetHolder");
    }

    /**
     * Default way to render widget in templates.
     * @return string HTML
     */
    public function forTemplate($holder = true)
    {
        if ($holder) {
            return $this->WidgetHolder();
        }

        return $this->Content();
    }

    /**
     * Renders the widget content in a custom template with the same name as the
     * current class. This should be the main point of output customization.
     *
     * Invoked from within WidgetHolder.ss, which contains the "framing" around
     * the custom content, like a title.
     *
     * Note: Overloaded in {@link WidgetController}.
     *
     * @return string HTML
     */
    public function Content()
    {
        return $this->renderWith(array_reverse(ClassInfo::ancestry($this->class)));
    }

    /**
     * Get the frontend title for this widget
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->getField('Title')
            ?: _t($this->class . '.TITLE', $this->config()->title);
    }

    /**
     * @return string
     */
    public function getCMSTitle()
    {
        return _t($this->class . '.CMSTITLE', $this->config()->cmsTitle);
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return _t($this->class . '.DESCRIPTION', $this->config()->description);
    }

    /**
     * @return string - HTML
     */
    public function DescriptionSegment()
    {
        return $this->renderWith('WidgetDescription');
    }

    /**
     * @see WidgetController::editablesegment()
     *
     * @return string - HTML
     */
    public function EditableSegment()
    {
        return $this->renderWith('WidgetEditor');
    }

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = new FieldList(
            new TextField('Title', $this->fieldLabel('Title'), null, 255),
            new CheckboxField('Enabled', $this->fieldLabel('Enabled'))
        );
        $this->extend('updateCMSFields', $fields);

        return $fields;
    }

    /**
     * @return FieldList
     */
    public function CMSEditor()
    {
        $fields = $this->getCMSFields();
        $outputFields = new FieldList();

        $this->FormID = $this->ID ?: uniqid();
        $outputFields->push(
            HiddenField::create(
                'Widget[' . $this->FormID . '][FormID]',
                'FormID',
                $this->FormID
            )->addExtraClass('formid')
        );

        foreach ($fields as $field) {
            $name = $field->getName();
            $value = $this->getField($name);
            if ($value) {
                $field->setValue($value);
            }
            $namefiltered = preg_replace("/([A-Za-z0-9\-_]+)/", "Widget[" . $this->FormID . "][\\1]", $name);

            $field->setName($namefiltered);
            $outputFields->push($field);
        }

        return $outputFields;
    }

    /**
     * A fully qualified class name is returned with underscores instead of backslashes so it is HTML safe. Dashes
     * can't be used as they're handled in the Javascript for other purposes.
     *
     * @return string
     */
    public function ClassName()
    {
        return str_replace('\\', '_', $this->class);
    }

    /**
     * @return string
     */
    public function Name()
    {
        return "Widget[" . $this->ID . "]";
    }

    /**
     * @throws Exception
     *
     * @return WidgetController
     */
    public function getController()
    {
        if ($this->controller) {
            return $this->controller;
        }

        foreach (array_reverse(ClassInfo::ancestry($this->class)) as $widgetClass) {
            $controllerClass = "{$widgetClass}Controller";
            if (class_exists($controllerClass)) {
                break;
            }
        }

        if (!class_exists($controllerClass)) {
            throw new Exception("Could not find controller class for $this->classname");
        }

        $this->controller = Injector::inst()->create($controllerClass, $this);

        return $this->controller;
    }

    /**
     * @param array $data
     */
    public function populateFromPostData($data)
    {
        $fields = $this->getCMSFields();
        foreach ($data as $name => $value) {
            if ($name != "Type") {
                if ($field = $fields->dataFieldByName($name)) {
                    $field->setValue($value);
                    $field->saveInto($this);
                } else {
                    $this->setField($name, $value);
                }
            }
        }

        //Look for checkbox fields not present in the data
        foreach ($fields as $field) {
            if ($field instanceof CheckboxField && !array_key_exists($field->getName(), $data)) {
                $field->setValue(false);
                $field->saveInto($this);
            }
        }

        $this->write();

        // The field must be written to ensure a unique ID.
        $this->Name = $this->class . $this->ID;
        $this->write();
    }
}