<?php

namespace App\Enum;

enum ServiceProviderType: string
{
    case FARRIER = 'farrier';
    case DENTIST = 'dentist';
    case VET = 'vet';
    case PHYSIO = 'physio';
    case OTHER = 'other';
}
