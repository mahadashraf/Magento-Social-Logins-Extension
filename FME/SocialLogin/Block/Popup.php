<?php


namespace FME\SocialLogin\Block;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use FME\SocialLogin\Helper\Data as HelperData;

class Popup extends Template
{
    protected $helperData;

    protected $customerSession;

    public function __construct(
        Context $context,
        HelperData $helperData,
        CustomerSession $customerSession,
        array $data = []
    ) {
        $this->helperData      = $helperData;
        $this->customerSession = $customerSession;

        parent::__construct($context, $data);
    }
    public function isEnabled()
    {
        if (str_contains($this->_request->getFullActionName(), 'customer_account')) {
            return false;
        }

        if ($this->helperData->isEnabled() && !$this->customerSession->isLoggedIn() && $this->helperData->getPopupLogin()) {
            return $this->helperData->getPopupLogin();
        }

        return false;
    }

    public function getFormParams()
    {
        $params = [
            'headerLink'    => $this->getHeaderLink(),
            'popupEffect'   => $this->getPopupEffect(),
            'formLoginUrl'  => $this->getFormLoginUrl(),
            'forgotFormUrl' => $this->getForgotFormUrl(),
            'createFormUrl' => $this->getCreateFormUrl(),
            'fakeEmailUrl'  => $this->getFakeEmailUrl(),
            'showFields'    => $this->getFieldCanShow(),
            'popupLogin'    => $this->isEnabled(),
            'actionName'    => $this->_request->getFullActionName(),
            'checkMode'     => $this->isCheckMode()
        ];

        return json_encode($params);
    }

    public function getFieldCanShow()
    {
        return $this->helperData->getFieldCanShow();
    }

    public function getHeaderLink()
    {
        $links = $this->helperData->getConfigGeneral('link_trigger');

        return $links ?: '.header .links, .section-item-content .header.links';
    }

    public function getPopupEffect()
    {
        return $this->helperData->getPopupEffect();
    }

    public function getFormLoginUrl()
    {
        return $this->getUrl('customer/ajax/login', ['_secure' => $this->isSecure()]);
    }

    public function getFakeEmailUrl()
    {
        return $this->getUrl('sociallogin/social/email', ['_secure' => $this->isSecure()]);
    }

    public function getForgotFormUrl()
    {
        return $this->getUrl('sociallogin/popup/forgot', ['_secure' => $this->isSecure()]);
    }

    public function getCreateFormUrl()
    {
        return $this->getUrl('sociallogin/popup/create', ['_secure' => $this->isSecure()]);
    }

    public function isSecure()
    {
        return (bool) $this->helperData->isSecure();
    }

    public function getStyleManagement()
    {
        return $this->helperData->getStyleManagement();
    }

    public function isRequireMoreInfo()
    {
        return ($this->helperData->requiredMoreInfo() && $this->isEnabled());
    }

    public function isCheckMode()
    {
        return (bool) $this->helperData->isCheckMode();
    }
}
