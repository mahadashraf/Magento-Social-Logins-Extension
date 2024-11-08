<?php


namespace FME\SocialLogin\Block\Popup;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use FME\SocialLogin\Helper\Social as SocialHelper;
use FME\SocialLogin\Model\System\Config\Source\Position;

class Social extends Template
{
    protected $socialHelper;

    public function __construct(
        Context $context,
        SocialHelper $socialHelper,
        array $data = []
    ) {
        $this->socialHelper = $socialHelper;

        parent::__construct($context, $data);
    }

    public function getAvailableSocials($storeId = null)
    {
        $availabelSocials = [];
    
        foreach ($this->socialHelper->getSocialTypes() as $socialKey => $socialLabel) {
            $this->socialHelper->setType($socialKey);
            if ($this->socialHelper->isEnabled($storeId)) {
                // Add image path for each social type
                $imageUrl = $this->getViewFileUrl("FME_SocialLogin::images/{$socialKey}.png");
    
                $availabelSocials[$socialKey] = [
                    'label'     => $socialLabel,
                    'login_url' => $this->getLoginUrl($socialKey),
                    'image'     => $imageUrl,  // Add the image URL here
                ];
            }
        }
    
        return $availabelSocials;
    }
    

    public function getBtnKey($key)
    {
        switch ($key) {
            case 'vkontakte':
                $class = 'vk';
                break;
            default:
                $class = $key;
        }

        return $class;
    }

    public function getSocialButtonsConfig()
    {
        $availableButtons = $this->getAvailableSocials();
        foreach ($availableButtons as $key => &$button) {
            $button['url']     = $this->getLoginUrl($key, ['authen' => 'popup']);
            $button['key']     = $key;
            $button['btn_key'] = $this->getBtnKey($key);
        }

        return $availableButtons;
    }
    public function canShow($position = null)
    {
        $displayConfig = $this->socialHelper->getConfigGeneral('social_display');
        $displayConfig = explode(',', $displayConfig ?? '');

        if (!$position) {
            $controllerName = $this->getRequest()->getFullActionName();
            switch ($controllerName) {
                case 'customer_account_login':
                    $position = Position::PAGE_LOGIN;
                    break;
                case 'customer_account_forgotpassword':
                    $position = Position::PAGE_FORGOT_PASS;
                    break;
                case 'customer_account_create':
                    $position = Position::PAGE_CREATE;
                    break;
                default:
                    return false;
            }
        }

        return in_array($position, $displayConfig);
    }

    public function getLoginUrl($socialKey, $params = [])
    {
        $params['type'] = $socialKey;

        return $this->getUrl('sociallogin/social/login', $params);
    }
}
