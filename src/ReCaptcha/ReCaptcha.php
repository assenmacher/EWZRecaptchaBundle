<?php

namespace EWZ\Bundle\RecaptchaBundle\ReCaptcha;

use ReCaptcha\ReCaptcha as GoogleReCaptcha;
use ReCaptcha\RequestMethod;

class ReCaptcha extends GoogleReCaptcha
{
    public function __construct($secret, RequestMethod $requestMethod = null)
    {
        parent::__construct($secret, $requestMethod);
    }

}
