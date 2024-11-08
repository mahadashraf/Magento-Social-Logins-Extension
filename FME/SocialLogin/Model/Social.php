<?php

namespace FME\SocialLogin\Model;

use Exception;
use Hybridauth\Exception\InvalidArgumentException;
use Hybridauth\Exception\UnexpectedValueException;
use Hybridauth\Hybridauth as Hybrid_Auth;
use Hybridauth\Storage\Session as HybridAuthSession;
use Hybridauth\User\Profile;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\EmailNotificationInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\State\InputMismatchException;
use Magento\Framework\Math\Random;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\StoreManagerInterface;
use Magento\User\Model\User;

class Social extends AbstractModel
{
    const STATUS_PROCESS = 'processing';

    const STATUS_LOGIN = 'logging';

    const STATUS_CONNECT = 'connected';

    protected $storeManager;

    protected $customerFactory;

    protected $customerDataFactory;

    protected $customerRepository;

    protected $apiHelper;

    protected $apiName;

    protected $_userModel;

    protected $_dateTime;

    protected $_hybridAuthSession;
    protected $_request;

    public function __construct(
        Context $context,
        Registry $registry,
        CustomerFactory $customerFactory,
        CustomerInterfaceFactory $customerDataFactory,
        CustomerRepositoryInterface $customerRepository,
        StoreManagerInterface $storeManager,
        \FME\SocialLogin\Helper\Social $apiHelper,
        User $userModel,
        DateTime $dateTime,
        HybridAuthSession $hybridAuthSession,
        RequestInterface $request,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->customerFactory     = $customerFactory;
        $this->customerRepository  = $customerRepository;
        $this->customerDataFactory = $customerDataFactory;
        $this->storeManager        = $storeManager;
        $this->apiHelper           = $apiHelper;
        $this->_userModel          = $userModel;
        $this->_dateTime           = $dateTime;
        $this->_hybridAuthSession  = $hybridAuthSession;
        $this->_request            = $request;

        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }
  protected function _construct()
    {
        $this->_init(ResourceModel\Social::class);
    }

    public function getCustomerBySocial($identify, $type)
    {
        $websiteId = $this->storeManager->getWebsite()->getId();
        $customer  = $this->customerFactory->create();

        $socialCustomer = $this->getCollection()
            ->addFieldToFilter('social_id', $identify)
            ->addFieldToFilter('type', $type)
            ->addFieldToFilter('status', ['null' => 'true'])
            ->addFieldToFilter('website_id', $websiteId)
            ->getFirstItem();

        if ($socialCustomer && $socialCustomer->getId()) {
            $customer->load($socialCustomer->getCustomerId());
        }

        return $customer;
    }

    public function getCustomerByEmail($email, $websiteId = null)
    {
        $customer = $this->customerFactory->create();
        $customer->setWebsiteId($websiteId ?: $this->storeManager->getWebsite()->getId());
        $customer->loadByEmail($email);

        return $customer;
    }
    public function createCustomerSocial($data, $store)
    {
        $customer = $this->customerDataFactory->create();
        $customer->setFirstname($data['firstname'])
            ->setLastname($data['lastname'])
            ->setEmail($data['email'])
            ->setStoreId($store->getId())
            ->setWebsiteId($store->getWebsiteId())
            ->setCreatedIn($store->getName());

        try {
            if ($data['password'] !== null) {
                $customer = $this->customerRepository->save($customer, $data['password']);
                $this->getEmailNotification()->newAccount(
                    $customer,
                    EmailNotificationInterface::NEW_ACCOUNT_EMAIL_REGISTERED,
                    '',
                    $store->getId()
                );
            } else {
                // If customer exists existing hash will be used by Repository
                $customer = $this->customerRepository->save($customer);

                $objectManager     = ObjectManager::getInstance();
                $mathRandom        = $objectManager->get(Random::class);
                $newPasswordToken  = $mathRandom->getUniqueHash();
                $accountManagement = $objectManager->get(AccountManagementInterface::class);
                $accountManagement->changeResetPasswordLinkToken($customer, $newPasswordToken);
            }

            if ($this->apiHelper->canSendPassword($store)) {
                $this->getEmailNotification()->newAccount(
                    $customer,
                    EmailNotificationInterface::NEW_ACCOUNT_EMAIL_REGISTERED_NO_PASSWORD,
                    '',
                    $store->getId()
                );
            }

            $this->setAuthorCustomer($data['identifier'], $customer->getId(), $data['type']);
        } catch (AlreadyExistsException $e) {
            throw new InputMismatchException(
                __('A customer with the same email already exists in an associated website.')
            );
        } catch (Exception $e) {
            if ($customer->getId()) {
                $this->_registry->register('isSecureArea', true, true);
                $this->customerRepository->deleteById($customer->getId());
            }
            throw $e;
        }

        return $this->customerFactory->create()->load($customer->getId());
    }

    protected function getEmailNotification()
    {
        return ObjectManager::getInstance()->get(EmailNotificationInterface::class);
    }

    public function setAuthorCustomer($identifier, $customerId, $type)
    {
        $this->setData(
            [
                'social_id'              => $identifier,
                'customer_id'            => $customerId,
                'type'                   => $type,
                'is_send_password_email' => $this->apiHelper->canSendPassword(),
                'social_created_at'      => $this->_dateTime->date(),
                'website_id'             => $this->storeManager->getWebsite()->getId()
            ]
        )
            ->setId(null)->save();

        return $this;
    }

    public function getUserProfile($apiName)
    {
        $apiName = strtolower($apiName);
        $config  = [
            'callback'   => $this->apiHelper->getAuthUrl($apiName),
            'providers'  => [
                $apiName => $this->getProviderData($apiName)
            ],
            'debug_mode' => false,
            'debug_file' => BP . '/var/log/social.log'
        ];
        $auth    = new Hybrid_Auth($config);
        try {
            $adapter     = $auth->authenticate($apiName);
            $userProfile = $adapter->getUserProfile();
        } catch (Exception $e) {
            $auth->disconnectAllAdapters();
            throw  $e;
        }

        return $userProfile;
    }

    public function getProviderData($apiName)
    {
        if (!$this->apiHelper->getType()) {
            $this->apiHelper->setType($apiName);
        }
        $data = [
            'enabled' => $this->apiHelper->isEnabled(),
            'keys'    => [
                'id'         => $this->apiHelper->getAppId(),
                'key'        => $this->apiHelper->getAppId(),
                'secret'     => $apiName !== 'steam' ? $this->apiHelper->getAppSecret() : '',
                'public_key' => $apiName === 'odnoklassniki' ? $this->apiHelper->getAppPublicKey() : ''
            ],
            'adapter' => $this->getAdapter($apiName)
        ];

        return array_merge($data, $this->apiHelper->getSocialConfig($apiName));
    }

    protected function getAdapter($type)
    {
        $adapters = [
            'zalo'      => 'Zalo',
            'vkontakte' => 'Vkontakte',
            'live'      => 'MicrosoftGraph'
        ];
        if (isset($adapters[$type])) {
            return 'FME\SocialLogin\Model\Providers' . "\\" . $adapters[$type];
        }
        $adaptersPro = [
            'pinterest'     => 'Pinterest',
            'odnoklassniki' => 'Odnoklassniki',
            'mailru'        => 'Mailru'
        ];
        if (isset($adaptersPro[$type])) {
            return 'FME\SocialLoginPro\Model\Providers' . "\\" . $adaptersPro[$type];
        }

        return null;
    }

    public function getUserBySocial($identify, $type)
    {
        $user = $this->_userModel;

        $socialCustomer = $this->getCollection()
            ->addFieldToFilter('social_id', $identify)
            ->addFieldToFilter('type', $type)->addFieldToFilter('user_id', ['notnull' => true])
            ->getFirstItem();

        if ($socialCustomer && $socialCustomer->getId()) {
            $user->load($socialCustomer->getUserId());
        }

        return $user;
    }

    public function getUser($type, $identifier)
    {
        return $this->getCollection()
            ->addFieldToSelect('user_id')
            ->addFieldToSelect('social_customer_id')
            ->addFieldToFilter('type', $type)
            ->addFieldToFilter('social_id', base64_decode($identifier))
            ->addFieldToFilter('status', self::STATUS_LOGIN)
            ->getFirstItem();
    }

    public function updateAuthCustomer($socialCustomerId, $identifier)
    {
        $social = $this->load($socialCustomerId);
        $social->addData(
            [
                'social_id' => $identifier,
                'status'    => self::STATUS_CONNECT
            ]
        );
        $social->save();

        return $this;
    }

    public function updateStatus($socialCustomerId, $status)
    {
        $social = $this->load($socialCustomerId);
        $social->addData(['status' => $status])->save();

        return $this;
    }

    public function getProviderConnected()
    {
        $providers = ['twitter', 'yahoo', 'vkontakte', 'zalo', 'pinterest'];
        foreach ($providers as $provider) {
            $state = $this->_hybridAuthSession->get($provider . '.request_token');
            if (!$state) {
                $state = $this->_hybridAuthSession->get($provider . '.authorization_state');
            }
            $stateRemote = $this->_request->getParam('oauth_token');
            if (!$stateRemote) {
                $stateRemote = $this->_request->getParam('state');

            }
            if ($state === $stateRemote) {
                return $provider;
            }
        }

        throw new  NoSuchEntityException(__("Unknown Provider"));
    }
}
