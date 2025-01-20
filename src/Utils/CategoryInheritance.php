<?php
/**
 * This file is part of a marmalade GmbH project
 * It is not Open Source and may not be redistributed.
 * For contact information please visit http://www.marmalade.de
 * Version:    1.0
 * Author:     Jens Richter <richter@marmalade.de>
 * Author URI: http://www.marmalade.de
 */

namespace Makaira\OxidConnect\Utils;

use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception as DBALException;
use Makaira\Query;
use OxidEsales\EshopCommunity\Internal\Framework\Database\QueryBuilderFactoryInterface;

use function array_map;
use function str_replace;

class CategoryInheritance
{
    public function __construct(
        private QueryBuilderFactoryInterface $queryBuilderFactory,
        private bool $useCategoryInheritance,
        private string $categoryAggregationId,
    ) {
    }

    /**
     * @param Query $query
     *
     * @throws DriverException
     * @throws DBALException
     * @SuppressWarnings(CyclomaticComplexity)
     */
    public function applyToAggregation(Query $query): void
    {
        if (!$this->useCategoryInheritance ||
            !$this->categoryAggregationId ||
            !isset($query->aggregations[$this->categoryAggregationId])) {
            return;
        }

        $key = $this->categoryAggregationId;

        $query->aggregations[$key] = $this->buildCategoryInheritance($query->aggregations[$key]);
    }

    /**
     * @param array|string $categoryId
     *
     * @return array
     * @throws DBALException
     * @throws DriverException
     */
    public function buildCategoryInheritance(array|string $categoryId): array
    {
        if (!$this->useCategoryInheritance) {
            return (array) $categoryId;
        }

        $categoryId = array_map(
            static fn (string $categoryId) => sprintf("'%s'", str_replace("'", "\\'", $categoryId)),
            (array) $categoryId
        );

        $qb = $this->queryBuilderFactory->create();
        $qb->select('c.OXID')
            ->from('oxcategories', 'c')
            ->from('oxcategories', 'r')
            ->where(
                $qb->expr()->and(
                    $qb->expr()->in('r.OXID', $categoryId),
                    $qb->expr()->eq('c.OXROOTID', 'r.OXROOTID'),
                    $qb->expr()->gte('c.OXLEFT', 'r.OXLEFT'),
                    $qb->expr()->lte('c.OXRIGHT', 'r.OXRIGHT'),
                ),
            );

        return $qb->execute()->fetchFirstColumn();
    }
}
