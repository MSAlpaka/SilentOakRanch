<?php

namespace App\Enum;

enum SignatureValidationStatus: string
{
    case VALID = 'VALID';
    case TAMPERED = 'TAMPERED';
    case UNSIGNED = 'UNSIGNED';
    case EXPIRED = 'EXPIRED';
}
