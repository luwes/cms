<?php
namespace Craft;

/**
 * Element actions
 */
class ElementsController extends BaseController
{
	/**
	 * Renders and returns the body of an ElementSelectorModal.
	 */
	public function actionGetModalBody()
	{
		$this->requireAjaxRequest();

		$showSources = craft()->request->getParam('sources');
		$context = craft()->request->getParam('context');
		$elementType = $this->_getElementType();
		$sources = $elementType->getSources($context);

		if (is_array($showSources))
		{
			foreach (array_keys($sources) as $source)
			{
				if (!in_array($source, $showSources))
				{
					unset($sources[$source]);
				}
			}
		}

		$this->renderTemplate('_elements/modalbody', array(
			'sources'   => $sources
		));
	}

	/**
	 * Renders and returns the list of elements in an ElementIndex.
	 */
	public function actionGetElements()
	{
		$context = craft()->request->getParam('context');
		$elementType = $this->_getElementType();
		$source = craft()->request->getParam('source');
		$viewState = craft()->request->getParam('viewState');
		$disabledElementIds = craft()->request->getParam('disabledElementIds');

		$baseCriteria = craft()->request->getPost('criteria');
		$criteria = craft()->elements->getCriteria($elementType->getClassHandle(), $baseCriteria);

		if ($source)
		{
			$sources = $elementType->getSources($context);
			$sourceCriteria = $this->_getSourceCriteria($sources, $source);

			if ($sourceCriteria !== null)
			{
				$criteria->setAttributes($sourceCriteria);
			}
			else
			{
				return false;
			}
		}

		if ($search = craft()->request->getParam('search'))
		{
			$criteria->search = $search;
		}

		if ($offset = craft()->request->getParam('offset'))
		{
			$criteria->offset = $offset;
		}

		$variables = array(
			'state'              => $viewState,
			'context'            => $context,
			'elementType'        => new ElementTypeVariable($elementType),
			'disabledElementIds' => $disabledElementIds,
		);

		switch ($viewState['mode'])
		{
			case 'table':
			{
				// Make sure the attribute is actually allowed
				$tableAttributes = $elementType->defineTableAttributes($source);

				// Ordering by an attribute?
				if (!empty($viewState['order']))
				{
					foreach ($tableAttributes as $attribute)
					{
						if ($attribute['attribute'] == $viewState['order'])
						{
							$criteria->order = $viewState['order'].' '.$viewState['sort'];
							break;
						}
					}
				}

				$variables['attributes'] = $tableAttributes;

				break;
			}

			case 'structure':
			{
				$criteria->limit = null;
				$criteria->offset = null;
			}
		}

		// Find the elements!
		$variables['elements'] = $criteria->find();

		if (!$criteria->offset)
		{
			$template = 'container';
		}
		else
		{
			$template = 'elements';
		}

		$html = craft()->templates->render('_elements/'.$viewState['mode'].'view/'.$template, $variables);

		$totalVisible = $criteria->offset + $criteria->limit;
		$remainingElements = $criteria->total() - $totalVisible;

		$this->returnJson(array(
			'html'         => $html,
			'headHtml'     => craft()->templates->getHeadHtml(),
			'totalVisible' => $totalVisible,
			'more'         => ($remainingElements > 0),
		));
	}

	/**
	 * Returns the element type based on the posted element type class.
	 *
	 * @access private
	 * @return BaseElementType
	 * @throws Exception
	 */
	private function _getElementType()
	{
		$class = craft()->request->getRequiredParam('elementType');
		$elementType = craft()->elements->getElementType($class);

		if (!$elementType)
		{
			throw new Exception(Craft::t('No element type exists with the class "{class}"', array('class' => $class)));
		}

		return $elementType;
	}

	/**
	 * Returns the criteria for a given source.
	 *
	 * @param array  $sources
	 * @param string $selectedSource
	 * @return array|null
	 */
	private function _getSourceCriteria($sources, $selectedSource)
	{
		if (isset($sources[$selectedSource]))
		{
			if (isset($sources[$selectedSource]['criteria']))
			{
				return $sources[$selectedSource]['criteria'];
			}
			else
			{
				return array();
			}
		}
		else
		{
			// Look through any nested sources
			foreach ($sources as $key => $source)
			{
				if (!empty($source['nested']) && ($nestedSourceCriteria = $this->_getSourceCriteria($source['nested'], $selectedSource)))
				{
					return $nestedSourceCriteria;
				}
			}
		}

		return null;
	}

}
