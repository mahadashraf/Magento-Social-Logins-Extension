<?php

namespace FME\SocialLogin\Controller\Social;

use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\Cookie\FailureToSendException;

class Login extends AbstractSocial
{
    public function execute()
    {
        if ($this->checkCustomerLogin() && $this->session->isLoggedIn()) {
            $this->_redirect('customer/account');

            return;
        }

        $type = $this->apiHelper->setType($this->getRequest()->getParam('type'));

        if (!$type) {
            $this->_forward('noroute');

            return;
        }

        return $this->login($type);
    }
}
