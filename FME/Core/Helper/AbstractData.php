<?php


namespace FME\Core\Helper;

use Exception;
use Magento\Backend\App\Config;
use Magento\Framework\App\Area;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\State;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Design\Theme\ThemeProviderInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use FME\Core\Model\Config\Source\NoticeType;

class AbstractData extends AbstractHelper
{
    const CONFIG_MODULE_PATH = 'FME';
    protected $_data = [];
    protected $storeManager;
    protected $objectManager;
    protected $backendConfig;
    protected $isArea = [];
    public function __construct(
        Context $context,
        ObjectManagerInterface $objectManager,
        StoreManagerInterface $storeManager
    ) {
        $this->objectManager = $objectManager;
        $this->storeManager  = $storeManager;

        parent::__construct($context);
    }

    public function isEnabled($storeId = null)
    {
        return $this->getConfigGeneral('enabled', $storeId);
    }

    public function isEnabledNotificationUpdate($storeId = null)
    {
        $isEnable   = $this->getConfigGeneral('notice_enable', $storeId);
        $noticeType = $this->getConfigGeneral('notice_type', $storeId);
        if ($noticeType) {
            $noticeType = explode(',', $noticeType);
            $noticeType = in_array(NoticeType::TYPE_NEWUPDATE, $noticeType);
        }

        return $isEnable && $noticeType;
    }

    public function getConfigGeneral($code = '', $storeId = null)
    {
        $code = ($code !== '') ? '/' . $code : '';

        return $this->getConfigValue(static::CONFIG_MODULE_PATH . '/general' . $code, $storeId);
    }
    public function getModuleConfig($field = '', $storeId = null)
    {
        $field = ($field !== '') ? '/' . $field : '';

        return $this->getConfigValue(static::CONFIG_MODULE_PATH . $field, $storeId);
    }

    public function getConfigValue($field, $scopeValue = null, $scopeType = ScopeInterface::SCOPE_STORE)
    {
        if ($scopeValue === null && !$this->isArea()) {
            /** @var Config $backendConfig */
            if (!$this->backendConfig) {
                $this->backendConfig = $this->objectManager->get(\Magento\Backend\App\ConfigInterface::class);
            }

            return $this->backendConfig->getValue($field);
        }

        return $this->scopeConfig->getValue($field, $scopeType, $scopeValue);
    }

    public function getData($name)
    {
        if (array_key_exists($name, $this->_data)) {
            return $this->_data[$name];
        }

        return null;
    }
    public function setData($name, $value)
    {
        $this->_data[$name] = $value;

        return $this;
    }
    public function getCurrentUrl()
    {
        $model = $this->objectManager->get(UrlInterface::class);

        return $model->getCurrentUrl();
    }

    public function versionCompare($ver, $operator = '>=')
    {
        $productMetadata = $this->objectManager->get(ProductMetadataInterface::class);
        $version         = $productMetadata->getVersion(); //will return the magento version

        return version_compare($version, $ver, $operator);
    }

    public function serialize($data)
    {
        if ($this->versionCompare('2.2.0')) {
            return self::jsonEncode($data);
        }

        return $this->getSerializeClass()->serialize($data);
    }

    public function unserialize($string)
    {
        if ($this->versionCompare('2.2.0')) {
            return self::jsonDecode($string);
        }

        return $this->getSerializeClass()->unserialize($string);
    }

    public static function jsonEncode($valueToEncode)
    {
        try {
            $encodeValue = self::getJsonHelper()->jsonEncode($valueToEncode);
        } catch (Exception $e) {
            $encodeValue = '{}';
        }

        return $encodeValue;
    }

    public static function jsonDecode($encodedValue)
    {
        try {
            $decodeValue = self::getJsonHelper()->jsonDecode($encodedValue);
        } catch (Exception $e) {
            $decodeValue = [];
        }

        return $decodeValue;
    }

    public function isAdmin()
    {
        return $this->isArea(Area::AREA_ADMINHTML);
    }

    public function isArea($area = Area::AREA_FRONTEND)
    {
        if (!isset($this->isArea[$area])) {
            /** @var State $state */
            $state = $this->objectManager->get(\Magento\Framework\App\State::class);

            try {
                $this->isArea[$area] = ($state->getAreaCode() == $area);
            } catch (Exception $e) {
                $this->isArea[$area] = false;
            }
        }

        return $this->isArea[$area];
    }

    public function createObject($path, $arguments = [])
    {
        return $this->objectManager->create($path, $arguments);
    }

    public function getObject($path)
    {
        return $this->objectManager->get($path);
    }

    public static function getJsonHelper()
    {
        return ObjectManager::getInstance()->get(JsonHelper::class);
    }

    protected function getSerializeClass()
    {
        return $this->objectManager->get('Zend_Serializer_Adapter_PhpSerialize');
    }

    public function getEdition()
    {
        return $this->objectManager->get(ProductMetadataInterface::class)->getEdition();
    }

    public static function extractBody($response_str)
    {
        $parts = preg_split('|(?:\r\n){2}|m', $response_str, 2);
        if (isset($parts[1])) {
            return $parts[1];
        }

        return '';
    }
    public static function getHtmlJqColorPicker(string $htmlId, $value = '')
    {
        return <<<HTML
<script type="text/javascript">
        require(["jquery","jquery/colorpicker/js/colorpicker"], function ($) {
            $(document).ready(function () {
                
                var el = $("#{$htmlId}");
                el.css("backgroundColor", "{$value}");
                el.ColorPicker({
                    color: "{$value}",
                    onChange: function (hsb, hex, rgb) {
                        el.css("backgroundColor", "#" + hex).val("#" + hex);
                    }
                });
            });
        });
</script>
HTML;
    }

    public function checkHyvaTheme()
    {
        try {
            $themeCode = $this->getThemeCodeByCache();
        } catch (\Exception $e) {
            try {
                /** @var ThemeProviderInterface $themeProviderInterface */
                $themeProviderInterface = $this->objectManager->create(ThemeProviderInterface::Class);
                $themeId                = $this->storeManager->getStore()->getConfig('design/theme/theme_id');
                $theme                  = $themeProviderInterface->getThemeById($themeId);
                $themeCode              = $theme->getCode();
            } catch (NoSuchEntityException $noSuchEntityException) {
                return false;
            }
        }

        if (str_contains($themeCode, 'Hyva')) {
            return true;
        }

        return false;
    }

    private function getThemeCodeByCache()
    {
        /** @var DesignInterface $themeProviderInterface */
        $themeProviderInterface = $this->objectManager->create(DesignInterface::Class);
        $theme                  = $themeProviderInterface->getDesignTheme();

        return $theme->getCode();
    }
}
