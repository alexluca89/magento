<?php

namespace Xsellco\Edesk\Block;
class Url extends \Magento\Framework\View\Element\Template
{
	protected $_backendUrl;

	public function __construct(
		\Magento\Framework\View\Element\Template\Context $context,
		\Magento\Backend\Model\UrlInterface $backendUrl
	)
	{
		parent::__construct($context);
		$this->_backendUrl = $backendUrl;
	}

	public function getExtensionUrl()
	{
		return $this->_backendUrl->getUrl('adminhtml/system_config/edit', array('section' => 'edesk'));
	}
}