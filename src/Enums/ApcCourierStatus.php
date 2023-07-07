<?php
declare(strict_types=1);
namespace Mcpuishor\ApcCourierDriver\Enums;

enum ApcCourierStatus: int
{
    case PARTIALCREATED = 102;
    case ERROR = 104;
    case CREATIONFAILED = 105;
    case WRONGFORMAT = 141;
    case CANCELLED = 121;
}
