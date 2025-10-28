<?php

namespace App\Enum;

enum ContractStatus: string
{
    case QUEUED = 'queued';
    case GENERATED = 'generated';
    case SIGNED = 'signed';
}
