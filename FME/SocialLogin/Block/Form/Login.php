<?php


namespace FME\SocialLogin\Block\Form;

class Login extends \Magento\Customer\Block\Form\Login
{
    protected function _prepareLayout()
    {
        return $this;
    }
}
