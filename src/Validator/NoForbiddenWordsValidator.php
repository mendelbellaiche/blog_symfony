<?php

namespace App\Validator;

use App\Moderation\ModerationMatcher;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class NoForbiddenWordsValidator extends ConstraintValidator
{
    public function __construct(private ModerationMatcher $matcher)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NoForbiddenWords) {
            throw new UnexpectedTypeException($constraint, NoForbiddenWords::class);
        }

        if (!is_string($value) || $value === '') {
            return;
        }

        if ($this->matcher->findForbidden($value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
