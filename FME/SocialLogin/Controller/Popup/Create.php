<?php


namespace FME\SocialLogin\Controller\Popup;

use Exception;
use Magento\Captcha\Helper\Data as CaptchaData;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Api\Data\RegionInterfaceFactory;
use Magento\Customer\Controller\Account\CreatePost;
use Magento\Customer\Helper\Address;
use Magento\Customer\Model\Account\Redirect as AccountRedirect;
use Magento\Customer\Model\CustomerExtractor;
use Magento\Customer\Model\Metadata\FormFactory;
use Magento\Customer\Model\Registration;
use Magento\Customer\Model\Session;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\DataObject;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\StateException;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\Cookie\PhpCookieManager;
use Magento\Framework\UrlFactory;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Store\Model\StoreManagerInterface;
use FME\SocialLogin\Helper\Data;

class Create extends CreatePost
{
    protected $resultJsonFactory;

    protected $captchaHelper;

    protected $socialHelper;

    private $cookieMetadataManager;

    private $cookieMetadataFactory;

    public function __construct(
        Context $context,
        Session $customerSession,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        AccountManagementInterface $accountManagement,
        Address $addressHelper,
        UrlFactory $urlFactory,
        FormFactory $formFactory,
        SubscriberFactory $subscriberFactory,
        RegionInterfaceFactory $regionDataFactory,
        AddressInterfaceFactory $addressDataFactory,
        CustomerInterfaceFactory $customerDataFactory,
        CustomerUrl $customerUrl,
        Registration $registration,
        Escaper $escaper,
        CustomerExtractor $customerExtractor,
        DataObjectHelper $dataObjectHelper,
        AccountRedirect $accountRedirect,
        CustomerRepository $customerRepository,
        JsonFactory $jsonFactory,
        Validator $formKeyValidator = null
    ) {
        parent::__construct(
            $context,
            $customerSession,
            $scopeConfig,
            $storeManager,
            $accountManagement,
            $addressHelper,
            $urlFactory,
            $formFactory,
            $subscriberFactory,
            $regionDataFactory,
            $addressDataFactory,
            $customerDataFactory,
            $customerUrl,
            $registration,
            $escaper,
            $customerExtractor,
            $dataObjectHelper,
            $accountRedirect,
            $customerRepository,
            $formKeyValidator
        );
        $this->resultJsonFactory = $jsonFactory;
    }

    protected function getJsonFactory()
    {
        return $this->resultJsonFactory;
    }
    protected function getCaptchaHelper()
    {
        if (!$this->captchaHelper) {
            $this->captchaHelper = ObjectManager::getInstance()->get(CaptchaData::class);
        }

        return $this->captchaHelper;
    }
    protected function getSocialHelper()
    {
        if (!$this->socialHelper) {
            $this->socialHelper = ObjectManager::getInstance()->get(Data::class);
        }

        return $this->socialHelper;
    }

    public function checkCaptcha()
    {
        $formId       = 'user_create';
        $captchaModel = $this->getCaptchaHelper()->getCaptcha($formId);
        $resolve      = $this->getSocialHelper()->captchaResolve($this->getRequest(), $formId);

        return !($captchaModel->isRequired() && !$captchaModel->isCorrect($resolve));
    }

    public function execute()
    {
        $resultJson = $this->getJsonFactory()->create();
        $result     = [
            'success' => false,
            'message' => []
        ];

        if (!$this->checkCaptcha()) {
            $result['message'] = __('Incorrect CAPTCHA.');

            return $resultJson->setData($result);
        }

        if ($this->session->isLoggedIn() || !$this->registration->isAllowed()) {
            $result['redirect'] = $this->urlModel->getUrl('customer/account');

            return $resultJson->setData($result);
        }

        if (!$this->getRequest()->isPost()) {
            $result['message'] = __('Data error. Please try again.');

            return $resultJson->setData($result);
        }

        $this->session->regenerateId();

        try {
            $address   = $this->extractAddress();
            $addresses = $address === null ? [] : [$address];

            $customer = $this->customerExtractor->extract('customer_account_create', $this->_request);
            $customer->setAddresses($addresses);

            $password     = $this->getRequest()->getParam('password');
            $confirmation = $this->getRequest()->getParam('password_confirmation');

            if (!$this->checkPasswordConfirmation($password, $confirmation)) {
                $result['message'][] = __('Please make sure your passwords match.');
            } else {
                $customer = $this->accountManagement
                    ->createAccount($customer, $password);

                if ($this->getRequest()->getParam('is_subscribed', false)) {
                    $this->subscriberFactory->create()->subscribeCustomerById($customer->getId());
                }

                $this->_eventManager->dispatch(
                    'customer_register_success',
                    [
                        'account_controller' => $this,
                        'customer'           => $customer
                    ]
                );

                $confirmationStatus = $this->accountManagement->getConfirmationStatus($customer->getId());

                if ($confirmationStatus === AccountManagementInterface::ACCOUNT_CONFIRMATION_REQUIRED) {
                    $email = $this->customerUrl->getEmailConfirmationUrl($customer->getEmail());
                    // @codingStandardsIgnoreStart
                    $result['success'] = true;
                    $this->messageManager->addSuccess(
                        __(
                            'You must confirm your account. Please check your email for the confirmation link or <a href="%1">click here</a> for a new link.',
                            $email
                        )
                    );
                } else {
                    $result['success']   = true;
                    $result['message'][] = __('Create an account successfully. Please wait...');
                    $this->session->setCustomerDataAsLoggedIn($customer);
                }

                if ($this->getCookieManager()->getCookie('mage-cache-sessid')) {
                    $metadata = $this->getCookieMetadataFactory()->createCookieMetadata();
                    $metadata->setPath('/');
                    $this->getCookieManager()->deleteCookie('mage-cache-sessid', $metadata);
                }
            }
        } catch (StateException $e) {
            $url = $this->urlModel->getUrl('customer/account/forgotpassword');
            // @codingStandardsIgnoreStart
            $result['message'][] = __(
                'There is already an account with this email address. If you are sure that it is your email address, <a href="%1">click here</a> to get your password and access your account.',
                $url
            );
        } catch (InputException $e) {
            $result['message'][] = $this->escaper->escapeHtml($e->getMessage());
            foreach ($e->getErrors() as $error) {
                $result['message'][] = $this->escaper->escapeHtml($error->getMessage());
            }
        } catch (LocalizedException $e) {
            $result['message'][] = $this->escaper->escapeHtml($e->getMessage());
            if ($this->session->getMpRedirectUrl()) {
                $result['redirect'] = $this->session->getMpRedirectUrl();
                $this->session->unsMpRedirectUrl();
            }
        } catch (Exception $e) {
            $result['message'][] = __('We can\'t save the customer.');
        }

        $result['url'] = $this->_loginPostRedirect();
        $this->session->setCustomerFormData($this->getRequest()->getPostValue());

        return $resultJson->setData($result);
    }

    protected function getCookieManager()
    {
        if (!$this->cookieMetadataManager) {
            $this->cookieMetadataManager = ObjectManager::getInstance()->get(
                PhpCookieManager::class
            );
        }

        return $this->cookieMetadataManager;
    }

    protected function getCookieMetadataFactory()
    {
        if (!$this->cookieMetadataFactory) {
            $this->cookieMetadataFactory = ObjectManager::getInstance()->get(
                CookieMetadataFactory::class
            );
        }

        return $this->cookieMetadataFactory;
    }

    protected function checkPasswordConfirmation($password, $confirmation)
    {
        return $password === $confirmation;
    }

    protected function _loginPostRedirect()
    {
        $url = $this->_url->getUrl('customer/account');

        $object = ObjectManager::getInstance()->create(DataObject::class, ['url' => $url]);
        $this->_eventManager->dispatch('social_manager_get_login_redirect', [
            'object'  => $object,
            'request' => $this->_request
        ]);
        $url = $object->getUrl();

        return $url;
    }
}
