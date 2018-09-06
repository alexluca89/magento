<?php

namespace Xsellco\Edesk\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Authorization\Model\Acl\Role\Group as RoleGroup;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Setup\Exception;

class ConfigObserver implements ObserverInterface
{
	const XSELLCO_NAME = 'xsellco';
	const XSELLCO_ROLE_NAME = 'xsellco_api_role';
	const XSELLCO_USERNAME = 'xsellco_api_user';
	const XSELLCO_EMAIL = 'tech@xsellco.com';
	const XSELLCO_API_URL = 'https://api.preprod.xsell.co/v1/magento/send_credentials';

	/**
	 * @var \Magento\Framework\App\Config\ScopeConfigInterface
	 */
	protected $_scopeConfig;

	/**
	 * @var \Magento\Authorization\Model\ResourceModel\Role\CollectionFactory
	 */
	protected $_roleFactory;

	/**
	 * @var \Magento\Authorization\Model\RulesFactory
	 */
	protected $_rulesFactory;

	/**
	 * @var \Magento\Framework\Message\ManagerInterface
	 */
	protected $_messageManager;

	/**
	 * @var \Magento\Store\Model\StoreManagerInterface
	 */
	protected $_storeManager;

	/**
	 * @var \Magento\Framework\HTTP\Client\Curl
	 */
	protected $_curl;

	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $_logger;

	/**
	 * @var \Magento\User\Model\ResourceModel\User\CollectionFactory
	 */
	protected $_userCollectionFactory;


	/**
	 * ConfigObserver constructor.
	 * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
	 * @param \Magento\Authorization\Model\ResourceModel\Role\CollectionFactory $roleFactory
	 * @param \Magento\Authorization\Model\RulesFactory $rulesFactory
	 * @param \Magento\Framework\Message\ManagerInterface $messageManager
	 * @param \Magento\Store\Model\StoreManagerInterface $storeManager
	 * @param \Magento\Framework\HTTP\Client\Curl $curl
	 * @param \Psr\Log\LoggerInterface $logger
	 * @param \Magento\User\Model\ResourceModel\User\CollectionFactory $userCollectionFactory
	 */
	public function __construct(
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Magento\Authorization\Model\ResourceModel\Role\CollectionFactory $roleFactory, /* Instance of Role*/
		\Magento\Authorization\Model\RulesFactory $rulesFactory, /* Instance of Rule */
		\Magento\Framework\Message\ManagerInterface $messageManager,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Framework\HTTP\Client\Curl $curl,
		\Psr\Log\LoggerInterface $logger,
		\Magento\User\Model\ResourceModel\User\CollectionFactory $userCollectionFactory
	)
	{
		$this->_scopeConfig = $scopeConfig;
		$this->_roleFactory = $roleFactory;
		$this->_rulesFactory = $rulesFactory;
		$this->_messageManager = $messageManager;
		$this->_storeManager = $storeManager;
		$this->_curl = $curl;
		$this->_logger = $logger;
		$this->_userCollectionFactory = $userCollectionFactory;
	}

	/**
	 * @param EventObserver $observer
	 * @return void
	 * @throws \Magento\Framework\Exception\LocalizedException
	 * @throws \Magento\Framework\Exception\NoSuchEntityException
	 */
	public function execute(EventObserver $observer)
	{
		$edeskToken = $this->_scopeConfig->getValue(
			'edesk/general/edesk_token',
			\Magento\Store\Model\ScopeInterface::SCOPE_STORE
		);
		if (!$edeskToken) {
			$this
				->_messageManager
				->addErrorMessage('Invalid token!');
			return;
		}

		$apiKey = md5(time());
		try {
			$this->_saveApiUserAndRole([
				'username' => self::XSELLCO_USERNAME,
				'firstname' => self::XSELLCO_NAME,
				'lastname' => self::XSELLCO_NAME,
				'email' => self::XSELLCO_EMAIL,
				'password' => $apiKey,
				'interface_locale' => 'en_US',
				'is_active' => 1
			]);

			$stores = array();
			foreach ($this->_storeManager->getStores() as $store) {
				$stores[$store->getId()] = $store->getName();
			}
			$baseUrl = $this
				->_storeManager
				->getStore()
				->getBaseUrl();
			$params = array(
				'user_email' => self::XSELLCO_EMAIL,
				'validation_token' => $edeskToken,
				'api_username' => self::XSELLCO_USERNAME,
				'api_key' => $apiKey,
				'stores' => $stores,
				'domain' => $baseUrl,
				'magento_version' => 2
			);
			$this->_sendCredentials($params);
		} catch (Exception $e) {
			$this
				->_messageManager
				->addErrorMessage('There was a problem during the process.');
			$this->_logger->error($e->getMessage());exit;
		}
	}

	/**
	 * @param array $adminInfo
	 * @throws \Magento\Framework\Exception\LocalizedException
	 */
	protected function _saveApiUserAndRole(array $adminInfo) {
		$role = $this
			->_roleFactory
			->create()
			->addFieldToFilter('role_name', self::XSELLCO_ROLE_NAME)
			->getFirstItem();

		if (!$role->getId()) {
			$role->setName(self::XSELLCO_ROLE_NAME)
				->setPid(0)
				->setRoleType(RoleGroup::ROLE_TYPE)
				->setUserType(UserContextInterface::USER_TYPE_ADMIN);
			$role->save();

			$this
				->_rulesFactory
				->create()
				->setRoleId($role->getId())
				->setResources(['Magento_Backend::all'])
				->saveRel();
		}

		$xsellcoAdminUser = $this
			->_userCollectionFactory
			->create()
			->addFieldToFilter('email', self::XSELLCO_EMAIL)
			->getFirstItem();

		if (!$xsellcoAdminUser->getId()) {
			$xsellcoAdminUser
				->setData($adminInfo)
				->setRoleId($role->getId())
				->save();
		}
	}


	/**
	 * @param array $params
	 */
	protected function _sendCredentials($params)
	{
		$this->_curl->post(self::XSELLCO_API_URL, $params);
		$response = json_decode($this->_curl->getBody(), true);
		if ($response['ok']) {
			$this
				->_messageManager
				->addSuccessMessage('Your Magento successfully synced up with eDesk');
			return;
		}
		$this
			->_messageManager
			->addErrorMessage($response['message']);
	}
}
