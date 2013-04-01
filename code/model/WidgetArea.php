<?php
/**
 * Represents a set of widgets shown on a page.
 * @package cms
 * @subpackage widgets
 */
class WidgetArea extends DataObject {

	/**
	 *
	 * @var array
	 */
	private static $has_many = array(
		"Widgets" => "Widget"
	);

	/**
	 *
	 * @var string
	 */
	public $template = __CLASS__;
	
	/**
	 * Used in template instead of {@link Widgets()}
	 * to wrap each widget in its controller, making
	 * it easier to access and process form logic
	 * and actions stored in {@link Widget_Controller}.
	 * 
	 * @return SS_List Collection of {@link Widget_Controller}
	 */
	public function WidgetControllers() {
		$controllers = new ArrayList();

		foreach($this->ItemsToRender() as $widget) {
			// find controller
			$controllerClass = '';
			foreach(array_reverse(ClassInfo::ancestry($widget->class)) as $widgetClass) {
				$controllerClass = "{$widgetClass}_Controller";
				if(class_exists($controllerClass)) break;
			}
			$controller = new $controllerClass($widget);
			$controller->init();
			$controllers->push($controller);
		}

		return $controllers;
	}

	/**
	 *
	 * @return HasManyList
	 */
	public function Items() {
		return $this->getComponents('Widgets');
	}

	/**
	 *
	 * @return HasManyList
	 */
	public function ItemsToRender() {
		return $this->getComponents('Widgets', "\"Widget\".\"Enabled\" = 1");
	}

	/**
	 *
	 * @return string - HTML
	 */
	public function forTemplate() {
		return $this->renderWith($this->template); 
	}

	/**
	 *
	 * @param string $template
	 */
	public function setTemplate($template) {
		$this->template = $template;
	}

	/**
	 * Delete all connected Widgets when this WidgetArea gets deleted
	 */
	public function onBeforeDelete() {
		parent::onBeforeDelete();
		foreach($this->Widgets() as $widget) {
			$widget->delete();
		}
	}
}

