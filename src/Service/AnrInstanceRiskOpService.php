<?php

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\InstanceRiskOpService;
use Monarc\FrontOffice\Service\Traits\InstanceRiskRecommendationUpdateTrait;

class AnrInstanceRiskOpService extends InstanceRiskOpService
{
    use InstanceRiskRecommendationUpdateTrait;
}
