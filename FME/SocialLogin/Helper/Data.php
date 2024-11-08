<?php


namespace FME\SocialLogin\Helper;

use Magento\Framework\App\RequestInterface;
use FME\Core\Helper\AbstractData as CoreHelper;

class Data extends CoreHelper
{
    const CONFIG_MODULE_PATH = 'sociallogin';

    public function captchaResolve(RequestInterface $request, $formId)
    {
        $captchaParams = $request->getPost(\Magento\Captcha\Helper\Data::INPUT_NAME_FIELD_VALUE);

        return isset($captchaParams[$formId]) ? $captchaParams[$formId] : '';
    }

    public function canSendPassword($storeId = null)
    {
        return $this->getConfigGeneral('send_password', $storeId);
    }

    public function getPopupEffect($storeId = null)
    {
        return $this->getConfigGeneral('popup_effect', $storeId);
    }

    public function getStyleManagement($storeId = null)
    {
        $style = $this->getConfigGeneral('style_management', $storeId);
        if ($style === 'custom') {
            return $this->getCustomColor($storeId);
        }

        return $style;
    }
    public function getCustomColor($storeId = null)
    {
        return $this->getConfigGeneral('custom_color', $storeId);
    }

    public function getCustomCss($storeId = null)
    {
        return $this->getConfigGeneral('custom_css', $storeId);
    }

    public function requiredMoreInfo($storeId = null)
    {
        return $this->getConfigGeneral('require_more_info', $storeId);
    }

    public function getFieldCanShow($storeId = null)
    {
        return $this->getConfigGeneral('information_require', $storeId);
    }
    public function isSecure()
    {
        return $this->getConfigValue('web/secure/use_in_frontend');
    }

    public function isReplaceAuthModal($storeId = null)
    {
        return $this->getPopupLogin() && $this->getConfigGeneral('authentication_popup', $storeId);
    }

    public function getPopupLogin($storeId = null)
    {
        return $this->getConfigGeneral('popup_login', $storeId);
    }

    public function isCheckMode($storeId = null)
    {
        return $this->getConfigGeneral('check_mode', $storeId) && $this->getPopupLogin();
    }

    public function getConfigGoogleRecaptcha($code = '', $storeId = null)
    {
        return $this->getConfigValue('googlerecaptcha' . $code, $storeId);
    }

    public function isEnabledGGRecaptcha($storeId = null)
    {
        return $this->getConfigGoogleRecaptcha('/general/enabled', $storeId)
            && $this->getConfigGoogleRecaptcha('/frontend/enabled', $storeId);
    }
}
