<?php

namespace App\Enums;

enum TokenType: string
{
    case Invitation = 'invitation';
    case Qr = 'qr';
}
