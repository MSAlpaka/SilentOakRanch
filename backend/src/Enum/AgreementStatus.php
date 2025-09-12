<?php

namespace App\Enum;

enum AgreementStatus: string
{
    case ACTIVE = 'active';
    case REVOKED = 'revoked';
}
