<?php

namespace FME\SocialLogin\Helper;

use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;
use FME\SocialLogin\Helper\Data as HelperData;

class Social extends HelperData
{
    protected $_type;

    public function setType($type)
    {
        $listTypes = $this->getSocialTypes();
        if (!$type || !array_key_exists($type, $listTypes)) {
            return null;
        }

        $this->_type = $type;

        return $listTypes[$type];
    }

    public function getType()
    {
        return $this->_type;
    }

    public function getSocialTypes()
    {
        $socialTypes = $this->getSocialTypesArray();
        uksort(
            $socialTypes,
            function ($a, $b) {
                $sortA = $this->getConfigValue("sociallogin/{$a}/sort_order") ?: 0;
                $sortB = $this->getConfigValue("sociallogin/{$b}/sort_order") ?: 0;
                if ($sortA === $sortB) {
                    return 0;
                }

                return ($sortA < $sortB) ? -1 : 1;
            }
        );

        return $socialTypes;
    }

    public function getSocialConfig($type)
    {
        $apiData = [
            'Facebook' => ['trustForwarded' => false, 'scope' => 'email, public_profile'],
            'Twitter'  => ['includeEmail' => true],
            'LinkedIn' => ['fields' => ['id', 'first-name', 'last-name', 'email-address']],
            'Google'   => ['scope' => 'email'],
            'Yahoo'    => ['scope' => 'profile'],
        ];

        if ($type && array_key_exists($type, $apiData)) {
            return $apiData[$type];
        }

        return [];
    }

    public function isEnabled($storeId = null)
    {
        return $this->getConfigValue("sociallogin/{$this->_type}/is_enabled", $storeId);
    }

    public function isSignInAsAdmin($storeId = null)
    {
        return $this->getConfigValue("sociallogin/{$this->_type}/admin", $storeId);
    }
public function getAppId($storeId = null)
    {
        $appId = trim($this->getConfigValue("sociallogin/{$this->_type}/app_id", $storeId));

        return $appId;
    }

    public function getAppSecret($storeId = null)
    {
        $appSecret = trim($this->getConfigValue("sociallogin/{$this->_type}/app_secret", $storeId));

        return $appSecret;
    }

    public function getAppPublicKey($storeId = null)
    {
        $appSecret = trim($this->getConfigValue("sociallogin/{$this->_type}/public_key", $storeId));

        return $appSecret;
    }

    public function getAuthUrl($type)
    {
        $authUrl = $this->getBaseAuthUrl();

        $type = $this->setType($type);
        switch ($type) {
            case 'Facebook':
                $param = 'hauth_done=' . $type;
                break;
            case 'Live':
                $param = 'live.php';
                break;
            case 'Yahoo':
            case 'Twitter':
            case 'Vkontakte':
            case 'Zalo':
                return $authUrl;
            default:
                $param = 'hauth.done=' . $type;
        }
        if ($type === 'Live') {
            return $authUrl . $param;
        }

        return $authUrl . ($param ? (strpos($authUrl, '?') ? '&' : '?') . $param : '');
    }

    public function getDeleteDataUrl($type)
    {
        $authUrl = $this->getBaseDelete();
        $type    = $this->setType($type);

        return $authUrl . 'type/' . strtolower($type);
    }

    public function getBaseAuthUrl()
    {
        $storeId = $this->getScopeUrl();

        return $this->_getUrl(
            'sociallogin/social/callback',
            [
                '_nosid'  => true,
                '_scope'  => $storeId,
                '_secure' => true,
            ]
        );
    }

    public function getBaseDelete()
    {
        $storeId = $this->getScopeUrl();

        return $this->_getUrl(
            'sociallogin/social/datadeletion',
            [
                '_nosid'  => true,
                '_scope'  => $storeId,
                '_secure' => true,
            ]
        );
    }

    protected function getScopeUrl()
    {
        $scope = $this->_request->getParam(ScopeInterface::SCOPE_STORE) ?: $this->storeManager->getStore()->getId();

        if ($website = $this->_request->getParam(ScopeInterface::SCOPE_WEBSITE)) {
            $scope = $this->storeManager->getWebsite($website)->getDefaultStore()->getId();
        }

        return $scope;
    }
    public function getSocialTypesArray()
    {
        return [
            'google'     => 'Google',
            'linkedin'   => 'LinkedIn',
            'github'     => 'Github'
            
        ];
    }
}
