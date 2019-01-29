<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

/**
 * This class is the service that handles assets coming from the common database and that may be imported into a
 * client-specific ANR.
 * @package MonarcFO\Service
 */
class AnrAssetCommonService extends \MonarcCore\Service\AbstractService
{
    protected $anrTable;
    protected $amvTable;
    protected $clientity;
    protected $clitable;
    protected $coreServiceAsset;
    protected $cliServiceAsset;

    /**
     * Returns the list of assets attached to the provided ANR ID
     * @param int $anrId The ANR ID
     * @return array An array of assets, in array (not entity) format
     * @throws \MonarcCore\Exception\Exception If the ANR does not exist
     */
    public function getListAssets($anrId)
    {
        $anr = $this->get('anrTable')->getEntity($anrId);
        if ($anr) {
            $model = $this->get('coreServiceAsset')->get('modelTable')->getEntity($anr->get('model'));

            $assets = $this->get('table')->getRepository()->createQueryBuilder('a');
            $fctWhere = 'where';
            if ($model->get('isGeneric') || !$model->get('isRegulator')) {
                $assets = $assets->where('a.mode = :mode')
                    ->setParameter(':mode', 0); // generic
                $fctWhere = 'andWhere';
            }
            if (!$model->get('isGeneric')) {
                $assets = $assets->innerJoin('a.models', 'm')
                    ->$fctWhere('m.id = :mid')
                    ->setParameter(':mid', $anr->get('model'));
            }

            $assets = $assets->orderBy('a.code', 'ASC')
                ->getQuery()->getResult();
            $return = [];
            $aObj = [
                'id',
                'label' . $anr->get('language'),
                'description' . $anr->get('language'),
                'status',
                'mode',
                'type',
                'code',
            ];
            foreach ($assets as $a) {
                $return[] = $a->getJsonArray($aObj);
            }
            return $return;
        } else {
            throw new \MonarcCore\Exception\Exception('Anr does not exist', 412);
        }
    }

    /**
     * Returns a specific asset data as an array
     * @param int $anrId The ANR ID
     * @param int $assetId The asset ID
     * @return array The asset fields
     * @throws \MonarcCore\Exception\Exception If the ANR mismatches or the entity does not exist
     */
    public function getAsset($anrId, $assetId)
    {
        $anr = $this->get('anrTable')->getEntity($anrId);
        if ($anr) {
            $model = $this->get('coreServiceAsset')->get('modelTable')->getEntity($anr->get('model'));

            $asset = $this->get('table')->getRepository()->createQueryBuilder('a');
            $asset = $asset->where('a.id = :assetId')->setParameter(':assetId', $assetId);
            $fctWhere = 'andWhere';
            if ($model->get('isGeneric') || !$model->get('isRegulator')) {
                $asset = $asset->andWhere('a.mode = :mode')
                    ->setParameter(':mode', 0); // generic
                $fctWhere = 'andWhere';
            }
            if (!$model->get('isGeneric')) {
                $asset = $asset->innerJoin('a.models', 'm')
                    ->$fctWhere('m.id = :mid')
                    ->setParameter(':mid', $anr->get('model'));
            }
            $asset = $asset->setFirstResult(0)->setMaxResults(1)
                ->getQuery()->getSingleResult();
            if ($asset) {
                $return = $asset->getJsonArray([
                    'id',
                    'label' . $anr->get('language'),
                    'description' . $anr->get('language'),
                    'status',
                    'mode',
                    'type',
                    'code',
                ]);
                $return['amvs'] = [];
                $amvs = $this->get('amvTable')->getRepository()->createQueryBuilder('t')
                    ->where('t.asset = :aid')
                    ->setParameter(':aid', $return['id'])// add orders
                    ->getQuery()->getResult();
                foreach ($amvs as $amv) {
                    $amvArray = [
                        'threat' => [
                            'code' => $amv->get('threat')->get('code'),
                            'label' . $anr->get('language') => $amv->get('threat')->get('label' . $anr->get('language')),
                        ],
                        'vulnerability' => [
                            'code' => $amv->get('vulnerability')->get('code'),
                            'label' . $anr->get('language') => $amv->get('vulnerability')->get('label' . $anr->get('language')),
                        ],
                    ];
                    for ($i = 1; $i <= 3; $i++) {
                        $amvArray['measure' . $i] = [
                            'code' => null,
                            'description' . $anr->get('language') => null,
                        ];
                        if ($amv->get('measure' . $i)) {
                            $amvArray['measure' . $i] = [
                                'code' => $amv->get('measure' . $i)->get('code'),
                                'description' . $anr->get('language') => $amv->get('measure' . $i)->get('description' . $anr->get('language')),
                            ];
                        }
                    }
                    $return['amvs'][] = $amvArray;
                }
                return $return;
            } else {
                throw new \MonarcCore\Exception\Exception('Asset does not exist', 412);
            }
        } else {
            throw new \MonarcCore\Exception\Exception('Anr does not exist', 412);
        }
    }

    /**
     * Imports an asset from the common knowledge base (common database) into the provided client (local) ANR
     * @param int $anrId The target ANR ID
     * @param int $assetId The common asset ID to import
     * @return int The generated asset ID
     * @throws \MonarcCore\Exception\Exception If the asset or ANR does not exist
     */
    public function importAsset($anrId, $assetId)
    {
        $anr = $this->get('anrTable')->getEntity($anrId);

        if ($anr) {
            // Lookup the asset inside the common database
            $model = $this->get('coreServiceAsset')->get('modelTable')->getEntity($anr->get('model'));

            $asset = $this->get('table')->getRepository()->createQueryBuilder('a');
            $asset->where('a.id = :assetId')->setParameter(':assetId', $assetId);

            $fctWhere = 'andWhere';
            if ($model->get('isGeneric') || !$model->get('isRegulator')) {
                $asset = $asset->andWhere('a.mode = :mode')
                    ->setParameter(':mode', 0); // generic
                $fctWhere = 'andWhere';
            }
            if (!$model->get('isGeneric')) {
                $asset = $asset->innerJoin('a.models', 'm')
                    ->$fctWhere('m.id = :mid')
                    ->setParameter(':mid', $anr->get('model'));
            }
            $asset = $asset->setFirstResult(0)->setMaxResults(1)
                ->getQuery()->getSingleResult(); // même si on fait une autre requête dans AssetService::generateExportArray(), cela permet d'avoir un contrôle sur asset_id & model_id

            if ($asset) {
                /*
                - faire un export de cet asset
                - utiliser l'import déjà en place
                */
                $f = null;
                $data = $this->get('coreServiceAsset')->get('assetExportService')->generateExportArray($asset->get('id'), $f);
                return $this->get('cliServiceAsset')->importFromArray($data, $anr);
            } else {
                throw new \MonarcCore\Exception\Exception('Asset does not exist', 412);
            }
        } else {
            throw new \MonarcCore\Exception\Exception('Anr does not exist', 412);
        }
    }
}