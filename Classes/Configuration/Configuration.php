<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Sypets\Brofix\Configuration;

use Sypets\Brofix\Linktype\AbstractLinktype;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MailUtility;

/**
 * Wrapper class for handling TSconfig. Using the convenience
 * functions in this class the properties can be accessed
 * directly and the functions will already return the correct type.
 *
 * Configuration can still be extended by extending the TSconfig mod.brofix
 * and the function getTsConfig() used in this class.
 */
class Configuration
{
    /**
     * @var array
     */
    protected $tsConfig = [];

    public function __construct(array $tsConfig = null)
    {
        if ($tsConfig !== null) {
            $this->setTsConfig($tsConfig);
        }
    }

    public function setTsConfig(array $tsConfig): void
    {
        $this->tsConfig = $tsConfig;
    }

    /**
     * Get the brofix modTSconfig for a page
     *
     * @param int $page Uid of the page
     * @throws \Exception
     */
    public function loadPageTsConfig(int $page): void
    {
        $this->setTsConfig(BackendUtility::getPagesTSconfig($page)['mod.']['brofix.'] ?? []);
    }

    /**
     * Reads page TSconfig from string and overrides existing tsconfig in
     * $this->tsconfig with the values.
     *
     * @param string $tsConfigString
     * @throws \Exception
     *
     * @todo Create specific exception
     */
    public function overrideTsConfigByString(string $tsConfigString): void
    {
        $parseObj = GeneralUtility::makeInstance(TypoScriptParser::class);
        $parseObj->parse($tsConfigString);
        if (!empty($parseObj->errors)) {
            $parseErrorMessage = 'Invalid TSconfig'
                . '<br />';
            foreach ($parseObj->errors as $errorInfo) {
                $parseErrorMessage .= $errorInfo[0] . '<br />';
            }
            throw new \Exception($parseErrorMessage);
        }
        $tsConfig = $parseObj->setup;
        $overrideTs = $tsConfig['mod.']['brofix.'];

        if (is_array($overrideTs)) {
            ArrayUtility::mergeRecursiveWithOverrule($this->tsConfig, $overrideTs);
        }
    }

    public function getTsConfig(): array
    {
        return $this->tsConfig;
    }

    /**
     * @param array<string,array<string>> $searchFields, e.g.
     *   [
     *      'tt_content' => ['bodytext', 'media']
     *   ]
     */
    public function setSearchFields(array $searchFields): void
    {
        foreach ($searchFields as $table => $fields) {
            $this->tsConfig['searchFields.'][$table] = implode(',', $fields);
        }
    }

    /**
     * Get the list of fields to parse in modTSconfig
     *
     * @return array $searchFields List of fields, e.g.
     *   [
     *      'tt_content' => ['bodytext', 'media']
     *   ]
     */
    public function getSearchFields(): array
    {
        // Get the searchFields from TypoScript
        foreach ($this->tsConfig['searchFields.'] as $table => $fieldList) {
            $fields = GeneralUtility::trimExplode(',', $fieldList);
            foreach ($fields as $field) {
                $searchFields[$table][] = $field;
            }
        }
        return $searchFields ?? [];
    }

    public function getExcludedCtypes(): array
    {
        return explode(',', (string)($this->tsConfig['excludeCtype'] ?? ''));
    }

    /**
     * @param array<int,string> $linkTypes
     */
    public function setLinkTypes(array $linkTypes): void
    {
        $this->tsConfig['linktypes'] = implode(',', $linkTypes);
    }

    public function getLinkTypes(): array
    {
        return explode(',', $this->tsConfig['linktypes'] ?? 'external,db,file');
    }

    /**
     * @return bool
     */
    public function isCheckHidden(): bool
    {
        return (bool)($this->tsConfig['checkhidden'] ?? false);
    }

    public function isReportHiddenRecords(): bool
    {
        return (bool)($this->tsConfig['reportHiddenRecords'] ?? true);
    }

    public function getLinktypesConfig(string $linktype): array
    {
        return $this->tsConfig['linktypesConfig.'][$linktype . '.'] ?? [];
    }

    public function getLinktypesConfigExternalHeaders(): array
    {
        $headers = $this->tsConfig['linktypesConfig.']['external.']['headers.'] ?? [];
        if (($headers['User-Agent'] ?? '') === '') {
            $headers['User-Agent'] = $this->getUserAgent();
        }
        return $headers;
    }

    public function getUserAgent(): string
    {
        return $this->tsConfig['linktypesConfig.']['external.']['headers.']['User-Agent'] ??
            'Mozilla/5.0 (compatible; using Brofix link checker/ ' . (MailUtility::getSystemFromAddress() ?: '');
    }

    public function getLinktypesConfigExternalTimeout(): int
    {
        return (int)($this->tsConfig['linktypesConfig.']['external.']['timeout'] ?? 10);
    }

    public function getLinktypesConfigExternalRedirects(): int
    {
        return (int)($this->tsConfig['linktypesConfig.']['external.']['redirects'] ?? 5);
    }

    public function getExcludeLinkTargetStoragePid(): int
    {
        return (int)($this->tsConfig['excludeLinkTarget.']['storagePid'] ?? 0);
    }

    public function getExcludeLinkTargetAllowedTypes(): array
    {
        return explode(',', $this->tsConfig['excludeLinkTarget.']['allowed'] ?? 'external');
    }

    public function getLinkTargetCacheExpires(int $flags = 0): int
    {
        if ($flags | AbstractLinktype::CHECK_LINK_FLAG_SYNCHRONOUS) {
            return (int)($this->tsConfig['linkTargetCache.']['expiresHigh'] ?? 604800);
        }
        return (int)($this->tsConfig['linkTargetCache.']['expiresLow'] ?? 518400);
    }

    public function getCrawlDelayMs(): int
    {
        return (int)($this->tsConfig['crawlDelay.']['ms'] ?? 5000);
    }

    public function getCrawlDelayNodelay(): array
    {
        return explode(',', $this->tsConfig['crawlDelay.']['nodelay'] ?? '');
    }

    public function getDocsUrl(): string
    {
        return $this->tsConfig['docsurl'] ?? '';
    }

    public function getMailSendOnCheckLinks(): bool
    {
        return (bool)($this->tsConfig['mail.']['sendOnCheckLinks'] ?? true);
    }

    /**
     * Get check depth (number of page recursion levels)
     *
     * @return int
     */
    public function getDepth(): int
    {
        return (int)($this->tsConfig['depth'] ?? 999);
    }

    public function setDepth(int $depth): void
    {
        $this->tsConfig['depth'] = $depth;
    }

    public function setMailRecipients(string $recipients): void
    {
        $this->tsConfig['mail.']['recipients'] = $recipients;
    }

    /**
     * @return array
     *
     * Example:
     *
     * [
     *      'email@from',
     *      'email2@from' => 'From name',
     * ]
     */
    public function getMailRecipients(): array
    {
        $recipients = trim($this->tsConfig['mail.']['recipients'] ?? '');
        if ($recipients === '') {
            if ($GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] ?? '') {
                $fromAddress = $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] ?? '';
                $fromName = $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] ?? '';
                if ($fromName) {
                    return [
                        $fromAddress => $fromName
                    ];
                }
                return [$fromAddress];
            }
            return [];
        }
        return explode(',', $recipients);
    }

    public function getMailTemplate(): string
    {
        return $this->tsConfig['mail.']['template'] ?? 'CheckLinksResults';
    }

    /**
     * From email address. Returns only the email address.
     *
     * @return string
     */
    public function getMailFromEmail(): string
    {
        if (($this->tsConfig['mail.']['fromemail'] ?? '') !== '') {
            return $this->tsConfig['mail.']['fromemail'];
        }
        if (($this->tsConfig['mail.']['from'] ?? '') !== '') {
            // this is deprecated
            return explode(' ', $this->tsConfig['mail.']['from'])[0];
        }
        return MailUtility::getSystemFromAddress() ?: '';
    }

    /**
     * From Name
     *
     * @return string
     */
    public function getMailFromName(): string
    {
        if (($this->tsConfig['mail.']['fromname'] ?? '') !== '') {
            return $this->tsConfig['mail.']['fromname'];
        }
        return MailUtility::getSystemFromName() ?: '';
    }

    public function getMailReplyToEmail(): string
    {
        if (($this->tsConfig['mail.']['replytoemail'] ?? '') !== '') {
            return $this->tsConfig['mail.']['replytoemail'];
        }
        if (($this->tsConfig['mail.']['replyto'] ?? '') !== '') {
            // this is deprecated
            return explode(' ', $this->tsConfig['mail.']['replyto'])[0];
        }
        // @todo use MailUtility::getSystemReplyTo()
        // getSystemReplyto returns an array of email addresses, can be in format
        // 'name <email@from>'
        return '';
    }

    /**
     * From Name
     *
     * @return string
     */
    public function getMailReplyToName(): string
    {
        if (($this->tsConfig['mail.']['replytoname'] ?? '') !== '') {
            return $this->tsConfig['mail.']['replytoname'];
        }
        // @todo use MailUtility::getSystemReplyTo()
        // getSystemReplyto returns an array of email addresses, can be in format
        // 'name <email@from>'
        return '';
    }

    public function getMailSubject(): string
    {
        return $this->tsConfig['mail.']['subject'] ?? '';
    }

    public function getCustom(): array
    {
        return $this->tsConfig['custom.'] ?? [];
    }
}