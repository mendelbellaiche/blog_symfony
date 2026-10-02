<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class NoForbiddenWords extends Constraint
{
    public string $message = 'Ton commentaire contient des termes interdits. Merci de le reformuler.';
}
