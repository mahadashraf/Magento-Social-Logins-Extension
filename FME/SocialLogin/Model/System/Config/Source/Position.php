<?php

namespace FME\SocialLogin\Model\System\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class Position implements ArrayInterface
{
    const PAGE_LOGIN       = 1;
    const PAGE_CREATE      = 2;
    const PAGE_POPUP       = 3;
    const PAGE_AUTHEN      = 4;
    const PAGE_FORGOT_PASS = 5;


    public function toOptionArray()
    {
        return [
            ['value' => '', 'label' => __('-- Please Select --')],
            ['value' => self::PAGE_LOGIN, 'label' => __('Customer Login Page')],
            ['value' => self::PAGE_CREATE, 'label' => __('Customer Create Page')],
            ['value' => self::PAGE_FORGOT_PASS, 'label' => __('Forgot Your Password Page')],
            ['value' => self::PAGE_POPUP, 'label' => __('Social Login Popup')],
            ['value' => self::PAGE_AUTHEN, 'label' => __('Customer Authentication Popup')]
        ];
    }
}
