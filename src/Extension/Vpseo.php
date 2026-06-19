<?php
/**
 * @package     VPJoomla.Plugin
 * @subpackage  System.Vpseo
 *
 * @copyright   Copyright (C) 2024 - 2026 VPJoomla. All rights reserved.
 * @license     GNU GPL v2 or later; see LICENSE.txt
 */

namespace VPJoomla\Plugin\System\Vpseo\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Event\Model;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;

/**
 * VP SEO - adds a per-article SEO Title (separate from the H1) plus
 * OpenGraph and Twitter Card meta tags. Joomla core provides none of these.
 *
 * @since  2.0.0
 */
final class Vpseo extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    /**
     * Load the plugin language file automatically (needed for the form labels).
     *
     * @var  boolean
     */
    protected $autoloadLanguage = true;

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepareForm' => 'onContentPrepareForm',
            'onBeforeCompileHead'  => 'onBeforeCompileHead',
        ];
    }

    /**
     * Inject the SEO Title field into the article edit form (Publishing tab).
     *
     * @param   Model\PrepareFormEvent  $event  The event.
     *
     * @return  void
     */
    public function onContentPrepareForm(Model\PrepareFormEvent $event): void
    {
        $form = $event->getForm();

        if (!$form instanceof Form || $form->getName() !== 'com_content.article') {
            return;
        }

        Form::addFormPath(\dirname(__DIR__, 2) . '/forms');
        $form->loadFile('article', false);

        // The core metadata layout renders Meta Description/Keywords first, then loops the
        // jmetadata fieldset in document order. Our fields are appended last on load, so move
        // them to the front of that fieldset to keep the SEO Title near the top.
        $this->moveFieldsFirst($form, 'metadata', 'jmetadata', ['vpseo_preview', 'seo_title', 'seo_append_sitename', 'og_description', 'og_image', 'canonical']);
    }

    /**
     * Move the given fields to the start of a fieldset (document order), preserving their order.
     *
     * @param   Form      $form      The form being prepared.
     * @param   string    $group     The field group name.
     * @param   string    $fieldset  The fieldset name.
     * @param   string[]  $names     Field names to move, in the desired order.
     *
     * @return  void
     */
    private function moveFieldsFirst(Form $form, string $group, string $fieldset, array $names): void
    {
        $sets = $form->getXml()->xpath(
            \sprintf('//fields[@name="%s"]/fieldset[@name="%s"]', $group, $fieldset)
        );

        if (!$sets) {
            return;
        }

        $fieldsetDom = dom_import_simplexml($sets[0]);
        $anchor      = $fieldsetDom->firstChild;

        foreach ($names as $name) {
            $found = $sets[0]->xpath(\sprintf('field[@name="%s"]', $name));

            if (!$found) {
                continue;
            }

            $node = dom_import_simplexml($found[0]);
            $fieldsetDom->insertBefore($node, $anchor);
            $anchor = $node->nextSibling;
        }
    }

    /**
     * Set the document title (when overridden) and emit OG / Twitter tags.
     *
     * @return  void
     */
    public function onBeforeCompileHead(): void
    {
        $app = $this->getApplication();

        if (!$app->isClient('site')) {
            return;
        }

        $doc = $app->getDocument();

        if (!$doc instanceof HtmlDocument) {
            return;
        }

        $input     = $app->getInput();
        $option    = $input->getCmd('option');
        $view      = $input->getCmd('view');
        $isArticle = $option === 'com_content' && $view === 'article';

        // Per-article data only applies to a single article view.
        $data = $isArticle ? $this->getArticleSeoData((int) $input->getInt('id')) : [];

        $seoTitle     = (string) ($data['seo_title'] ?? '');
        $articleTitle = (string) ($data['title'] ?? '');
        $baseTitle    = $seoTitle !== '' ? $seoTitle : $articleTitle;

        // 1) <title> — this is what separates the browser title from the H1.
        if ($seoTitle !== '') {
            $doc->setTitle($this->buildPageTitle($seoTitle, (string) ($data['seo_append_sitename'] ?? '')));
        }


        // 2) Description.
        //    Raw base description: native meta description -> global default -> auto from intro text.
        $nativeDesc = (string) $doc->getDescription();
        $rawBase    = $nativeDesc ?: (string) $this->params->get('default_description', '');

        if ($rawBase === '' && $isArticle && (int) $this->params->get('auto_description', 1) === 1) {
            $rawBase = $this->stripShortcodes((string) ($data['introtext'] ?? ''));
        }

        // One description for both search and social: keep an existing meta description as-is,
        // otherwise build one at ~160 chars (search-snippet best practice). Social previews
        // truncate around the same length, so a single base avoids over-long og:description.
        $baseDesc = $nativeDesc !== '' ? $nativeDesc : $this->truncate($rawBase, 160);

        if ($nativeDesc === '' && $baseDesc !== '') {
            $doc->setDescription($baseDesc);
        }

        // Social description: per-article override (Pro) wins, otherwise the same base description.
        $ogOverride  = $this->truncate((string) ($data['og_description'] ?? ''), 200);
        $description = $ogOverride !== '' ? $ogOverride : $baseDesc;

        // 3) Image: per-article override -> article intro image -> global fallback.
        //    SVG candidates are skipped — social platforms can't render them as previews.
        $imageRaw = $this->pickSocialImage([
            (string) ($data['og_image'] ?? ''),
            (string) ($data['image_intro'] ?? ''),
            (string) $this->params->get('default_image', ''),
        ]);
        $image = $this->makeAbsolute($imageRaw);

        // 4) Canonical (Pro).

        // og:title uses the clean title (no site-name / category decoration).
        $ogTitle = $seoTitle !== '' ? $seoTitle : ($articleTitle ?: $doc->getTitle());

        // --- OpenGraph ---
        $this->setProperty($doc, 'og:title', $this->truncate($ogTitle, 95));
        $this->setProperty($doc, 'og:description', $description);
        $this->setProperty($doc, 'og:type', $isArticle ? 'article' : 'website');
        $this->setProperty($doc, 'og:url', Uri::current());
        $this->setProperty($doc, 'og:site_name', (string) $app->get('sitename'));
        $this->setProperty($doc, 'og:locale', str_replace('-', '_', $app->getLanguage()->getTag()));

        if ($image !== '') {
            $this->setProperty($doc, 'og:image', $image);

        }

        // Article-specific OpenGraph timestamps.
        if ($isArticle) {
            if ($published = $this->toIso8601((string) ($data['created'] ?? ''))) {
                $this->setProperty($doc, 'article:published_time', $published);
            }

            if ($modified = $this->toIso8601((string) ($data['modified'] ?? ''))) {
                $this->setProperty($doc, 'article:modified_time', $modified);
            }

        }

        // --- Twitter Card (uses name= attribute, not property=) ---
        $this->setName($doc, 'twitter:card', $image !== '' ? 'summary_large_image' : 'summary');
        $this->setName($doc, 'twitter:title', $this->truncate($ogTitle, 70));
        $this->setName($doc, 'twitter:description', $description);

        if ($image !== '') {
            $this->setName($doc, 'twitter:image', $image);
        }

        if ($twitterSite = trim((string) $this->params->get('twitter_site', ''))) {
            $this->setName($doc, 'twitter:site', $twitterSite);
        }

        // Joomla's article view echoes every metadata key as a <meta name>. Our fields live in
        // the metadata column for the Publishing-tab UI, so strip those internal keys from the head.
        if ($isArticle) {
            $this->removeLeakedMeta($doc, ['vpseo_preview', 'seo_title', 'seo_append_sitename', 'og_description', 'og_image', 'canonical']);
        }
    }

    /**
     * Remove internal storage keys that Joomla's article view emits as bogus <meta name> tags.
     *
     * @param   HtmlDocument  $doc   The document.
     * @param   string[]      $keys  The meta names to remove.
     *
     * @return  void
     */
    private function removeLeakedMeta(HtmlDocument $doc, array $keys): void
    {
        \Closure::bind(
            function () use ($keys) {
                foreach ($keys as $k) {
                    unset($this->_metaTags['name'][$k]);
                }
            },
            $doc,
            $doc
        )();
    }


    /**
     * Fetch all per-article SEO data needed for the head.
     *
     * @param   int  $id  The article id.
     *
     * @return  array
     */
    private function getArticleSeoData(int $id): array
    {
        if ($id <= 0) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select(
                [
                    $db->quoteName('a.id'),
                    $db->quoteName('a.title'),
                    $db->quoteName('a.catid'),
                    $db->quoteName('a.language'),
                    $db->quoteName('a.metadata'),
                    $db->quoteName('a.images'),
                    $db->quoteName('a.created'),
                    $db->quoteName('a.modified'),
                    $db->quoteName('a.introtext'),
                    $db->quoteName('a.created_by_alias'),
                    $db->quoteName('u.name', 'author_name'),
                    $db->quoteName('c.title', 'category_title'),
                ]
            )
            ->from($db->quoteName('#__content', 'a'))
            ->join('LEFT', $db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('a.created_by'))
            ->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid'))
            ->where($db->quoteName('a.id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        $row = $db->setQuery($query)->loadObject();

        if (!$row) {
            return [];
        }

        $metadata = json_decode((string) $row->metadata, true) ?: [];
        $images   = json_decode((string) $row->images, true) ?: [];

        return [
            'id'                  => (int) $row->id,
            'title'               => (string) $row->title,
            'catid'               => (int) $row->catid,
            'language'            => (string) $row->language,
            'created'             => (string) $row->created,
            'modified'            => (string) $row->modified,
            'introtext'           => (string) $row->introtext,
            'author'              => (string) ($row->created_by_alias ?: $row->author_name),
            'section'             => (string) $row->category_title,
            'image_intro'         => (string) ($images['image_intro'] ?? ''),
            'seo_title'           => (string) ($metadata['seo_title'] ?? ''),
            'seo_append_sitename' => (string) ($metadata['seo_append_sitename'] ?? ''),
            'og_description'      => (string) ($metadata['og_description'] ?? ''),
            'og_image'            => (string) ($metadata['og_image'] ?? ''),
            'canonical'           => (string) ($metadata['canonical'] ?? ''),
        ];
    }


    /**
     * Convert a Joomla UTC datetime string to an ISO-8601 timestamp, or '' if empty/invalid.
     *
     * @param   string  $datetime  The stored datetime.
     *
     * @return  string
     */
    private function toIso8601(string $datetime): string
    {
        if ($datetime === '' || str_starts_with($datetime, '0000-00-00')) {
            return '';
        }

        $ts = strtotime($datetime . ' UTC');

        return $ts ? gmdate('c', $ts) : '';
    }


    /**
     * Apply Joomla's "Include Site Name in Page Titles" rule to a raw title,
     * honouring an optional per-article override.
     *
     * @param   string  $title       The raw (clean) title.
     * @param   string  $appendMode  '', 'global', 'yes' or 'no'.
     *
     * @return  string
     */
    private function buildPageTitle(string $title, string $appendMode): string
    {
        $app  = $this->getApplication();
        $mode = (int) $app->get('sitename_pagetitles', 0);

        switch ($appendMode) {
            case 'no':
                $mode = 0;
                break;
            case 'yes':
                // Force inclusion; keep the global position, default to "after".
                $mode = $mode === 1 ? 1 : 2;
                break;
            // '', 'global' -> keep the global setting as-is.
        }

        $sitename = (string) $app->get('sitename');

        if ($mode === 1) {
            return Text::sprintf('JPAGETITLE', $sitename, $title);
        }

        if ($mode === 2) {
            return Text::sprintf('JPAGETITLE', $title, $sitename);
        }

        return $title;
    }

    /**
     * Turn a (possibly relative, possibly Joomla-tagged) image path into an absolute URL.
     *
     * @param   string  $path  The image path.
     *
     * @return  string
     */
    private function makeAbsolute(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '';
        }

        // Strip the Joomla 4+ "#joomlaImage://..." metadata suffix.
        if (($hash = strpos($path, '#')) !== false) {
            $path = substr($path, 0, $hash);
        }

        if ($path === '' || preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return rtrim(Uri::root(), '/') . '/' . ltrim($path, '/');
    }

    /**
     * Resolve the first usable social image from a list of candidates.
     * SVG candidates are skipped because social platforms cannot render them as previews.
     *
     * @param   string[]  $candidates  Raw image paths in priority order.
     *
     * @return  string  The first raster candidate (raw, un-absolutised), or '' if none.
     */
    private function pickSocialImage(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);

            if ($candidate === '') {
                continue;
            }

            $clean = $this->cleanImagePath($candidate);

            if ($clean !== '' && !$this->isSvg($clean)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * Strip the Joomla 4+ "#joomlaImage://..." suffix and any query string from an image path.
     *
     * @param   string  $path  The raw image path.
     *
     * @return  string
     */
    private function cleanImagePath(string $path): string
    {
        $path = trim($path);

        if (($hash = strpos($path, '#')) !== false) {
            $path = substr($path, 0, $hash);
        }

        if (($query = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $query);
        }

        return $path;
    }


    /**
     * Whether a URL points to an SVG file (ignoring any query string).
     *
     * @param   string  $url  The URL to test.
     *
     * @return  bool
     */
    private function isSvg(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?: $url;

        return strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) === 'svg';
    }

    /**
     * Remove Joomla plugin shortcodes (e.g. {loadmodule ...}) from text.
     *
     * @param   string  $text  The raw text.
     *
     * @return  string
     */
    private function stripShortcodes(string $text): string
    {
        return (string) preg_replace('/\{[^}]*\}/', '', $text);
    }

    private function setProperty(HtmlDocument $doc, string $key, string $content): void
    {
        if ($content !== '') {
            $doc->setMetaData($key, $content, 'property');
        }
    }

    private function setName(HtmlDocument $doc, string $key, string $content): void
    {
        if ($content !== '') {
            $doc->setMetaData($key, $content);
        }
    }

    private function truncate(string $text, int $limit): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)));

        return mb_strlen($text) > $limit ? rtrim(mb_substr($text, 0, $limit - 1)) . '…' : $text;
    }
}
