<?php


namespace FME\SocialLogin\Block\Form;

class Register extends \Magento\Customer\Block\Form\Register
{
    protected function _prepareLayout()
    {
        return $this;
    }
}
