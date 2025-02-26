<?php


namespace FME\SocialLogin\Controller\Social;

use Exception;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Model\Account\Redirect as AccountRedirect;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\Cookie\FailureToSendException;
use Magento\Framework\Stdlib\Cookie\PhpCookieManager;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Integration\Model\Oauth\TokenFactory;
use FME\SocialLogin\Helper\Social as SocialHelper;
use FME\SocialLogin\Model\Social;

abstract class AbstractSocial extends Action
{
    protected $session;

    protected $storeManager;

    protected $accountManager;

    protected $apiHelper;

    protected $apiObject;

    protected $accountRedirect;

    protected $cookieMetadataManager;

    protected $cookieMetadataFactory;

    protected $resultRawFactory;

    protected $customerModel;

    protected $tokenFactory;

    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        AccountManagementInterface $accountManager,
        SocialHelper $apiHelper,
        Social $apiObject,
        Session $customerSession,
        AccountRedirect $accountRedirect,
        RawFactory $resultRawFactory,
        Customer $customerModel,
        TokenFactory $tokenFactory
    ) {
        $this->storeManager      = $storeManager;
        $this->accountManager    = $accountManager;
        $this->apiHelper         = $apiHelper;
        $this->apiObject         = $apiObject;
        $this->session           = $customerSession;
        $this->accountRedirect   = $accountRedirect;
        $this->resultRawFactory  = $resultRawFactory;
        $this->customerModel     = $customerModel;
        $this->tokenFactory      = $tokenFactory;

        parent::__construct($context);
    }

    public function getStore()
    {
        return $this->storeManager->getStore();
    }

    public function createCustomerProcess($userProfile, $type)
    {
        $name = explode(' ', $userProfile->displayName ?: __('New User'));
        if (strtolower($type) === 'steam') {
            $userProfile->identifier = trim($userProfile->identifier, "https://steamcommunity.com/openid/id/");
        }
        $user = array_merge(
            [
                'email'      => $userProfile->email ?: $userProfile->identifier . '@' . strtolower($type) . '.com',
                'firstname'  => $userProfile->firstName ?: (array_shift($name) ?: $userProfile->identifier),
                'lastname'   => $userProfile->lastName ?: (array_shift($name) ?: $userProfile->identifier),
                'identifier' => $userProfile->identifier,
                'type'       => $type,
                'password'   => isset($userProfile->password) ? $userProfile->password :
                    $this->getRequest()->getParam('password')
            ],
            $this->getUserData($userProfile)
        );

        return $this->createCustomer($user, $type);
    }

    public function createCustomer($user, $type)
    {
        $customer = $this->apiObject->getCustomerByEmail($user['email'], $this->getStore()->getWebsiteId());
        if ($customer->getId()) {
            $this->apiObject->setAuthorCustomer($user['identifier'], $customer->getId(), $type);
        } else {
            try {
                $customer = $this->apiObject->createCustomerSocial($user, $this->getStore());
            } catch (Exception $e) {
                $this->emailRedirect($e->getMessage(), false);

                return false;
            }
        }

        return $customer;
    }

    protected function getUserData($profile)
    {
        return [];
    }

    public function emailRedirect($apiLabel, $needTranslate = true)
    {
        $message = $needTranslate ? __('Email is Null, Please enter email in your %1 profile', $apiLabel) : $apiLabel;
        $this->messageManager->addErrorMessage($message);
        $this->_redirect('customer/account/login');

        return $this;
    }
    protected function _loginPostRedirect()
    {
        $url = $this->_url->getUrl('customer/account');

        if ($this->_request->getParam('authen') === 'popup') {
            $url = $this->_url->getUrl('checkout');
        } else {
            $requestedRedirect = $this->accountRedirect->getRedirectCookie();
            if ($requestedRedirect && !$this->apiHelper->getConfigValue('customer/startup/redirect_dashboard')) {
                $url = $this->_redirect->success($requestedRedirect);
                $this->accountRedirect->clearRedirectCookie();
            }
        }

        $object = ObjectManager::getInstance()->create(DataObject::class, ['url' => $url]);
        $this->_eventManager->dispatch(
            'social_manager_get_login_redirect',
            [
                'object'  => $object,
                'request' => $this->_request
            ]
        );
        $url = $object->getUrl();

        return $url;
    }

    public function _appendJs($content = null, $customerToken = null)
    {
        /** @var Raw $resultRaw */
        $resultRaw = $this->resultRawFactory->create();

        if ($this->_loginPostRedirect()) {
            $raw = $resultRaw->setContents(
                $content ?: sprintf(
                    "<script>window.opener.socialCallback('%s', window);</script>",
                    $this->_loginPostRedirect()
                )
            );
        } else {
            $raw = $resultRaw->setContents($content ?:
                "<script>
                    window.opener.location.reload(true);
                    window.close();
                    if ('{$customerToken}') {
                        window.MP_ACCESS_TOKEN_KEY = '{$customerToken}';
                    }
                </script>");
        }

        return $raw;
    }

    public function refresh($customer)
    {
        if ($customer && $customer->getId()) {
            $this->session->setCustomerAsLoggedIn($customer);
            $this->session->regenerateId();

            if ($this->getCookieManager()->getCookie('mage-cache-sessid')) {
                $metadata = $this->getCookieMetadataFactory()->createCookieMetadata();
                $metadata->setPath('/');
                $this->getCookieManager()->deleteCookie('mage-cache-sessid', $metadata);
            }
        }
    }

    private function getCookieManager()
    {
        if (!$this->cookieMetadataManager) {
            $this->cookieMetadataManager = ObjectManager::getInstance()->get(
                PhpCookieManager::class
            );
        }

        return $this->cookieMetadataManager;
    }

    private function getCookieMetadataFactory()
    {
        if (!$this->cookieMetadataFactory) {
            $this->cookieMetadataFactory = ObjectManager::getInstance()->get(
                CookieMetadataFactory::class
            );
        }

        return $this->cookieMetadataFactory;
    }

    public function login($type)
    {
        try {
            if (!$type) {
                $type = $this->apiObject->getProviderConnected();
            }
            $userProfile = $this->apiObject->getUserProfile($type);
            if (!$userProfile->identifier) {
                return $this->emailRedirect($type);
            }
        } catch (Exception $e) {
            $this->setBodyResponse($e->getMessage());

            return;
        }

        $customer      = $this->apiObject->getCustomerBySocial($userProfile->identifier, $type);
        $customerData  = $this->customerModel->load($customer->getId());
        if ($customer->getId()) {
            $customerToken = $this->getCustomerToken($customer->getId());
        } else {
            $customerToken = '';
        }

        if (!$customer->getId()) {
            $requiredMoreInfo = (int) $this->apiHelper->requiredMoreInfo();

            if ((!$userProfile->email && $requiredMoreInfo === 2) || $requiredMoreInfo === 1) {
                $this->session->setUserProfile($userProfile);

                return $this->_appendJs(
                    sprintf(
                        "<script>
                                window.close();
                                window.opener.fakeEmailCallback('%s','%s','%s');
                                window.MP_ACCESS_TOKEN_KEY = '{$customerToken}';
                        </script>",
                        $type,
                        $userProfile->firstName,
                        $userProfile->lastName
                    )
                );
            }

            $customer = $this->createCustomerProcess($userProfile, $type);
        } elseif ($this->apiHelper->isCheckMode() && $customerData->getData('password_hash') === null) {
            $this->session->setUserProfile($userProfile);

            return $this->_appendJs(
                sprintf(
                    "<script>
                            window.close();
                            window.opener.fakeEmailCallback('%s','%s','%s');
                            window.MP_ACCESS_TOKEN_KEY = '{$customerToken}';
                    </script>",
                    $type,
                    $userProfile->firstName,
                    $userProfile->lastName
                )
            );

        }
        $this->refresh($customer);

        return $this->_appendJs(null, $customerToken);
    }

    public function checkRequest($key, $value = null)
    {
        $param = $this->getRequest()->getParam($key, false);

        if ($value) {
            return $param === $value;
        }

        return $param;
    }

    public function checkCustomerLogin()
    {
        return true;
    }
    protected function setBodyResponse($message)
    {
        $content = '<html><head></head><body>';
        $content .= '<div class="message message-error">' . __('Ooophs, we got an error: %1', $message) . '</div>';
        $content .= <<<Style
<style type="text/css">
    .message{
        background: #fffbbb;
        border: none;
        border-radius: 0;
        color: #333333;
        font-size: 1.4rem;
        margin: 0 0 10px;
        padding: 1.8rem 4rem 1.8rem 1.8rem;
        position: relative;
        text-shadow: none;
    }
    .message-error{
        background:#ffcccc;
    }
</style>
Style;
        $content .= '</body></html>';
        $this->getResponse()->setBody($content);
    }

    protected function getCustomerToken($customerId)
    {
        $tokenModelFactory = $this->tokenFactory->create();

        return $tokenModelFactory->createCustomerToken($customerId)->getToken();
    }
}
