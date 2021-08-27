<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Doctrine\ORM\Query\Expr\Join;
use Monarc\Core\Model\Table\InstanceRiskOpTable as CoreInstanceRiskOpTable;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Asset;
use Monarc\FrontOffice\Model\Entity\Instance;
use Monarc\FrontOffice\Model\Entity\InstanceRiskOp;
use Monarc\FrontOffice\Model\Entity\RolfRisk;

/**
 * Class InstanceRiskOpTable
 * @package Monarc\FrontOffice\Model\Table
 */
class InstanceRiskOpTable extends CoreInstanceRiskOpTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, $connectedUserService);

        $this->entityClass = InstanceRiskOp::class;
    }

    public function findByAnrInstanceAndRolfRisk(Anr $anr, Instance $instance, RolfRisk $rolfRisk): ?InstanceRiskOp
    {
        return $this->getRepository()
            ->createQueryBuilder('oir')
            ->where('oir.anr = :anr')
            ->andWhere('oir.instance = :instance')
            ->andWhere('oir.rolfRisk = :rolfRisk')
            ->setParameter('anr', $anr)
            ->setParameter('instance', $instance)
            ->setParameter('rolfRisk', $rolfRisk)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return InstanceRiskOp[]
     */
    public function findRisksDataForStatsByAnr(Anr $anr): array
    {
        return $this->getRepository()
            ->createQueryBuilder('oprisk')
            ->where('oprisk.anr = :anr')
            ->setParameter('anr', $anr)
            ->andWhere('oprisk.cacheNetRisk > -1')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return InstanceRiskOp[]
     */
    public function findByAnrInstancesAndFilterParams(Anr $anr, array $instancesIds, array $filterParams = []): array
    {
        $language = $anr->getLanguage();
        $queryBuilder = $this->getRepository()->createQueryBuilder('iro')
            ->innerJoin('iro.instance', 'i')
            ->innerJoin('i.asset', 'a', Join::WITH, 'a.type = ' . Asset::TYPE_PRIMARY)
            ->where('iro.anr = :anr')
            ->setParameter('anr', $anr);

        if (!empty($instancesIds)) {
            $queryBuilder->andWhere($queryBuilder->expr()->in('i.id', $instancesIds));
        }

        if (isset($filterParams['rolfRisks'])) {
            if (!\is_array($filterParams['rolfRisks'])) {
                $filterParams['rolfRisks'] = explode(',', substr($filterParams['rolfRisks'], 1, -1));
            }
            $queryBuilder->innerJoin('iro.rolfRisk', 'rr')
                ->andWhere($queryBuilder->expr()->in('rr.id', ':rolfRisks'))
                ->setParameter('rolfRisks', $filterParams['rolfRisks']);
        }

        if (isset($filterParams['kindOfMeasure'])) {
            if ((int)$filterParams['kindOfMeasure'] === InstanceRiskOp::KIND_NOT_TREATED) {
                $queryBuilder->andWhere(
                    'iro.kindOfMeasure IS NULL OR iro.kindOfMeasure = ' . InstanceRiskOp::KIND_NOT_TREATED
                );
            } else {
                $queryBuilder->andWhere('iro.kindOfMeasure = :kindOfMeasure')
                    ->setParameter('kindOfMeasure', $filterParams['kindOfMeasure']);
            }
        }

        if (!empty($filterParams['keywords'])) {
            $queryBuilder->andWhere(
                'i.name' . $language . ' LIKE :keywords OR ' .
                'i.label' . $language . ' LIKE :keywords OR ' .
                'iro.riskCacheLabel' . $language . ' LIKE :keywords OR ' .
                'iro.riskCacheDescription' . $language . ' LIKE :keywords OR ' .
                'iro.comment LIKE :keywords'
            )->setParameter('keywords', '%' . $filterParams['keywords'] . '%');
        }

        if (isset($filterParams['thresholds']) && (int)$filterParams['thresholds'] !== -1) {
            $queryBuilder->andWhere('iro.cacheNetRisk > :thresholds')
                ->setParameter('thresholds', $filterParams['thresholds']);
        }

        $filterParams['order_direction'] = isset($filterParams['order_direction'])
            && strtolower(trim($filterParams['order_direction'])) === 'asc' ? 'ASC' : 'DESC';

        $queryBuilder->orderBy('', $filterParams['order_direction']);

        switch ($filterParams['order']) {
            case 'instance':
                $queryBuilder->orderBy('i.name' . $language, $filterParams['order_direction']);
                break;
            case 'position':
                $queryBuilder->orderBy('i.position', $filterParams['order_direction'])
                    ->addOrderBy('i.name' . $language);
                break;
            case 'brutProb':
                $queryBuilder->orderBy('iro.brutProb', $filterParams['order_direction']);
                break;
            case 'netProb':
                $queryBuilder->orderBy('iro.netProb', $filterParams['order_direction']);
                break;
            case 'cacheBrutRisk':
                $queryBuilder->orderBy('iro.cacheBrutRisk', $filterParams['order_direction'])
                    ->addOrderBy('i.name' . $language);
                break;
            case 'cacheTargetedRisk':
                $queryBuilder->orderBy('iro.cacheTargetedRisk', $filterParams['order_direction'])
                    ->addOrderBy('i.name' . $language);
                break;
            default:
            case 'cacheNetRisk':
                $queryBuilder->orderBy('iro.cacheNetRisk', $filterParams['order_direction'])
                    ->addOrderBy('i.name' . $language);
                break;
        }

        return $queryBuilder->getQuery()->getResult();
    }
}
