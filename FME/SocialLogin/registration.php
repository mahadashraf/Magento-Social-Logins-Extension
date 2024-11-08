<?php
/**
 * FME
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the FME.com license that is
 * available through the world-wide-web at this URL:
 * https://www.FME.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category  FME
 * @package   FME_SocialLogin
 * @copyright Copyright (c) FME (https://www.FME.com/)
 * @license   https://www.FME.com/LICENSE.txt
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'FME_SocialLogin',
    __DIR__
);
