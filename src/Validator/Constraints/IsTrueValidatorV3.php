<?php

namespace EWZ\Bundle\RecaptchaBundle\Validator\Constraints;

use EWZ\Bundle\RecaptchaBundle\Form\Type\EWZRecaptchaV3Type;
use Psr\Log\LoggerInterface;
use ReCaptcha\ReCaptcha;
use ReCaptcha\RequestMethod as ReCaptchaRequestMethod;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class IsTrueValidatorV3 extends ConstraintValidator
{
    /** @var bool */
    private $enabled;

    /** @var string */
    private $secret;

    /** @var ReCaptchaRequestMethod */
    private $reCaptchaRequestMethod;

    /**
     * Recaptcha.
     *
     * @var ReCaptcha
     */
    protected $recaptcha;

    /** @var float */
    private $scoreThreshold;

    /** @var RequestStack */
    private $requestStack;

    /** @var LoggerInterface */
    private $logger;

    /**
     * ContainsRecaptchaValidator constructor.
     *
     * @param bool                   $enabled
     * @param string                 $secret
     * @param float                  $scoreThreshold
     * @param RequestStack           $requestStack
     * @param LoggerInterface        $logger
     * @param ReCaptchaRequestMethod $reCaptchaRequestMethod
     */
    public function __construct(
        bool $enabled,
        string $secret,
        float $scoreThreshold,
        RequestStack $requestStack,
        LoggerInterface $logger,
        ?ReCaptchaRequestMethod $reCaptchaRequestMethod = null
    ) {
        $this->enabled = $enabled;
        $this->secret = $secret;
        $this->reCaptchaRequestMethod = $reCaptchaRequestMethod;
        $this->scoreThreshold = $scoreThreshold;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
    }

    /**
     * @param mixed      $value
     * @param Constraint $constraint
     */
    public function validate($value, Constraint $constraint): void
    {
        if (!$this->enabled) {
            return;
        }

        if (!$constraint instanceof IsTrueV3) {
            throw new UnexpectedTypeException($constraint, IsTrueV3::class);
        }

        if (null === $value) {
            $value = '';
        }

        if (!is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }
        $this->secret  = $constraint->secret ?: $this->secret;

        if (!$this->isTokenValid($value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ string }}', $value)
                ->addViolation();
        }
    }

    /**
     * @param string $token
     *
     * @return bool
     */
    private function isTokenValid(string $token): bool
    {
        try {
            $this->recaptcha  = new ReCaptcha($this->secret, $this->reCaptchaRequestMethod);

            $remoteIp = $this->requestStack->getCurrentRequest()->getClientIp();
            $action = $this->getActionName();

            $response = $this->reCaptcha
                ->setExpectedAction($action)
                ->setScoreThreshold($this->scoreThreshold)
                ->verify($token, $remoteIp);

            return $response->isSuccess();
        } catch (\Throwable $exception) {
            $this->logger->error(
                'reCAPTCHA validator error: '.$exception->getMessage(),
                [
                    'exception' => $exception,
                ]
            );

            return false;
        }
    }

    private function getActionName(): string
    {
        $object = $this->context->getObject();
        $action = null;

        if ($object instanceof FormInterface) {
            $action = $object->getConfig()->getOption('action_name');
        }

        return $action ?: EWZRecaptchaV3Type::DEFAULT_ACTION_NAME;
    }
}
