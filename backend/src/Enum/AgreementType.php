<?php

namespace App\Enum;

enum AgreementType: string
{
    case AGB = 'agb';
    case PRIVACY = 'privacy';
    case BOARDING_CONTRACT = 'boarding_contract';
    case ADDENDUM = 'addendum';
}
