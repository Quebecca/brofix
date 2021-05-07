<?php

declare(strict_types=1);

namespace Sypets\Brofix\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryHelper;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Handle database queries for table of broken links
 *
 * @internal
 */
class PagesRepository
{
    protected const TABLE = 'pages';

    /**
     * Calls TYPO3\CMS\Backend\FrontendBackendUserAuthentication::extGetTreeList.
     * Although this duplicates the function TYPO3\CMS\Backend\FrontendBackendUserAuthentication::extGetTreeList
     * this is necessary to create the object that is used recursively by the original function.
     *
     * Generates a list of page uids from $id. List does not include $id itself.
     * The only pages excluded from the list are deleted pages.
     *
     * @param int $id Start page id
     * @param int $depth Depth to traverse down the page tree.
     * @param string $permsClause Perms clause
     * @param bool $considerHidden Whether to consider hidden pages or not
     *
     * @todo begin is never really used
     */
    public function getAllSubpagesForPage(int $id, int $depth, string $permsClause, bool $considerHidden = false): array
    {
        $subPageIds = [];
        if ($depth === 0) {
            return $subPageIds;
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $result = $queryBuilder
            ->select('uid', 'title', 'hidden', 'extendToSubpages')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($id, \PDO::PARAM_INT)
                ),
                QueryHelper::stripLogicalOperatorPrefix($permsClause)
            )
            ->execute();

        while ($row = $result->fetch()) {
            $id = (int)$row['uid'];
            $isHidden = (bool)$row['hidden'];
            $extendToSubpages = (bool)($row['extendToSubpages'] ?? 0);
            if (!$isHidden || $considerHidden) {
                $subPageIds[] = $id;
            }
            if ($depth > 1 && (!($isHidden && $extendToSubpages) || $considerHidden)) {
                $subPageIds = array_merge($subPageIds, $this->getAllSubpagesForPage(
                    $id,
                    $depth - 1,
                    $permsClause,
                    $considerHidden
                ));
            }
        }
        return $subPageIds;
    }

    /**
     * Generates an array of page uids from current pageUid.
     * List does include pageUid itself.
     *
     * @return array
     */
    public function getPageList(int $id, int $depth, string $permsClause, bool $considerHidden = false): array
    {
        $pageList = $this->getAllSubpagesForPage(
            $id,
            $depth,
            $permsClause,
            $considerHidden
        );
        // Always add the current page
        $pageList[] = $id;
        $pageTranslations = $this->getTranslationForPage(
            $id,
            $permsClause,
            $considerHidden
        );
        return array_merge($pageList, $pageTranslations);
    }

    /**
     * Check if rootline contains a hidden page
     *
     * @param array $pageInfo Array with uid, title, hidden, extendToSubpages from pages table
     * @return bool TRUE if rootline contains a hidden page, FALSE if not
     */
    public function getRootLineIsHidden(array $pageInfo)
    {
        if ($pageInfo['pid'] === 0) {
            return false;
        }

        if ($pageInfo['extendToSubpages'] == 1 && $pageInfo['hidden'] == 1) {
            return true;
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll();

        $row = $queryBuilder
            ->select('uid', 'title', 'hidden', 'extendToSubpages')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($pageInfo['pid'], \PDO::PARAM_INT)
                )
            )
            ->execute()
            ->fetch();

        if ($row !== false) {
            return $this->getRootLineIsHidden($row);
        }
        return false;
    }

    /**
     * Add page translations to list of pages
     *
     * @param int $currentPage
     * @param string $permsClause
     * @param bool $considerHiddenPages
     * @param int[] $limitToLanguageIds
     * @return int[]
     */
    public function getTranslationForPage(
        int $currentPage,
        string $permsClause,
        bool $considerHiddenPages,
        array $limitToLanguageIds = []
    ): array {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        if (!$considerHiddenPages) {
            $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(HiddenRestriction::class));
        }
        $constraints = [
            $queryBuilder->expr()->eq(
                'l10n_parent',
                $queryBuilder->createNamedParameter($currentPage, \PDO::PARAM_INT)
            )
        ];
        if (!empty($limitToLanguageIds)) {
            $constraints[] = $queryBuilder->expr()->in(
                'sys_language_uid',
                $queryBuilder->createNamedParameter($limitToLanguageIds, Connection::PARAM_INT_ARRAY)
            );
        }
        if ($permsClause) {
            $constraints[] = QueryHelper::stripLogicalOperatorPrefix($permsClause);
        }

        $result = $queryBuilder
            ->select('uid', 'title', 'hidden')
            ->from(self::TABLE)
            ->where(...$constraints)
            ->execute();

        $translatedPages = [];
        while ($row = $result->fetch()) {
            $translatedPages[] = (int)$row['uid'];
        }

        return $translatedPages;
    }
}