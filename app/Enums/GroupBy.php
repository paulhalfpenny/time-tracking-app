<?php

namespace App\Enums;

enum GroupBy: string
{
    case Client = 'client';
    case Project = 'project';
    case Task = 'task';
    case User = 'user';
}
