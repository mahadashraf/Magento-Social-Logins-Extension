<?php

namespace FME\SocialLogin\Controller\Social;

use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\Cookie\FailureToSendException;

class Callback extends AbstractSocial
{
    public function execute()
    {
        $param = $this->getRequest()->getParams();
        if (isset($param['live.php'])) {
            $param = array_merge($param, ['hauth_done' => 'Live']);
        }

        $type = $param['hauth_done'] ?? '';

        if ($this->checkRequest('hauth_start', false)
            && (($this->checkRequest('error_reason', 'user_denied')
                    && $this->checkRequest('error', 'access_denied')
                    && $this->checkRequest('error_code', '200')
                    && $this->checkRequest('hauth_done', 'Facebook'))
                || ($this->checkRequest('hauth_done', 'Twitter') && $this->checkRequest('denied')))
        ) {
            return $this->_appendJs(sprintf('<script>window.close();</script>'));
        }

        return $this->login($type);
    }
}
