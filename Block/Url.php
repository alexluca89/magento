<?php

namespace Xsellco\Edesk\Block;

class Url extends \Magento\Framework\View\Element\Template
{
    private $backendUrl;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Backend\Model\UrlInterface $backendUrl
    ) {
        parent::__construct($context);
        $this->backendUrl = $backendUrl;
    }

    public function getExtensionUrl()
    {
        return $this->backendUrl->getUrl('adminhtml/system_config/edit', ['section' => 'edesk']);
    }
}
