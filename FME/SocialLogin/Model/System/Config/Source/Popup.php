<?php

namespace FME\SocialLogin\Model\System\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class Popup implements ArrayInterface
{
    const POPUP_LOGIN = 'popup_login';

    public function toOptionArray()
    {
        return [
            ['value' => '', 'label' => __('None')],
            ['value' => self::POPUP_LOGIN, 'label' => __('Popup Login')]
        ];
    }
}
