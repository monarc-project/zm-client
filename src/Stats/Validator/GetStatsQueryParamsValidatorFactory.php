<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Stats\Validator;

use Interop\Container\ContainerInterface;
use Laminas\InputFilter\Factory;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Monarc\FrontOffice\Model\Table\AnrTable;

class GetStatsQueryParamsValidatorFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        /** @var Factory $inputFilterFactory */
        $inputFilterFactory = $container->get(Factory::class);

        return new $requestedName($inputFilterFactory->createInputFilter([]), $container->get(AnrTable::class));
    }
}

